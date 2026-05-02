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
require_once 'config/helpers.php';
require_once 'compute-insights.php';

$pdo = DB::get();
// S73.B.37: ensure colors_config column exists
try { DB::run("ALTER TABLE tenants ADD COLUMN colors_config TEXT NULL"); } catch(Exception $e) {}
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

    // ─── S78: Trigger product insights compute (skeleton — S79 fills logic) ───
    if ($ajax === 'compute_insights') {
        $cur = $tenant['currency'] ?? 'EUR';
        computeProductInsights((int)$tenant_id, (int)$store_id, $cur);
        echo json_encode(['ok' => true, 'computed' => 19]);
        exit;
    }

    // ─── SEARCH ───
    // ═══ S79 A2 — AJAX SECTIONS ═══
if ($ajax === 'sections') {
    // Топик-към-фундаментален въпрос mapping
    $topicMap = [
        'stock_zero_bestsellers' => 'loss',
        'stock_critical_low' => 'order',
        'stock_below_minimum' => 'order',
        'stock_size_gaps' => 'loss',
        'price_selling_at_loss' => 'loss_cause',
        'price_low_margin' => 'loss_cause',
        'price_wholesale_close' => 'loss_cause',
        'dq_no_cost_price' => 'loss_cause',
        'profit_top5' => 'gain',
        'new_sell_through' => 'gain',
        'profit_bottom5' => 'gain_cause',
        'new_no_sales_7d' => 'anti_order',
        'zombie_30d' => 'anti_order',
        'zombie_60d' => 'anti_order',
        'stock_overstock' => 'anti_order',
        'cash_frozen_zombie' => 'anti_order',
        'promo_expiring' => 'order',
        'promo_bestseller_discount' => 'anti_order',
        'promo_below_cost' => 'loss_cause',
    ];

    $sections = [
        'q1' => ['items' => [], 'total' => 0, 'label' => 'Какво губиш'],
        'q2' => ['items' => [], 'total' => 0, 'label' => 'От какво губиш'],
        'q3' => ['items' => [], 'total' => 0, 'label' => 'Какво печелиш'],
        'q4' => ['items' => [], 'total' => 0, 'label' => 'От какво печелиш'],
        'q5' => ['items' => [], 'total' => 0, 'label' => 'Какво да поръчаш'],
        'q6' => ['items' => [], 'total' => 0, 'label' => 'Какво да НЕ поръчаш'],
    ];

    $fqToQ = [
        'loss' => 'q1', 'loss_cause' => 'q2', 'gain' => 'q3',
        'gain_cause' => 'q4', 'order' => 'q5', 'anti_order' => 'q6',
    ];

    // Взимаме всички insights за този tenant/store
    // S88.PRODUCTS.AIBRAIN_WIRE: action_label/action_type/action_data позволяват
    // фронтендът да рендерира call-to-action бутон под всеки item.
    $insights = DB::run(
        "SELECT topic_id, fundamental_question, title, detail_text, data_json, value_numeric, product_count,
                action_label, action_type, action_data
         FROM ai_insights
         WHERE tenant_id=? AND store_id=? AND expires_at > NOW()
         ORDER BY urgency='critical' DESC, urgency='warning' DESC, value_numeric DESC",
        [$tenant_id, $store_id]
    )->fetchAll();

    foreach ($insights as $ins) {
        $fq = $ins['fundamental_question'] ?: ($topicMap[$ins['topic_id']] ?? null);
        if (!$fq || !isset($fqToQ[$fq])) continue;
        $qkey = $fqToQ[$fq];

        $data = $ins['data_json'] ? json_decode($ins['data_json'], true) : null;
        $items = $data['items'] ?? [];

        // S88: action payload — извлечен веднъж per insight, разпределен на всеки item.
        // intent (от action_data) е семантичен (включва navigate_chart/transfer_draft/...);
        // type е валиден ENUM за rotate-aware UI диспетчер.
        $actionData = $ins['action_data'] ? json_decode($ins['action_data'], true) : [];
        if (!is_array($actionData)) $actionData = [];
        $actionPayload = [
            'label'  => $ins['action_label'] ?? null,
            'type'   => $ins['action_type'] ?? null,
            'intent' => $actionData['intent'] ?? ($ins['action_type'] ?? null),
            'topic'  => $ins['topic_id'],
        ];

        // Взимаме първите 4 артикула от insight-а
        foreach (array_slice($items, 0, 4) as $it) {
            if (!isset($it['id']) || !isset($it['name'])) continue;
            $stock = isset($it['quantity']) ? intval($it['quantity']) : 0;
            $price = isset($it['retail_price']) ? floatval($it['retail_price']) : 0;

            $stkClass = $stock === 0 ? 'danger' : ($stock <= 2 ? 'warn' : 'ok');
            $stkText = $stock . ' бр';

            // Контекст според fq
            $ctx = '';
            $tag = '';
            $tagClass = 'dim';
            if ($fq === 'loss') {
                $sold = isset($it['sold_30d']) ? intval($it['sold_30d']) : 0;
                $ctx = $sold > 0 ? ($sold . ' прод/30д · <b>свършва</b>') : '<b>нула с търсене</b>';
                $tag = $stock === 0 ? '0 БР' : $stock.' БР';
                $tagClass = 'bad';
            } elseif ($fq === 'loss_cause') {
                $ctx = isset($it['margin_pct']) ? ('Марж '.$it['margin_pct'].'% · <b>нисък profit</b>') : (isset($it['loss_per_unit']) ? ('Доставна '.fmtMoney($it['cost_price']??0,'BGN').' · <b>под себестойност</b>') : '<b>без доставна цена</b>');
                $tag = isset($it['margin_pct']) ? $it['margin_pct'].'%' : '?';
                $tagClass = 'violet';
            } elseif ($fq === 'gain') {
                $profit = isset($it['profit']) ? floatval($it['profit']) : 0;
                $ctx = 'Топ '.($profit>0?'+'.round($profit).' лв profit':'продавач');
                $tag = '#'.(count($sections[$qkey]['items'])+1);
                $tagClass = 'good';
            } elseif ($fq === 'gain_cause') {
                $ctx = 'Висок профит · <b>драйвер</b>';
                $tag = 'TOP';
                $tagClass = 'teal';
            } elseif ($fq === 'order') {
                $ctx = '<b>Поръчай</b>';
                $tag = 'ORDER';
                $tagClass = 'hot';
            } elseif ($fq === 'anti_order') {
                $days = isset($it['days_idle']) ? intval($it['days_idle']) : 0;
                $frozen = isset($it['frozen']) ? floatval($it['frozen']) : 0;
                $ctx = ($days>0?$days.' дни · ':'').'<b>'.round($frozen).' лв замразени</b>';
                $tag = $days>0 ? $days.'д' : 'dim';
                $tagClass = 'dim';
            }

            $sections[$qkey]['items'][] = [
                'id' => intval($it['id']),
                'name' => $it['name'],
                'price' => $price,
                'stock' => $stock,
                'stkClass' => $stkClass,
                'stkText' => $stkText,
                'tag' => $tag,
                'tagClass' => $tagClass,
                'ctx' => $ctx,
                'image_url' => $it['image_url'] ?? null,
                'category_name' => $it['category_name'] ?? null,
                'subcategory_name' => $it['subcategory_name'] ?? null,
                'action' => $actionPayload,
            ];}

        // S79 A2.6: total per insight (once)
        if ($ins['value_numeric'] > 0 && !empty($items)) {
            $sections[$qkey]['total'] += floatval($ins['value_numeric']);
        }
    }

    // Формат на total pill
    $totalLabels = [];
    foreach ($sections as $k => $s) {
        $cnt = count($s['items']);
        if ($cnt === 0) { $totalLabels[$k] = ''; continue; }
        if ($k === 'q1') $totalLabels[$k] = '−' . round($s['total']) . ' лв/седм';
        elseif ($k === 'q2') $totalLabels[$k] = '−' . round($s['total']) . ' лв profit';
        elseif ($k === 'q3') $totalLabels[$k] = '+' . round($s['total']) . ' лв';
        elseif ($k === 'q4') $totalLabels[$k] = $cnt . ' причини';
        elseif ($k === 'q5') $totalLabels[$k] = $cnt . ' артикула';
        elseif ($k === 'q6') $totalLabels[$k] = $cnt . ' · ' . round($s['total']) . ' лв';
    }

    // Общ брой артикули за header
    $totalProducts = DB::run(
        "SELECT COUNT(*) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL",
        [$store_id, $tenant_id]
    )->fetchColumn();

    echo json_encode([
        'ok' => true,
        'total_products' => intval($totalProducts),
        'sections' => $sections,
        'totals' => $totalLabels
    ]);
    exit;
}

    if ($ajax === 'search') {
        $q = trim($_GET['q'] ?? '');
        $sid = (int)($_GET['store_id'] ?? $store_id);
        $mix = isset($_GET['mix']) ? (int)$_GET['mix'] : 0;  // S79.FIX Bug #2: return products+categories
        if (strlen($q) < 1) { echo json_encode($mix ? ['products'=>[],'categories'=>[]] : []); exit; }
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
              AND p.parent_id IS NULL
              AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)
            GROUP BY p.id
            ORDER BY (p.name LIKE ?) DESC, p.name ASC
            LIMIT 30
        ", [$sid, $tenant_id, $like, $like, $like, $q.'%'])->fetchAll(PDO::FETCH_ASSOC);
        if (!$can_see_cost) { foreach ($rows as &$r) unset($r['cost_price']); }
        if ($mix) {
            // S79.FIX Bug #2: also fetch categories matching query
            $cats = DB::run("
                SELECT c.id, c.name, c.parent_id, COUNT(DISTINCT p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.tenant_id = ?
                WHERE c.tenant_id = ? AND c.name LIKE ?
                GROUP BY c.id
                ORDER BY (c.name LIKE ?) DESC, c.name ASC
                LIMIT 5
            ", [$tenant_id, $tenant_id, $like, $q.'%'])->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['products' => array_slice($rows, 0, 8), 'categories' => $cats, 'total_products' => count($rows)]);
            exit;
        }
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
        // Check if product has children (variant parent)
        $child_count = DB::run("SELECT COUNT(*) FROM products WHERE parent_id=? AND tenant_id=? AND is_active=1", [$pid,$tenant_id])->fetchColumn();
        if ($child_count > 0) {
            // Sum children stock per store
            $stocks = DB::run("
                SELECT st.id AS store_id, st.name AS store_name,
                    CAST(COALESCE((SELECT SUM(i2.quantity) FROM inventory i2 JOIN products p2 ON p2.id=i2.product_id WHERE p2.parent_id=? AND i2.store_id=st.id),0) AS SIGNED) AS qty
                FROM stores st WHERE st.company_id = (SELECT company_id FROM stores WHERE id = ?) ORDER BY st.name
            ", [$pid, $store_id])->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stocks = DB::run("
                SELECT st.id AS store_id, st.name AS store_name, CAST(COALESCE(i.quantity, 0) AS SIGNED) AS qty
                FROM stores st LEFT JOIN inventory i ON i.store_id = st.id AND i.product_id = ?
                WHERE st.company_id = (SELECT company_id FROM stores WHERE id = ?) ORDER BY st.name
            ", [$pid, $store_id])->fetchAll(PDO::FETCH_ASSOC);
        }
        $variations = DB::run("
            SELECT p.id, p.name, p.code, p.retail_price, p.barcode, p.size, p.color, p.image_url,
                   CAST(COALESCE(SUM(i.quantity), 0) AS SIGNED) AS total_stock
            FROM products p LEFT JOIN inventory i ON i.product_id = p.id
            WHERE p.parent_id = ? AND p.tenant_id = ? AND p.is_active = 1
            GROUP BY p.id ORDER BY p.name
        ", [$pid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $price_history = DB::run("
            SELECT retail_price, cost_price, created_at AS changed_at FROM price_history WHERE product_id = ? ORDER BY created_at DESC LIMIT 10
        ", [$pid])->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['product'=>$product,'stocks'=>$stocks,'variations'=>$variations,'price_history'=>$price_history]);
        exit;
    }

    // ─── S88.BUG#7: product history (audit_log timeline) ───
    if ($ajax === 'product_history') {
        $pid = (int)($_GET['id'] ?? 0);
        if (!$pid) { echo json_encode(['error' => 'no_id']); exit; }
        $rows = DB::run(
            "SELECT a.id, a.user_id, a.action, a.old_values, a.new_values, a.created_at, a.source,
                    u.name AS user_name
             FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id=? AND a.table_name='products' AND a.record_id=?
             ORDER BY a.id DESC LIMIT 50",
            [$tenant_id, $pid]
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── S88.BUG#7: revert a single audit_log change ───
    if ($ajax === 'revert_change') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'method']); exit; }
        $hid = (int)($_POST['history_id'] ?? 0);
        if (!$hid) { echo json_encode(['error' => 'no_history_id']); exit; }
        $row = DB::run(
            "SELECT record_id, old_values FROM audit_log
             WHERE id=? AND tenant_id=? AND table_name='products' AND action='update'",
            [$hid, $tenant_id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['old_values'])) { echo json_encode(['error' => 'no_snapshot']); exit; }
        $old = json_decode($row['old_values'], true) ?: [];
        $pid = (int)$row['record_id'];

        // Snapshot CURRENT before reverting (so the revert itself is reversible)
        $cur = DB::run(
            "SELECT name, code, barcode, category_id, supplier_id,
                    cost_price, retail_price, wholesale_price, unit,
                    min_quantity, location, description, size, color,
                    vat_rate, origin_country, composition, is_domestic
             FROM products WHERE id=? AND tenant_id=?",
            [$pid, $tenant_id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$cur) { echo json_encode(['error' => 'product_gone']); exit; }

        // Whitelist columns from old_values
        $cols = ['name','code','barcode','category_id','supplier_id',
                 'cost_price','retail_price','wholesale_price','unit',
                 'min_quantity','location','description','size','color',
                 'vat_rate','origin_country','composition','is_domestic'];
        $sets = []; $params = [];
        foreach ($cols as $c) {
            if (array_key_exists($c, $old)) { $sets[] = "$c=?"; $params[] = $old[$c]; }
        }
        if (!$sets) { echo json_encode(['error' => 'empty_snapshot']); exit; }
        $params[] = $pid; $params[] = $tenant_id;
        DB::run("UPDATE products SET " . implode(',', $sets) . ", updated_at=NOW() WHERE id=? AND tenant_id=?", $params);

        DB::run("INSERT INTO audit_log (tenant_id,user_id,table_name,record_id,action,source_detail,old_values,new_values) VALUES (?,?,?,?,?,?,?,?)",
            [$tenant_id, $user_id, 'products', $pid, 'update',
             'revert_of:' . $hid,
             json_encode($cur, JSON_UNESCAPED_UNICODE),
             json_encode($old, JSON_UNESCAPED_UNICODE)]);

        echo json_encode(['ok' => true, 'reverted_to' => $hid]);
        exit;
    }

    // ─── S88.BUG#3 + S88.KP: last saved parent product (for "Като предния") ───
    if ($ajax === 'last_product') {
        // S88.KP: extended payload — names + variant axes for the new wizard UI
        // S88B.HOTFIX: products has no subcategory_id column — subcategories live as
        // categories.parent_id. Derive category_id/subcategory_id from the cat hierarchy.
        $row = DB::run(
            "SELECT p.id, p.code, p.name, p.supplier_id,
                    CASE WHEN cat.parent_id IS NOT NULL THEN cat.parent_id ELSE p.category_id END AS category_id,
                    CASE WHEN cat.parent_id IS NOT NULL THEN p.category_id ELSE NULL END AS subcategory_id,
                    p.unit, p.cost_price, p.retail_price, p.wholesale_price, p.vat_rate,
                    p.min_quantity, p.location, p.description, p.origin_country,
                    p.composition, p.is_domestic, p.image_url,
                    sup.name AS supplier_name,
                    COALESCE(par.name, cat.name) AS category_name,
                    CASE WHEN cat.parent_id IS NOT NULL THEN cat.name ELSE NULL END AS subcategory_name
             FROM products p
             LEFT JOIN suppliers  sup ON sup.id = p.supplier_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             LEFT JOIN categories par ON par.id = cat.parent_id
             WHERE p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
             ORDER BY p.id DESC LIMIT 1",
            [$tenant_id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['error' => 'no_previous']); exit; }
        $sum_qty = (int)DB::run(
            "SELECT COALESCE(SUM(i.quantity),0) FROM inventory i
             WHERE i.product_id=? AND i.store_id=?",
            [(int)$row['id'], $store_id]
        )->fetchColumn();

        // S88.KP: detect variant structure of source — group children by color → sizes[]
        $children = DB::run(
            "SELECT color, size FROM products
             WHERE tenant_id=? AND parent_id=? AND is_active=1
             ORDER BY color, size",
            [$tenant_id, (int)$row['id']]
        )->fetchAll(PDO::FETCH_ASSOC);
        $colors_map = [];
        $sizes_set  = [];
        foreach ($children as $c) {
            $col = $c['color'] !== null ? trim((string)$c['color']) : '';
            $sz  = $c['size']  !== null ? trim((string)$c['size'])  : '';
            if ($col === '' && $sz === '') continue;
            if (!isset($colors_map[$col])) $colors_map[$col] = [];
            if ($sz !== '' && !in_array($sz, $colors_map[$col], true)) $colors_map[$col][] = $sz;
            if ($sz !== '' && !in_array($sz, $sizes_set, true)) $sizes_set[] = $sz;
        }
        $row['has_variants'] = count($children) > 0 ? 1 : 0;
        $row['variant_axes'] = [
            'colors'   => array_keys($colors_map),
            'sizes'    => array_values($sizes_set),
            'by_color' => $colors_map,
        ];

        // S88B.KP: per BIBLE 7.2.8 v1.3 — code задължително празно
        // (Митко scan-ва barcode който става code, или voice въвежда). No auto-increment.
        $row['next_code']    = '';
        $row['source_qty']   = $sum_qty;
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
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
        $top_sellers = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url, SUM(si.quantity) AS sold_qty, SUM(si.quantity*si.unit_price) AS revenue FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE p.tenant_id=? AND s.store_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND s.status!='canceled' GROUP BY p.id ORDER BY sold_qty DESC LIMIT 5", [$tenant_id, $sid])->fetchAll(PDO::FETCH_ASSOC);
        $slow_movers = DB::run("SELECT p.id, p.name, p.code, p.retail_price, p.image_url, COALESCE(i.quantity,0) AS qty, DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=p.id), p.created_at)) AS days_stale FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity>0 AND p.parent_id IS NULL HAVING days_stale BETWEEN 25 AND 45 ORDER BY days_stale DESC LIMIT 10", [$sid, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);
        $counts = DB::run("SELECT COUNT(DISTINCT p.id) AS total_products, COALESCE(SUM(i.quantity),0) AS total_units FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL", [$sid, $tenant_id])->fetch(PDO::FETCH_ASSOC);
        // S79.FIX.B-HIDDEN-INV-BE: Store Health metrics (Вариант B)
        $sh_total = (int)DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL", [$tenant_id])->fetchColumn();
        if ($sh_total > 0) {
            $sh_recent = (int)DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND last_counted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$tenant_id])->fetchColumn();
            $sh_accuracy = (int)round(($sh_recent / $sh_total) * 100);
            $sh_avg_days = DB::run("SELECT AVG(DATEDIFF(NOW(), last_counted_at)) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND last_counted_at IS NOT NULL", [$tenant_id])->fetchColumn();
            $sh_freshness = $sh_avg_days === null ? 0 : (int)max(0, min(100, round(100 - ($sh_avg_days * 100 / 30))));
            $sh_conf = DB::run("SELECT AVG(confidence_score) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL", [$tenant_id])->fetchColumn();
            $sh_confidence = $sh_conf === null ? 0 : (int)round($sh_conf);
            $sh_score = (int)round($sh_accuracy * 0.4 + $sh_freshness * 0.3 + $sh_confidence * 0.3);
            $sh_uncounted = (int)DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND (last_counted_at IS NULL OR last_counted_at < DATE_SUB(NOW(), INTERVAL 60 DAY))", [$tenant_id])->fetchColumn();
            $sh_incomplete = (int)DB::run("SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL AND (supplier_id IS NULL OR category_id IS NULL)", [$tenant_id])->fetchColumn();
        } else {
            $sh_score = 0; $sh_accuracy = 0; $sh_freshness = 0; $sh_confidence = 0;
            $sh_uncounted = 0; $sh_incomplete = 0;
        }
        $store_health = ['score'=>$sh_score,'accuracy'=>$sh_accuracy,'freshness'=>$sh_freshness,'confidence'=>$sh_confidence,'uncounted'=>$sh_uncounted,'incomplete'=>$sh_incomplete,'total'=>$sh_total];
        echo json_encode(['capital'=>$can_see_margin?round($capital['retail_value'],2):null,'avg_margin'=>$can_see_margin?$avg_margin:null,'zombies'=>$zombies,'low_stock'=>$low_stock,'out_of_stock'=>$out_of_stock,'top_sellers'=>$top_sellers,'slow_movers'=>$slow_movers,'counts'=>$counts,'store_health'=>$store_health]);
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
            // S90.PRODUCTS.SPRINT_B D3: за wizard-а Pesho трябва да види категориите
            // на ИЗБРАНИЯ доставчик. UNION на:
            //   (a) ръчно мапнати категории в supplier_categories
            //   (b) категории с поне 1 активен продукт от този доставчик (auto-discover)
            $categories = DB::run(
                "SELECT DISTINCT c.id, c.name, c.parent_id
                 FROM categories c
                 WHERE c.tenant_id = ?
                   AND (
                        EXISTS (SELECT 1 FROM supplier_categories sc
                                WHERE sc.category_id=c.id AND sc.supplier_id=? AND sc.tenant_id=?)
                     OR EXISTS (SELECT 1 FROM products p
                                WHERE p.category_id=c.id AND p.supplier_id=? AND p.tenant_id=? AND p.is_active=1)
                   )
                 ORDER BY c.name",
                [$tenant_id, $sup, $tenant_id, $sup, $tenant_id]
            )->fetchAll(PDO::FETCH_ASSOC);
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

    // ─── S90.PRODUCTS.SPRINT_B D5: live duplicate-name check докато Pesho пише ───
    // Връща top 5 близки имена с similarity ≥ 0.65; UI показва banner само за ≥ 0.85.
    if ($ajax === 'name_dupe_check') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 3) { echo json_encode([]); exit; }
        $editId = (int)($_GET['exclude_id'] ?? 0);
        $like = '%' . $q . '%';
        $params = [$tenant_id, $like];
        $sql = "SELECT id, name, retail_price FROM products
                WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL
                  AND name LIKE ?";
        if ($editId > 0) { $sql .= " AND id <> ?"; $params[] = $editId; }
        $sql .= " ORDER BY CHAR_LENGTH(name) ASC LIMIT 40";
        $rows = DB::run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        // PHP-side similarity: similar_text() връща percent (0..100).
        $qLower = mb_strtolower($q);
        $matches = [];
        foreach ($rows as $r) {
            $nameLower = mb_strtolower($r['name']);
            similar_text($qLower, $nameLower, $pct);
            $sim = round($pct / 100.0, 3);
            if ($sim >= 0.65) {
                $matches[] = [
                    'id'    => (int)$r['id'],
                    'name'  => $r['name'],
                    'price' => (float)$r['retail_price'],
                    'score' => $sim,
                ];
            }
        }
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        echo json_encode(array_slice($matches, 0, 5)); exit;
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
        elseif ($flt==='zero_stock') { $where[] = "i.quantity = 0"; $where[] = "p.id IN (SELECT si2.product_id FROM sale_items si2 JOIN sales s2 ON s2.id=si2.sale_id WHERE s2.store_id=? AND s2.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND s2.status!='canceled')"; $params[] = $sid; }
        elseif ($flt==='below_min') { $where[] = "p.min_quantity > 0 AND i.quantity > 0 AND i.quantity <= p.min_quantity"; }
        elseif ($flt==='top_sales') { $where[] = "p.id IN (SELECT si3.product_id FROM sale_items si3 JOIN sales s3 ON s3.id=si3.sale_id WHERE s3.store_id=? AND s3.status!='canceled' AND s3.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY si3.product_id ORDER BY SUM(si3.quantity) DESC LIMIT 10)"; $params[] = $sid; }
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
            $products = DB::run("SELECT p.id,p.name,p.code,p.barcode,p.retail_price,p.cost_price,p.image_url,p.supplier_id,p.category_id,p.parent_id,p.discount_pct,p.discount_ends_at,p.min_quantity,p.unit,s.name AS supplier_name,c.name AS category_name,CAST(COALESCE(
                CASE WHEN EXISTS(SELECT 1 FROM products ch WHERE ch.parent_id=p.id AND ch.is_active=1)
                THEN (SELECT SUM(ci.quantity) FROM inventory ci JOIN products cp ON cp.id=ci.product_id WHERE cp.parent_id=p.id AND ci.store_id={$sid})
                ELSE i.quantity END
            ,0) AS SIGNED) AS store_stock,{$dse} AS days_stale, CAST(COALESCE((SELECT SUM(si99.quantity) FROM sale_items si99 JOIN sales s99 ON s99.id=si99.sale_id JOIN products cp2 ON cp2.id=si99.product_id WHERE (cp2.id=p.id OR cp2.parent_id=p.id) AND s99.store_id={$sid} AND s99.status!='canceled' AND s99.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)),0) AS SIGNED) AS sold_30d FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql} HAVING {$hSQL} ORDER BY {$order} LIMIT ? OFFSET ?", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);
            $total = DB::run("SELECT COUNT(*) FROM (SELECT p.id,{$dse} AS days_stale FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql} HAVING {$hSQL}) sub", array_merge([$sid], $params))->fetchColumn();
        } else {
            $products = DB::run("SELECT p.id,p.name,p.code,p.barcode,p.retail_price,p.cost_price,p.image_url,p.supplier_id,p.category_id,p.parent_id,p.discount_pct,p.discount_ends_at,p.min_quantity,p.unit,s.name AS supplier_name,c.name AS category_name,CAST(COALESCE(
                CASE WHEN EXISTS(SELECT 1 FROM products ch WHERE ch.parent_id=p.id AND ch.is_active=1)
                THEN (SELECT SUM(ci.quantity) FROM inventory ci JOIN products cp ON cp.id=ci.product_id WHERE cp.parent_id=p.id AND ci.store_id={$sid})
                ELSE i.quantity END
            ,0) AS SIGNED) AS store_stock, CAST(COALESCE((SELECT SUM(si99.quantity) FROM sale_items si99 JOIN sales s99 ON s99.id=si99.sale_id JOIN products cp2 ON cp2.id=si99.product_id WHERE (cp2.id=p.id OR cp2.parent_id=p.id) AND s99.store_id={$sid} AND s99.status!='canceled' AND s99.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)),0) AS SIGNED) AS sold_30d FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE {$where_sql} ORDER BY {$order} LIMIT ? OFFSET ?", array_merge([$sid], $params, [$per_page, $offset]))->fetchAll(PDO::FETCH_ASSOC);
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
        $sales_30d = DB::run("SELECT CAST(COALESCE(SUM(si.quantity),0) AS SIGNED) AS qty, ROUND(COALESCE(SUM(si.quantity*si.unit_price),0),2) AS revenue FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=? AND s.store_id=? AND s.status!='canceled' AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$pid, $sid])->fetch(PDO::FETCH_ASSOC);
        $child_count = DB::run("SELECT COUNT(*) FROM products WHERE parent_id=? AND tenant_id=? AND is_active=1", [$pid,$tenant_id])->fetchColumn();
        if ($child_count > 0) {
            $stock = DB::run("SELECT COALESCE(SUM(i.quantity),0) FROM inventory i JOIN products p ON p.id=i.product_id WHERE p.parent_id=? AND p.is_active=1", [$pid])->fetchColumn();
        } else {
            $stock = DB::run("SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id=?", [$pid])->fetchColumn();
        }
        $days_supply = ($sales_30d['qty']>0) ? round(($stock/($sales_30d['qty']/30)),0) : 999;
        $days_alive = (int)DB::run("SELECT DATEDIFF(NOW(), created_at) FROM products WHERE id=?", [$pid])->fetchColumn();
        $last_sale_days = (int)DB::run("SELECT DATEDIFF(NOW(), COALESCE((SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE si.product_id=?), NOW()))", [$pid])->fetchColumn();
        $days_stale = min($days_alive, $sales_30d['qty'] > 0 ? $last_sale_days : $days_alive);
        // i18n texts per tenant language
        $t = match($lang ?? 'bg') {
            'en' => [
                'zombie' => "Sitting for {days} days. {stock} pcs locked — consider a discount or bundle.",
                'slow' => "Slow movement — {days} days of supply. Check if there is demand.",
                'low' => "{stock} pcs left, minimum is {min}. Consider restocking.",
                'out' => "Out of stock. If there is demand, you lose sales every day.",
                'margin' => "Net profit {margin}% — low. Check your cost price.",
                'sales' => "30 days: {qty} pcs sold for {revenue} {currency}.",
                'ok' => "Everything looks good.",
            ],
            'de' => [
                'zombie' => "Liegt seit {days} Tagen. {stock} Stk. gebunden — Rabatt oder Paket erwaegen.",
                'slow' => "Langsame Bewegung — Vorrat fuer {days} Tage. Nachfrage pruefen.",
                'low' => "{stock} Stk. uebrig bei Minimum {min}. Nachbestellen erwaegen.",
                'out' => "Ausverkauft. Bei Nachfrage verlierst du taeglich Umsatz.",
                'margin' => "Reingewinn {margin}% — niedrig. Einkaufspreis pruefen.",
                'sales' => "30 Tage: {qty} Stk. verkauft fuer {revenue} {currency}.",
                'ok' => "Alles in Ordnung.",
            ],
            default => [
                'zombie' => "Застоява от {days} дни. {stock} бр. заключени — обмисли намаление или пакетна оферта.",
                'slow' => "Бавно движение — запас за {days} дни. Провери дали има търсене.",
                'low' => "Остават {stock} бр. при минимум {min}. Помисли за зареждане.",
                'out' => "Свърши. Ако има търсене, губиш продажби всеки ден.",
                'margin' => "Чиста печалба {margin}% — ниска. Провери доставната цена.",
                'sales' => "30 дни: {qty} бр. продадени за {revenue} {currency}.",
                'ok' => "Всичко изглежда наред.",
            ],
        };
        $rep = fn($s, $vars) => str_replace(array_map(fn($k)=>'{'.$k.'}', array_keys($vars)), array_values($vars), $s);
        $rv = ['days'=>$days_stale,'stock'=>(int)$stock,'min'=>$product['min_quantity']??0,'qty'=>$sales_30d['qty'],'revenue'=>number_format($sales_30d['revenue'],2,',','.'),'currency'=>$currency,'margin'=>''];
        $analysis = [];
        if ($days_stale>90 && $stock>0) $analysis[] = ['type'=>'zombie','icon'=>'','text'=>$rep($t['zombie'],$rv),'severity'=>'high'];
        elseif ($days_stale>45 && $stock>0) $analysis[] = ['type'=>'slow','icon'=>'','text'=>$rep($t['slow'],$rv),'severity'=>'medium'];
        if ($stock<=$product['min_quantity'] && $stock>0 && $product['min_quantity']>0) $analysis[] = ['type'=>'low','icon'=>'','text'=>$rep($t['low'],$rv),'severity'=>'high'];
        elseif ($stock==0) $analysis[] = ['type'=>'out','icon'=>'','text'=>$rep($t['out'],$rv),'severity'=>'critical'];
        if ($can_see_margin && $product['cost_price']>0) { $margin=round((($product['retail_price']-$product['cost_price'])/$product['retail_price'])*100,1); $rv['margin']=$margin; if($margin<20) $analysis[]=['type'=>'margin','icon'=>'','text'=>$rep($t['margin'],$rv),'severity'=>'medium']; }
        if ($sales_30d['qty']>0) $analysis[] = ['type'=>'sales','icon'=>'','text'=>$rep($t['sales'],$rv),'severity'=>'info'];
        echo json_encode(['analysis'=>$analysis,'days_supply'=>$days_supply,'sales_30d'=>$sales_30d]); exit;
    }


    // ─── UPLOAD IMAGE ───
    if ($ajax === 'upload_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $pid = (int)($input['product_id'] ?? 0);
        $image = $input['image'] ?? '';
        if (!$pid || !$image) { echo json_encode(['error'=>'missing_data']); exit; }
        // Verify product belongs to tenant
        $exists = DB::run("SELECT id FROM products WHERE id=? AND tenant_id=?", [$pid, $tenant_id])->fetch();
        if (!$exists) { echo json_encode(['error'=>'not_found']); exit; }
        // Decode base64
        if (preg_match('/^data:image\/(\w+);base64,/', $image, $m)) {
            $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $image = preg_replace('/^data:image\/\w+;base64,/', '', $image);
        } else { $ext = 'jpg'; }
        $data = base64_decode($image);
        if (!$data) { echo json_encode(['error'=>'invalid_image']); exit; }
        // Save file
        $dir = __DIR__ . '/uploads/products/' . $tenant_id;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = $pid . '_' . time() . '.' . $ext;
        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, $data);
        $url = '/uploads/products/' . $tenant_id . '/' . $filename;
        // Update DB
        DB::run("UPDATE products SET image_url=? WHERE id=? AND tenant_id=?", [$url, $pid, $tenant_id]);
        echo json_encode(['ok'=>true, 'image_url'=>$url]); exit;
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
        $payload = ['contents'=>[['parts'=>[['inlineData'=>['mimeType'=>'image/jpeg','data'=>$image_data]],['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.3,'maxOutputTokens'=>1024]];
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
        $cat = $input['category'] ?? '';
        $sup = $input['supplier'] ?? '';
        $axes = $input['axes'] ?? '';
        $composition = $input['composition'] ?? '';
        $lang_name = match($lang ?? 'bg') { 'en'=>'English', 'de'=>'German', 'fr'=>'French', 'es'=>'Spanish', default=>'Bulgarian' };
        $prompt = "You are an e-commerce copywriter. Write a short SEO product description.\n";
        $prompt .= "Product: {$name}\n";
        if ($cat) $prompt .= "Category: {$cat}\n";
        if ($sup) $prompt .= "Brand: {$sup}\n";
        if ($axes) $prompt .= "Available variations: {$axes}\n";
        if ($composition) $prompt .= "Composition/Material: {$composition}\n";
        $prompt .= "\nRULES (MANDATORY - follow ALL):\n";
        $prompt .= "- Write in {$lang_name}\n";
        $prompt .= "- MINIMUM 3 sentences, MINIMUM 40 words. Never less than 40 words.\n";
        $prompt .= "- MUST mention product name, category, brand in the text\n";
        $prompt .= "- If variations are given, MUST list the available sizes and colors explicitly\n";
        $prompt .= "- Describe material, style, occasion for wearing/using\n";
        $prompt .= "- End with a call to action (perfect choice for..., ideal for...)\n";
        $prompt .= "- No emoji, no quotes, no title - output ONLY the description text\n";
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key='.GEMINI_API_KEY;
        $payload = ['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.7,'maxOutputTokens'=>4000]];
        $keys = [GEMINI_API_KEY, GEMINI_API_KEY_2];
        $description = '';
        foreach ($keys as $key) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.GEMINI_MODEL.':generateContent?key='.$key;
            $ch = curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
            $resp = curl_exec($ch); curl_close($ch);
            $data = json_decode($resp, true);
            $txt = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if ($txt && strlen($txt) > 10) { $description = $txt; break; }
        }
        echo json_encode(['description'=>$description]); exit;
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

    if ($ajax === 'delete_unit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $unit = trim($_POST['unit'] ?? '');
        if (!$unit) { echo json_encode(['error'=>'Липсва']); exit; }
        $t = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $units = json_decode($t['units_config'] ?? '[]', true) ?: [];
        $units = array_values(array_filter($units, function($u) use($unit){ return $u !== $unit; }));
        DB::run("UPDATE tenants SET units_config=? WHERE id=?", [json_encode($units, JSON_UNESCAPED_UNICODE), $tenant_id]);
        echo json_encode(['units'=>$units]); exit;
    }

    // S73.B.37: Tenant-specific колорит
    if ($ajax === 'add_color' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $hex = trim($_POST['hex'] ?? '');
        if (!$name || !preg_match('/^#[0-9a-fA-F]{6}$/',$hex)) { echo json_encode(['error'=>'Невалидни данни']); exit; }
        $t = DB::run("SELECT colors_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $custom = json_decode($t['colors_config'] ?? '[]', true) ?: [];
        // Replace ако съществува
        $found = false;
        foreach ($custom as &$c) { if (mb_strtolower($c['name']) === mb_strtolower($name)) { $c['hex']=$hex; $found=true; break; } }
        unset($c);
        if (!$found) $custom[] = ['name'=>$name,'hex'=>$hex];
        DB::run("UPDATE tenants SET colors_config=? WHERE id=?", [json_encode($custom, JSON_UNESCAPED_UNICODE), $tenant_id]);
        echo json_encode(['ok'=>true, 'custom'=>$custom, 'added'=>['name'=>$name,'hex'=>$hex]]); exit;
    }
    if ($ajax === 'delete_color' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(['error'=>'Липсва име']); exit; }
        $t = DB::run("SELECT colors_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
        $custom = json_decode($t['colors_config'] ?? '[]', true) ?: [];
        $custom = array_values(array_filter($custom, function($c) use($name){ return mb_strtolower($c['name']) !== mb_strtolower($name); }));
        DB::run("UPDATE tenants SET colors_config=? WHERE id=?", [json_encode($custom, JSON_UNESCAPED_UNICODE), $tenant_id]);
        echo json_encode(['ok'=>true, 'custom'=>$custom]); exit;
    }

    // ─── SKIP WHOLESALE ───
    if ($ajax === 'skip_wholesale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        DB::run("UPDATE tenants SET skip_wholesale_price=1 WHERE id=?", [$tenant_id]);
        echo json_encode(['ok'=>true]); exit;
    }

    // ─── AI IMAGE (legacy stub) ───
    if ($ajax === 'ai_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'bg_removal';
        if ($type === 'bg_removal') { $cr = DB::run("SELECT ai_credits_bg FROM tenants WHERE id=?",[$tenant_id])->fetchColumn(); if ($cr<=0) { echo json_encode(['error'=>'Нямаш кредити за бял фон']); exit; } }
        else { $cr = DB::run("SELECT ai_credits_tryon FROM tenants WHERE id=?",[$tenant_id])->fetchColumn(); if ($cr<=0) { echo json_encode(['error'=>'Нямаш кредити за AI Магия']); exit; } }
        echo json_encode(['status'=>'pending','message'=>'AI обработва снимката...']); exit;
    }

    // ─── S82.STUDIO.1: AI credits + plan info for the AI Studio modal header ───
    if ($ajax === 'ai_credits') {
        require_once __DIR__ . '/config/helpers.php';
        $row = DB::run("SELECT id, plan, plan_effective, trial_ends_at, ai_credits_bg, ai_credits_tryon, ai_credits_bg_total, ai_credits_tryon_total FROM tenants WHERE id=?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'tenant not found']); exit; }
        $eff = effectivePlan($row);
        echo json_encode([
            'plan'            => $eff,
            'plan_real'       => $row['plan'] ?? 'free',
            'trial_ends_at'   => $row['trial_ends_at'],
            'bg_remaining'    => (int)($row['ai_credits_bg'] ?? 0),
            'bg_total'        => (int)($row['ai_credits_bg_total'] ?? 0),
            'tryon_remaining' => (int)($row['ai_credits_tryon'] ?? 0),
            'tryon_total'     => (int)($row['ai_credits_tryon_total'] ?? 0),
            'is_locked'       => ($eff === 'free'),
        ]);
        exit;
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
        $v = DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id AND i.store_id=? WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL AND i.quantity=0 AND p.id IN (SELECT si.product_id FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.store_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND s.status!='canceled')", [$sid,$tenant_id,$sid])->fetchColumn();
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
        $v = DB::run("SELECT COUNT(DISTINCT si.product_id) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.store_id=? AND s.status!='canceled' AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$sid])->fetchColumn();
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
if (file_exists(__DIR__.'/biz-coefficients.php')) {
    require_once __DIR__.'/biz-coefficients.php';
    if (file_exists(__DIR__.'/biz-compositions.php')) {
        require_once __DIR__.'/biz-compositions.php';
        $bizComps = getBizCompositions($business_type ?: 'магазин');
    } else {
        $bizComps = ['compositions' => [], 'countries' => []];
    }
    $bizVars = findBizVariants($business_type ?: 'магазин');
    $allBizPresets = ['sizes'=>[],'colors'=>[],'other'=>[]];
    foreach ($BIZ_VARIANTS as $bv) {
        if (!empty($bv['variant_presets'])) {
            foreach ($bv['variant_presets'] as $field => $vals) {
                $fl = mb_strtolower($field);
                if (mb_strpos($fl,'размер')!==false||mb_strpos($fl,'size')!==false||mb_strpos($fl,'ръст')!==false) {
                    foreach ($vals as $v) { if (!in_array($v,$allBizPresets['sizes'])) $allBizPresets['sizes'][] = $v; }
                } elseif (mb_strpos($fl,'цвят')!==false||mb_strpos($fl,'color')!==false||mb_strpos($fl,'десен')!==false) {
                    foreach ($vals as $v) { if (!in_array($v,$allBizPresets['colors'])) $allBizPresets['colors'][] = $v; }
                } else {
                    foreach ($vals as $v) { if (!in_array($v,$allBizPresets['other'])) $allBizPresets['other'][] = $v; }
                }
            }
        }
    }
} else { $bizVars = []; $allBizPresets = ['sizes'=>[],'colors'=>[],'other'=>[]]; }

// Page data
$all_suppliers = DB::run("SELECT id, name FROM suppliers WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$all_categories = DB::run("SELECT id, name, parent_id FROM categories WHERE tenant_id=? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$tenant_cfg = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];
$COLOR_PALETTE = [['name'=>'Черен','hex'=>'#1a1a1a'],['name'=>'Бял','hex'=>'#f5f5f5'],['name'=>'Сив','hex'=>'#6b7280'],['name'=>'Червен','hex'=>'#ef4444'],['name'=>'Син','hex'=>'#3b82f6'],['name'=>'Зелен','hex'=>'#22c55e'],['name'=>'Жълт','hex'=>'#eab308'],['name'=>'Розов','hex'=>'#ec4899'],['name'=>'Оранжев','hex'=>'#f97316'],['name'=>'Лилав','hex'=>'#8b5cf6'],['name'=>'Кафяв','hex'=>'#92400e'],['name'=>'Navy','hex'=>'#1e40af'],['name'=>'Бежов','hex'=>'#d4b896'],['name'=>'Бордо','hex'=>'#7f1d1d'],['name'=>'Тюркоаз','hex'=>'#14b8a6'],['name'=>'Графит','hex'=>'#374151'],['name'=>'Пудра','hex'=>'#f9a8d4'],['name'=>'Маслинен','hex'=>'#65a30d'],['name'=>'Корал','hex'=>'#fb923c'],['name'=>'Екрю','hex'=>'#fef3c7']];
// S73.B.37: append tenant custom colors
$_tenantColors = DB::run("SELECT colors_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
$_custom_colors = json_decode($_tenantColors['colors_config'] ?? '[]', true) ?: [];
$_existing_names = array_map(function($c){return mb_strtolower($c['name']);}, $COLOR_PALETTE);
foreach ($_custom_colors as $cc) {
    $lower = mb_strtolower($cc['name']);
    $idx = array_search($lower, $_existing_names);
    if ($idx === false) { $COLOR_PALETTE[] = $cc; }
    else { $COLOR_PALETTE[$idx] = $cc; } // override default ако има override
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<title>Артикули — RunMyStore.ai</title>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="business-type" content="<?= htmlspecialchars($business_type) ?>">
<meta name="theme-color" content="#08090d">

<!-- Theme bootstrap — ПЪРВОТО нещо в head, преди CSS -->
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- DESIGN KIT v1.1 — точно в този ред -->
<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">
<style>
/* ═══════════════════════════════════════════════════════════
   PRODUCTS MODULE — module-specific helpers only.
   Tokens, .glass / .shine / .glow / .rms-* / .pill — design-kit/.
   ═══════════════════════════════════════════════════════════ */

/* mod-prod-* helpers (преди бяха inline стилове, извадени в класове) */
.mod-prod-more-groups{margin-top:10px;padding:10px 12px;border-radius:14px;border:1px dashed hsl(var(--hue1) 30% 40% / 0.5);background:rgba(255,255,255,0.02);cursor:pointer;display:flex;align-items:center;gap:8px;color:hsl(var(--hue1) 60% 78%);font-size:12px;font-weight:600}
.mod-prod-v4-footer{position:fixed;left:0;right:0;bottom:0;padding:8px 12px;background:rgba(10,11,20,0.98);border-top:1px solid hsl(var(--hue1) 30% 20% / 0.5);z-index:201;display:flex;gap:6px;max-width:480px;margin:0 auto}
.mod-prod-mx-cta{flex:1;height:44px;border-radius:12px;background:linear-gradient(135deg,hsl(var(--hue1) 65% 42%),hsl(var(--hue2) 65% 36%));border:1px solid hsl(var(--hue1) 65% 60%);color:#fff;font-size:11px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px hsl(var(--hue1) 70% 35% / 0.4),inset 0 1px 0 rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;gap:4px;font-family:inherit;animation:vCtaPulse 2.2s ease-in-out infinite}

/* ═══ VAR STEP (S73.B.6 — 1:1 от add-product-variations.html) ═══ */
.v-var-card{padding:0;margin-bottom:12px;overflow:hidden}
.v-preview-pill{display:flex;align-items:center;gap:10px;padding:10px 14px;margin-bottom:12px;border-radius:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06)}
.v-preview-thumb{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,hsl(var(--hue1) 40% 25%),hsl(var(--hue2) 40% 22%));border:1px solid hsl(var(--hue1) 40% 35% / 0.4);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.v-preview-thumb svg{width:18px;height:18px;stroke:hsl(var(--hue1) 60% 80%);stroke-width:1.5;fill:none}
.v-preview-info{flex:1;min-width:0}
.v-preview-name{font-size:13px;font-weight:700;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.v-preview-meta{font-size:10px;color:rgba(255,255,255,0.4);margin-top:1px}

.v-axis-tabs{display:flex;border-bottom:1px solid hsl(var(--hue2) 15% 18% / 0.8);padding:0 14px;gap:4px;overflow-x:auto;scrollbar-width:none}
.v-axis-tabs::-webkit-scrollbar{display:none}
.v-axis-tab{position:relative;padding:14px 18px 12px;font-size:13px;font-weight:700;color:rgba(255,255,255,0.4);cursor:pointer;border:none;background:transparent;border-bottom:2px solid transparent;display:flex;align-items:center;gap:7px;margin-bottom:-1px;font-family:inherit;white-space:nowrap}
.v-axis-tab.active{color:hsl(var(--hue1) 60% 85%);border-bottom-color:hsl(var(--hue1) 70% 55%);text-shadow:0 0 12px hsl(var(--hue1) 60% 50% / 0.4)}
.v-axis-tab svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.v-axis-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:100px;background:hsl(var(--hue1) 40% 25% / 0.7);color:hsl(var(--hue1) 60% 88%);font-size:10px;font-weight:800;border:1px solid hsl(var(--hue1) 50% 40% / 0.5)}
.v-axis-tab.active .v-axis-tab-count{background:hsl(var(--hue1) 60% 45%);color:#fff;box-shadow:0 0 10px hsl(var(--hue1) 60% 50% / 0.5)}
.v-axis-tab-add{padding:14px 14px 12px;color:hsl(var(--hue1) 60% 70%);font-size:18px;cursor:pointer;border:none;background:transparent;font-family:inherit}

.v-sel-bar{display:flex;flex-wrap:wrap;gap:6px;padding:12px 14px 10px;align-items:center;border-bottom:1px solid hsl(var(--hue2) 15% 18% / 0.5);min-height:56px}
.v-sel-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 10px 5px 11px;border-radius:100px;background:linear-gradient(135deg,hsl(var(--hue1) 50% 28%),hsl(var(--hue1) 60% 22%));border:1px solid hsl(var(--hue1) 60% 50%);color:#fff;font-size:12px;font-weight:700;box-shadow:0 0 10px hsl(var(--hue1) 60% 45% / 0.3),inset 0 1px 0 hsl(var(--hue1) 60% 60% / 0.3);cursor:pointer}
.v-sel-chip-x{opacity:0.65;font-size:10px;font-weight:400;padding-left:2px}
.v-sel-chip .v-dot{width:10px;height:10px;border-radius:50%;border:1px solid rgba(255,255,255,0.3);flex-shrink:0}
.v-sel-empty{font-size:11px;color:rgba(255,255,255,0.4);font-style:italic;line-height:1.4}
.v-clear-btn{margin-left:auto;padding:4px 10px;border-radius:100px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#fca5a5;font-size:10px;font-weight:700;cursor:pointer;letter-spacing:0.02em;text-transform:uppercase;font-family:inherit}

.v-picker-body{padding:12px 14px 14px;max-height:520px;overflow-y:auto;-webkit-overflow-scrolling:touch}
.v-picker-search{position:relative;margin-bottom:12px}
.v-picker-search input{width:100%;padding:10px 14px 10px 38px;border-radius:12px;border:1px solid hsl(var(--hue2) 15% 20% / 0.6);background:linear-gradient(to bottom,hsl(var(--hue1) 20% 15% / 0.2),hsl(var(--hue1) 30% 10% / 0.4));color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.v-picker-search input:focus{border-color:hsl(var(--hue1) 50% 55% / 0.7);box-shadow:0 0 0 3px hsl(var(--hue1) 60% 50% / 0.15)}
.v-picker-search-ic{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.4);pointer-events:none}
.v-picker-search-ic svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}

.v-pgroup{border:1px solid hsl(var(--hue1) 20% 18% / 0.4);border-radius:14px;margin-bottom:10px;overflow:hidden;background:rgba(0,0,0,0.15)}
.v-pgroup-head{display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;background:linear-gradient(90deg,hsl(var(--hue1) 25% 15% / 0.5),hsl(var(--hue1) 15% 10% / 0.2) 80%,transparent)}
.v-pgroup-title{flex:1;font-size:11px;font-weight:800;color:hsl(var(--hue1) 50% 80%);letter-spacing:0.05em;text-transform:uppercase}
.v-pgroup-title.starred::before{content:'★ ';color:hsl(45 90% 65%);font-size:10px;text-shadow:0 0 8px hsl(45 90% 50% / 0.5)}
.v-pgroup-count{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);padding:2px 7px;border-radius:100px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06)}
.v-pgroup-count.has{color:hsl(var(--hue1) 60% 85%);background:hsl(var(--hue1) 40% 25% / 0.6);border-color:hsl(var(--hue1) 50% 40% / 0.6)}
.v-pgroup-arr{color:hsl(var(--hue1) 40% 55%);font-size:10px;transition:transform 0.25s var(--ease)}
.v-pgroup.open .v-pgroup-arr{transform:rotate(90deg)}
.v-pgroup-actions{display:flex;gap:4px;align-items:center}
.v-pgroup-footer{display:grid;grid-template-columns:repeat(5,1fr);gap:5px;padding:8px 10px;border-top:1px solid hsl(var(--hue1) 20% 18% / 0.3);background:rgba(0,0,0,0.25)}
.v-pgroup-footer .v-pgroup-act{padding:6px 0;font-size:9.5px;min-height:32px;display:flex;align-items:center;justify-content:center;text-align:center;gap:3px;line-height:1;letter-spacing:0.01em}
.v-pgroup-footer .v-pgroup-act svg{width:11px;height:11px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.v-pgroup-footer .v-pgroup-act.warn{background:linear-gradient(135deg,hsl(var(--hue1) 55% 32%),hsl(var(--hue2) 55% 28%));border-color:hsl(var(--hue1) 60% 50%);color:#fff;box-shadow:0 2px 8px hsl(var(--hue1) 60% 35% / 0.3),inset 0 1px 0 rgba(255,255,255,0.15);font-weight:700}
.v-pgroup-footer .v-pgroup-act.warn:hover{box-shadow:0 4px 14px hsl(var(--hue1) 60% 40% / 0.45)}
.v-pgroup-act{padding:6px 11px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.75);font-family:inherit;min-width:36px;text-align:center}
.v-pgroup-act:hover{color:hsl(var(--hue1) 60% 85%)}
.v-pgroup-act.warn{color:rgba(245,158,11,0.9);border-color:rgba(245,158,11,0.2);background:rgba(245,158,11,0.06)}
.v-pgroup-act.danger{color:rgba(239,68,68,0.7);border-color:rgba(239,68,68,0.2)}
.v-pgroup-body{display:none;padding:8px 10px 12px;flex-wrap:wrap;gap:6px;border-top:1px solid hsl(var(--hue1) 20% 18% / 0.3);background:rgba(0,0,0,0.2)}
.v-pgroup.open .v-pgroup-body{display:flex}

.v-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:100px;font-size:12px;font-weight:600;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.6);cursor:pointer;user-select:none;-webkit-user-select:none;font-family:inherit}
.v-chip:hover{background:hsl(var(--hue1) 30% 20% / 0.4);border-color:hsl(var(--hue1) 40% 40% / 0.5);color:hsl(var(--hue1) 60% 85%)}
.v-chip.selected{background:linear-gradient(135deg,hsl(var(--hue1) 70% 45%),hsl(var(--hue1) 80% 35%));border-color:hsl(var(--hue1) 80% 70%);color:#fff;box-shadow:0 0 18px hsl(var(--hue1) 70% 55% / 0.6),0 4px 12px hsl(var(--hue1) 70% 35% / 0.4),inset 0 1px 0 rgba(255,255,255,0.35);font-weight:800;transform:scale(1.03)}
.v-chip .v-dot{width:10px;height:10px;border-radius:50%;border:1px solid rgba(255,255,255,0.2);flex-shrink:0}

.v-custom-row{display:flex;gap:8px;margin-top:12px;align-items:stretch;flex-wrap:nowrap}
.v-custom-input{min-width:0}
.v-custom-btn{flex-shrink:0}
.v-custom-input{flex:1;padding:10px 14px;border-radius:12px;border:1px dashed hsl(var(--hue1) 30% 40% / 0.5);background:rgba(255,255,255,0.02);color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.v-custom-input:focus{border-style:solid;border-color:hsl(var(--hue1) 50% 55% / 0.7);box-shadow:0 0 0 3px hsl(var(--hue1) 60% 50% / 0.15)}
.v-custom-btn{padding:12px 18px;border-radius:12px;background:linear-gradient(135deg,hsl(var(--hue1) 65% 45%),hsl(var(--hue1) 75% 38%));border:1px solid hsl(var(--hue1) 65% 60%);color:#fff;font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:6px;box-shadow:0 4px 14px hsl(var(--hue1) 60% 40% / 0.4),0 0 16px hsl(var(--hue1) 60% 50% / 0.3),inset 0 1px 0 rgba(255,255,255,0.3);font-family:inherit;text-shadow:0 0 8px rgba(255,255,255,0.2);white-space:nowrap}
.v-custom-btn svg{width:14px;height:14px;stroke:#fff;stroke-width:2.5;fill:none;stroke-linecap:round}

.v-matrix-cta-wrap{padding:16px;margin-bottom:12px}
.v-mc-row{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.v-mc-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,hsl(var(--hue1) 50% 30%),hsl(var(--hue2) 50% 25%));border:1px solid hsl(var(--hue1) 50% 45% / 0.5);display:flex;align-items:center;justify-content:center;box-shadow:0 0 16px hsl(var(--hue1) 60% 40% / 0.25);flex-shrink:0}
.v-mc-icon svg{width:20px;height:20px;stroke:hsl(var(--hue1) 60% 85%);stroke-width:1.8;fill:none}
.v-mc-text{flex:1}
.v-mc-title{font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:2px}
.v-mc-sub{font-size:11px;color:rgba(255,255,255,0.4);line-height:1.4}
.v-mc-sub b{color:hsl(var(--hue1) 60% 85%);font-weight:700}
.v-matrix-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:14px;background:linear-gradient(135deg,hsl(var(--hue1) 55% 30% / 0.7),hsl(var(--hue2) 55% 26% / 0.7));border:1px solid hsl(var(--hue1) 60% 55% / 0.6);color:#fff;font-size:13px;font-weight:700;cursor:pointer;text-shadow:0 0 12px rgba(255,255,255,0.2);width:100%;font-family:inherit;animation:vCtaPulse 2.2s ease-in-out infinite}
@keyframes vCtaPulse{0%,100%{box-shadow:0 8px 24px hsl(var(--hue1) 70% 30% / 0.35),0 0 20px hsl(var(--hue1) 60% 45% / 0.2),inset 0 1px 0 rgba(255,255,255,0.2)}50%{box-shadow:0 12px 32px hsl(var(--hue1) 70% 40% / 0.55),0 0 40px hsl(var(--hue1) 60% 55% / 0.5),inset 0 1px 0 rgba(255,255,255,0.3)}}
.v-matrix-cta-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;text-align:left}
.v-matrix-cta-pill{padding:3px 8px;border-radius:8px;background:rgba(0,0,0,0.3);font-size:10px;font-weight:800;border:1px solid rgba(255,255,255,0.15)}
.v-matrix-cta-arrow{font-size:18px;flex-shrink:0}
.v-matrix-summary{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.25);margin-bottom:12px}
.v-ms-check{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;box-shadow:0 0 12px rgba(34,197,94,0.5);flex-shrink:0}
.v-ms-check svg{width:14px;height:14px;stroke:#fff;stroke-width:3;fill:none}
.v-ms-text{flex:1}
.v-ms-title{font-size:12px;font-weight:700;color:#86efac;margin-bottom:1px}
.v-ms-sub{font-size:10px;color:rgba(134,239,172,0.7)}
.v-ms-edit{padding:5px 12px;border-radius:100px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:#86efac;font-size:10px;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:0.03em;font-family:inherit}

/* ═══ MATRIX FULLSCREEN OVERLAY ═══ */
.mx-overlay{position:fixed;inset:0;z-index:999;background:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / 0.25) 0%,transparent 60%),radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / 0.25) 0%,transparent 60%),linear-gradient(180deg,#0a0b14 0%,#050609 100%);display:none;flex-direction:column;opacity:0;transition:opacity 0.25s var(--ease)}
.mx-overlay.open{display:flex;opacity:1}
.mx-header{flex-shrink:0;display:flex;align-items:center;gap:10px;padding:14px 16px 12px;background:rgba(3,7,18,0.9);border-bottom:1px solid hsl(var(--hue2) 15% 18% / 0.6)}
.mx-close{min-width:36px;height:36px;padding:0 12px;border-radius:11px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.75);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:600;font-family:inherit}
.mx-close svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}
.mx-title-wrap{flex:1;min-width:0}
.mx-title{font-size:15px;font-weight:800;background:linear-gradient(135deg,#f1f5f9 30%,hsl(var(--hue1) 60% 80%) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1}
.mx-subtitle{font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:0.08em;font-weight:600;margin-top:2px}
.mx-subtitle b{color:hsl(var(--hue1) 60% 85%);font-weight:700}
.mx-quick{flex-shrink:0;display:flex;gap:6px;overflow-x:auto;padding:10px 16px;scrollbar-width:none;border-bottom:1px solid hsl(var(--hue2) 15% 18% / 0.5);background:rgba(3,7,18,0.6)}
.mx-quick::-webkit-scrollbar{display:none}
.mx-qchip{flex-shrink:0;padding:7px 13px;border-radius:100px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.6);font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit}
.mx-qchip:hover{background:hsl(var(--hue1) 40% 22% / 0.4);border-color:hsl(var(--hue1) 50% 45% / 0.5);color:hsl(var(--hue1) 60% 88%)}
.mx-qchip.danger{border-color:rgba(239,68,68,0.25);color:#fca5a5}
.mx-body-wrap{flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:6px 0;position:relative}
@keyframes mxFlash{0%{opacity:0;transform:scale(0.97);filter:brightness(2)}40%{opacity:1;filter:brightness(1.3)}100%{opacity:1;transform:scale(1);filter:brightness(1)}}
.mx-table{border-collapse:separate;border-spacing:0;width:max-content;min-width:100%}
.mx-head-cell,.mx-row-head,.mx-corner{position:sticky;background:rgba(8,9,13,0.98);z-index:2}
.mx-corner{left:0;top:0;z-index:4;border-right:1px solid hsl(var(--hue2) 15% 18% / 0.8);border-bottom:1px solid hsl(var(--hue2) 15% 18% / 0.8)}
.mx-head-cell{top:0;z-index:3;padding:10px 8px;text-align:center;font-size:11px;font-weight:700;color:hsl(var(--hue1) 60% 85%);min-width:110px;border-bottom:1px solid hsl(var(--hue2) 15% 18% / 0.8);border-left:1px solid hsl(var(--hue2) 10% 14% / 0.3)}
.mx-head-cell .v-dot{width:12px;height:12px;border-radius:50%;border:1px solid rgba(255,255,255,0.25);display:inline-block;margin-right:6px;vertical-align:middle;box-shadow:0 0 8px rgba(255,255,255,0.1)}
.mx-row-head{left:0;z-index:2;padding:8px 10px;text-align:center;font-size:13px;font-weight:800;color:hsl(var(--hue1) 60% 88%);min-width:52px;width:52px;border-right:1px solid hsl(var(--hue2) 15% 18% / 0.8);border-bottom:1px solid hsl(var(--hue2) 10% 14% / 0.5);background:linear-gradient(to right,hsl(var(--hue1) 30% 16% / 0.7),hsl(var(--hue1) 20% 12% / 0.6))}
.mx-cell{padding:6px 4px;min-width:110px;border-bottom:1px solid hsl(var(--hue2) 10% 14% / 0.5);border-left:1px solid hsl(var(--hue2) 10% 14% / 0.3);background:rgba(0,0,0,0.15);vertical-align:middle;animation:mxPulse 1.8s ease-in-out infinite}
@keyframes mxPulse{0%,100%{background:rgba(0,0,0,0.15);box-shadow:inset 0 0 0 0 hsl(var(--hue1) 60% 50% / 0)}50%{background:hsl(var(--hue1) 50% 20% / 0.55);box-shadow:inset 0 0 0 2px hsl(var(--hue1) 60% 50% / 0.35)}}
.mx-cell.has-value{background:hsl(var(--hue1) 40% 18% / 0.45);animation:none;box-shadow:inset 0 0 0 1px hsl(var(--hue1) 60% 45% / 0.3)}
.mx-cell-inputs{display:flex;flex-direction:column;gap:4px;align-items:center}
.mx-cell-qty{width:54px;height:34px;padding:4px 2px;border-radius:8px;border:1px solid hsl(var(--hue2) 15% 22% / 0.7);background:rgba(8,9,13,0.5);color:hsl(var(--hue1) 60% 90%);font-size:14px;font-weight:800;font-family:inherit;text-align:center;outline:none;-moz-appearance:textfield}
.mx-cell-qty::placeholder{color:rgba(255,255,255,0.18);font-weight:500;font-size:10px}
.mx-cell-min::placeholder{color:rgba(245,158,11,0.25);font-weight:500}
.mx-cell-qty::-webkit-outer-spin-button,.mx-cell-qty::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.mx-cell-qty:focus{border-color:hsl(var(--hue1) 60% 55%);background:hsl(var(--hue1) 40% 15% / 0.6);box-shadow:0 0 0 2px hsl(var(--hue1) 60% 50% / 0.2)}
.mx-cell-lbl{font-size:8px;font-weight:700;color:rgba(255,255,255,0.4);letter-spacing:0.08em;text-transform:uppercase;line-height:1}
.mx-cell-min-row{display:flex;align-items:center;gap:2px;justify-content:center}
.mx-cell-min{width:36px;height:26px;padding:0;border-radius:6px;border:1px solid hsl(45 40% 22% / 0.7);background:rgba(8,9,13,0.5);color:hsl(45 80% 70%);font-size:11px;font-weight:700;font-family:inherit;text-align:center;outline:none;-moz-appearance:textfield}
.mx-cell-min::-webkit-outer-spin-button,.mx-cell-min::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.mx-cell-min:focus{border-color:hsl(45 70% 55%);box-shadow:0 0 0 2px hsl(45 70% 50% / 0.2)}
.mx-min-step{width:18px;height:26px;border-radius:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:hsl(45 80% 70%);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;font-family:inherit;padding:0}
.mx-min-step:hover{background:hsl(45 30% 20% / 0.5);border-color:hsl(45 40% 40% / 0.5)}
.mx-bottom{flex-shrink:0;padding:14px 16px max(80px, calc(20px + env(safe-area-inset-bottom)));background:rgba(3,7,18,0.95);border-top:1px solid hsl(var(--hue2) 15% 18% / 0.8)}
.mx-stats{display:flex;justify-content:space-around;gap:8px;margin-bottom:12px;padding:10px 14px;border-radius:14px;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.05)}
.mx-stat{text-align:center;flex:1}
.mx-stat-v{font-size:20px;font-weight:800;letter-spacing:-0.02em;background:linear-gradient(135deg,#fff 0%,hsl(var(--hue1) 60% 85%) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.mx-stat-l{font-size:9px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:0.1em;font-weight:700;margin-top:4px}
.mx-done{width:100%;padding:14px 18px;border-radius:14px;background:linear-gradient(135deg,hsl(var(--hue1) 70% 52%),hsl(var(--hue1) 80% 42%));border:1px solid hsl(var(--hue1) 60% 55%);color:#fff;font-size:14px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;box-shadow:0 8px 24px hsl(var(--hue1) 70% 40% / 0.4),0 0 24px hsl(var(--hue1) 70% 50% / 0.25),inset 0 1px 0 rgba(255,255,255,0.25);text-shadow:0 0 12px rgba(255,255,255,0.3)}
.mx-done svg{width:16px;height:16px;stroke:#fff;stroke-width:2.5;fill:none;stroke-linecap:round;stroke-linejoin:round}
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
    font-size:13px;font-weight:700;font-family:inherit;outline:none;
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
.search-display .ph{color:var(--text-secondary);font-size:14px}
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
    background:transparent;color:var(--text-secondary);font-size:13px;font-weight:600;
    white-space:nowrap;cursor:pointer;font-family:inherit;flex-shrink:0;transition:all 0.2s;
}
.tab-pill.active{
    background:linear-gradient(135deg,var(--indigo-500),var(--indigo-600));
    color:#fff;border-color:transparent;box-shadow:0 0 12px rgba(99,102,241,0.3);
}
.tab-pill .cnt{
    display:inline-block;min-width:16px;height:16px;line-height:16px;text-align:center;
    border-radius:8px;background:rgba(255,255,255,0.15);font-size:12px;margin-left:4px;padding:0 4px;
}

/* ═══ STAT CARDS ═══ */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px 16px 0}
.stat-card{
    padding:12px 14px;border-radius:12px;background:var(--bg-card);
    border:1px solid var(--border-subtle);position:relative;overflow:hidden;
}
.stat-card .st-label{font-size:12px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;font-weight:700}
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
.ch-title{font-size:14px;font-weight:700}
.ch-count{font-size:12px;color:var(--text-secondary);background:rgba(99,102,241,0.1);padding:1px 7px;border-radius:99px;font-weight:600}
.ch-arrow{transition:transform 0.3s;color:var(--text-secondary);font-size:12px}
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
.p-name{font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.p-meta{font-size:12px;color:var(--text-secondary);margin-top:2px;display:flex;gap:5px}
.p-right{text-align:right;flex-shrink:0}
.p-price{font-size:13px;font-weight:700;color:var(--indigo-300)}
.p-stock{font-size:12px;margin-top:2px}
.p-stock.ok{color:var(--success)} .p-stock.low{color:var(--warning)} .p-stock.out{color:var(--danger)}
.p-discount{position:absolute;top:3px;right:3px;font-size:11px;padding:1px 5px;border-radius:4px;background:var(--danger);color:#fff;font-weight:700}

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
.sup-card .sc-count{font-size:13px;color:var(--text-secondary);margin-top:2px}
.sup-card .sc-badges{display:flex;gap:5px;margin-top:8px;flex-wrap:wrap}
.sc-badge{font-size:12px;padding:2px 7px;border-radius:99px;font-weight:600}
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
    padding:12px 16px 4px;font-size:13px;font-weight:700;color:var(--text-secondary);
    text-transform:uppercase;letter-spacing:0.8px;display:flex;align-items:center;gap:8px;
}
.sec-title::before{content:'';display:inline-block;width:20px;height:1px;background:linear-gradient(to right,transparent,var(--indigo-500))}
.sec-title::after{content:'';flex:1;height:1px;background:linear-gradient(to left,transparent,var(--border-subtle))}

/* ═══ PAGINATION ═══ */
.pagination{display:flex;align-items:center;justify-content:center;gap:4px;padding:12px 16px}
.pg-btn{
    width:28px;height:28px;border-radius:7px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-secondary);font-size:13px;font-weight:600;
    cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;
}
.pg-btn.active{background:var(--indigo-500);color:#fff;border-color:transparent}

/* ═══ QUICK ACTIONS PILL BAR ═══ */
.qa-bar{
    position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:41;
    display:flex;gap:5px;
    background:rgba(8,8,24,0.92);    padding:5px 6px;border-radius:14px;
    border:1px solid var(--border-glow);
    box-shadow:0 4px 30px rgba(99,102,241,0.15),0 0 50px rgba(0,0,0,0.4);
}
.qa-btn{
    display:flex;align-items:center;gap:5px;border:none;cursor:pointer;
    font-family:inherit;border-radius:10px;transition:all 0.15s;
}
.qa-btn:active{transform:scale(0.92)}
.qa-btn span{font-size:12px;font-weight:800;letter-spacing:0.3px}
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
    display:flex;gap:2px;background:rgba(3,7,18,0.9);    border-radius:11px;padding:3px;border:1px solid var(--border-subtle);
}
.sn-btn{
    flex:1;display:flex;flex-direction:column;align-items:center;gap:1px;
    padding:5px 2px;border-radius:8px;border:none;background:transparent;
    color:var(--text-secondary);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;
    transition:all 0.2s;
}
.sn-btn.active{background:rgba(99,102,241,0.18);color:#fff;box-shadow:0 0 10px rgba(99,102,241,0.15)}
.sn-btn:active{transform:scale(0.95)}
.sn-btn svg{width:13px;height:13px}

/* ═══ BOTTOM NAV ═══ */






/* ═══ VOICE OVERLAY — EXACTLY sale.php ═══ */
.rec-ov{
    position:fixed;inset:0;z-index:300;
    background:rgba(3,7,18,0.6);    display:none;align-items:flex-end;justify-content:center;
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
.rec-hint{font-size:13px;color:var(--text-secondary);margin-bottom:14px;text-align:center;line-height:1.4}
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
    opacity:0;pointer-events:none;transition:opacity 0.25s;}
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
.wiz-label{font-size:12px;color:var(--text-secondary);padding:0 16px 6px}
.wiz-label b{color:var(--indigo-300)}
/* S92.PRODUCTS.PRICE_LAYOUT: padding sides 16px → 8px. На малки cover display-и
 * (Samsung Z Flip6 ~373px wide) 16px от двете страни ядеше ~33px от ширината,
 * което караше 3-те input-а в price-row да се crop-ват. */
.wiz-page{display:none;padding:14px 8px max(120px,calc(16px + env(safe-area-inset-bottom)))}
.wiz-page.active{display:block;animation:wizFade 0.2s ease}
@keyframes wizFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
/* S90.PRODUCTS.SPRINT_B C4: AI auto-fill hint — Pesho вижда какво е AI vs какво е писал. */
.wiz-ai-hint{display:inline-flex;align-items:center;gap:5px;margin-top:5px;padding:3px 8px;font-size:10px;font-weight:600;color:#a5b4fc;background:linear-gradient(135deg,rgba(99,102,241,0.10),rgba(139,92,246,0.06));border:1px solid rgba(139,92,246,0.28);border-radius:8px;line-height:1.3;letter-spacing:0.01em}
.wiz-ai-hint svg{stroke:#a5b4fc;stroke-width:2.2;fill:none;flex-shrink:0;width:11px;height:11px}

/* === S82.COLOR.4 — Photo mode toggle + multi-photo + camera loop === */
.photo-mode-toggle{display:flex;gap:5px;padding:3px;background:rgba(0,0,0,0.3);border-radius:10px;margin-bottom:10px;border:1px solid rgba(99,102,241,0.1)}
.pmt-opt{flex:1;padding:7px 8px;border-radius:8px;background:transparent;border:none;color:rgba(255,255,255,0.5);font-size:10.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;font-family:inherit;transition:all .18s}
.pmt-opt.active{background:linear-gradient(180deg,rgba(99,102,241,0.2),rgba(67,56,202,0.1));color:var(--indigo-300);box-shadow:inset 0 1px 0 rgba(255,255,255,0.05)}
.pmt-opt svg{width:13px;height:13px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

/* S82.COLOR.6: horizontal swipe carousel — 2 cards visible, snap-to-card, native momentum scroll. */
.photo-multi-grid{display:flex;gap:8px;margin-bottom:8px;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;padding:2px 2px 8px;scrollbar-width:none;scroll-padding-left:2px}
.photo-multi-grid::-webkit-scrollbar{display:none}
.photo-multi-cell{position:relative;display:flex;flex-direction:column;gap:6px;flex:0 0 calc(50% - 4px);min-width:0;scroll-snap-align:start;scroll-snap-stop:always}
.photo-multi-thumb{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:rgba(99,102,241,0.05);border:1px solid rgba(99,102,241,0.18)}
.photo-multi-thumb .ph-img{width:100%;height:100%;object-fit:cover;display:block}
.photo-multi-thumb .ph-num{position:absolute;top:5px;left:5px;padding:2px 7px;border-radius:100px;background:rgba(0,0,0,0.7);color:#fff;font-size:10px;font-weight:800;line-height:1.4}
.photo-multi-thumb .ph-rm{position:absolute;top:5px;right:5px;width:22px;height:22px;border-radius:50%;background:rgba(239,68,68,0.85);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;font-family:inherit;line-height:1;padding:0}

.photo-color-input{display:flex;flex-wrap:wrap;align-items:center;gap:5px;padding:6px 9px;border-radius:8px;background:rgba(0,0,0,0.3);border:1px solid rgba(99,102,241,0.2)}
.photo-color-swatch{width:14px;height:14px;border-radius:4px;flex-shrink:0;border:0.5px solid rgba(255,255,255,0.2)}
.photo-color-input input{flex:1 1 100%;order:2;background:transparent;border:none;color:var(--text-primary);font-size:11px;font-weight:600;outline:none;font-family:inherit;padding:2px 0;min-width:0}
.photo-color-conf{font-size:8px;font-weight:800;color:#86efac;letter-spacing:0.05em;flex-shrink:0}
.photo-color-conf.warn{color:#fbbf24}
.photo-color-conf.detecting{color:var(--indigo-300)}

.photo-empty-add{aspect-ratio:1;border-radius:10px;background:rgba(99,102,241,0.05);border:1.5px dashed rgba(99,102,241,0.3);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;color:var(--indigo-300);font-size:10px;font-weight:600;font-family:inherit;transition:all .15s;padding:8px}
.photo-empty-add:hover{background:rgba(99,102,241,0.1);border-color:rgba(99,102,241,0.5)}
.photo-empty-add svg{width:22px;height:22px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

.photo-multi-info{padding:7px 10px;border-radius:9px;background:rgba(139,92,246,0.06);border:1px solid rgba(139,92,246,0.2);font-size:10.5px;color:var(--indigo-300);font-weight:600;text-align:center;margin-bottom:8px;line-height:1.4}
.photo-multi-info b{color:var(--text-primary)}

/* ═══ S82.STUDIO.2 — Step 4 inline AI prompt card (replaces step 5 entry) ═══ */
.step4-ai-card{margin:14px auto 0;padding:16px 14px;border-radius:18px;background:linear-gradient(135deg,rgba(124,58,237,0.18),rgba(99,102,241,0.10));border:1.5px solid rgba(139,92,246,0.45);position:relative;overflow:hidden;max-width:480px;animation:s4aiFadeIn 0.32s ease}
@keyframes s4aiFadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.step4-ai-card.flash-attention{animation:s4aiPulse 1.6s ease}
@keyframes s4aiPulse{0%,100%{box-shadow:0 0 0 0 rgba(167,139,250,0)}30%{box-shadow:0 0 0 6px rgba(167,139,250,0.45)}}
.s4ai-summary{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;background:rgba(34,197,94,0.10);border:1px solid rgba(34,197,94,0.32);font-size:12.5px;color:#fff;font-weight:600;margin-bottom:12px;line-height:1.4}
.s4ai-summary svg{width:18px;height:18px;flex-shrink:0;fill:none}
.s4ai-summary b{color:#86efac;font-weight:800}
.s4ai-summary.warn{background:rgba(251,191,36,0.10);border-color:rgba(251,191,36,0.35);color:#fef3c7}
.s4ai-summary.warn b{color:#fbbf24}
.s4ai-minqty{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:11px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.22);margin-bottom:12px}
.s4ai-mq-label{font-size:11.5px;color:#fde68a;font-weight:700;flex:1;min-width:0;line-height:1.3}
.s4ai-mq-hint{display:block;font-size:9.5px;color:rgba(251,191,36,0.7);font-weight:500;letter-spacing:0.02em;margin-top:1px}
.s4ai-mq-stepper{display:flex;align-items:center;gap:0;flex-shrink:0}
.s4ai-mq-stepper button{width:30px;height:32px;border:1px solid rgba(245,158,11,0.3);background:rgba(245,158,11,0.08);color:#fbbf24;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center}
.s4ai-mq-stepper button:first-child{border-radius:8px 0 0 8px;border-right:0}
.s4ai-mq-stepper button:last-child{border-radius:0 8px 8px 0;border-left:0}
.s4ai-mq-stepper input{width:48px;height:32px;text-align:center;background:transparent;border:1px solid rgba(245,158,11,0.3);color:#fff;font-size:13px;font-weight:700;font-family:inherit;outline:none;-moz-appearance:textfield}
.s4ai-mq-stepper input::-webkit-outer-spin-button,.s4ai-mq-stepper input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.s4ai-prompt{padding-top:4px}
.s4ai-prompt-title{display:flex;align-items:center;gap:8px;font-size:14.5px;font-weight:800;color:#fff;margin-bottom:8px;letter-spacing:-0.005em}
.s4ai-prompt-title svg{width:18px;height:18px;flex-shrink:0;fill:none}
.s4ai-prompt-list{font-size:11.5px;color:rgba(233,213,255,0.78);line-height:1.6;margin:0 0 12px;padding-left:18px;list-style:disc}
.s4ai-prompt-list li{margin-bottom:1px}
/* S82.STUDIO.9: visual feature grid replaces the bullet list */
.s4ai-feature-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:14px}
.s4ai-feat{display:flex;flex-direction:column;align-items:center;gap:6px;padding:10px 4px;border-radius:11px;background:rgba(0,0,0,0.25);border:1px solid rgba(139,92,246,0.18)}
.s4ai-feat-ico{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.s4ai-feat-ico svg{width:22px;height:22px;fill:none}
.s4ai-feat-ico.bg{background:linear-gradient(135deg,rgba(165,180,252,0.22),rgba(99,102,241,0.1));color:#a5b4fc;box-shadow:0 0 12px rgba(99,102,241,0.18)}
.s4ai-feat-ico.magic{background:linear-gradient(135deg,rgba(251,191,36,0.22),rgba(245,158,11,0.1));color:#fbbf24;box-shadow:0 0 12px rgba(251,191,36,0.18)}
.s4ai-feat-ico.seo{background:linear-gradient(135deg,rgba(125,211,252,0.22),rgba(14,165,233,0.1));color:#7dd3fc;box-shadow:0 0 12px rgba(125,211,252,0.18)}
.s4ai-feat-ico.exp{background:linear-gradient(135deg,rgba(240,171,252,0.22),rgba(192,38,211,0.1));color:#f0abfc;box-shadow:0 0 12px rgba(240,171,252,0.18)}
.s4ai-feat-lbl{font-size:10.5px;font-weight:700;color:#fff;text-align:center;line-height:1.25}
.s4ai-feat-sub{font-size:8.5px;font-weight:600;color:rgba(233,213,255,0.55);letter-spacing:0.02em}
.s4ai-prompt-actions{display:flex;flex-direction:column;gap:7px}
.s4ai-btn{width:100%;padding:13px;border-radius:12px;font-size:13px;font-weight:800;border:none;cursor:pointer;font-family:inherit;letter-spacing:0.005em;transition:transform 0.12s}
.s4ai-btn:active{transform:scale(0.98)}
.s4ai-btn.yes{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;box-shadow:0 4px 16px rgba(124,58,237,0.4)}
.s4ai-btn.no{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.78);border:1px solid rgba(255,255,255,0.12);font-weight:700;font-size:12px}
/* S82.STUDIO.7: disabled state when matrix is empty — visually grey + still tappable to trigger toast */
.s4ai-btn.disabled{opacity:0.4;cursor:not-allowed;filter:grayscale(0.6)}
.step4-ai-card.awaiting-qty{border-color:rgba(251,191,36,0.5);box-shadow:0 0 0 1px rgba(251,191,36,0.2)}

/* ═══ S82.STUDIO.1.a — AI Studio modal (Phase 1: scaffold + plan lock + bg removal) ═══ */
.studio-modal-ov{position:fixed;inset:0;background:rgba(0,0,0,0.78);z-index:9990;display:none;align-items:flex-end;justify-content:center;padding:0;animation:studioOvFade 0.25s ease-out}
.studio-modal-ov.show{display:flex}
@keyframes studioOvFade{from{opacity:0}to{opacity:1}}
@media (min-width: 600px){
    .studio-modal-ov{align-items:center;padding:24px}
}
.studio-modal{width:100%;max-width:480px;max-height:92vh;border-radius:22px 22px 0 0;display:flex;flex-direction:column;overflow:hidden;animation:studioCardIn 0.32s cubic-bezier(0.32, 0.72, 0, 1)}
@media (min-width: 600px){
    .studio-modal{border-radius:22px;max-height:88vh}
}
@keyframes studioCardIn{from{transform:translateY(60px);opacity:0}to{transform:translateY(0);opacity:1}}

.studio-modal-hdr{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:14px 16px;border-bottom:1px solid var(--border-subtle);flex-shrink:0;position:relative;z-index:5}
.studio-modal-hdr h2{font-size:17px;font-weight:800;margin:0;flex:1;text-align:center;background:linear-gradient(135deg,#fff,var(--indigo-300));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.studio-mh-close,.studio-mh-help{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:var(--text-secondary);font-size:18px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;flex-shrink:0;transition:all 0.18s}
.studio-mh-close:hover,.studio-mh-help:hover{color:var(--indigo-300);border-color:rgba(99,102,241,0.4)}
.studio-mh-help{font-size:15px}

.studio-modal-body{flex:1;overflow-y:auto;padding:14px;-webkit-overflow-scrolling:touch}
.studio-modal-body > * + *{margin-top:12px}

.studio-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:60px 20px;color:rgba(255,255,255,0.55);font-size:13px}
.studio-spin{width:32px;height:32px;border-radius:50%;border:3px solid rgba(167,139,250,0.2);border-top-color:#a78bfa;animation:studioSpin 0.85s linear infinite}
@keyframes studioSpin{to{transform:rotate(360deg)}}
.studio-error{padding:30px 20px;text-align:center;color:#fca5a5;font-size:13px}

/* Plan lock */
.studio-lock{padding:8px 8px 14px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:8px}
.studio-lock-ico{width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,var(--indigo-600,#4f46e5),var(--indigo-500,#6366f1));display:flex;align-items:center;justify-content:center;box-shadow:0 0 26px rgba(99,102,241,0.5);margin-bottom:4px}
.studio-lock-ico svg{width:24px;height:24px}
.studio-lock-title{font-size:18px;font-weight:800;color:#fff;letter-spacing:-0.01em}
.studio-lock-sub{font-size:12px;color:rgba(233,213,255,0.72);line-height:1.5;max-width:300px;margin-bottom:6px}
.studio-lock-features{display:grid;grid-template-columns:1fr 1fr;gap:7px;width:100%;max-width:320px;margin-bottom:8px}
.studio-lock-feat{padding:8px 9px;border-radius:9px;background:rgba(99,102,241,0.07);border:1px solid rgba(99,102,241,0.22);font-size:11px;color:var(--indigo-300);display:flex;align-items:center;gap:6px;font-weight:600}
.studio-lock-feat svg{width:12px;height:12px;flex-shrink:0;fill:none}
.studio-lock-cta{padding:13px 24px;border-radius:100px;background:linear-gradient(135deg,#7c3aed,#6366f1);border:1px solid var(--indigo-400,#818cf8);color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 6px 20px rgba(124,58,237,0.45);width:100%;max-width:300px;transition:all 0.18s}
.studio-lock-cta:active{transform:translateY(1px)}
.studio-lock-skip{margin-top:4px;background:transparent;border:none;color:rgba(255,255,255,0.45);font-size:11.5px;cursor:pointer;font-family:inherit;padding:8px;text-decoration:underline}

/* Credits bar */
.studio-credits-bar{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:14px;background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.06));border:1px solid rgba(99,102,241,0.28);width:100%;cursor:pointer;font-family:inherit;text-align:left;transition:all 0.18s}
.studio-credits-bar:active{transform:scale(0.985)}
.studio-cr-plan{padding:5px 11px;border-radius:100px;background:linear-gradient(135deg,var(--indigo-600,#4f46e5),var(--indigo-500,#6366f1));color:#fff;font-size:10px;font-weight:800;letter-spacing:0.06em;flex-shrink:0;box-shadow:0 0 12px rgba(99,102,241,0.4)}
.studio-cr-plan.start{background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 0 12px rgba(14,165,233,0.4)}
.studio-cr-content{flex:1;min-width:0}
.studio-cr-line{display:flex;align-items:center;gap:8px;font-size:12px;color:#fff;font-weight:600;margin-bottom:2px}
.studio-cr-item b{color:#a5b4fc;font-size:14px;font-weight:800}
.studio-cr-item.tryon b{color:#fbbf24}
.studio-cr-sep{width:1px;height:14px;background:rgba(255,255,255,0.15)}
.studio-cr-sub{font-size:10px;color:rgba(255,255,255,0.45);font-weight:500}
.studio-cr-arrow{flex-shrink:0;color:rgba(255,255,255,0.4)}
.studio-cr-arrow svg{width:14px;height:14px;display:block;fill:none}

/* Studio sections */
.studio-section{padding:14px 13px;border-radius:16px;background:rgba(15,15,40,0.55);border:1px solid var(--border-subtle);position:relative;overflow:hidden}
.studio-section.studio-soon{opacity:0.55;pointer-events:none}
.studio-section.studio-soon::after{content:'СКОРО';position:absolute;top:10px;right:10px;font-size:8.5px;font-weight:800;letter-spacing:0.1em;color:var(--indigo-300);padding:3px 7px;border-radius:6px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25)}
.studio-sect-head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.studio-sect-ico{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,rgba(167,139,250,0.22),rgba(99,102,241,0.1));border:1px solid rgba(139,92,246,0.32);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--indigo-300)}
.studio-sect-ico svg{width:19px;height:19px;fill:none}
.studio-sect-title{font-size:13.5px;font-weight:700;color:#fff;letter-spacing:-0.005em}
.studio-sect-sub{font-size:11px;color:rgba(233,213,255,0.55);margin-top:1px}
.studio-sect-price{margin-left:auto;font-size:11px;font-weight:700;color:#86efac;background:rgba(34,197,94,0.1);padding:3px 8px;border-radius:6px;border:1px solid rgba(34,197,94,0.25);flex-shrink:0}

/* Bg removal grid */
.studio-bg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-bottom:10px}
.studio-bg-empty{padding:24px 12px;text-align:center;color:rgba(255,255,255,0.4);font-size:12px;background:rgba(0,0,0,0.2);border-radius:10px;border:1px dashed rgba(99,102,241,0.18)}
.studio-bg-cell{display:flex;flex-direction:column;gap:6px;position:relative}
.studio-bg-thumb{aspect-ratio:1;border-radius:10px;overflow:hidden;background:rgba(0,0,0,0.3);border:1px solid rgba(99,102,241,0.2);position:relative}
.studio-bg-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.studio-bg-status{position:absolute;top:5px;left:5px;right:5px;padding:4px 8px;border-radius:7px;font-size:10px;font-weight:700;text-align:center}
.studio-bg-status.processing{background:rgba(0,0,0,0.7);color:#a5b4fc}
.studio-bg-status.done{background:rgba(34,197,94,0.85);color:#fff}
.studio-bg-status.error{background:rgba(239,68,68,0.85);color:#fff;cursor:help}
.studio-bg-btn{padding:7px 8px;border-radius:9px;background:linear-gradient(135deg,var(--indigo-500,#6366f1),var(--indigo-600,#4f46e5));border:none;color:#fff;font-size:10.5px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:5px;line-height:1.2}
.studio-bg-btn svg{width:11px;height:11px;fill:none}
.studio-bulk-btn{display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:11px;background:linear-gradient(135deg,#7c3aed,#6366f1);border:none;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;width:100%;box-shadow:0 4px 14px rgba(124,58,237,0.35)}
.studio-bulk-btn svg{width:14px;height:14px;fill:none}

/* Export bar (Phase 4 — disabled placeholders for now) */
.studio-export-row{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.studio-export-btn{padding:10px 6px;border-radius:11px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.55);font-size:10.5px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:4px;transition:all 0.18s}
.studio-export-btn svg{width:18px;height:18px;fill:none}
.studio-export-btn.soon{opacity:0.45;cursor:not-allowed}

.studio-done-btn{display:flex;align-items:center;justify-content:center;gap:7px;padding:13px;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);border:none;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;width:100%;box-shadow:0 4px 16px rgba(22,163,74,0.35)}
.studio-done-btn svg{width:14px;height:14px;fill:none}

/* ═══ S82.STUDIO.3 — Phase D additions (image compare, AI Magic grid, Studio chips, SEO, buy credits) ═══ */
.studio-compare{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.studio-cmp-cell{position:relative;aspect-ratio:1;border-radius:14px;overflow:hidden;background:rgba(0,0,0,0.35);border:1px solid rgba(99,102,241,0.22)}
.studio-cmp-cell img{width:100%;height:100%;object-fit:cover;display:block}
.studio-cmp-pill{position:absolute;top:8px;left:8px;padding:4px 10px;border-radius:100px;font-size:9.5px;font-weight:800;letter-spacing:0.06em;z-index:2}
.studio-cmp-pill.before{background:rgba(0,0,0,0.6);color:#fff}
.studio-cmp-pill.after{background:rgba(34,197,94,0.85);color:#fff;text-shadow:0 0 6px rgba(0,0,0,0.4)}
.studio-cmp-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,0.4);font-size:11px;background:linear-gradient(135deg,rgba(99,102,241,0.04),rgba(139,92,246,0.06))}
.studio-cmp-placeholder svg{width:32px;height:32px}

.studio-sect-ico.bg{color:#a5b4fc}
.studio-sect-ico.magic{color:#fbbf24;background:linear-gradient(135deg,rgba(251,191,36,0.22),rgba(245,158,11,0.1));border-color:rgba(245,158,11,0.32)}
.studio-sect-ico.studio{color:#86efac;background:linear-gradient(135deg,rgba(34,197,94,0.22),rgba(22,163,74,0.1));border-color:rgba(34,197,94,0.32)}
.studio-sect-ico.seo{color:#7dd3fc;background:linear-gradient(135deg,rgba(125,211,252,0.22),rgba(14,165,233,0.1));border-color:rgba(14,165,233,0.32)}
.studio-sect-ico.exp{color:#f0abfc;background:linear-gradient(135deg,rgba(240,171,252,0.22),rgba(192,38,211,0.1));border-color:rgba(192,38,211,0.32)}
.studio-sect-text{flex:1;min-width:0}
.studio-sect-price.tryon{color:#fbbf24;background:rgba(251,191,36,0.10);border-color:rgba(251,191,36,0.28)}

.studio-models-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:10px}
.studio-model-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:11px 6px;border-radius:11px;background:rgba(255,255,255,0.04);border:1.5px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.65);cursor:pointer;font-family:inherit;transition:all 0.18s}
.studio-model-btn svg{width:22px;height:22px;fill:none;stroke-width:1.7}
.studio-model-lbl{font-size:10.5px;font-weight:700;letter-spacing:0.01em}
.studio-model-btn:active{transform:scale(0.96)}
.studio-model-btn.sel{background:linear-gradient(135deg,rgba(251,191,36,0.18),rgba(245,158,11,0.08));border-color:#fbbf24;color:#fbbf24;box-shadow:0 0 14px rgba(251,191,36,0.3)}

.studio-preset-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-bottom:10px}
.studio-preset-chip{display:flex;align-items:center;gap:6px;padding:9px 10px;border-radius:10px;background:rgba(255,255,255,0.04);border:1.5px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;text-align:left;transition:all 0.18s}
.studio-preset-chip svg{width:14px;height:14px;flex-shrink:0;fill:none;stroke-width:1.7}
.studio-preset-chip:active{transform:scale(0.97)}
.studio-preset-chip.sel{background:linear-gradient(135deg,rgba(34,197,94,0.18),rgba(22,163,74,0.08));border-color:#86efac;color:#86efac;box-shadow:0 0 12px rgba(34,197,94,0.25)}

.studio-prompt-row{display:flex;gap:6px;margin-bottom:10px}
.studio-prompt-input{flex:1;padding:10px 12px;border-radius:10px;background:rgba(0,0,0,0.3);border:1px solid rgba(99,102,241,0.2);color:#fff;font-size:12px;font-family:inherit;outline:none;min-width:0}
.studio-prompt-input::placeholder{color:rgba(255,255,255,0.35);font-style:italic}
.studio-prompt-input:focus{border-color:rgba(167,139,250,0.55);box-shadow:0 0 0 3px rgba(167,139,250,0.15)}
.studio-gen-btn{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:13px;border-radius:12px;border:none;color:#fff;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;letter-spacing:0.005em;transition:transform 0.12s}
.studio-gen-btn svg{width:14px;height:14px;fill:none;stroke-width:2}
.studio-gen-btn:active{transform:scale(0.98)}
.studio-gen-btn.magic{background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 4px 16px rgba(245,158,11,0.35)}
.studio-gen-btn.studio{background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 4px 16px rgba(22,163,74,0.35)}

.studio-seo-textarea{width:100%;min-height:120px;padding:12px;border-radius:12px;background:rgba(0,0,0,0.3);border:1px solid rgba(99,102,241,0.2);color:#fff;font-size:12.5px;line-height:1.55;font-family:inherit;outline:none;resize:vertical;margin-bottom:8px}
.studio-seo-textarea::placeholder{color:rgba(255,255,255,0.35);font-style:italic}
.studio-seo-textarea:focus{border-color:rgba(125,211,252,0.55);box-shadow:0 0 0 3px rgba(125,211,252,0.15)}
.studio-seo-stats{font-size:10.5px;color:rgba(255,255,255,0.45);font-weight:600;letter-spacing:0.02em;margin-bottom:10px;text-align:right}
.studio-seo-actions{display:flex;gap:6px}
.studio-seo-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.78);font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit}
.studio-seo-btn svg{width:13px;height:13px;fill:none;stroke-width:2}
.studio-seo-btn.primary{background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;border-color:transparent;box-shadow:0 3px 12px rgba(14,165,233,0.35)}
.studio-seo-btn:active{transform:scale(0.97)}

.studio-export-btn:not(.soon){background:rgba(99,102,241,0.08);border-color:rgba(139,92,246,0.28);color:#c4b5fd;cursor:pointer;opacity:1}
.studio-export-btn:not(.soon):active{transform:scale(0.97)}
.seb-lbl{font-size:11.5px;font-weight:800;color:inherit;line-height:1.2;margin-top:3px}
.seb-sub{font-size:9px;color:rgba(255,255,255,0.4);font-weight:600;letter-spacing:0.04em;margin-top:1px}

.studio-buy-ov{position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:10001;display:flex;align-items:flex-end;justify-content:center;padding:0;animation:studioOvFade 0.22s ease-out}
@media (min-width: 600px){.studio-buy-ov{align-items:center;padding:24px}}
.studio-buy-card{width:100%;max-width:420px;border-radius:22px 22px 0 0;padding:18px 16px calc(18px + env(safe-area-inset-bottom,0));display:flex;flex-direction:column;gap:10px;animation:studioCardIn 0.3s cubic-bezier(0.32, 0.72, 0, 1)}
@media (min-width: 600px){.studio-buy-card{border-radius:22px}}
.studio-buy-hdr{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.studio-buy-ico{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,rgba(167,139,250,0.25),rgba(99,102,241,0.12));border:1px solid rgba(139,92,246,0.4);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.studio-buy-ico svg{width:18px;height:18px;fill:none}
.studio-buy-hdr h3{flex:1;font-size:16px;font-weight:800;color:#fff;letter-spacing:-0.01em;margin:0}
.studio-buy-x{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.6);font-size:16px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.studio-buy-sub{font-size:11.5px;color:rgba(233,213,255,0.65);line-height:1.5;margin-bottom:4px}
.studio-pack{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:14px;background:rgba(99,102,241,0.07);border:1.5px solid rgba(99,102,241,0.22);cursor:pointer;font-family:inherit;text-align:left;transition:all 0.18s;position:relative}
.studio-pack:active{transform:scale(0.985)}
.studio-pack.popular{background:linear-gradient(135deg,rgba(124,58,237,0.18),rgba(99,102,241,0.10));border-color:rgba(167,139,250,0.55);box-shadow:0 0 18px rgba(167,139,250,0.18)}
.sp-price{font-size:24px;font-weight:900;color:#a5b4fc;letter-spacing:-0.02em;flex-shrink:0;min-width:54px}
.studio-pack.popular .sp-price{color:#c4b5fd}
.sp-info{flex:1;min-width:0}
.sp-main{font-size:12.5px;color:#fff;font-weight:600}
.sp-main b{color:#86efac;font-weight:800}
.sp-sub{font-size:10px;color:rgba(255,255,255,0.45);margin-top:2px}
.sp-tag{padding:3px 8px;border-radius:100px;font-size:8.5px;font-weight:800;letter-spacing:0.06em;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;flex-shrink:0;box-shadow:0 0 10px rgba(167,139,250,0.45);text-transform:uppercase}
.studio-buy-cancel{padding:11px;border-radius:11px;background:transparent;border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;margin-top:4px}

/* Camera loop fullscreen overlay (S82.COLOR.10: native phone camera + spinners) */
.cam-loop-ov{position:fixed;inset:0;background:#000;z-index:9999;display:none;flex-direction:column}
.cam-loop-ov.show{display:flex}
.cam-loop-stage{flex:1;display:flex;align-items:center;justify-content:center;background:#000;overflow:hidden;position:relative}
.cam-loop-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:20px}
.cam-loop-empty-msg{color:rgba(255,255,255,0.55);font-size:13px;text-align:center;line-height:1.5;max-width:280px}
.cam-loop-preview{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;background:#000;display:block}

/* Camera loading state — visible during the OS-camera app-switch flicker (S82.COLOR.11: bolder so it actually registers) */
.cam-loop-stage:has(.cam-loading){background:linear-gradient(135deg,#1a1033,#0a0518)}
.cam-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:24px;padding:40px 28px;text-align:center;animation:camLoadFadeIn 0.18s ease}
@keyframes camLoadFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.cam-loader{display:flex;gap:14px}
.cam-loader div{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#6366f1);box-shadow:0 0 28px rgba(167,139,250,0.85),0 0 50px rgba(99,102,241,0.5);opacity:0.35;animation:camLoaderPulse 1.2s infinite ease-in-out}
.cam-loader div:nth-child(1){animation-delay:-0.32s}
.cam-loader div:nth-child(2){animation-delay:-0.16s}
@keyframes camLoaderPulse{0%,80%,100%{opacity:0.35;transform:scale(0.7)}40%{opacity:1;transform:scale(1.4)}}
.cam-loading-msg{font-size:18px;font-weight:800;color:#fff;letter-spacing:0.01em;text-shadow:0 2px 12px rgba(167,139,250,0.4)}
.cam-loading-sub{font-size:12.5px;color:rgba(233,213,255,0.65);max-width:280px;line-height:1.5;font-weight:500}

/* S82.COLOR.14: first-time camera tip card */
.cam-tip{display:flex;flex-direction:column;align-items:center;gap:18px;padding:28px 24px;max-width:340px;background:linear-gradient(135deg,rgba(124,58,237,0.18),rgba(99,102,241,0.10));border:1.5px solid rgba(139,92,246,0.4);border-radius:20px;margin:20px}
.cam-tip-icon{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,rgba(167,139,250,0.25),rgba(99,102,241,0.15));display:flex;align-items:center;justify-content:center}
.cam-tip-icon svg{width:30px;height:30px}
.cam-tip-title{font-size:18px;font-weight:800;color:#e9d5ff;text-align:center}
.cam-tip-body{font-size:13px;color:rgba(233,213,255,0.85);text-align:center;line-height:1.6;font-weight:500}
.cam-tip-body b{color:#fff}
.cam-tip-flip{display:inline-block;padding:2px 8px;border-radius:6px;background:rgba(167,139,250,0.25);font-size:14px;border:1px solid rgba(167,139,250,0.4)}
.cam-tip-btn{padding:13px 22px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;width:100%;box-shadow:0 4px 18px rgba(124,58,237,0.4)}

/* S82.COLOR.17: compact one-line camera tip in the picker drawer */
.cam-drawer-tip{display:flex;align-items:flex-start;gap:10px;padding:11px 12px;margin-bottom:12px;border-radius:12px;background:linear-gradient(135deg,rgba(124,58,237,0.14),rgba(99,102,241,0.07));border:1px solid rgba(139,92,246,0.32);position:relative;overflow:hidden;animation:tipFadeIn 0.4s ease-out}
.cam-drawer-tip::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 30%,rgba(167,139,250,0.08) 50%,transparent 70%);background-size:200% 200%;animation:tipShine 4s ease-in-out infinite;pointer-events:none}
@keyframes tipFadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
@keyframes tipShine{0%,100%{background-position:200% 200%}50%{background-position:0% 0%}}
.cam-drawer-tip-icon{font-size:22px;flex-shrink:0;line-height:1.2;animation:tipIconPulse 2.4s ease-in-out infinite;filter:drop-shadow(0 0 8px rgba(251,191,36,0.6))}
@keyframes tipIconPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.18)}}
.cam-drawer-tip-text{font-size:11.5px;color:rgba(233,213,255,0.82);line-height:1.55;font-weight:500;flex:1;min-width:0;position:relative}
.cam-drawer-tip-text b{color:#fff;font-weight:700}
.cam-drawer-tip-app{display:inline-block;padding:1px 6px;margin:0 1px;border-radius:5px;background:rgba(167,139,250,0.22);font-size:11px;font-weight:700;border:1px solid rgba(167,139,250,0.35);color:#fff;white-space:nowrap}
.cam-drawer-tip-or{display:inline-block;color:rgba(233,213,255,0.6);font-size:11px}
.cam-drawer-tip-flip{display:inline-block;font-size:13px;animation:tipFlipRot 2.6s linear infinite;vertical-align:middle}
@keyframes tipFlipRot{from{transform:rotate(0)}to{transform:rotate(360deg)}}

/* S82.COLOR.16: super-friendly first-time camera setup card (now unused but kept inert) */
.cam-setup{display:flex;flex-direction:column;gap:16px;padding:24px 20px 20px;max-width:420px;width:100%;overflow-y:auto;max-height:90vh}
.cam-setup-header{text-align:center;margin-bottom:6px}
.cam-setup-emoji{font-size:48px;margin-bottom:8px;animation:setupBounce 2.4s ease-in-out infinite}
@keyframes setupBounce{0%,100%{transform:translateY(0) rotate(-5deg)}50%{transform:translateY(-6px) rotate(5deg)}}
.cam-setup-title{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.01em;margin-bottom:6px}
.cam-setup-sub{font-size:13.5px;color:rgba(233,213,255,0.8);line-height:1.5}
.cam-setup-steps{display:flex;flex-direction:column;gap:11px;margin:8px 0}
.cam-setup-step{display:flex;gap:14px;align-items:flex-start;padding:14px 14px;border-radius:14px;background:linear-gradient(135deg,rgba(124,58,237,0.16),rgba(99,102,241,0.08));border:1px solid rgba(139,92,246,0.32);opacity:0;transform:translateX(-12px);animation:setupStepIn 0.55s ease-out forwards}
@keyframes setupStepIn{to{opacity:1;transform:translateX(0)}}
.cam-setup-num{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#6366f1);color:#fff;font-size:15px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 14px rgba(167,139,250,0.5);animation:setupNumPulse 2s ease-in-out infinite}
@keyframes setupNumPulse{0%,100%{box-shadow:0 0 14px rgba(167,139,250,0.5)}50%{box-shadow:0 0 22px rgba(167,139,250,0.85)}}
.cam-setup-step-body{flex:1;min-width:0}
.cam-setup-step-title{font-size:14.5px;font-weight:700;color:#fff;line-height:1.35;margin-bottom:3px}
.cam-setup-step-desc{font-size:12px;color:rgba(233,213,255,0.72);line-height:1.5}
.cam-setup-step-desc b{color:#fff}
.cam-setup-tap{display:inline-block;padding:1px 7px;margin:0 2px;border-radius:5px;background:rgba(167,139,250,0.25);font-size:13px;border:1px solid rgba(167,139,250,0.4)}
.cam-setup-app{display:inline-block;padding:1px 8px;margin:0 2px;border-radius:6px;background:rgba(167,139,250,0.25);font-size:13px;font-weight:700;border:1px solid rgba(167,139,250,0.4);color:#fff}
.cam-setup-flip{display:inline-block;font-size:16px;animation:setupFlipRot 2.4s linear infinite}
@keyframes setupFlipRot{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.cam-setup-finale{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:14px;background:linear-gradient(135deg,rgba(34,197,94,0.18),rgba(22,163,74,0.08));border:1.5px solid rgba(34,197,94,0.4);opacity:0;transform:translateY(8px);animation:setupStepIn 0.55s ease-out forwards}
.cam-setup-finale-icon{font-size:32px;animation:setupFinaleSparkle 1.8s ease-in-out infinite}
@keyframes setupFinaleSparkle{0%,100%{transform:scale(1) rotate(0)}50%{transform:scale(1.15) rotate(8deg)}}
.cam-setup-finale-text{font-size:13.5px;color:#fff;font-weight:600;line-height:1.4}
.cam-setup-finale-text b{color:#86efac}
.cam-setup-done-btn{margin-top:6px;padding:15px 22px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 22px rgba(124,58,237,0.5);opacity:0;transform:translateY(8px);animation:setupStepIn 0.55s ease-out forwards}
.cam-setup-done-btn:active{transform:scale(0.98)}
.cam-setup-done-btn svg{width:18px;height:18px}
.cam-setup-skip{margin-top:4px;background:transparent;border:none;color:rgba(255,255,255,0.4);font-size:11.5px;cursor:pointer;font-family:inherit;padding:8px;text-decoration:underline}

/* S82.COLOR.15: live <video> + camera-picker UI (kept for harmless reuse) */
.cam-loop-video{width:100%;height:100%;object-fit:cover;display:block;background:#000}
.cam-picker{display:flex;flex-direction:column;gap:14px;padding:20px;width:100%;max-width:420px;align-items:stretch}
.cam-picker-title{font-size:18px;font-weight:800;color:#e9d5ff;text-align:center}
.cam-picker-sub{font-size:12px;color:rgba(233,213,255,0.65);text-align:center;line-height:1.55;padding:0 8px}
.cam-picker-list{display:flex;flex-direction:column;gap:8px;width:100%}
.cam-picker-item{display:flex;flex-direction:column;gap:8px;padding:12px 14px;border-radius:14px;background:rgba(99,102,241,0.08);border:1px solid rgba(139,92,246,0.25)}
.cam-picker-item-info{display:flex;flex-direction:column;gap:2px}
.cam-picker-item-name{font-size:13px;font-weight:700;color:#fff;word-break:break-word}
.cam-picker-item-sub{font-size:10.5px;color:rgba(233,213,255,0.55)}
.cam-picker-item-actions{display:flex;gap:6px}
.cam-picker-test,.cam-picker-save{flex:1;padding:9px 12px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;border:none}
.cam-picker-test{background:rgba(255,255,255,0.08);color:#e9d5ff;border:1px solid rgba(255,255,255,0.12)}
.cam-picker-save{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;box-shadow:0 2px 10px rgba(124,58,237,0.35)}
.cam-picker-test-bar{position:absolute;bottom:14px;left:14px;right:14px;display:flex;gap:8px;z-index:2}
.cam-picker-back,.cam-picker-use{flex:1;padding:11px 14px;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;border:none}
.cam-picker-back{background:rgba(0,0,0,0.5);color:#fff;border:1px solid rgba(255,255,255,0.15)}
.cam-picker-use{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 2px 14px rgba(22,163,74,0.4)}
.cam-change-link{position:absolute;bottom:calc(80px + env(safe-area-inset-bottom,0));left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.55);color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.1);padding:6px 14px;border-radius:100px;font-size:10.5px;font-weight:600;cursor:pointer;font-family:inherit;z-index:1}

/* AI Vision processing overlay — fullscreen, sits ABOVE the wizard */
.ai-working-ov{position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:10000;display:flex;align-items:center;justify-content:center;animation:aiOvFade 0.22s ease;padding:20px}
@keyframes aiOvFade{from{opacity:0}to{opacity:1}}
.ai-working-card{padding:32px 26px;border-radius:24px;background:linear-gradient(135deg,rgba(124,58,237,0.32),rgba(99,102,241,0.18));border:1.5px solid rgba(139,92,246,0.55);box-shadow:0 0 50px rgba(139,92,246,0.45),inset 0 1px 0 rgba(255,255,255,0.1);display:flex;flex-direction:column;align-items:center;gap:14px;min-width:260px;max-width:340px}
.ai-working-orb{display:flex;gap:10px;margin-bottom:6px}
.ai-working-orb div{width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#6366f1);box-shadow:0 0 16px rgba(167,139,250,0.7);animation:aiOrbPulse 1.4s infinite ease-in-out}
.ai-working-orb div:nth-child(1){animation-delay:-0.32s}
.ai-working-orb div:nth-child(2){animation-delay:-0.16s}
@keyframes aiOrbPulse{0%,80%,100%{opacity:0.4;transform:scale(0.7)}40%{opacity:1;transform:scale(1.4)}}
.ai-working-title{font-size:18px;font-weight:800;color:#e9d5ff;letter-spacing:-0.01em}
.ai-working-msg{font-size:13px;color:rgba(233,213,255,0.85);text-align:center;line-height:1.5;font-weight:600}
.ai-working-hint{font-size:10.5px;color:rgba(233,213,255,0.5);text-align:center;letter-spacing:0.02em}
.cam-loop-controls{padding:14px 14px calc(14px + env(safe-area-inset-bottom,0));background:rgba(0,0,0,0.9);display:flex;gap:8px;align-items:center;justify-content:center}
.cam-loop-btn{padding:14px 18px;border-radius:14px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s}
.cam-loop-btn svg{width:16px;height:16px;stroke:currentColor;stroke-width:2.2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.cam-loop-btn.shoot{width:74px;height:74px;border-radius:50%;background:#fff;color:#000;padding:0;box-shadow:0 0 0 4px rgba(255,255,255,0.25)}
.cam-loop-btn.shoot svg{width:30px;height:30px;stroke-width:2.2}
.cam-loop-btn.next{background:linear-gradient(135deg,var(--indigo-500),var(--indigo-600));color:#fff;flex:1;max-width:160px}
.cam-loop-btn.done{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;flex:1;max-width:160px}
.cam-loop-btn.retake{background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);flex:1;max-width:140px}
.cam-loop-btn.cancel{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);width:50px;height:50px;border-radius:14px;padding:0}
.cam-loop-counter{position:absolute;top:calc(14px + env(safe-area-inset-top,0));left:50%;transform:translateX(-50%);padding:6px 14px;border-radius:100px;background:rgba(0,0,0,0.7);color:#fff;font-size:12px;font-weight:700;z-index:1}

/* Step 5 final AI prompt card */
.s82-finalprompt{margin-top:14px;padding:16px 14px;border-radius:16px;background:linear-gradient(135deg,rgba(124,58,237,.18),rgba(99,102,241,.10));border:1.5px solid rgba(139,92,246,.45);position:relative;overflow:hidden}
.s82-finalprompt-title{font-size:14px;font-weight:800;color:var(--text-primary);margin-bottom:6px;display:flex;align-items:center;gap:8px}
.s82-finalprompt-list{font-size:11px;color:var(--text-secondary);line-height:1.6;margin-bottom:12px;padding-left:14px}
.s82-finalprompt-list li{margin-bottom:2px}
.s82-finalprompt-actions{display:flex;gap:8px}
.s82-finalprompt-btn{flex:1;padding:11px;border-radius:11px;font-size:12px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .15s}
.s82-finalprompt-btn.yes{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;box-shadow:0 4px 14px rgba(124,58,237,0.3)}
.s82-finalprompt-btn.no{background:rgba(255,255,255,0.05);color:var(--text-secondary);border:1px solid rgba(255,255,255,0.1)}

/* ═══ FORM ELEMENTS ═══ */
.fg{margin-bottom:10px}
.fl{display:block;font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:3px;text-transform:uppercase;letter-spacing:0.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fl .hint{color:rgba(107,114,128,0.7);font-weight:400;text-transform:none;letter-spacing:0}
.fl .fl-add{float:right;color:var(--indigo-300);font-weight:700;cursor:pointer;text-transform:none;letter-spacing:0;font-size:12px;padding:4px 10px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3)}
.fc{
    width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border-subtle);
    background:rgba(30,35,50,0.9);color:var(--text-primary);font-size:14px;outline:none;
    font-family:inherit;transition:border-color 0.2s;
}
.fc:focus{border-color:var(--border-glow);box-shadow:0 0 12px rgba(99,102,241,0.1)}
.fc::placeholder{color:var(--text-secondary)}
/* S92.PRODUCTS.D11_REGRESSION_FIX: equal input boxes across wizard steps.
 * C1 (e8df5f9) направи .v-custom-input + axis-button 42×42, но останалите .fc inputs
 * в wizard стъпките останаха със стария padding 9px 12px / border-radius 10px → ~40px height.
 * Унифицираме .fg inputs/selects до 42px height + padding 10px 14px + border-radius 12px,
 * за да изглеждат всички wizard input полета еднакви на око при минаване през стъпките.
 * Textarea-та са изключени (rows=N контролира височина); матрицата (extends) и label-print
 * inputs не са в .fg, така че не се засягат. */
.fg input.fc,.fg select.fc{
    min-height:42px;padding:10px 14px;border-radius:12px;font-size:14px;box-sizing:border-box;
}
.v-custom-input,.v-custom-btn{min-height:42px;box-sizing:border-box}
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
.preset-ov{position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.6);display:flex;align-items:flex-end;justify-content:center;animation:fadeIn 0.2s ease}
.preset-box{background:var(--bg-card);border-radius:20px 20px 0 0;width:100%;max-width:480px;max-height:85vh;overflow-y:auto;padding:20px;border:1px solid var(--border-subtle);border-bottom:none}
.preset-chip{display:inline-block;padding:8px 16px;margin:4px;border-radius:10px;border:1.5px solid var(--border-subtle);background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:14px;font-weight:600;cursor:pointer;transition:all 0.15s;user-select:none}
.preset-chip.sel{border-color:var(--indigo-500);background:rgba(99,102,241,0.2);color:var(--indigo-300);box-shadow:0 0 8px rgba(99,102,241,0.2)}
.preset-chip:active{transform:scale(0.95)}
.preset-cat{font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin:12px 0 6px;letter-spacing:0.5px}
.inline-add input{flex:1;padding:7px 10px;border-radius:6px;border:1px solid var(--border-subtle);background:var(--bg-card);color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.inline-add button{padding:7px 12px;border-radius:6px;border:none;background:var(--indigo-500);color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.wiz-mic{width:42px;min-width:42px;height:42px;border-radius:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
.wiz-mic:active{background:rgba(239,68,68,.2);transform:scale(.95)}
.fg.wiz-next{background:rgba(99,102,241,.08);border-radius:10px;padding:8px;margin-left:-8px;margin-right:-8px;border:1.5px solid rgba(99,102,241,.35);animation:wizNextPulse 1.5s infinite}
.fg.wiz-next .wiz-mic{background:rgba(99,102,241,.25);border-color:#6366f1;animation:wizNextPulse 1.5s infinite}
@keyframes wizNextPulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.2)}50%{box-shadow:0 0 14px 4px rgba(99,102,241,.2)}}
.fg.wiz-done .fl::after{content:' \2713';color:#4ade80;font-weight:700}
.wiz-mic.recording{background:rgba(239,68,68,.3)!important;border-color:#ef4444!important;color:#fff!important;animation:micRecPulse .8s infinite!important;position:relative}
.wiz-mic.recording::after{content:'REC';position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:8px;font-weight:800;color:#ef4444;letter-spacing:1px;white-space:nowrap;text-shadow:0 0 8px rgba(239,68,68,.6)}
.wiz-mic.recording::before{content:'';position:absolute;top:-8px;right:-2px;width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 6px #ef4444,0 0 12px rgba(239,68,68,.5);animation:micRecDot .6s infinite}
@keyframes micRecPulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 16px 4px rgba(239,68,68,.3)}}
@keyframes micRecDot{0%,100%{opacity:1}50%{opacity:.3}}
.fg.wiz-active{background:rgba(99,102,241,.06);border-radius:10px;padding:8px;margin-left:-8px;margin-right:-8px;border:1.5px solid rgba(99,102,241,.25);transition:all .2s}
.fg.wiz-active .wiz-mic{border-color:rgba(99,102,241,.4);background:rgba(99,102,241,.12)}

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
    border:1px solid var(--border-glow);    color:var(--text-primary);font-size:13px;font-weight:600;
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
.ai-insight .ai-text{font-size:14px;line-height:1.4}

/* ═══ DETAIL ROW ═══ */
.d-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid rgba(99,102,241,0.06)}
.d-row:last-child{border-bottom:none}
.d-label{font-size:13px;color:var(--text-secondary)}
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
.breadcrumb{display:flex;align-items:center;gap:4px;padding:6px 16px;font-size:13px;color:var(--text-secondary);flex-wrap:wrap}
.breadcrumb a{color:var(--indigo-400);text-decoration:none}

/* ═══ SORT DROPDOWN ═══ */
.sort-wrap{position:relative}
.sort-btn{background:transparent;border:1px solid var(--border-subtle);color:var(--text-secondary);padding:6px 12px;border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:4px}
.sort-dd{position:absolute;top:100%;right:0;margin-top:4px;background:#080818;border:1px solid var(--border-subtle);border-radius:10px;padding:4px;min-width:160px;z-index:60;display:none;box-shadow:0 8px 32px rgba(0,0,0,0.5)}
.sort-dd.open{display:block}
.sort-opt{padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer;color:var(--text-secondary);transition:background 0.15s}
.sort-opt.active{background:rgba(99,102,241,0.12);color:var(--indigo-300)}
.sort-opt:active{background:rgba(99,102,241,0.08)}

/* ═══ FILTER DRAWER ═══ */
.filter-chips{display:flex;flex-wrap:wrap;gap:5px}
.f-chip{padding:7px 13px;border-radius:8px;border:1px solid var(--border-subtle);background:transparent;color:var(--text-secondary);font-size:13px;cursor:pointer;font-family:inherit;transition:all 0.15s}
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
.label-var .lv-name{font-size:14px;font-weight:600;margin-bottom:2px}
.label-var .lv-code{font-size:12px;color:var(--text-secondary)}
.label-var .lv-fields{display:flex;gap:8px;margin-top:6px}
.label-var .lv-field{flex:1}
.label-var .lv-field label{font-size:11px;color:var(--text-secondary);text-transform:uppercase}
.label-var .lv-field input{
    width:100%;padding:6px 8px;border-radius:6px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);font-size:13px;text-align:center;
    outline:none;font-family:inherit;
}
.format-chips{display:flex;gap:6px;margin:10px 0}
.fmt-chip{
    padding:6px 14px;border-radius:8px;border:1px solid var(--border-subtle);
    background:transparent;color:var(--text-secondary);font-size:13px;font-weight:600;
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
.credits-bar .cr-item{font-size:13px;color:var(--text-secondary);font-weight:600}
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
input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{-webkit-text-fill-color:var(--text-primary)!important;-webkit-box-shadow:0 0 0 1000px rgba(30,35,50,0.9) inset!important;transition:background-color 5000s ease-in-out 0s;caret-color:var(--text-primary)}

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
    background:rgba(8,8,24,0.97);    border-left:1px solid var(--border-glow);
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
.info-q .iq-text{font-size:14px;font-weight:600;color:var(--text-primary)}
.info-q .iq-arrow{color:var(--text-secondary);font-size:13px;margin-left:auto;flex-shrink:0}
.info-answer{padding:10px 12px;margin:-2px 0 6px;border-radius:0 0 10px 10px;
    background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-top:none;
    font-size:14px;color:rgba(241,245,249,0.8);line-height:1.5;display:none}
.info-answer.open{display:block;animation:fadeUp 0.2s ease}
.info-section-title{font-size:12px;font-weight:800;color:var(--indigo-300);text-transform:uppercase;
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
.wiz-dd-list{position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#1a1f35;border:1px solid var(--border-subtle);border-radius:10px;z-index:100;margin-top:2px;box-shadow:0 8px 24px rgba(0,0,0,0.4)}
.wiz-dd-item{padding:10px 14px;font-size:13px;color:var(--text-primary);cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.04)}
.wiz-dd-item:hover,.wiz-dd-item:active{background:rgba(99,102,241,0.15)}
.wiz-dd-item:last-child{border-bottom:none}
.wiz-info-overlay{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,0.7);display:flex;align-items:center;justify-content:center;padding:20px}
.wiz-info-box{background:#080818;border:1px solid var(--border-glow);border-radius:16px;padding:16px;max-width:320px;width:100%;box-shadow:0 10px 40px rgba(99,102,241,0.2)}

/* ═══ NEW HEADER (chat.php matching) ═══ */
.hdr-row1{display:flex;align-items:center;justify-content:space-between;margin-bottom:2px}
.hdr-logo{font-size:14px;font-weight:700;color:rgba(165,180,252,.6);letter-spacing:.5px}
.hdr-right{display:flex;align-items:center;gap:8px}
.hdr-badge{font-size:11px;font-weight:800;letter-spacing:.8px;padding:3px 8px;border-radius:6px;background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:1px solid rgba(34,197,94,.25);color:#4ade80}
.hdr-ico{width:26px;height:26px;border-radius:50%;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;text-decoration:none}
.hdr-row2{display:flex;align-items:center;justify-content:space-between;margin-top:8px}
.hdr-page-title{font-size:15px;font-weight:800;color:#e2e8f0;font-family:'Montserrat',system-ui}
.hdr-count{font-size:13px;color:rgba(165,180,252,.5);font-weight:600}
.top-header{position:sticky;top:0;z-index:50;padding:10px 16px;background:rgba(3,7,18,.95);border-bottom:1px solid var(--border-subtle)}

/* ═══ NEW SEARCH ═══ */
.new-search-sec{display:flex;align-items:center;gap:6px;padding:12px 16px 0;position:relative}
.new-search-bar{display:flex;align-items:center;gap:10px;background:rgba(15,15,40,.6);border:1px solid rgba(99,102,241,.12);border-radius:14px;padding:10px 14px;flex:1;cursor:pointer}
.new-search-bar svg{flex-shrink:0;opacity:.5}
.new-search-ph{font-size:15px;color:rgba(165,180,252,.4);font-weight:500;flex:1}
.new-search-mic{width:28px;height:28px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.new-info-btn{width:16px;height:16px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.18);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}

/* ═══ CASCADE FILTERS ═══ */
.fltr-label{display:flex;align-items:center;gap:6px;padding:0 16px;margin-top:10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(165,180,252,.35)}
.fltr-label span{white-space:nowrap}
.fltr-hint{font-size:11px;color:rgba(165,180,252,.2);font-weight:500;text-transform:none;letter-spacing:0}
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
.add-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
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
.prod-hdr{display:flex;align-items:center;gap:10px;padding:10px 16px;position:sticky;top:52px;z-index:9;background:rgba(3,7,18,.92)}
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
    border-top: 0.5px solid rgba(99,102,241,0.2);
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
    cursor: pointer; z-index: 41; white-space: nowrap;
}
.ai-float-btn span { font-size: 13px; font-weight: 600; color: #a5b4fc; }
.ai-waves { display: flex; align-items: flex-end; gap: 2px; height: 18px; }
.ai-wave-bar { width: 3px; border-radius: 2px; background: currentColor; animation: wave-anim 1s ease-in-out infinite; }
@keyframes wave-anim { 0%, 100% { transform: scaleY(0.35); } 50% { transform: scaleY(1); } }


/* ═══ S43: RICH PRODUCT CARDS ═══ */
.rc-card{position:relative;background:rgba(15,15,40,.75);border:1px solid var(--border-subtle);border-radius:14px;overflow:hidden;margin-bottom:8px}
.rc-card .stock-bar{position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px}
.rc-card .p-discount{position:absolute;top:6px;right:8px;font-size:9px;padding:2px 6px;border-radius:5px;background:var(--danger);color:#fff;font-weight:700;z-index:2}
.rc-top{display:flex;gap:10px;padding:12px 12px 0 14px;cursor:pointer}
.rc-thumb{width:52px;height:52px;border-radius:10px;background:rgba(99,102,241,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.rc-thumb img{width:100%;height:100%;object-fit:cover;border-radius:10px}
.rc-info{flex:1;min-width:0}
.rc-row1{display:flex;justify-content:space-between;align-items:flex-start;gap:6px}
.rc-name{font-size:13px;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px}
.rc-price{text-align:right;flex-shrink:0}
.rc-code{font-size:10px;color:var(--text-secondary);margin-top:1px}
.rc-code span{color:var(--indigo-400)}
.rc-pills{display:flex;flex-wrap:wrap;gap:4px;padding:6px 12px 0 14px;cursor:pointer}
.rc-pill{font-size:9px;padding:2px 7px;border-radius:6px;font-weight:600}
.rc-sup{background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.12);color:#818cf8}
.rc-cat{background:rgba(20,184,166,.08);border:1px solid rgba(20,184,166,.12);color:#5eead4}
.rc-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5}
.rc-warn{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);color:#fbbf24}
.rc-orange{background:rgba(251,146,60,.08);border:1px solid rgba(251,146,60,.15);color:rgba(251,146,60,.7)}
.rc-stats{display:flex;gap:0;padding:8px 12px 0 14px;margin-top:6px;border-top:1px solid rgba(99,102,241,.06);cursor:pointer}
.rc-stat{flex:1;text-align:center;padding:2px 0 6px}
.rc-sl{font-size:7px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px}
.rc-sv{font-size:12px;font-weight:700;margin-top:1px}
.rc-sub{font-size:7px;color:rgba(245,158,11,.5)}
.rc-sep{width:1px;background:rgba(99,102,241,.08);margin:2px 0}
.rc-actions{display:flex;border-top:1px solid rgba(99,102,241,.08);margin-top:4px}
.rc-act{flex:1;padding:8px;text-align:center;font-size:10px;font-weight:600;color:#818cf8;cursor:pointer;border-right:1px solid rgba(99,102,241,.08);transition:background .15s}
.rc-act:last-child{border-right:none}
.rc-act:active{background:rgba(99,102,241,.1)}
.rc-more-dd{background:#080818;border:1px solid var(--border-glow);border-radius:12px;padding:4px;min-width:180px;z-index:500;box-shadow:0 8px 32px rgba(0,0,0,.6)}
.rc-dd-item{padding:9px 14px;border-radius:8px;font-size:12px;color:var(--text-primary);cursor:pointer;transition:background .15s}
.rc-dd-item:active{background:rgba(99,102,241,.1)}
.rc-dd-danger{color:var(--danger)}
/* S73.A start */
/* S73.A — Neon Glass + Matrix CSS */

.v4-var-card{padding:0;margin-bottom:12px;overflow:hidden;border-radius:18px;
    border:1px solid rgba(99,102,241,.18);
    background:linear-gradient(235deg,hsl(255 50% 10% / .55),hsl(255 50% 10% / 0) 33%),
        linear-gradient(45deg,hsl(222 50% 10% / .55),hsl(222 50% 10% / 0) 33%),
        rgba(8,9,13,.78);
    box-shadow:hsl(222 50% 2%) 0 10px 16px -8px, hsl(222 50% 4%) 0 20px 36px -14px;
}

.v4-axis-tabs{display:flex;border-bottom:1px solid hsl(222 15% 18% / .8);padding:0 14px;gap:4px}
.v4-axis-tab{position:relative;padding:14px 18px 12px;font-size:13px;font-weight:700;
    color:rgba(255,255,255,.4);cursor:pointer;border:none;background:transparent;
    border-bottom:2px solid transparent;transition:all .2s;
    display:flex;align-items:center;gap:7px;margin-bottom:-1px;font-family:inherit}
.v4-axis-tab:hover{color:rgba(255,255,255,.6)}
.v4-axis-tab.active{color:hsl(255 60% 85%);border-bottom-color:hsl(255 70% 55%);
    text-shadow:0 0 12px hsl(255 60% 50% / .4)}
.v4-axis-tab svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;
    stroke-linecap:round;stroke-linejoin:round}
.v4-axis-count{display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;padding:0 6px;border-radius:100px;
    background:hsl(255 40% 25% / .7);color:hsl(255 60% 88%);
    font-size:10px;font-weight:800;border:1px solid hsl(255 50% 40% / .5)}
.v4-axis-tab.active .v4-axis-count{background:hsl(255 60% 45%);color:#fff;
    box-shadow:0 0 10px hsl(255 60% 50% / .5)}

.v4-selected-bar{display:flex;flex-wrap:wrap;gap:6px;padding:12px 14px 10px;
    align-items:center;border-bottom:1px solid hsl(222 15% 18% / .5);min-height:56px}
.v4-sel-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 10px 5px 11px;
    border-radius:100px;
    background:linear-gradient(135deg,hsl(255 50% 28%),hsl(255 60% 22%));
    border:1px solid hsl(255 60% 50%);color:#fff;font-size:12px;font-weight:700;
    box-shadow:0 0 10px hsl(255 60% 45% / .3),inset 0 1px 0 hsl(255 60% 60% / .3);
    cursor:pointer;transition:all .15s}
.v4-sel-chip:hover{background:linear-gradient(135deg,hsl(255 55% 32%),hsl(255 65% 26%));
    transform:translateY(-1px)}
.v4-sel-chip-x{opacity:.65;font-size:10px;font-weight:400;padding-left:2px}
.v4-sel-chip .v4-dot{width:10px;height:10px;border-radius:50%;
    border:1px solid rgba(255,255,255,.3);flex-shrink:0}
.v4-sel-empty{font-size:11px;color:rgba(255,255,255,.4);font-style:italic;line-height:1.4}
.v4-clear-btn{margin-left:auto;padding:4px 10px;border-radius:100px;
    background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
    color:#fca5a5;font-size:10px;font-weight:700;cursor:pointer;
    letter-spacing:.02em;text-transform:uppercase;font-family:inherit}
.v4-clear-btn:hover{background:rgba(239,68,68,.15)}

.v4-picker-body{padding:12px 14px 14px;max-height:520px;overflow-y:auto;-webkit-overflow-scrolling:touch}
.v4-picker-search{position:relative;margin-bottom:12px}
.v4-picker-search input{width:100%;padding:10px 14px 10px 38px;border-radius:12px;
    border:1px solid hsl(222 15% 20% / .6);
    background:linear-gradient(to bottom,hsl(255 20% 15% / .2),hsl(255 30% 10% / .4));
    color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.v4-picker-search input::placeholder{color:rgba(255,255,255,.4)}
.v4-picker-search input:focus{border-color:hsl(255 50% 55% / .7);
    box-shadow:0 0 0 3px hsl(255 60% 50% / .15)}
.v4-picker-search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);
    color:rgba(255,255,255,.4);pointer-events:none}
.v4-picker-search-icon svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}

.v4-preset-group{border:1px solid hsl(255 20% 18% / .4);border-radius:14px;
    margin-bottom:10px;overflow:hidden;background:rgba(0,0,0,.15)}
.v4-preset-group-header{display:flex;align-items:center;gap:8px;padding:10px 12px;
    cursor:pointer;transition:background .15s;
    background:linear-gradient(90deg,hsl(255 25% 15% / .5),hsl(255 15% 10% / .2) 80%,transparent)}
.v4-preset-group-header:hover{background:linear-gradient(90deg,hsl(255 35% 20% / .6),hsl(255 20% 12% / .3) 80%,transparent)}
.v4-preset-group-title{flex:1;font-size:11px;font-weight:800;color:hsl(255 50% 80%);
    letter-spacing:.05em;text-transform:uppercase}
.v4-preset-group-title.starred::before{content:'\2605  ';color:hsl(45 90% 65%);
    font-size:10px;text-shadow:0 0 8px hsl(45 90% 50% / .5)}
.v4-preset-group-count{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);
    padding:2px 7px;border-radius:100px;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.06)}
.v4-preset-group-count.has-selected{color:hsl(255 60% 85%);background:hsl(255 40% 25% / .6);
    border-color:hsl(255 50% 40% / .6)}
.v4-preset-group-arrow{color:hsl(255 40% 55%);font-size:10px;transition:transform .25s}
.v4-preset-group.open .v4-preset-group-arrow{transform:rotate(90deg)}
.v4-preset-group-body{display:none;padding:8px 10px 12px;flex-wrap:wrap;gap:6px;
    border-top:1px solid hsl(255 20% 18% / .3);background:rgba(0,0,0,.2)}
.v4-preset-group.open .v4-preset-group-body{display:flex}

.v4-p-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;
    border-radius:100px;font-size:12px;font-weight:600;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.6);cursor:pointer;transition:all .15s;
    user-select:none;-webkit-user-select:none}
.v4-p-chip:hover{background:hsl(255 30% 20% / .4);border-color:hsl(255 40% 40% / .5);
    color:hsl(255 60% 85%)}
.v4-p-chip.sel{background:linear-gradient(135deg,hsl(255 60% 32%),hsl(255 70% 26%));
    border-color:hsl(255 60% 55%);color:#fff;
    box-shadow:0 0 10px hsl(255 60% 45% / .4),inset 0 1px 0 hsl(255 60% 65% / .3);
    font-weight:700}
.v4-p-chip .v4-dot{width:10px;height:10px;border-radius:50%;
    border:1px solid rgba(255,255,255,.2);flex-shrink:0}

.v4-add-row{display:flex;gap:8px;margin-top:10px;align-items:center}
.v4-add-input{flex:1;padding:10px 14px;border-radius:12px;
    border:1px dashed hsl(255 30% 40% / .5);background:rgba(255,255,255,.02);
    color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.v4-add-input:focus{border-style:solid;border-color:hsl(255 50% 55% / .7);
    box-shadow:0 0 0 3px hsl(255 60% 50% / .15)}
.v4-add-btn{padding:10px 14px;border-radius:12px;
    background:linear-gradient(135deg,hsl(255 60% 35%),hsl(255 70% 28%));
    border:1px solid hsl(255 60% 50%);color:#fff;font-size:12px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;gap:5px;
    box-shadow:0 0 12px hsl(255 60% 45% / .3),inset 0 1px 0 rgba(255,255,255,.2);
    font-family:inherit}
.v4-add-btn svg{width:14px;height:14px;stroke:#fff;stroke-width:2.5;fill:none;stroke-linecap:round}

.v4-matrix-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:14px 16px;border-radius:14px;
    background:linear-gradient(135deg,hsl(255 55% 30% / .7),hsl(222 55% 26% / .7));
    border:1px solid hsl(255 60% 55% / .6);color:#fff;font-size:13px;font-weight:700;
    cursor:pointer;transition:all .25s;
    box-shadow:0 8px 24px hsl(255 70% 30% / .35),0 0 20px hsl(255 60% 45% / .2),
        inset 0 1px 0 rgba(255,255,255,.2);
    text-shadow:0 0 12px rgba(255,255,255,.2);width:100%;font-family:inherit;letter-spacing:.01em;
    margin-top:12px}
.v4-matrix-cta:hover{transform:translateY(-1px)}
.v4-matrix-cta:active{transform:translateY(0) scale(.98)}
.v4-matrix-cta-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;text-align:left}
.v4-matrix-cta-pill{padding:3px 8px;border-radius:8px;background:rgba(0,0,0,.3);
    font-size:10px;font-weight:800;border:1px solid rgba(255,255,255,.15)}
.v4-matrix-cta-arrow{font-size:18px;flex-shrink:0}

.v4-matrix-ov{position:fixed;inset:0;z-index:9999;
    background:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(255 60% 35% / .25) 0%,transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%,hsl(222 60% 35% / .25) 0%,transparent 60%),
        linear-gradient(180deg,#0a0b14 0%,#050609 100%);
    display:none;flex-direction:column;opacity:0;transition:opacity .25s}
.v4-matrix-ov.open{display:flex;opacity:1}

.v4-mx-header{flex-shrink:0;display:flex;align-items:center;gap:10px;
    padding:14px 16px 12px;background:rgba(3,7,18,.9);    border-bottom:1px solid hsl(222 15% 18% / .6)}
.v4-mx-close{width:36px;height:36px;border-radius:11px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.6);cursor:pointer;display:flex;
    align-items:center;justify-content:center}
.v4-mx-close:hover{color:hsl(255 60% 85%)}
.v4-mx-close svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}
.v4-mx-title-wrap{flex:1;min-width:0}
.v4-mx-title{font-size:15px;font-weight:800;
    background:linear-gradient(135deg,#f1f5f9 30%,hsl(255 60% 80%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1}
.v4-mx-sub{font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;
    letter-spacing:.08em;font-weight:600;margin-top:2px}
.v4-mx-sub b{color:hsl(255 60% 85%);font-weight:700}

.v4-mx-quick{flex-shrink:0;display:flex;gap:6px;overflow-x:auto;
    padding:10px 16px;scrollbar-width:none;
    border-bottom:1px solid hsl(222 15% 18% / .5);background:rgba(3,7,18,.6)}
.v4-mx-quick::-webkit-scrollbar{display:none}
.v4-qchip{flex-shrink:0;padding:7px 13px;border-radius:100px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.6);font-size:11px;font-weight:700;cursor:pointer;
    transition:all .2s;white-space:nowrap;font-family:inherit}
.v4-qchip:hover{background:hsl(255 40% 22% / .4);border-color:hsl(255 50% 45% / .5);
    color:hsl(255 60% 88%)}
.v4-qchip.danger{border-color:rgba(239,68,68,.25);color:#fca5a5}
.v4-qchip.danger:hover{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.45)}

.v4-mx-body{flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:6px 0;position:relative}
.v4-mx-table{border-collapse:separate;border-spacing:0;width:max-content;min-width:100%}
.v4-mx-head,.v4-mx-rowh,.v4-mx-corner{position:sticky;background:rgba(8,9,13,.98);
    z-index:2}
.v4-mx-corner{left:0;top:0;z-index:4;
    border-right:1px solid hsl(222 15% 18% / .8);
    border-bottom:1px solid hsl(222 15% 18% / .8)}
.v4-mx-head{top:0;z-index:3;padding:10px 8px;text-align:center;
    font-size:11px;font-weight:700;color:hsl(255 60% 85%);min-width:110px;
    border-bottom:1px solid hsl(222 15% 18% / .8);
    border-left:1px solid hsl(222 10% 14% / .3)}
.v4-mx-head .v4-dot{width:12px;height:12px;border-radius:50%;
    border:1px solid rgba(255,255,255,.25);display:inline-block;
    margin-right:6px;vertical-align:middle;box-shadow:0 0 8px rgba(255,255,255,.1)}
.v4-mx-rowh{left:0;z-index:2;padding:8px 14px;text-align:left;font-size:13px;
    font-weight:800;color:hsl(255 60% 88%);min-width:80px;
    border-right:1px solid hsl(222 15% 18% / .8);
    border-bottom:1px solid hsl(222 10% 14% / .5);
    background:linear-gradient(to right,hsl(255 30% 16% / .7),hsl(255 20% 12% / .6))}
.v4-mx-cell{padding:6px 4px;min-width:110px;
    border-bottom:1px solid hsl(222 10% 14% / .5);
    border-left:1px solid hsl(222 10% 14% / .3);
    background:rgba(0,0,0,.15);vertical-align:middle}
.v4-mx-cell.has-value{background:hsl(255 30% 14% / .35)}

.v4-cell-inputs{display:flex;flex-direction:column;gap:4px;align-items:stretch}
.v4-cell-qty-row{display:flex;align-items:center;gap:4px;justify-content:center}
.v4-cell-input{width:54px;height:34px;padding:4px 2px;border-radius:8px;
    border:1px solid hsl(222 15% 22% / .7);background:rgba(8,9,13,.5);
    color:var(--text-primary);font-size:14px;font-weight:800;font-family:inherit;
    text-align:center;outline:none;transition:all .15s;-moz-appearance:textfield}
.v4-cell-input::-webkit-outer-spin-button,.v4-cell-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.v4-cell-input:focus{border-color:hsl(255 60% 55%);background:hsl(255 40% 15% / .6);
    box-shadow:0 0 0 2px hsl(255 60% 50% / .2),0 0 12px hsl(255 60% 45% / .3)}
.v4-cell-input.qty{color:hsl(255 60% 90%)}
.v4-cell-input.min-input{width:36px;height:26px;font-size:11px;font-weight:700;
    color:hsl(45 80% 70%);border-color:hsl(45 40% 22% / .7)}
.v4-cell-input.min-input:focus{border-color:hsl(45 70% 55%);
    box-shadow:0 0 0 2px hsl(45 70% 50% / .2)}
.v4-cell-label{font-size:8px;font-weight:700;color:rgba(255,255,255,.4);
    letter-spacing:.08em;text-transform:uppercase;text-align:center;
    width:54px;line-height:1;margin:0 auto}
.v4-cell-min-row{display:flex;align-items:center;gap:2px;justify-content:center}
.v4-min-step{width:18px;height:26px;border-radius:6px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    color:hsl(45 80% 70%);cursor:pointer;display:flex;align-items:center;
    justify-content:center;font-size:10px;font-weight:700;font-family:inherit;padding:0}
.v4-min-step:hover{background:hsl(45 30% 20% / .5);border-color:hsl(45 40% 40% / .5)}

.v4-mx-bottom{flex-shrink:0;padding:14px 16px 20px;background:rgba(3,7,18,.95);
    border-top:1px solid hsl(222 15% 18% / .8)}
.v4-mx-stats{display:flex;justify-content:space-around;gap:8px;margin-bottom:12px;
    padding:10px 14px;border-radius:14px;background:rgba(0,0,0,.3);
    border:1px solid rgba(255,255,255,.05)}
.v4-mx-stat{text-align:center;flex:1}
.v4-mx-stat-value{font-size:20px;font-weight:800;letter-spacing:-.02em;
    background:linear-gradient(135deg,#fff 0%,hsl(255 60% 85%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
    line-height:1}
.v4-mx-stat-label{font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase;
    letter-spacing:.1em;font-weight:700;margin-top:4px}

.v4-mx-summary{display:flex;align-items:center;gap:10px;padding:12px 14px;
    border-radius:14px;background:rgba(34,197,94,.06);
    border:1px solid rgba(34,197,94,.25);margin-top:12px}
.v4-mx-summary-check{width:28px;height:28px;border-radius:50%;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 12px rgba(34,197,94,.5);flex-shrink:0;color:#fff;
    font-size:14px;font-weight:800}
.v4-mx-summary-text{flex:1}
.v4-mx-summary-title{font-size:12px;font-weight:700;color:#86efac;margin-bottom:1px}
.v4-mx-summary-sub{font-size:10px;color:rgba(134,239,172,.7)}
.v4-mx-summary-edit{padding:5px 12px;border-radius:100px;
    background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);
    color:#86efac;font-size:10px;font-weight:700;cursor:pointer;
    text-transform:uppercase;letter-spacing:.03em;font-family:inherit}

@media (max-width:380px){
    .v4-mx-head,.v4-mx-cell{min-width:96px}
    .v4-cell-input{width:48px}
}
/* S73.A end */

/* ═══ S74 · NEON GLASS WIZARD V4 ═══ */
.v4-glass-pro{
  box-shadow:
    0 8px 40px rgba(0,0,0,0.4),
    0 0 30px rgba(99,102,241,0.18),
    0 0 60px rgba(139,92,246,0.08),
    inset 0 1px 0 rgba(255,255,255,0.06) !important;
  border-color:rgba(99,102,241,0.28) !important;
}
/* Photo zone ALL-IN-ONE */
.v4-pz{
  position:relative;overflow:hidden;
  border-radius:18px;margin-bottom:14px;padding:16px 14px 12px;
  background:
    radial-gradient(ellipse 80% 60% at 50% 30%, rgba(99,102,241,0.10), transparent 70%),
    linear-gradient(180deg, rgba(99,102,241,0.04), rgba(8,11,24,0.5));
  border:1.5px dashed rgba(99,102,241,0.32);
}
.v4-pz::before{content:'';position:absolute;top:0;left:20%;right:20%;height:1px;
  background:linear-gradient(90deg,transparent,rgba(165,180,252,0.5),transparent);}
.v4-pz-top{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.v4-pz-ic{
  width:44px;height:44px;border-radius:13px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.15));
  border:1px solid rgba(139,92,246,0.4);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 18px rgba(99,102,241,0.25),inset 0 1px 0 rgba(255,255,255,0.08);
}
.v4-pz-title{font-size:14px;font-weight:700;letter-spacing:-0.01em;
  background:linear-gradient(135deg,#fff,#a5b4fc);
  -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}
.v4-pz-sub{font-size:10px;color:rgba(226,232,240,0.55);margin-top:1px}
.v4-pz-btns{display:flex;gap:8px;margin-bottom:10px}
.v4-pz-btn{
  flex:1;height:42px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;gap:6px;
  font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;
}
.v4-pz-btn.primary{
  background:linear-gradient(135deg,rgba(99,102,241,0.25),rgba(99,102,241,0.12));
  border:1px solid rgba(99,102,241,0.5);color:#c7d2fe;
  box-shadow:0 0 14px rgba(99,102,241,0.2),inset 0 1px 0 rgba(255,255,255,0.08);
}
.v4-pz-btn.primary:active{transform:scale(.97)}
.v4-pz-btn.sec{
  background:rgba(255,255,255,0.03);
  border:1px solid rgba(255,255,255,0.12);color:#cbd5e1;
}
.v4-pz-tips{
  display:flex;flex-wrap:wrap;gap:4px 10px;
  padding-top:10px;border-top:1px dashed rgba(99,102,241,0.15);
}
.v4-pz-tip{display:inline-flex;align-items:center;gap:4px;
  font-size:9.5px;font-weight:500;color:rgba(255,255,255,0.55);}
.v4-pz-tip svg{width:10px;height:10px;color:#86efac;flex-shrink:0}

/* Neon Underline INPUT (Variant C) */
.v4-inpC-wrap{
  flex:1;position:relative;
  background:linear-gradient(180deg,rgba(17,24,44,0.4),rgba(8,11,24,0.6));
  border-radius:10px 10px 2px 2px;
  border-top:1px solid rgba(99,102,241,0.1);
  border-left:1px solid rgba(99,102,241,0.08);
  border-right:1px solid rgba(99,102,241,0.08);
  overflow:hidden;
}
.v4-inpC-wrap::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#6366f1,#8b5cf6,#d946ef,#8b5cf6,#6366f1,transparent);box-shadow:0 0 12px rgba(139,92,246,0.7),0 0 24px rgba(99,102,241,0.5),0 -2px 8px rgba(139,92,246,0.3);opacity:0.75;transform:scaleX(0.45);transition:all 0.4s;transform-origin:center;filter:blur(0.3px)}
.v4-inpC-wrap:focus-within::after{opacity:1;transform:scaleX(1);box-shadow:0 0 16px rgba(217,70,239,0.85),0 0 32px rgba(139,92,246,0.6),0 -3px 12px rgba(139,92,246,0.4);filter:blur(0px)}
.v4-inpC-wrap input.fc{
  background:transparent !important;border:none !important;
  border-radius:0 !important;padding:13px 14px !important;
  box-shadow:none !important;
}
/* Chip inline input (for "+ нова" unit) */
.v4-chipInput{
  min-width:90px;padding:8px 12px;border-radius:100px;
  background:linear-gradient(180deg,rgba(17,24,44,0.4),rgba(8,11,24,0.6));
  border:1px dashed rgba(99,102,241,0.4);
  color:#fff;font-size:11px;font-weight:500;font-family:inherit;
  outline:none;transition:all .2s;
}
.v4-chipInput:focus{
  border-color:rgba(139,92,246,0.7);border-style:solid;
  box-shadow:0 0 12px rgba(139,92,246,0.3);
}
.v4-chipInput::placeholder{color:#a5b4fc;font-weight:600}
/* S74: усилване на .fc в wizard модала */
#wizModal .fc{
  background:linear-gradient(180deg,rgba(17,24,44,0.55),rgba(8,11,24,0.8)) !important;
  border:1px solid rgba(99,102,241,0.2) !important;
  border-bottom:none !important;
  border-radius:10px !important;
  box-shadow:
    inset 0 -3px 0 rgba(139,92,246,0.55),
    inset 0 -4px 10px rgba(139,92,246,0.2),
    0 3px 8px rgba(99,102,241,0.15),
    0 0 14px rgba(139,92,246,0.1) !important;
  transition:all .25s !important;
  position:relative !important;
}
#wizModal .fc:focus{
  border-color:rgba(139,92,246,0.4) !important;
  box-shadow:
    inset 0 -4px 0 #a855f7,
    inset 0 -6px 14px rgba(217,70,239,0.35),
    0 4px 14px rgba(139,92,246,0.4),
    0 0 24px rgba(139,92,246,0.3),
    0 0 0 1px rgba(139,92,246,0.2),
    inset 0 1px 0 rgba(255,255,255,0.05) !important;
}




/* ═══ S75.2 TYPE TOGGLE — REQUIRED ═══ */
.v4-type-toggle.needs-select{
  border-color:rgba(234,179,8,0.55);
  animation:ttPulseRequired 1.3s ease-in-out infinite;
}
@keyframes ttPulseRequired{
  0%,100%{box-shadow:0 0 0 0 rgba(234,179,8,0.2),0 4px 20px rgba(0,0,0,0.3)}
  50%{box-shadow:0 0 28px 6px rgba(234,179,8,0.35),0 4px 20px rgba(0,0,0,0.3)}
}
.v4-type-toggle.pulsing-strong{
  animation:ttPulseStrong 0.5s ease-in-out 3;
}
@keyframes ttPulseStrong{
  0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.3),0 4px 20px rgba(0,0,0,0.3);border-color:rgba(239,68,68,0.8)}
  50%{box-shadow:0 0 36px 10px rgba(239,68,68,0.55),0 4px 20px rgba(0,0,0,0.3);border-color:#ef4444}
}
.v4-tt-warn{
  font-size:10px;font-weight:700;color:#fbbf24;
  text-align:center;margin-bottom:6px;letter-spacing:0.05em;
  text-transform:uppercase;
  text-shadow:0 0 8px rgba(234,179,8,0.5);
  animation:warnBlink 1.3s ease-in-out infinite;
}
@keyframes warnBlink{0%,100%{opacity:1}50%{opacity:0.55}}

/* S75.2: autofill fix — Chrome/Safari не бели полетата */
#wizModal input:-webkit-autofill,
#wizModal input:-webkit-autofill:hover,
#wizModal input:-webkit-autofill:focus,
#wizModal input:-webkit-autofill:active{
  -webkit-box-shadow:0 0 0 30px rgba(17,24,44,0.9) inset !important;
  -webkit-text-fill-color:#fff !important;
  caret-color:#fff !important;
  transition:background-color 9999s ease-out 0s;
}

/* ═══ S75 TYPE TOGGLE (Единичен / С варианти) ═══ */
.v4-type-toggle{
  display:grid;grid-template-columns:1fr 1fr;gap:6px;
  padding:4px;margin-bottom:14px;border-radius:14px;
  background:linear-gradient(145deg,rgba(17,24,44,0.65),rgba(10,13,30,0.85));
  border:1px solid rgba(99,102,241,0.18);
    box-shadow:0 4px 20px rgba(0,0,0,0.3),inset 0 1px 0 rgba(255,255,255,0.04);
  position:relative;overflow:hidden;
}
.v4-type-toggle::before{
  content:'';position:absolute;top:0;left:20%;right:20%;height:1px;
  background:linear-gradient(90deg,transparent,rgba(165,180,252,0.5),transparent);
}
.v4-tt-opt{
  display:flex;align-items:center;justify-content:center;gap:7px;
  height:40px;border-radius:10px;cursor:pointer;
  background:transparent;border:1px solid transparent;
  color:rgba(255,255,255,0.55);
  font-family:inherit;font-size:12px;font-weight:600;
  letter-spacing:0.01em;transition:all 0.25s;
  position:relative;
}
.v4-tt-opt svg{width:15px;height:15px}
.v4-tt-opt:not(.active):hover{
  background:rgba(255,255,255,0.03);color:rgba(255,255,255,0.75);
}
.v4-tt-opt.active{
  background:linear-gradient(180deg,rgba(99,102,241,0.25),rgba(67,56,202,0.12));
  border-color:rgba(139,92,246,0.5);color:#fff;
  box-shadow:
    0 0 14px rgba(139,92,246,0.3),
    inset 0 1px 0 rgba(255,255,255,0.08);
}
.v4-tt-opt.active::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:1.5px;
  background:linear-gradient(90deg,transparent,#6366f1,#8b5cf6,#6366f1,transparent);
  box-shadow:0 0 8px rgba(139,92,246,0.6);
}

/* ═══ S74.3 FOOTER NEON ═══ */
.v4-foot-save::after,.v4-foot-next::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:1.5px;
  opacity:0.8;transition:all 0.3s;
}
.v4-foot-save::after{
  background:linear-gradient(90deg,transparent,#22c55e,#4ade80,#22c55e,transparent);
  box-shadow:0 0 8px rgba(34,197,94,0.6);
}
.v4-foot-next::after{
  background:linear-gradient(90deg,transparent,#6366f1,#8b5cf6,#6366f1,transparent);
  box-shadow:0 0 8px rgba(139,92,246,0.6);
}
.v4-foot-save:hover::after,.v4-foot-next:hover::after{
  opacity:1;box-shadow:0 0 14px rgba(139,92,246,0.9);
}
.v4-foot-save:hover{border-color:rgba(34,197,94,0.7);box-shadow:0 0 22px rgba(34,197,94,0.3),inset 0 1px 0 rgba(255,255,255,0.06)}
.v4-foot-next:hover{border-color:rgba(139,92,246,0.8);box-shadow:0 0 22px rgba(139,92,246,0.35),inset 0 1px 0 rgba(255,255,255,0.06)}

/* S76.3: Variants neon refresh */
/* ═══ Var Card = v4-glass-pro ═══ */
#wizModal .v-var-card{
  padding:18px 14px 14px !important;
  border-radius:20px !important;
  border:1px solid rgba(99,102,241,0.28) !important;
  background:linear-gradient(145deg,rgba(17,24,44,0.75),rgba(10,13,30,0.9)) !important;
      box-shadow:
    0 8px 40px rgba(0,0,0,0.4),
    0 0 30px rgba(99,102,241,0.18),
    0 0 60px rgba(139,92,246,0.08),
    inset 0 1px 0 rgba(255,255,255,0.06) !important;
}

/* ═══ Preview Pill = Glass Mini Card ═══ */
#wizModal .v-preview-pill{
  padding:12px 14px !important;
  margin-bottom:14px !important;
  border-radius:16px !important;
  background:linear-gradient(145deg,rgba(99,102,241,0.12),rgba(139,92,246,0.04)) !important;
  border:1px solid rgba(139,92,246,0.28) !important;
  box-shadow:
    0 0 18px rgba(99,102,241,0.18),
    inset 0 1px 0 rgba(255,255,255,0.06) !important;
  position:relative; overflow:hidden;
}
#wizModal .v-preview-pill::before{
  content:''; position:absolute; top:0; left:15%; right:15%; height:1px;
  background:linear-gradient(90deg,transparent,rgba(165,180,252,0.55),transparent);
}
#wizModal .v-preview-thumb{
  width:40px !important; height:40px !important;
  border-radius:12px !important;
  background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.15)) !important;
  border:1px solid rgba(139,92,246,0.4) !important;
  box-shadow:0 0 14px rgba(99,102,241,0.25),inset 0 1px 0 rgba(255,255,255,0.08) !important;
}
#wizModal .v-preview-thumb svg{stroke:#c4b5fd !important; width:20px !important; height:20px !important}
#wizModal .v-preview-name{
  font-size:13px !important; font-weight:700 !important;
  background:linear-gradient(135deg,#fff,#a5b4fc);
  -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent;
}
#wizModal .v-preview-meta{
  font-size:10.5px !important; color:rgba(226,232,240,0.6) !important;
  margin-top:1px;
}

/* ═══ Axis Tabs = Pill Toggle (DESIGN_SYSTEM 4.14) ═══ */
#wizModal .v-axis-tabs{
  border-bottom:none !important;
  padding:4px !important;
  margin:0 0 12px !important;
  gap:6px !important;
  border-radius:14px !important;
  background:linear-gradient(145deg,rgba(17,24,44,0.65),rgba(10,13,30,0.85)) !important;
  border:1px solid rgba(99,102,241,0.18) !important;
  box-shadow:0 4px 20px rgba(0,0,0,0.3),inset 0 1px 0 rgba(255,255,255,0.04) !important;
  position:relative;
  overflow-x:auto !important;
  overflow-y:hidden !important;
  -webkit-overflow-scrolling:touch !important;
  scrollbar-width:none !important;
}
#wizModal .v-axis-tabs::-webkit-scrollbar{display:none !important}
#wizModal .v-axis-tab{flex:1 1 auto !important; min-width:0 !important}
#wizModal .v-axis-tab-add{flex-shrink:0 !important}
#wizModal .v-axis-tabs::before{
  content:''; position:absolute; top:0; left:20%; right:20%; height:1px;
  background:linear-gradient(90deg,transparent,rgba(165,180,252,0.5),transparent);
}
#wizModal .v-axis-tab{
  height:38px !important;
  padding:0 14px !important;
  border-radius:10px !important;
  background:transparent !important;
  border:1px solid transparent !important;
  border-bottom:1px solid transparent !important;
  color:rgba(255,255,255,0.55) !important;
  font-weight:600 !important;
  font-size:12px !important;
  letter-spacing:0.01em !important;
  margin-bottom:0 !important;
  transition:all 0.25s !important;
  position:relative;
}
#wizModal .v-axis-tab.active{
  background:linear-gradient(180deg,rgba(99,102,241,0.25),rgba(67,56,202,0.12)) !important;
  border-color:rgba(139,92,246,0.5) !important;
  color:#fff !important;
  text-shadow:none !important;
  box-shadow:0 0 14px rgba(139,92,246,0.3),inset 0 1px 0 rgba(255,255,255,0.08) !important;
}
#wizModal .v-axis-tab.active::after{
  content:''; position:absolute; bottom:0; left:0; right:0; height:1.5px;
  background:linear-gradient(90deg,transparent,#6366f1,#8b5cf6,#6366f1,transparent);
  box-shadow:0 0 8px rgba(139,92,246,0.6);
}
#wizModal .v-axis-tab-count{
  background:rgba(99,102,241,0.22) !important;
  border-color:rgba(139,92,246,0.4) !important;
  color:#c4b5fd !important;
  box-shadow:none !important;
}
#wizModal .v-axis-tab.active .v-axis-tab-count{
  background:linear-gradient(135deg,#6366f1,#8b5cf6) !important;
  color:#fff !important;
  box-shadow:0 0 10px rgba(139,92,246,0.5) !important;
}
#wizModal .v-axis-tab-add{
  color:#c4b5fd !important;
  font-size:18px !important;
  padding:0 12px !important;
  font-weight:700;
}

/* ═══ Selected Bar — glass ═══ */
#wizModal .v-sel-bar{
  padding:10px 2px !important;
  border-bottom:none !important;
  margin-bottom:10px !important;
  min-height:auto !important;
}
#wizModal .v-sel-chip{
  background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.18)) !important;
  border:1px solid rgba(139,92,246,0.5) !important;
  box-shadow:0 0 12px rgba(99,102,241,0.25),inset 0 1px 0 rgba(255,255,255,0.1) !important;
  color:#fff !important;
  font-size:11.5px !important;
  padding:6px 11px 6px 12px !important;
}
#wizModal .v-clear-btn{
  background:rgba(239,68,68,0.08) !important;
  border:1px solid rgba(239,68,68,0.25) !important;
  color:#fca5a5 !important;
  border-radius:100px !important;
  padding:5px 12px !important;
  font-size:10px !important;
  font-weight:700 !important;
  letter-spacing:0.03em !important;
  text-transform:uppercase;
}
#wizModal .v-sel-empty{
  color:rgba(255,255,255,0.4) !important;
  font-size:11px !important;
  font-style:italic;
  padding:4px 0;
}

/* ═══ Picker search input (3D underline от 4.5) ═══ */
#wizModal .v-picker-search{
  position:relative; margin-bottom:10px;
}
#wizModal .v-picker-search input{
  width:100% !important;
  padding:11px 12px 11px 36px !important;
  background:linear-gradient(180deg,rgba(17,24,44,0.55),rgba(8,11,24,0.8)) !important;
  border:1px solid rgba(99,102,241,0.2) !important;
  border-bottom:none !important;
  border-radius:10px !important;
  color:#fff !important;
  font-size:12px !important;
  font-family:inherit !important;
  outline:none !important;
  box-shadow:
    inset 0 -3px 0 rgba(139,92,246,0.55),
    inset 0 -4px 10px rgba(139,92,246,0.2),
    0 3px 8px rgba(99,102,241,0.15),
    0 0 14px rgba(139,92,246,0.1) !important;
  transition:all .25s;
}
#wizModal .v-picker-search input:focus{
  border-color:rgba(139,92,246,0.4) !important;
  box-shadow:
    inset 0 -4px 0 #a855f7,
    inset 0 -6px 14px rgba(217,70,239,0.35),
    0 4px 14px rgba(139,92,246,0.4),
    0 0 24px rgba(139,92,246,0.3) !important;
}
#wizModal .v-picker-search-ic{
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  color:#a5b4fc; pointer-events:none;
}
#wizModal .v-picker-search-ic svg{width:14px;height:14px;stroke-width:2}

/* ═══ Preset groups (nested glass) ═══ */
#wizModal .v-pgroup{
  border:1px solid rgba(99,102,241,0.22) !important;
  background:linear-gradient(180deg,rgba(17,24,44,0.45),rgba(8,11,24,0.6)) !important;
  border-radius:14px !important;
  margin-bottom:8px !important;
  overflow:hidden;
}
#wizModal .v-pgroup-head{
  background:linear-gradient(90deg,rgba(99,102,241,0.1),transparent) !important;
  padding:11px 13px !important;
}
#wizModal .v-pgroup-title{
  color:#a5b4fc !important;
  font-size:11px !important;
  font-weight:800 !important;
  letter-spacing:0.06em !important;
}
#wizModal .v-pgroup-count{
  background:rgba(99,102,241,0.15) !important;
  border-color:rgba(139,92,246,0.3) !important;
  color:rgba(255,255,255,0.7) !important;
}
#wizModal .v-pgroup-count.has{
  background:linear-gradient(135deg,#6366f1,#8b5cf6) !important;
  border-color:rgba(139,92,246,0.6) !important;
  color:#fff !important;
  box-shadow:0 0 8px rgba(139,92,246,0.4) !important;
}
#wizModal .v-pgroup-body{
  background:rgba(0,0,0,0.15) !important;
  border-top:1px solid rgba(99,102,241,0.1) !important;
  padding:10px 11px 13px !important;
}
#wizModal .v-pgroup-footer{
  border-top:1px solid rgba(99,102,241,0.1) !important;
  background:rgba(0,0,0,0.2) !important;
}

/* ═══ Chips in picker body ═══ */
#wizModal .v-chip{
  padding:7px 13px !important;
  border-radius:100px !important;
  background:rgba(255,255,255,0.04) !important;
  border:1px solid rgba(139,92,246,0.2) !important;
  color:rgba(255,255,255,0.75) !important;
  font-size:11.5px !important;
  font-weight:600 !important;
  transition:all 0.2s;
}
#wizModal .v-chip:hover{
  border-color:rgba(139,92,246,0.4) !important;
  color:#c4b5fd !important;
}
#wizModal .v-chip.selected{
  background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.2)) !important;
  border-color:rgba(139,92,246,0.6) !important;
  color:#fff !important;
  box-shadow:0 0 12px rgba(99,102,241,0.3),inset 0 1px 0 rgba(255,255,255,0.1) !important;
}

/* ═══ Matrix CTA (glass card с indigo glow) ═══ */
#wizModal .v-matrix-cta-wrap{
  padding:14px !important;
  margin-top:12px !important;
  background:linear-gradient(145deg,rgba(17,24,44,0.7),rgba(10,13,30,0.9)) !important;
  border-radius:18px !important;
  border:1px solid rgba(139,92,246,0.3) !important;
  box-shadow:0 0 20px rgba(99,102,241,0.2) !important;
}
#wizModal .v-matrix-cta{
  background:linear-gradient(180deg,rgba(99,102,241,0.2),rgba(67,56,202,0.08)) !important;
  border:1px solid rgba(139,92,246,0.5) !important;
  color:#c4b5fd !important;
  border-radius:12px !important;
  box-shadow:0 0 14px rgba(139,92,246,0.22),inset 0 1px 0 rgba(255,255,255,0.05) !important;
  font-weight:700 !important;
}
#wizModal .v-matrix-cta-pill{
  background:linear-gradient(135deg,#6366f1,#8b5cf6) !important;
  color:#fff !important;
  box-shadow:0 0 10px rgba(139,92,246,0.5) !important;
}
#wizModal .v-matrix-summary{
  background:rgba(34,197,94,0.08) !important;
  border:1px solid rgba(34,197,94,0.3) !important;
  border-radius:14px !important;
  padding:12px 14px !important;
  box-shadow:0 0 14px rgba(34,197,94,0.12) !important;
}
#wizModal .v-ms-check{
  background:linear-gradient(135deg,#22c55e,#16a34a) !important;
  box-shadow:0 0 12px rgba(34,197,94,0.5) !important;
}
#wizModal .v-ms-title{color:#86efac !important; font-weight:700 !important}
#wizModal .v-ms-edit{
  background:rgba(34,197,94,0.12) !important;
  border:1px solid rgba(34,197,94,0.3) !important;
  color:#86efac !important;
  border-radius:100px !important;
  font-size:10px !important;
  font-weight:700 !important;
  letter-spacing:0.03em;
}
/* S76.3 end */

/* S76.4: Variants full refresh */
/* ═══ По-неонов контейнер .v-var-card ═══ */
#wizModal .v-var-card{
  padding:16px 12px 14px !important;
  border-radius:22px !important;
  border:1px solid rgba(139,92,246,0.32) !important;
  background:
    linear-gradient(235deg, rgba(99,102,241,0.12), transparent 35%),
    linear-gradient(45deg, rgba(139,92,246,0.10), transparent 35%),
    linear-gradient(145deg,rgba(17,24,44,0.78),rgba(10,13,30,0.92)) !important;
  box-shadow:
    0 8px 40px rgba(0,0,0,0.45),
    0 0 40px rgba(99,102,241,0.22),
    0 0 80px rgba(139,92,246,0.12),
    inset 0 1px 0 rgba(255,255,255,0.07),
    inset 0 0 40px rgba(99,102,241,0.05) !important;
}
#wizModal .v-var-card::before{
  content:''; position:absolute; top:0; left:10%; right:10%; height:1px;
  background:linear-gradient(90deg,transparent,rgba(165,180,252,0.7),transparent);
  pointer-events:none;
}
#wizModal .v-var-card::after{
  content:''; position:absolute; bottom:0; left:25%; right:25%; height:1px;
  background:linear-gradient(90deg,transparent,rgba(217,70,239,0.5),transparent);
  pointer-events:none;
}

/* ═══ Tabs с 2 реда label (S76.4: „Вариация 1" + „Размер") ═══ */
#wizModal .v-axis-tab{
  flex-direction:column !important;
  height:auto !important;
  padding:8px 14px !important;
  min-height:48px !important;
  gap:2px !important;
  line-height:1.2 !important;
  white-space:nowrap !important;
}
#wizModal .v-axis-tab .v-tab-top{
  font-size:8.5px !important;
  font-weight:700 !important;
  letter-spacing:0.08em !important;
  text-transform:uppercase !important;
  opacity:0.55 !important;
  line-height:1 !important;
}
#wizModal .v-axis-tab.active .v-tab-top{opacity:0.9 !important; color:#c4b5fd !important}
#wizModal .v-axis-tab .v-tab-bottom{
  font-size:12px !important;
  font-weight:700 !important;
  display:flex !important;
  align-items:center !important;
  gap:5px !important;
  line-height:1.2 !important;
}

/* Tab add бутон — кръгъл + в v4 стил (S76.5 fix) */
#wizModal .v-axis-tab-add{
  display:inline-flex !important;
  align-items:center !important;
  justify-content:center !important;
  min-height:48px !important;
  width:48px !important;
  padding:0 !important;
  border-radius:12px !important;
  background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08)) !important;
  border:1px solid rgba(139,92,246,0.5) !important;
  color:#c4b5fd !important;
  font-weight:700 !important;
  font-size:22px !important;
  line-height:1 !important;
  box-shadow:0 0 10px rgba(139,92,246,0.22),inset 0 1px 0 rgba(255,255,255,0.05) !important;
  flex-shrink:0 !important;
}
#wizModal .v-axis-tab-add:hover{
  background:linear-gradient(180deg,rgba(99,102,241,0.28),rgba(67,56,202,0.14)) !important;
  box-shadow:0 0 16px rgba(139,92,246,0.35),inset 0 1px 0 rgba(255,255,255,0.08) !important;
}

/* Instruction hide при focus (S76.5) */
.v4-matrix-ov.open:focus-within #mxInstruction{
  max-height:0 !important;
  opacity:0 !important;
  padding-top:0 !important;
  padding-bottom:0 !important;
  border-bottom-width:0 !important;
}
/* S76.7 applied: Matrix focus mode */
/* S76.8 applied: floating back button */
#mxFocusBack{
  position:fixed; top:max(12px,env(safe-area-inset-top,12px)); left:12px;
  width:44px; height:44px; border-radius:14px;
  background:linear-gradient(145deg,rgba(99,102,241,0.25),rgba(67,56,202,0.12));
  border:1px solid rgba(139,92,246,0.55);
  color:#c4b5fd;
  display:none; align-items:center; justify-content:center;
  cursor:pointer; z-index:10001;
    box-shadow:0 6px 20px rgba(0,0,0,0.5),0 0 18px rgba(139,92,246,0.35),inset 0 1px 0 rgba(255,255,255,0.08);
  font-family:inherit;
  transition:transform .15s;
}
#mxFocusBack:active{transform:scale(0.92)}
#mxOverlay.mx-focused #mxFocusBack{display:flex}
#mxOverlay.mx-focused .mx-header,
#mxOverlay.mx-focused #mxInstruction,
#mxOverlay.mx-focused .mx-quick,
#mxOverlay.mx-focused .mx-bottom{
  display:none !important;
}
#mxOverlay.mx-focused .mx-body-wrap{
  flex:1 !important;
  max-height:none !important;
  padding-top:max(40px, calc(8px + env(safe-area-inset-top))) !important;
}
/* S76.5 applied */
/* S76.6 applied */

/* ═══ Chips — по-красиви + padding fix ═══ */
#wizModal .v-chip{
  padding:8px 14px !important;
  min-height:32px !important;
  border-radius:100px !important;
  background:linear-gradient(180deg,rgba(99,102,241,0.08),rgba(67,56,202,0.02)) !important;
  border:1px solid rgba(139,92,246,0.28) !important;
  color:rgba(226,232,240,0.8) !important;
  font-size:11.5px !important;
  font-weight:600 !important;
  line-height:1 !important;
  white-space:nowrap !important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.04), 0 2px 6px rgba(99,102,241,0.08) !important;
  transition:all .2s !important;
}
#wizModal .v-chip:hover{
  border-color:rgba(139,92,246,0.5) !important;
  color:#fff !important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,0.08), 0 0 14px rgba(139,92,246,0.2) !important;
}
#wizModal .v-chip.selected{
  background:linear-gradient(135deg,rgba(99,102,241,0.4),rgba(139,92,246,0.28)) !important;
  border-color:rgba(139,92,246,0.75) !important;
  color:#fff !important;
  box-shadow:
    0 0 16px rgba(99,102,241,0.4),
    0 0 30px rgba(139,92,246,0.15),
    inset 0 1px 0 rgba(255,255,255,0.15) !important;
  font-weight:700 !important;
}
#wizModal .v-sel-chip{
  padding:7px 11px 7px 13px !important;
  font-size:11.5px !important;
  font-weight:700 !important;
  white-space:nowrap !important;
  line-height:1 !important;
}

/* ═══ Footer (Back/Колко бройки/Избери/Запиши) v4 ═══ */
#wizModal #v4Footer{
  padding:10px 12px !important;
  padding-bottom:max(60px, calc(10px + env(safe-area-inset-bottom))) !important;
  gap:8px !important;
}
#wizModal #v4Footer > button{
  height:42px !important;
  border-radius:12px !important;
  font-size:12px !important;
  font-weight:700 !important;
  letter-spacing:0.02em !important;
  font-family:inherit !important;
  cursor:pointer;
  transition:all .25s;
  position:relative;
  overflow:hidden;
}
/* Назад (първи бутон) */
#wizModal #v4Footer > button:first-child{
  background:rgba(255,255,255,0.04) !important;
  border:1px solid rgba(255,255,255,0.1) !important;
  color:#cbd5e1 !important;
  box-shadow:none !important;
  width:42px !important;
  flex:0 0 42px !important;
}
/* Колко бройки (indigo glass + underline) */
#wizModal #v4Footer > button[onclick*="openMxOverlay"]{
  flex:1.3 !important;
  background:linear-gradient(180deg,rgba(99,102,241,0.22),rgba(67,56,202,0.08)) !important;
  border:1px solid rgba(139,92,246,0.55) !important;
  color:#c4b5fd !important;
  box-shadow:0 0 16px rgba(139,92,246,0.28),inset 0 1px 0 rgba(255,255,255,0.06) !important;
  animation:none !important;
  text-shadow:none !important;
}
#wizModal #v4Footer > button[onclick*="openMxOverlay"]::after{
  content:''; position:absolute; bottom:0; left:0; right:0; height:1.5px;
  background:linear-gradient(90deg,transparent,#6366f1,#8b5cf6,#d946ef,#8b5cf6,#6366f1,transparent);
  box-shadow:0 0 10px rgba(139,92,246,0.7);
  opacity:0.85;
}
/* Запиши (зелен v4-foot-save) */
#wizModal #v4Footer > button[onclick*="wizSave"]{
  flex:1.3 !important;
  background:linear-gradient(180deg,rgba(34,197,94,0.18),rgba(22,163,74,0.06)) !important;
  border:1px solid rgba(34,197,94,0.5) !important;
  color:#86efac !important;
  box-shadow:0 0 16px rgba(34,197,94,0.22),inset 0 1px 0 rgba(255,255,255,0.05) !important;
  text-shadow:none !important;
}
#wizModal #v4Footer > button[onclick*="wizSave"]::after{
  content:''; position:absolute; bottom:0; left:0; right:0; height:1.5px;
  background:linear-gradient(90deg,transparent,#22c55e,#4ade80,#22c55e,transparent);
  box-shadow:0 0 10px rgba(34,197,94,0.7);
  opacity:0.85;
}
/* „Избери размер/цвят" (следващ axis) */
#wizModal #v4Footer > button[onclick*="_wizActiveTab"]{
  flex:1.3 !important;
  background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08)) !important;
  border:1px solid rgba(139,92,246,0.5) !important;
  color:#c4b5fd !important;
  box-shadow:0 0 14px rgba(139,92,246,0.22),inset 0 1px 0 rgba(255,255,255,0.05) !important;
  text-shadow:none !important;
}
#wizModal #v4Footer > button[onclick*="_wizActiveTab"]::after{
  content:''; position:absolute; bottom:0; left:0; right:0; height:1.5px;
  background:linear-gradient(90deg,transparent,#6366f1,#8b5cf6,#6366f1,transparent);
  box-shadow:0 0 8px rgba(139,92,246,0.6);
  opacity:0.8;
}

/* ═══ Matrix grid — по-малки клетки + hide помощ при keyboard ═══ */
#wizModal .v4-mx-cell{
  min-width:78px !important;
  padding:4px 3px !important;
}
#wizModal .v4-mx-head, #wizModal .v4-mx-rowh{
  min-width:78px !important;
  font-size:10px !important;
  padding:6px 3px !important;
}
#wizModal .v4-cell-input{width:46px !important; height:30px !important; font-size:12px !important}
#wizModal .v4-mx-sub{font-size:9.5px !important; line-height:1.35}
/* Когато има focus на клетка → скрий sub text */
#wizModal .v4-matrix-ov.open:has(.v4-cell-input:focus) .v4-mx-sub,
#wizModal .v4-matrix-ov.open:has(.v4-cell-input:focus) .v4-mx-quick{
  display:none !important;
}
/* Matrix footer buttons в v4 стил */
#wizModal .v4-mx-bottom button,
#wizModal .v4-mx-bottom .abtn{
  height:42px !important;
  border-radius:12px !important;
  font-size:12px !important;
  font-weight:700 !important;
  letter-spacing:0.02em !important;
}
/* S76.4 end */

/* ═══ S79A1 NEON SECTIONS CSS START ═══ */
/* .glass.q1–.q6 — design-kit/components.css */

/* ── Q-head (section header) ── */
.q-head { display: flex; align-items: center; gap: 10px; padding: 16px 4px 10px; }
.q-badge {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 900; color: white;
    flex-shrink: 0;
}
.q-head.q1 .q-badge { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 0 16px rgba(239,68,68,.55), inset 0 1px 0 rgba(255,255,255,.2); }
.q-head.q2 .q-badge { background: linear-gradient(135deg, #a855f7, #7e22ce); box-shadow: 0 0 16px rgba(168,85,247,.55), inset 0 1px 0 rgba(255,255,255,.2); }
.q-head.q3 .q-badge { background: linear-gradient(135deg, #22c55e, #15803d); box-shadow: 0 0 16px rgba(34,197,94,.55), inset 0 1px 0 rgba(255,255,255,.2); }
.q-head.q4 .q-badge { background: linear-gradient(135deg, #14b8a6, #0d9488); box-shadow: 0 0 16px rgba(20,184,166,.55), inset 0 1px 0 rgba(255,255,255,.2); }
.q-head.q5 .q-badge { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 0 16px rgba(245,158,11,.55), inset 0 1px 0 rgba(255,255,255,.2); }
.q-head.q6 .q-badge { background: linear-gradient(135deg, #64748b, #475569); box-shadow: 0 0 12px rgba(100,116,139,.4), inset 0 1px 0 rgba(255,255,255,.15); }

.q-ttl { flex: 1; min-width: 0; }
.q-nm { font-size: 13px; font-weight: 800; letter-spacing: -0.01em; }
.q-nm.q1 { color: #fca5a5; text-shadow: 0 0 10px rgba(239,68,68,.35); }
.q-nm.q2 { color: #d8b4fe; text-shadow: 0 0 10px rgba(192,132,252,.35); }
.q-nm.q3 { color: #86efac; text-shadow: 0 0 10px rgba(34,197,94,.35); }
.q-nm.q4 { color: #5eead4; text-shadow: 0 0 10px rgba(45,212,191,.35); }
.q-nm.q5 { color: #fcd34d; text-shadow: 0 0 10px rgba(251,191,36,.35); }
.q-nm.q6 { color: rgba(255,255,255,.72); }
.q-sub { font-size: 9.5px; color: var(--text-muted); margin-top: 2px; letter-spacing: 0.02em; font-weight: 600; }

.q-total {
    padding: 5px 11px; border-radius: 100px;
    font-size: 11px; font-weight: 800;
    white-space: nowrap; flex-shrink: 0;
    letter-spacing: 0.01em;
}
.q-total.q1 { background: linear-gradient(135deg, rgba(239,68,68,.18), rgba(239,68,68,.08)); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; box-shadow: 0 0 14px rgba(239,68,68,.25), inset 0 1px 0 rgba(255,255,255,.05); }
.q-total.q2 { background: linear-gradient(135deg, rgba(168,85,247,.18), rgba(168,85,247,.08)); border: 1px solid rgba(168,85,247,.35); color: #d8b4fe; box-shadow: 0 0 14px rgba(168,85,247,.25), inset 0 1px 0 rgba(255,255,255,.05); }
.q-total.q3 { background: linear-gradient(135deg, rgba(34,197,94,.18), rgba(34,197,94,.08)); border: 1px solid rgba(34,197,94,.35); color: #86efac; box-shadow: 0 0 14px rgba(34,197,94,.25), inset 0 1px 0 rgba(255,255,255,.05); }
.q-total.q4 { background: linear-gradient(135deg, rgba(20,184,166,.18), rgba(20,184,166,.08)); border: 1px solid rgba(20,184,166,.35); color: #5eead4; box-shadow: 0 0 14px rgba(20,184,166,.25), inset 0 1px 0 rgba(255,255,255,.05); }
.q-total.q5 { background: linear-gradient(135deg, rgba(245,158,11,.18), rgba(245,158,11,.08)); border: 1px solid rgba(245,158,11,.35); color: #fcd34d; box-shadow: 0 0 14px rgba(245,158,11,.25), inset 0 1px 0 rgba(255,255,255,.05); }
.q-total.q6 { background: rgba(100,116,139,.1); border: 1px solid rgba(100,116,139,.25); color: rgba(255,255,255,.75); }

/* ── Horizontal scroll container ── */
.h-scroll {
    display: flex; gap: 8px;
    overflow-x: auto; overflow-y: hidden;
    padding: 0 12px 18px;
    margin: 0 -12px;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.h-scroll::-webkit-scrollbar { display: none; }

/* ── Article card in h-scroll ── */
.art {
    width: 162px; flex-shrink: 0;
    padding: 10px; cursor: pointer;
    scroll-snap-align: start;
    transition: transform 0.15s var(--ease, cubic-bezier(0.5,1,0.89,1));
}
.art:active { transform: scale(0.98); }
.art-photo {
    width: 100%; aspect-ratio: 1;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(99,102,241,.06), rgba(139,92,246,.02));
    border: 1px solid rgba(99,102,241,.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 44px; margin-bottom: 8px;
    position: relative; overflow: hidden;
    z-index: 5;
}
.art-photo .tag {
    position: absolute; top: 6px; right: 6px;
    padding: 2px 7px; border-radius: 100px;
    font-size: 9px; font-weight: 800; letter-spacing: 0.02em;
    }
.art-photo .tag.bad    { background: rgba(239,68,68,.22); border: 1px solid rgba(239,68,68,.45); color: #fca5a5; box-shadow: 0 0 10px rgba(239,68,68,.3); }
.art-photo .tag.good   { background: rgba(34,197,94,.22); border: 1px solid rgba(34,197,94,.45); color: #86efac; box-shadow: 0 0 10px rgba(34,197,94,.3); }
.art-photo .tag.hot    { background: rgba(245,158,11,.22); border: 1px solid rgba(245,158,11,.45); color: #fcd34d; box-shadow: 0 0 10px rgba(245,158,11,.3); }
.art-photo .tag.teal   { background: rgba(20,184,166,.22); border: 1px solid rgba(20,184,166,.45); color: #5eead4; box-shadow: 0 0 10px rgba(20,184,166,.3); }
.art-photo .tag.dim    { background: rgba(100,116,139,.22); border: 1px solid rgba(100,116,139,.45); color: rgba(255,255,255,.7); }
.art-photo .tag.violet { background: rgba(168,85,247,.22); border: 1px solid rgba(168,85,247,.45); color: #d8b4fe; box-shadow: 0 0 10px rgba(168,85,247,.3); }

.art-nm {
    font-size: 11px; font-weight: 700; line-height: 1.3;
    margin-bottom: 6px; color: var(--text-primary, #f1f5f9);
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
    position: relative; z-index: 5;
}
.art-bot { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; position: relative; z-index: 5; }
.art-prc { font-size: 11px; font-weight: 800; color: var(--text-primary, #f1f5f9); }
.art-stk { font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 6px; }
.art-stk.ok     { background: rgba(34,197,94,.1); color: #86efac; }
.art-stk.warn   { background: rgba(245,158,11,.1); color: #fbbf24; }
.art-stk.danger { background: rgba(239,68,68,.1); color: #fca5a5; }

.art-ctx {
    font-size: 9.5px; line-height: 1.35;
    padding-top: 6px;
    border-top: 1px dashed rgba(99,102,241,.18);
    color: var(--text-secondary, rgba(255,255,255,.6));
    position: relative; z-index: 5;
}
.art-ctx b { font-weight: 800; }
.art-ctx.q1 b { color: #fca5a5; text-shadow: 0 0 8px rgba(239,68,68,.3); }
.art-ctx.q2 b { color: #d8b4fe; text-shadow: 0 0 8px rgba(192,132,252,.3); }
.art-ctx.q3 b { color: #86efac; text-shadow: 0 0 8px rgba(34,197,94,.3); }
.art-ctx.q4 b { color: #5eead4; text-shadow: 0 0 8px rgba(45,212,191,.3); }
.art-ctx.q5 b { color: #fcd34d; text-shadow: 0 0 8px rgba(251,191,36,.3); }
.art-ctx.q6 b { color: rgba(255,255,255,.9); }

/* Empty state card */
.art.empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 30px 10px; cursor: default;
}
.art.empty .empty-ico { margin-bottom: 8px; opacity: 0.4; font-size: 28px; }
.art.empty .empty-txt { font-size: 11px; font-weight: 700; color: var(--text-secondary, rgba(255,255,255,.6)); }
.art.empty .empty-sub { font-size: 9px; color: var(--text-muted, rgba(255,255,255,.4)); margin-top: 4px; }

/* Skeleton loading */
@keyframes skelShimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
.art.skeleton { pointer-events: none; cursor: default; }
.skel-photo, .skel-line, .skel-ctx {
    background: linear-gradient(90deg, rgba(255,255,255,.03) 25%, rgba(255,255,255,.08) 50%, rgba(255,255,255,.03) 75%);
    background-size: 200% 100%;
    animation: skelShimmer 1.5s infinite;
    border-radius: 8px;
}
.skel-photo { width: 100%; aspect-ratio: 1; margin-bottom: 8px; }
.skel-line { height: 10px; margin-bottom: 6px; }
.skel-line.w80 { width: 80%; }
.skel-line.w50 { width: 50%; }
.skel-ctx { height: 18px; margin-top: 8px; }

/* ── Ask AI FAB (floating action button) ── */
.ask-ai {
    position: fixed; bottom: 84px; left: 50%;
    transform: translateX(-50%);
    width: 56px; height: 56px; border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, hsl(255 70% 55%), hsl(222 70% 50%));
    border: none;
    box-shadow:
        0 0 28px hsl(255 70% 50% / .55),
        0 8px 24px rgba(0,0,0,.4),
        inset 0 1px 0 rgba(255,255,255,.25);
    z-index: 45;
}
.ask-ai::before, .ask-ai::after {
    content: ''; position: absolute; inset: -5px;
    border-radius: 50%;
    border: 2px solid hsl(255 70% 60% / .4);
    animation: aiRing 2.5s ease-out infinite;
    pointer-events: none;
}
.ask-ai::after { animation-delay: 1.25s; }
@keyframes aiRing {
    0% { transform: scale(.9); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}
.ask-ai svg {
    width: 22px; height: 22px; stroke: white;
    stroke-width: 2; fill: none;
    stroke-linecap: round; stroke-linejoin: round;
    filter: drop-shadow(0 0 6px rgba(255,255,255,.4));
}

/* Section divider */
.q-sections-wrap { margin-top: 6px; }

/* View all link at the end */
.view-all-link {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px; margin: 10px 4px 0;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(99,102,241,.1), rgba(139,92,246,.05));
    border: 1px solid rgba(99,102,241,.2);
    font-size: 12px; font-weight: 700;
    color: #a5b4fc; cursor: pointer;
    transition: all 0.2s;
}
.view-all-link:hover { filter: brightness(1.15); transform: translateY(-1px); }
.view-all-link svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }

/* ═══ S79A1 NEON SECTIONS CSS END ═══ */



/* ═══ S79 A1.7 — SCRHOME (tokens / .glass / hue variants → design-kit) ═══ */
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{min-height:100vh;overflow-x:hidden;-webkit-user-select:none;user-select:none}
input,button{font-family:inherit}
input{-webkit-user-select:text;user-select:text}
body{padding-bottom:130px;position:relative}
.app{position:relative;z-index:2;max-width:480px;margin:0 auto;padding:12px}

.header{display:flex;align-items:center;gap:6px;padding:max(4px,calc(env(safe-area-inset-top,0px) + 4px)) 2px 10px;min-height:36px}
.h-menu,.h-icon{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);color:var(--text-secondary);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.h-menu svg,.h-icon svg{width:13px;height:13px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.h-icon.h-theme,.h-icon.h-print{position:relative;transition:color .2s,border-color .2s,background .2s}
.h-icon.h-theme:hover,.h-icon.h-print:hover{color:var(--indigo-300,#a5b4fc);border-color:var(--border-glow,rgba(99,102,241,.40))}
.h-icon.h-print::after{content:'';position:absolute;top:1px;right:1px;width:6px;height:6px;border-radius:50%;background:rgba(148,163,184,.7);border:1.5px solid var(--bg-main,#030712)}
.h-icon.h-print.paired{color:#86efac;border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.08)}
.h-icon.h-print.paired::after{background:#22c55e;box-shadow:0 0 6px #22c55e}
.h-icon.h-print.error{color:#fca5a5;border-color:rgba(239,68,68,.40)}
.h-icon.h-print.error::after{background:#ef4444;box-shadow:0 0 6px #ef4444}
.brand{font-size:10.5px;font-weight:900;letter-spacing:.12em;color:hsl(var(--hue1) 50% 70%);text-shadow:0 0 10px hsl(var(--hue1) 60% 50% / .3);white-space:nowrap}
.store-switch{display:flex;align-items:center;gap:4px;padding:4px 8px;border-radius:100px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);font-size:10px;font-weight:700;color:var(--text-secondary)}
.store-switch svg{width:10px;height:10px;stroke:currentColor;stroke-width:2;fill:none}
.plan-tag{font-size:8.5px;font-weight:900;letter-spacing:.08em;padding:3px 6px;border-radius:6px;background:hsl(var(--hue1) 40% 20% / .5);border:1px solid hsl(var(--hue1) 50% 35% / .4);color:hsl(var(--hue1) 60% 78%)}
.h-spacer{flex:1}

.title-row{display:flex;align-items:baseline;gap:8px;padding:6px 4px 10px}
.title-main{font-size:20px;font-weight:900;letter-spacing:-.02em;background:linear-gradient(135deg,#fff 30%,hsl(var(--hue1) 60% 82%));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.title-sub{font-size:11px;font-weight:700;color:var(--text-muted);letter-spacing:.02em}

.search-wrap{display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:14px;background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.05);margin-bottom:10px}
.search-wrap > svg{width:14px;height:14px;stroke:var(--text-muted);stroke-width:2;fill:none;flex-shrink:0}
.search-wrap input{flex:1;background:transparent;border:none;outline:none;color:white;font-size:12px;font-weight:500;min-width:0}
.search-wrap input::placeholder{color:var(--text-muted)}
.s-btn{width:28px;height:28px;border-radius:8px;background:hsl(var(--hue1) 40% 22% / .6);border:1px solid hsl(var(--hue1) 50% 40% / .5);color:hsl(var(--hue1) 60% 85%);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative}
.s-btn svg{width:13px;height:13px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.s-btn.mic{background:hsl(340 40% 22% / .6);border-color:hsl(340 50% 40% / .5);color:hsl(340 60% 85%)}
.s-btn .dot{position:absolute;top:-3px;right:-3px;background:hsl(var(--hue1) 70% 50%);color:#001;font-size:8px;font-weight:900;width:14px;height:14px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #08090d}

.add-card{display:flex;align-items:stretch;margin-bottom:16px;padding:0;--radius:16px;overflow:hidden}
.add-main{flex:1;display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;position:relative;z-index:5}
.add-ico{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,hsl(var(--hue1) 60% 45%),hsl(var(--hue2) 70% 38%));border:1px solid hsl(var(--hue1) 60% 55%);color:white;display:flex;align-items:center;justify-content:center;box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / .45),inset 0 1px 0 rgba(255,255,255,.2);flex-shrink:0}
.add-ico svg{width:16px;height:16px;stroke:white;stroke-width:2.8;fill:none;stroke-linecap:round}
.add-txt{flex:1}
.add-title{font-size:13.5px;font-weight:800;color:white}
.add-hint{font-size:10px;font-weight:600;color:var(--text-muted);margin-top:2px}
.add-modes{display:flex;gap:4px;padding:10px 12px 10px 4px;align-items:center}
.add-mode{width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);color:var(--text-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;z-index:5}
.add-mode.voice{background:hsl(340 40% 22% / .5);border-color:hsl(340 50% 38% / .4);color:hsl(340 60% 85%)}
.add-mode svg{width:15px;height:15px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round}

/* QUESTION HEAD */
.q-head{display:flex;align-items:center;gap:10px;padding:20px 4px 12px}
.q-badge{display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;font-size:11px;font-weight:900;flex-shrink:0;letter-spacing:-.02em}
.q-head.q1 .q-badge{background:linear-gradient(135deg,hsl(0 60% 42%),hsl(340 60% 38%));color:#fff;border:1px solid hsl(0 60% 55%);box-shadow:0 0 10px hsl(0 70% 45% / .4)}
.q-head.q2 .q-badge{background:linear-gradient(135deg,hsl(280 55% 45%),hsl(260 55% 40%));color:#fff;border:1px solid hsl(280 55% 60%);box-shadow:0 0 10px hsl(280 60% 50% / .4)}
.q-head.q3 .q-badge{background:linear-gradient(135deg,hsl(145 60% 38%),hsl(165 60% 36%));color:#fff;border:1px solid hsl(145 60% 52%);box-shadow:0 0 10px hsl(145 60% 45% / .4)}
.q-head.q4 .q-badge{background:linear-gradient(135deg,hsl(175 60% 36%),hsl(195 60% 38%));color:#fff;border:1px solid hsl(175 60% 52%);box-shadow:0 0 10px hsl(175 60% 45% / .4)}
.q-head.q5 .q-badge{background:linear-gradient(135deg,hsl(38 70% 45%),hsl(28 70% 42%));color:#fff;border:1px solid hsl(38 70% 58%);box-shadow:0 0 10px hsl(38 70% 50% / .4)}
.q-head.q6 .q-badge{background:linear-gradient(135deg,hsl(220 10% 30%),hsl(230 10% 25%));color:rgba(255,255,255,.85);border:1px solid hsl(220 10% 45%);box-shadow:0 0 8px rgba(0,0,0,.4)}
.q-ttl{flex:1;min-width:0}
.q-nm{font-size:13px;font-weight:900;letter-spacing:-.01em;line-height:1.2}
.q-nm.q1{color:#fca5a5;text-shadow:0 0 10px rgba(239,68,68,.3)}
.q-nm.q2{color:#d8b4fe;text-shadow:0 0 10px rgba(192,132,252,.3)}
.q-nm.q3{color:#86efac;text-shadow:0 0 10px rgba(34,197,94,.3)}
.q-nm.q4{color:#5eead4;text-shadow:0 0 10px rgba(45,212,191,.3)}
.q-nm.q5{color:#fcd34d;text-shadow:0 0 10px rgba(251,191,36,.3)}
.q-nm.q6{color:rgba(255,255,255,.7)}
.q-sub{font-size:10px;font-weight:700;color:var(--text-muted);margin-top:2px;letter-spacing:.01em}
.q-total{font-size:11px;font-weight:900;padding:3px 9px;border-radius:100px;font-variant-numeric:tabular-nums;white-space:nowrap;flex-shrink:0}
.q-total.q1{background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.4)}
.q-total.q2{background:rgba(192,132,252,.15);color:#d8b4fe;border:1px solid rgba(192,132,252,.4)}
.q-total.q3{background:rgba(34,197,94,.15);color:#86efac;border:1px solid rgba(34,197,94,.4)}
.q-total.q4{background:rgba(45,212,191,.15);color:#5eead4;border:1px solid rgba(45,212,191,.4)}
.q-total.q5{background:rgba(251,191,36,.15);color:#fcd34d;border:1px solid rgba(251,191,36,.4)}
.q-total.q6{background:rgba(255,255,255,.06);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.12)}

/* ARTICLE CARDS */
.h-scroll{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;margin:0 -12px 4px;padding:4px 12px 12px;scrollbar-width:none}
.h-scroll::-webkit-scrollbar{display:none}
.art{width:162px;flex-shrink:0;scroll-snap-align:start;padding:10px;--radius:13px;cursor:pointer;display:flex;flex-direction:column}
.art-photo{width:100%;aspect-ratio:1;border-radius:10px;background:linear-gradient(135deg,hsl(var(--hue1) 25% 22%),hsl(var(--hue2) 25% 18%));border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;position:relative;z-index:5;overflow:hidden;color:hsl(var(--hue1) 55% 82%)}
.art-photo svg{width:50px;height:50px;stroke:currentColor;stroke-width:1.5;fill:none;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 0 10px hsl(var(--hue1) 60% 50% / .4))}
.art-photo .tag{position:absolute;top:5px;right:5px;font-size:8px;font-weight:900;letter-spacing:.04em;padding:2px 6px;border-radius:5px;background:rgba(0,0,0,.7)}
.art-photo .tag.bad{color:#fca5a5;border:1px solid rgba(239,68,68,.5);background:rgba(60,0,0,.75)}
.art-photo .tag.good{color:#86efac;border:1px solid rgba(34,197,94,.5);background:rgba(0,40,10,.75)}
.art-photo .tag.teal{color:#5eead4;border:1px solid rgba(45,212,191,.5);background:rgba(0,40,35,.75)}
.art-photo .tag.hot{color:#fcd34d;border:1px solid rgba(251,191,36,.5);background:rgba(50,30,0,.75)}
.art-photo .tag.dim{color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.2);background:rgba(30,30,40,.75)}
.art-photo .tag.violet{color:#d8b4fe;border:1px solid rgba(192,132,252,.5);background:rgba(40,20,60,.75)}
.art-nm{font-size:11px;font-weight:800;color:white;margin-top:8px;line-height:1.25;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;position:relative;z-index:5;min-height:28px}
.art-bot{display:flex;align-items:center;justify-content:space-between;margin-top:5px;position:relative;z-index:5}
.art-prc{font-size:11px;font-weight:900;color:white;font-variant-numeric:tabular-nums;letter-spacing:-.01em}
.art-stk{font-size:9.5px;font-weight:800;font-variant-numeric:tabular-nums}
.art-stk.ok{color:#86efac}
.art-stk.warn{color:#fbbf24}
.art-stk.danger{color:#fca5a5}
.art-ctx{font-size:9.5px;font-weight:800;margin-top:5px;line-height:1.3;letter-spacing:-.003em;position:relative;z-index:5;padding-top:5px;border-top:1px solid rgba(255,255,255,.06)}
.art-ctx.q1{color:#fca5a5}
.art-ctx.q2{color:#d8b4fe}
.art-ctx.q3{color:#86efac}
.art-ctx.q4{color:#5eead4}
.art-ctx.q5{color:#fcd34d}
.art-ctx.q6{color:rgba(255,255,255,.55)}
.art-ctx b{font-weight:900}

/* S88.PRODUCTS.AIBRAIN_WIRE — call-to-action button под art-ctx (mobile 375px optimized) */
.art-action{display:block;width:100%;margin-top:6px;padding:6px 8px;border-radius:8px;font-size:9.5px;font-weight:900;letter-spacing:.01em;text-align:center;background:rgba(255,255,255,.08);color:rgba(255,255,255,.92);border:1px solid rgba(255,255,255,.12);cursor:pointer;position:relative;z-index:6;transition:background .15s ease;font-family:inherit;line-height:1.2}
.art-action:hover{background:rgba(255,255,255,.14)}
.art-action.q1{background:linear-gradient(135deg,rgba(239,68,68,.18),rgba(239,68,68,.08));border-color:rgba(239,68,68,.3);color:#fecaca}
.art-action.q2{background:linear-gradient(135deg,rgba(192,132,252,.18),rgba(192,132,252,.08));border-color:rgba(192,132,252,.3);color:#e9d5ff}
.art-action.q3{background:linear-gradient(135deg,rgba(34,197,94,.18),rgba(34,197,94,.08));border-color:rgba(34,197,94,.3);color:#bbf7d0}
.art-action.q4{background:linear-gradient(135deg,rgba(45,212,191,.18),rgba(45,212,191,.08));border-color:rgba(45,212,191,.3);color:#99f6e4}
.art-action.q5{background:linear-gradient(135deg,rgba(251,191,36,.20),rgba(251,191,36,.08));border-color:rgba(251,191,36,.35);color:#fde68a}
.art-action.q6{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:rgba(255,255,255,.6)}

.view-all{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;margin:18px 0 8px;cursor:pointer;--radius:14px;color:hsl(var(--hue1) 60% 85%);font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.view-all svg{width:13px;height:13px;stroke:currentColor;stroke-width:2.5;fill:none;position:relative;z-index:5}
.view-all span{position:relative;z-index:5}

.bnav{position:fixed;bottom:0;left:0;right:0;max-width:480px;margin:0 auto;display:flex;padding:8px 8px 14px;background:linear-gradient(180deg,hsl(220 25% 6% / .85),hsl(220 25% 4% / .95));border-top:1px solid hsl(var(--hue2) 20% 15% / .5);z-index:40}
.ntab{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:7px 4px 4px;border-radius:12px;background:transparent;border:none;color:var(--text-muted);cursor:pointer;font-family:inherit}
.ntab svg{width:20px;height:20px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round}
.ntab-lbl{font-size:9px;font-weight:700;letter-spacing:.02em}
.ntab.active{color:hsl(var(--hue1) 60% 88%);text-shadow:0 0 8px hsl(var(--hue1) 70% 50% / .5)}
.ntab.active svg{filter:drop-shadow(0 0 6px hsl(var(--hue1) 70% 50% / .6))}


.art-emoji {
    font-size: 48px;
    line-height: 1;
    display: block;
    filter: drop-shadow(0 0 12px hsl(var(--hue1) 60% 50% / .55))
            drop-shadow(0 0 4px hsl(var(--hue2) 65% 55% / .4));
    animation: emojiFloat 3s ease-in-out infinite;
    text-shadow: 0 0 20px hsl(var(--hue1) 70% 55% / .5);
}
.q1 .art-emoji { filter: drop-shadow(0 0 12px hsl(0 70% 55% / .6)) drop-shadow(0 0 4px hsl(340 65% 55% / .4)); }
.q2 .art-emoji { filter: drop-shadow(0 0 12px hsl(280 65% 60% / .6)) drop-shadow(0 0 4px hsl(260 65% 55% / .4)); }
.q3 .art-emoji { filter: drop-shadow(0 0 12px hsl(145 65% 50% / .6)) drop-shadow(0 0 4px hsl(165 65% 50% / .4)); }
.q4 .art-emoji { filter: drop-shadow(0 0 12px hsl(175 65% 50% / .6)) drop-shadow(0 0 4px hsl(195 65% 55% / .4)); }
.q5 .art-emoji { filter: drop-shadow(0 0 12px hsl(38 75% 55% / .6)) drop-shadow(0 0 4px hsl(28 70% 50% / .4)); }
.q6 .art-emoji { filter: drop-shadow(0 0 8px hsl(220 20% 60% / .4)); opacity: .7; }
@keyframes emojiFloat {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-2px) scale(1.05); }
}
.art-svg-fallback {
    width: 56px;
    height: 56px;
    color: hsl(var(--hue1) 65% 68%);
    filter: drop-shadow(0 0 14px hsl(var(--hue1) 65% 55% / .65))
            drop-shadow(0 0 4px hsl(var(--hue2) 65% 55% / .4));
    animation: ngPulse 2.8s ease-in-out infinite;
}
.q1 .art-svg-fallback { color: hsl(0 70% 68%); filter: drop-shadow(0 0 14px hsl(0 70% 55% / .65)); }
.q2 .art-svg-fallback { color: hsl(280 65% 72%); filter: drop-shadow(0 0 14px hsl(280 65% 60% / .65)); }
.q3 .art-svg-fallback { color: hsl(145 65% 62%); filter: drop-shadow(0 0 14px hsl(145 65% 50% / .65)); }
.q4 .art-svg-fallback { color: hsl(175 65% 62%); filter: drop-shadow(0 0 14px hsl(175 65% 50% / .65)); }
.q5 .art-svg-fallback { color: hsl(38 75% 66%); filter: drop-shadow(0 0 14px hsl(38 75% 55% / .65)); }
.q6 .art-svg-fallback { color: hsl(220 20% 65%); opacity: .7; filter: drop-shadow(0 0 8px hsl(220 20% 60% / .4)); }
@keyframes ngPulse {
    0%, 100% { transform: scale(1); filter: brightness(1); }
    50% { transform: scale(1.06); filter: brightness(1.15); }
}

/* ═══ S79.FIX.B-HIDDEN-INV-UI: Store Health card ═══ */
.health-sec{margin:14px 12px 0;padding:14px 14px 12px;border:1px solid rgba(20,184,166,0.25);background:linear-gradient(135deg,rgba(20,184,166,0.08),rgba(6,182,212,0.05));border-radius:18px;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.health-sec::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at top right,rgba(20,184,166,0.12),transparent 60%);pointer-events:none}
.health-sec:active{transform:scale(.99)}
.health-sec .mod-prod-health-line{position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(94,234,212,.6),transparent);pointer-events:none}
.health-row{display:flex;align-items:center;gap:10px;position:relative;z-index:1}
.health-dot{width:10px;height:10px;border-radius:50%;background:#64748b;flex-shrink:0;transition:background .3s,box-shadow .3s}
.health-dot.dot-green{background:#22c55e;box-shadow:0 0 10px rgba(34,197,94,.7)}
.health-dot.dot-yellow{background:#eab308;box-shadow:0 0 10px rgba(234,179,8,.7)}
.health-dot.dot-orange{background:#f97316;box-shadow:0 0 10px rgba(249,115,22,.7)}
.health-dot.dot-red{background:#ef4444;box-shadow:0 0 10px rgba(239,68,68,.7)}
.health-info{flex:1;min-width:0}
.health-title{font-size:13px;font-weight:600;color:#5eead4;margin-bottom:2px;letter-spacing:.2px}
.health-meta{font-size:11px;color:rgba(94,234,212,.7);line-height:1.3}
.health-pct{font-size:18px;font-weight:700;color:#5eead4;flex-shrink:0;font-variant-numeric:tabular-nums}
.health-bar{margin-top:10px;height:5px;background:rgba(20,184,166,.12);border-radius:3px;overflow:hidden;position:relative;z-index:1}
.health-fill{height:100%;background:linear-gradient(90deg,#14b8a6,#06b6d4);border-radius:3px;transition:width .8s ease-out;box-shadow:0 0 8px rgba(20,184,166,.5)}
.health-fill.fill-yellow{background:linear-gradient(90deg,#eab308,#ca8a04)}
.health-fill.fill-orange{background:linear-gradient(90deg,#f97316,#ea580c)}
.health-fill.fill-red{background:linear-gradient(90deg,#ef4444,#dc2626)}

/* ═══ S79.FIX.B-HEALTH-OV: Health Detail Overlay (опция В) ═══ */
.health-ov{position:fixed;inset:0;background:rgba(3,7,18,0.78);z-index:9999;display:flex;align-items:flex-end;justify-content:center;opacity:0;transition:opacity .25s}
.health-ov.open{opacity:1}
.health-ov-box{position:relative;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;background:linear-gradient(180deg,rgba(15,15,40,0.95),rgba(8,12,30,0.98));border-top:1px solid rgba(20,184,166,0.35);border-radius:20px 20px 0 0;padding:24px 18px 32px;box-shadow:0 -16px 60px rgba(20,184,166,0.15);transform:translateY(100%);transition:transform .3s cubic-bezier(.16,1,.3,1)}
.health-ov.open .health-ov-box{transform:translateY(0)}
.health-ov-box .mod-prod-health-line{position:absolute;top:0;left:18%;right:18%;height:1px;background:linear-gradient(90deg,transparent,rgba(94,234,212,.7),transparent);pointer-events:none}
.health-ov-close{position:absolute;top:12px;right:12px;width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.25);color:#a5b4fc;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit}
.health-ov-close:active{background:rgba(99,102,241,.25)}
.health-ov-hdr{text-align:center;padding:6px 0 18px}
.health-ov-icon{margin-bottom:8px;display:flex;justify-content:center}
.health-ov-icon svg{width:32px;height:32px}
.hov-act-ic svg{width:22px;height:22px}
.health-ov-close svg{width:14px;height:14px}
.health-ov-score{font-size:42px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:6px}
.health-ov-title{font-size:14px;color:#5eead4;font-weight:600;margin-bottom:4px}
.health-ov-status{font-size:12px;color:rgba(94,234,212,.7);padding:0 16px;line-height:1.4}
.health-ov-bd{padding:14px 8px;border-top:1px solid rgba(20,184,166,.15);border-bottom:1px solid rgba(20,184,166,.15);margin:0 0 16px}
.hov-row{display:flex;align-items:center;gap:8px;margin-top:12px}
.hov-row:first-child{margin-top:0}
.hov-lbl{font-size:11px;color:#5eead4;width:88px;flex-shrink:0;font-weight:600}
.hov-bar{flex:1;height:5px;background:rgba(20,184,166,.12);border-radius:3px;overflow:hidden}
.hov-fill{height:100%;background:linear-gradient(90deg,#14b8a6,#06b6d4);border-radius:3px;box-shadow:0 0 6px rgba(20,184,166,.4);transition:width .8s ease-out}
.hov-val{font-size:12px;color:#5eead4;font-weight:700;min-width:38px;text-align:right;font-variant-numeric:tabular-nums}
.hov-sub{font-size:10px;color:rgba(148,163,184,.6);margin:3px 0 0 96px;line-height:1.3}
.health-ov-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:0 4px 16px}
.hov-meta-cell{text-align:center;padding:10px 6px;background:rgba(15,15,40,.4);border:1px solid rgba(99,102,241,.15);border-radius:12px}
.hov-meta-num{font-size:20px;font-weight:700;color:#e0e7ff;font-variant-numeric:tabular-nums}
.hov-meta-lbl{font-size:10px;color:rgba(165,180,252,.7);margin-top:2px}
.health-ov-actions{display:flex;flex-direction:column;gap:8px;padding:0 2px}
.hov-act{display:flex;align-items:center;gap:12px;padding:14px;background:rgba(15,15,40,.5);border:1px solid rgba(99,102,241,.2);border-radius:14px;cursor:pointer;text-align:left;color:inherit;font-family:inherit;transition:all .15s;min-height:60px}
.hov-act:active{transform:scale(.99);background:rgba(15,15,40,.7)}
.hov-act-primary{border-color:rgba(20,184,166,.4);background:linear-gradient(135deg,rgba(20,184,166,.12),rgba(6,182,212,.08))}
.hov-act-primary:active{background:linear-gradient(135deg,rgba(20,184,166,.18),rgba(6,182,212,.12))}
.hov-act-ic{font-size:22px;width:36px;text-align:center;flex-shrink:0}
.hov-act-txt{flex:1}
.hov-act-ttl{font-size:14px;font-weight:600;color:#e0e7ff;margin-bottom:2px}
.hov-act-hnt{font-size:11px;color:rgba(148,163,184,.7)}

/* S82.CAPACITOR safe-area */
body{padding-bottom:env(safe-area-inset-bottom);}
.bottom-nav,.btm-nav,nav.bottom,[class*="bottom-nav"]{padding-bottom:calc(8px + env(safe-area-inset-bottom)) !important;box-sizing:content-box;}

/* === S81.BUGFIX.V3 — mobile CSS rework (Samsung Z Flip6, Capacitor) === */
/* Bug 1 — qty "+" button cut: flex overflow in narrow viewport */
*,*::before,*::after{box-sizing:border-box}
.wiz-page{max-width:100%;min-width:0}
.wiz-page .fg,.wiz-page .fg > div{min-width:0;max-width:100%}
.wiz-page input[type="number"],.wiz-page input[type="text"],.wiz-page input[type="tel"]{min-width:0;max-width:100%}
.wiz-page button[type="button"]{flex-shrink:0}
/* Bug 3 — horizontal scroll root: html + #app + .app clamp */
html{overflow-x:hidden;max-width:100vw}
.app,#app,.wiz-page{overflow-x:hidden;max-width:100vw}

/* ═══════════════════════════════════════════════
   S88.KP — "Като предния" wizard (DESIGN_LAW §2,§7,§8,§10,§16)
   ═══════════════════════════════════════════════ */
.q-magic{--hue1:280;--hue2:310}
.q-gain{--hue1:145;--hue2:165}
#kpModal{position:fixed;inset:0;z-index:9990;background:#08090d;overflow-y:auto;padding-bottom:calc(20px + env(safe-area-inset-bottom));background-image:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / 0.22) 0%,transparent 60%),radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / 0.22) 0%,transparent 60%),linear-gradient(180deg,#0a0b14 0%,#050609 100%)}
.kp-app{position:relative;z-index:2;max-width:480px;margin:0 auto;width:100%;padding:0 12px 20px}
.kp-header{position:sticky;top:0;z-index:60;display:flex;align-items:center;gap:8px;padding:max(10px,calc(env(safe-area-inset-top,0px) + 10px)) 14px 12px;margin:0 -12px 14px;background:rgba(3,7,18,0.93);border-bottom:1px solid rgba(99,102,241,0.1)}
.kp-back{width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;font-family:inherit;transition:all 0.2s var(--ease)}
.kp-back:hover{color:var(--indigo-300);border-color:rgba(99,102,241,0.4)}
.kp-back svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.kp-title-area{flex:1;min-width:0}
.kp-title{font-size:13px;font-weight:800;color:var(--text-primary);line-height:1.1}
.kp-subtitle{font-size:10px;color:rgba(255,255,255,0.6);margin-top:2px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kp-save{padding:9px 16px;border-radius:100px;background:linear-gradient(135deg,hsl(145,70%,38%),hsl(165,70%,32%));color:hsl(145,95%,96%);font-weight:900;font-size:11px;letter-spacing:0.04em;cursor:pointer;display:flex;align-items:center;gap:5px;border:1.5px solid hsl(145,75%,60%,0.85);box-shadow:0 0 16px hsl(145,75%,55%,0.45),inset 0 1px 0 hsl(145,80%,70%,0.3);text-shadow:0 0 7px hsl(145,90%,70%,0.5);flex-shrink:0;font-family:inherit;transition:transform 0.15s var(--ease)}
.kp-save:active{transform:translateY(1px)}
.kp-save svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.kp-photo-hero{height:200px;margin-bottom:14px;border-radius:22px;background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(139,92,246,0.06));border:1.5px dashed rgba(99,102,241,0.32);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;position:relative;overflow:hidden;transition:all 0.25s var(--ease)}
.kp-photo-hero.has-photo{border-style:solid;padding:0;border-color:rgba(99,102,241,0.6)}
.kp-photo-hero img{width:100%;height:100%;object-fit:cover}
/* S88B.KP: BIBLE 7.2.8.5 v1.3 — overlay pill в долен ляв ъгъл */
.kp-photo-overlay{position:absolute;left:0;bottom:0;padding:10px;pointer-events:none;display:flex}
.kp-photo-overlay-text{font-size:12px;font-weight:700;color:#fff;letter-spacing:0.02em;background:rgba(0,0,0,0.6);padding:6px 10px;border-radius:8px;opacity:0.7}
.kp-ph-icon{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,var(--indigo-500),var(--purple));display:flex;align-items:center;justify-content:center;box-shadow:0 0 32px rgba(99,102,241,0.55),inset 0 1px 0 rgba(255,255,255,0.25);margin-bottom:12px}
.kp-ph-icon svg{width:24px;height:24px;fill:#fff;filter:drop-shadow(0 0 8px rgba(255,255,255,0.6))}
.kp-ph-label{font-size:14px;font-weight:800;color:var(--text-primary)}
.kp-ph-hint{font-size:11px;color:rgba(255,255,255,0.6);margin-top:4px;font-weight:600}
.kp-name-card{padding:14px;margin-bottom:12px}
.kp-label-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.kp-label{font-size:11px;font-weight:800;letter-spacing:0.06em;color:hsl(var(--hue1) 70% 75%);text-transform:uppercase}
.kp-req{color:var(--danger);margin-left:4px}
.kp-voice-mic{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;flex-shrink:0;border:0;box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / 0.4);font-family:inherit}
.kp-voice-mic[disabled]{opacity:0.35;cursor:not-allowed;box-shadow:none}
.kp-voice-mic svg{width:14px;height:14px;fill:#fff}
.kp-input{width:100%;padding:14px;background:rgba(0,0,0,0.4);border:1px solid rgba(99,102,241,0.4);border-radius:12px;color:var(--text-primary);font-size:14px;font-weight:700;outline:none;-webkit-appearance:none;appearance:none;transition:all 0.2s var(--ease);font-family:inherit}
.kp-input::placeholder{color:#6b7280;font-weight:500}
.kp-input:focus{border-color:hsl(var(--hue1) 75% 65%);box-shadow:0 0 0 1px hsl(var(--hue1) 80% 65% / 0.3),0 0 18px hsl(var(--hue1) 70% 55% / 0.35)}
.kp-copied-card{padding:12px 14px;margin-bottom:12px}
.kp-copied-head{display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none}
.kp-copied-ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,hsl(145 60% 28%),hsl(145 50% 20%));border:1px solid hsl(145 70% 50% / 0.45);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 0 12px hsl(145 70% 50% / 0.25)}
.kp-copied-ico svg{width:13px;height:13px;stroke:#86efac;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 0 4px hsl(145 70% 60%))}
.kp-copied-text{flex:1;min-width:0}
.kp-copied-t1{font-size:12px;font-weight:800;color:var(--text-primary)}
.kp-copied-t2{font-size:10px;color:rgba(255,255,255,0.6);margin-top:1px;font-weight:600}
.kp-chev{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;transition:transform 0.25s var(--ease);flex-shrink:0}
.kp-copied-head.expanded .kp-chev{transform:rotate(180deg)}
.kp-chev svg{width:11px;height:11px;stroke:rgba(255,255,255,0.6);stroke-width:2.5;fill:none;stroke-linecap:round;stroke-linejoin:round}
.kp-copied-fields{display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06)}
.kp-copied-fields.expanded{display:block}
.kp-cf-row{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,0.05)}
.kp-cf-row:last-child{border-bottom:0}
.kp-cf-label{font-size:11px;color:rgba(255,255,255,0.6);min-width:92px;font-weight:600}
.kp-cf-value{flex:1;font-size:13px;font-weight:700;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.kp-cf-value.computed{color:var(--success)}
.kp-cf-input{flex:1;padding:6px 8px;background:rgba(0,0,0,0.4);border:1px solid rgba(99,102,241,0.4);border-radius:8px;color:var(--text-primary);font-size:13px;font-weight:700;outline:none;font-family:inherit;min-width:0}
.kp-cf-auto{font-size:10px;color:#6b7280;font-weight:600}
.kp-cf-edit{width:26px;height:26px;border-radius:7px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;padding:0;font-family:inherit}
.kp-cf-edit svg{width:11px;height:11px;stroke:var(--indigo-300);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.kp-section-head{display:flex;align-items:center;gap:10px;padding:0 4px;margin:18px 0 10px}
.kp-section-ico{width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,var(--indigo-600),var(--purple));display:flex;align-items:center;justify-content:center;box-shadow:0 0 18px rgba(99,102,241,0.35);flex-shrink:0}
.kp-section-ico svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.kp-section-text{flex:1;min-width:0}
.kp-section-title{font-size:13px;font-weight:800;letter-spacing:0.02em;color:var(--text-primary)}
.kp-section-sub{font-size:10px;color:rgba(255,255,255,0.6);margin-top:1px;font-weight:600}
.kp-section-pill{padding:4px 10px;border-radius:100px;font-size:10px;font-weight:800;background:rgba(99,102,241,0.12);color:var(--indigo-300);border:0.5px solid rgba(99,102,241,0.28);flex-shrink:0;letter-spacing:0.05em}
.kp-color-card{margin-bottom:12px;padding:0}
.kp-cc-head{display:flex;align-items:center;gap:11px;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,0.06)}
.kp-swatch{width:30px;height:30px;border-radius:50%;border:1.5px solid hsl(220,30%,30%,0.4);flex-shrink:0;box-shadow:0 0 8px var(--swatch-glow,currentColor),inset 0 1px 0 rgba(255,255,255,0.18)}
.kp-cc-name{flex:1;font-size:14px;font-weight:800;letter-spacing:-0.01em;color:var(--text-primary)}
.kp-cc-total{padding:3px 8px;border-radius:100px;font-size:10px;font-weight:900;letter-spacing:0.04em;background:rgba(129,140,248,0.12);border:1px solid rgba(129,140,248,0.28);color:var(--indigo-300);font-variant-numeric:tabular-nums}
.kp-cc-remove{width:28px;height:28px;border-radius:8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.22);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;padding:0;font-family:inherit}
.kp-cc-remove svg{width:13px;height:13px;stroke:#fca5a5;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.kp-cc-body{padding:10px 14px}
.kp-size-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,0.04)}
.kp-size-row:last-of-type{border-bottom:0;padding-bottom:4px}
.kp-sz-label{min-width:36px;padding:5px 10px;border-radius:7px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);font-size:12px;font-weight:800;text-align:center;flex-shrink:0;color:var(--text-primary)}
.kp-sz-stepper{flex:1;display:flex;align-items:center;justify-content:center;gap:8px}
.kp-sz-btn{width:30px;height:30px;border-radius:8px;background:hsl(var(--hue1) 60% 35% / 0.25);border:1px solid hsl(var(--hue1) 60% 50% / 0.4);display:flex;align-items:center;justify-content:center;cursor:pointer;color:hsl(var(--hue1) 85% 85%);font-weight:800;font-size:16px;user-select:none;transition:transform 0.15s var(--ease);font-family:inherit;padding:0}
.kp-sz-btn:active{transform:scale(0.92)}
.kp-sz-qty{min-width:44px;text-align:center;font-size:16px;font-weight:800;font-variant-numeric:tabular-nums;color:var(--text-primary)}
.kp-add-color{margin:4px 0 14px;padding:14px;background:linear-gradient(135deg,hsl(280 60% 25% / 0.3),hsl(310 60% 25% / 0.2));border:1.5px dashed hsl(280 70% 55% / 0.5);border-radius:22px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:all 0.25s var(--ease)}
.kp-ac-icon{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,hsl(280 70% 55%),hsl(310 70% 50%));display:flex;align-items:center;justify-content:center;box-shadow:0 0 18px hsl(280 70% 50% / 0.45),inset 0 1px 0 rgba(255,255,255,0.15);flex-shrink:0}
.kp-ac-icon svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.kp-ac-text{flex:1}
.kp-ac-text .kp-t1{font-size:13px;font-weight:800;color:var(--text-primary)}
.kp-ac-text .kp-t2{font-size:10px;color:rgba(255,255,255,0.6);margin-top:2px;font-weight:600}
.kp-copy-qty-row{display:flex;align-items:center;gap:10px;padding:10px 14px;margin-top:8px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.2);border-radius:12px;cursor:pointer;user-select:none}
.kp-copy-qty-row input[type="checkbox"]{width:18px;height:18px;accent-color:hsl(255 70% 60%);cursor:pointer;flex-shrink:0}
.kp-copy-qty-row .kp-cq-label{font-size:12px;font-weight:700;color:var(--text-primary);flex:1}
.kp-copy-qty-row .kp-cq-hint{font-size:11px;font-weight:600;color:rgba(255,255,255,0.55);font-variant-numeric:tabular-nums}
.kp-bottom-bar{display:flex;gap:10px;margin-top:18px;padding:12px 0 calc(12px + env(safe-area-inset-bottom));position:sticky;bottom:0;z-index:50;background:linear-gradient(180deg,rgba(8,9,13,0) 0%,rgba(8,9,13,0.95) 30%,rgba(8,9,13,1) 100%)}
.kp-btn-print,.kp-btn-ai{flex:1;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;gap:7px;font-size:12px;font-weight:800;letter-spacing:0.04em;cursor:pointer;padding:0 14px;font-family:inherit;transition:transform 0.15s var(--ease)}
.kp-btn-print:active,.kp-btn-ai:active{transform:translateY(1px)}
.kp-btn-print{background:linear-gradient(135deg,hsl(255,65%,38%),hsl(222,65%,32%));color:hsl(255,95%,96%);border:1.5px solid hsl(255,75%,60%,0.85);box-shadow:0 0 16px hsl(255,75%,55%,0.4),inset 0 1px 0 hsl(255,80%,70%,0.3);text-shadow:0 0 7px hsl(255,90%,70%,0.4)}
.kp-btn-ai{background:linear-gradient(135deg,hsl(280,70%,42%),hsl(310,70%,36%));color:hsl(280,95%,96%);border:1.5px solid hsl(280,75%,62%,0.9);box-shadow:0 0 18px hsl(280,75%,55%,0.5),inset 0 1px 0 hsl(280,80%,70%,0.3);text-shadow:0 0 7px hsl(280,90%,72%,0.5)}
.kp-btn-print svg,.kp-btn-ai svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.kp-btn-ai svg.kp-ai-spark{fill:currentColor;stroke:none}
@keyframes kpCardin{0%{opacity:0;transform:translateY(30px) scale(0.85)}70%{transform:translateY(-4px) scale(1.02)}100%{opacity:1;transform:translateY(0) scale(1)}}
.kp-cardin{animation:kpCardin 0.95s cubic-bezier(0.34,1.8,0.64,1) both}
.kp-cardin:nth-child(1){animation-delay:0s}
.kp-cardin:nth-child(2){animation-delay:0.05s}
.kp-cardin:nth-child(3){animation-delay:0.1s}
.kp-cardin:nth-child(4){animation-delay:0.15s}
.kp-cardin:nth-child(5){animation-delay:0.2s}
.kp-cardin:nth-child(6){animation-delay:0.25s}
.kp-swatch.c-black{background:linear-gradient(135deg,#2a2a2e,#0f0f12);--swatch-glow:hsl(220,50%,50%)}
.kp-swatch.c-white{background:linear-gradient(135deg,#f8f8fb,#d8d8de);--swatch-glow:hsl(220,30%,80%)}
.kp-swatch.c-blue{background:linear-gradient(135deg,#3b82f6,#1d4ed8);--swatch-glow:hsl(220,90%,60%)}
.kp-swatch.c-red{background:linear-gradient(135deg,#ef4444,#b91c1c);--swatch-glow:hsl(0,90%,55%)}
.kp-swatch.c-beige{background:linear-gradient(135deg,#e8c39e,#c08e5d);--swatch-glow:hsl(35,70%,60%)}
.kp-swatch.c-pink{background:linear-gradient(135deg,#ec4899,#be185d);--swatch-glow:hsl(335,85%,60%)}
.kp-swatch.c-green{background:linear-gradient(135deg,#22c55e,#15803d);--swatch-glow:hsl(145,80%,50%)}
.kp-swatch.c-yellow{background:linear-gradient(135deg,#fbbf24,#d97706);--swatch-glow:hsl(45,95%,55%)}
.kp-swatch.c-violet{background:linear-gradient(135deg,#a855f7,#7c3aed);--swatch-glow:hsl(270,80%,60%)}
.kp-swatch.c-gray{background:linear-gradient(135deg,#9ca3af,#4b5563);--swatch-glow:hsl(220,10%,60%)}
.kp-swatch.c-brown{background:linear-gradient(135deg,#92400e,#451a03);--swatch-glow:hsl(25,70%,30%)}
</style>
<?php require __DIR__ . '/includes/capacitor-head.php'; ?>
<script src="js/capacitor-printer.js"></script>
<link rel="stylesheet" href="css/aibrain-modals.css">
</head>
<body class="has-rms-shell mode-<?= ($user_role === 'seller') ? 'simple' : 'detailed' ?>">

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

    <!-- ═══ SCREEN: HOME ═══ -->
    <section id="scrHome" class="screen-section active">
<!-- ═══ S79 A1.7 — SCRHOME START ═══ -->
<div class="app">

    <?php include __DIR__ . '/design-kit/partial-header.html'; ?>

    <div class="title-row">
        <div class="title-main">Артикули</div>
        <div class="title-sub" id="hTitleCount">·</div>
        <button class="store-switch" onclick="if(typeof openStoreSwitcher==='function')openStoreSwitcher()" style="margin-left:auto">Магазин 1<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
    </div>

    <div class="search-wrap">
        <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="hSearchInp" placeholder="Търси по име, код или баркод..." oninput="onLiveSearchHome(this.value)" autocomplete="off">
        <button class="s-btn"><svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg><span class="dot">3</span></button>
        <button class="s-btn mic" onclick="openVoiceSearch()"><svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg></button>
    </div>
    <!-- S79.FIX Bug #2: Search autocomplete dropdown -->
    <div id="hSearchDD" style="display:none;margin:0 12px 8px;border-radius:14px;background:rgba(8,8,24,0.97);backdrop-filter:blur(16px);border:1px solid rgba(99,102,241,0.25);box-shadow:0 8px 32px rgba(0,0,0,0.5);max-height:60vh;overflow-y:auto;-webkit-overflow-scrolling:touch"></div>


    <div class="glass add-card">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="add-main" onclick="openManualWizard()" style="cursor:pointer">
            <div class="add-ico"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
            <div class="add-txt"><div class="add-title">Добави артикул</div><div class="add-hint">Избери начин →</div></div>
        </div>
        <div class="add-modes">
            <!-- S92.PRODUCTS.D9_CLEANUP: махнат микрофон (безсмислен) + молив (дублира главния "Добави артикул") бутони. ⋯ → явен "Като предния" бутон с директен handler. -->
            <button class="add-mode-kp" onclick="event.stopPropagation();openLikePreviousWizardS88()" style="height:36px;padding:0 14px;border-radius:10px;background:linear-gradient(135deg,rgba(99,102,241,0.18),rgba(67,56,202,0.06));border:1px solid rgba(139,92,246,0.45);color:#e2e8f0;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;letter-spacing:0.02em;white-space:nowrap;font-family:inherit">📋 Като предния</button>
        </div>
    </div>

<!-- ═══ S79.FIX.B-HIDDEN-INV-UI: Здраве на склада (Вариант B) ═══ -->
    <div class="health-sec" onclick="openStoreHealthDetail()">
        <span class="mod-prod-health-line"></span>
        <div class="health-row">
            <div class="health-dot" id="healthDot"></div>
            <div class="health-info">
                <div class="health-title">Здраве на склада</div>
                <div class="health-meta" id="healthMeta">Изчислява се...</div>
            </div>
            <div class="health-pct" id="healthPct">—</div>
        </div>
        <div class="health-bar"><div class="health-fill" id="healthFill" style="width:0%"></div></div>
    </div>

        <!-- ═══ 1. КАКВО ГУБИШ ═══ -->
    <div class="q-head q1" onclick="goScreenWithHistory('products',{filter:'zero_stock'})" style="cursor:pointer">
        <div class="q-badge">1</div>
        <div class="q-ttl">
            <div class="q-nm q1">Какво губиш</div>
            <div class="q-sub">Артикули с продажби без наличност</div>
        </div>
        <div class="q-total q1">−340 лв/седм</div>
    </div>
    <div class="h-scroll">
        <div class="glass sm q1 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M2 17h3l2-3h7l3 3h5v-2l-3-2h-2l-3-3H9l-2 3H4l-2 2z"/><line x1="2" y1="20" x2="22" y2="20"/></svg><span class="tag bad">0 БР</span></div>
            <div class="art-nm">Nike Air Max 42 черни</div>
            <div class="art-bot"><div class="art-prc">120 лв</div><div class="art-stk danger">0 бр</div></div>
            <div class="art-ctx q1">3 търсения /7д · <b>~360 лв profit/мес пропуснат</b></div>
        </div>
        <div class="glass sm q1 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M9 2h6l1 3-2 2v1l4 14H6l4-14V7L8 5z"/></svg><span class="tag bad">0 БР</span></div>
            <div class="art-nm">Рокля Zara черна S</div>
            <div class="art-bot"><div class="art-prc">89 лв</div><div class="art-stk danger">0 бр</div></div>
            <div class="art-ctx q1">2 търсения /7д · <b>~178 лв profit/мес</b></div>
        </div>
        <div class="glass sm q1 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.47a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.47a2 2 0 00-1.34-2.23z"/></svg><span class="tag bad">1 БР</span></div>
            <div class="art-nm">Тениска H&M бяла M</div>
            <div class="art-bot"><div class="art-prc">24 лв</div><div class="art-stk warn">1 бр</div></div>
            <div class="art-ctx q1">9 прод/30д · <b>свършва утре</b></div>
        </div>
        <div class="glass sm q1 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M7 2h10l1 5-1 3 2 12h-5l-2-10-2 10H5l2-12-1-3z"/></svg><span class="tag bad">1 БР</span></div>
            <div class="art-nm">Джинси Levi's 501 W32</div>
            <div class="art-bot"><div class="art-prc">180 лв</div><div class="art-stk warn">1 бр</div></div>
            <div class="art-ctx q1">14 прод/30д · <b>под минимум</b></div>
        </div>
    </div>

    <!-- ═══ 2. ОТ КАКВО ГУБИШ ═══ -->
    <div class="q-head q2" onclick="goScreenWithHistory('products',{filter:'at_loss'})" style="cursor:pointer">
        <div class="q-badge">2</div>
        <div class="q-ttl">
            <div class="q-nm q2">От какво губиш</div>
            <div class="q-sub">Артикули които изяждат profit</div>
        </div>
        <div class="q-total q2">−180 лв profit</div>
    </div>
    <div class="h-scroll">
        <div class="glass sm q2 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M3 19h4l3-3V8l6-4v8l3 3v3H3z"/></svg><span class="tag violet">−8%</span></div>
            <div class="art-nm">Обувки Geox 38</div>
            <div class="art-bot"><div class="art-prc">65 лв</div><div class="art-stk ok">2 бр</div></div>
            <div class="art-ctx q2">Доставна 70 лв · <b>продаваш на загуба</b></div>
        </div>
        <div class="glass sm q2 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M8 2l-5 4 2 4 2-1v13h10V9l2 1 2-4-5-4-2 3h-4z"/></svg><span class="tag violet">?</span></div>
            <div class="art-nm">Блуза Mango XS</div>
            <div class="art-bot"><div class="art-prc">48 лв</div><div class="art-stk ok">5 бр</div></div>
            <div class="art-ctx q2">Без доставна цена · <b>не виждаш profit</b></div>
        </div>
        <div class="glass sm q2 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M4 10h16v11a1 1 0 01-1 1H5a1 1 0 01-1-1z"/><path d="M8 10V6a4 4 0 018 0v4"/></svg><span class="tag violet">12%</span></div>
            <div class="art-nm">Чанта Parfois кафява</div>
            <div class="art-bot"><div class="art-prc">70 лв</div><div class="art-stk ok">3 бр</div></div>
            <div class="art-ctx q2">Profit 8 лв · <b>под 15% марж</b></div>
        </div>
        <div class="glass sm q2 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M7 2h10l1 4-1 3 2 7h-5l-2-5-2 5H5l2-7-1-3z"/></svg><span class="tag violet">−12%</span></div>
            <div class="art-nm">Шорти H&M</div>
            <div class="art-bot"><div class="art-prc">22 лв</div><div class="art-stk ok">4 бр</div></div>
            <div class="art-ctx q2">Мария даде отстъпки · <b>−48 лв profit</b></div>
        </div>
    </div>

    <!-- ═══ 3. КАКВО ПЕЧЕЛИШ ═══ -->
    <div class="q-head q3" onclick="goScreenWithHistory('products',{filter:'top_sales'})" style="cursor:pointer">
        <div class="q-badge">3</div>
        <div class="q-ttl">
            <div class="q-nm q3">Какво печелиш</div>
            <div class="q-sub">Топ артикули по profit за 30д</div>
        </div>
        <div class="q-total q3">+2 840 лв</div>
    </div>
    <div class="h-scroll">
        <div class="glass sm q3 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M2 17h3l2-3h7l3 3h5v-2l-3-2h-2l-3-3H9l-2 3H4l-2 2z"/><line x1="2" y1="20" x2="22" y2="20"/></svg><span class="tag good">#1</span></div>
            <div class="art-nm">Adidas Superstar 40</div>
            <div class="art-bot"><div class="art-prc">140 лв</div><div class="art-stk ok">8 бр</div></div>
            <div class="art-ctx q3">18 прод · <b>+840 лв profit</b></div>
        </div>
        <div class="glass sm q3 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M9 2h6l1 3-2 2v1l4 14H6l4-14V7L8 5z"/></svg><span class="tag good">#2</span></div>
            <div class="art-nm">Рокля Zara черна M</div>
            <div class="art-bot"><div class="art-prc">89 лв</div><div class="art-stk ok">6 бр</div></div>
            <div class="art-ctx q3">11 прод · <b>+568 лв profit</b></div>
        </div>
        <div class="glass sm q3 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M6 2h4l2 3 2-3h4l2 4-4 3v13H8V9L4 6z"/><line x1="12" y1="5" x2="12" y2="22"/></svg><span class="tag good">#3</span></div>
            <div class="art-nm">Яке Tommy Hilfiger L</div>
            <div class="art-bot"><div class="art-prc">320 лв</div><div class="art-stk ok">4 бр</div></div>
            <div class="art-ctx q3">4 прод · <b>+576 лв profit</b></div>
        </div>
        <div class="glass sm q3 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M7 2h10l1 5-1 3 2 12h-5l-2-10-2 10H5l2-12-1-3z"/></svg><span class="tag good">#4</span></div>
            <div class="art-nm">Джинси Levi's W34</div>
            <div class="art-bot"><div class="art-prc">180 лв</div><div class="art-stk ok">5 бр</div></div>
            <div class="art-ctx q3">8 прод · <b>+720 лв profit</b></div>
        </div>
    </div>

    <!-- ═══ 4. ОТ КАКВО ПЕЧЕЛИШ ═══ -->
    <div class="q-head q4" onclick="goScreenWithHistory('products',{filter:'top_profit'})" style="cursor:pointer">
        <div class="q-badge">4</div>
        <div class="q-ttl">
            <div class="q-nm q4">От какво печелиш</div>
            <div class="q-sub">Артикули-причини за profit</div>
        </div>
        <div class="q-total q4">4 причини</div>
    </div>
    <div class="h-scroll">
        <div class="glass sm q4 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M9 2h6l1 3-2 2v1l4 14H6l4-14V7L8 5z"/></svg><span class="tag teal">58%</span></div>
            <div class="art-nm">Рокля Zara черна M</div>
            <div class="art-bot"><div class="art-prc">89 лв</div><div class="art-stk ok">6 бр</div></div>
            <div class="art-ctx q4">Най-висок марж · <b>58% профитност</b></div>
        </div>
        <div class="glass sm q4 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M2 17h3l2-3h7l3 3h5v-2l-3-2h-2l-3-3H9l-2 3H4l-2 2z"/><line x1="2" y1="20" x2="22" y2="20"/></svg><span class="tag teal">↑22%</span></div>
            <div class="art-nm">Adidas Superstar 40</div>
            <div class="art-bot"><div class="art-prc">140 лв</div><div class="art-stk ok">8 бр</div></div>
            <div class="art-ctx q4">Растящ тренд · <b>↑22% седмично</b></div>
        </div>
        <div class="glass sm q4 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M7 2h10l1 5-1 3 2 12h-5l-2-10-2 10H5l2-12-1-3z"/></svg><span class="tag teal">5×</span></div>
            <div class="art-nm">Джинси Levi's W32</div>
            <div class="art-bot"><div class="art-prc">180 лв</div><div class="art-stk ok">5 бр</div></div>
            <div class="art-ctx q4">5 повторни клиенти · <b>лоялен артикул</b></div>
        </div>
        <div class="glass sm q4 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.47a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.47a2 2 0 00-1.34-2.23z"/></svg><span class="tag teal">+Y</span></div>
            <div class="art-nm">Тениска H&M бяла</div>
            <div class="art-bot"><div class="art-prc">24 лв</div><div class="art-stk ok">12 бр</div></div>
            <div class="art-ctx q4">Купуват го с джинси · <b>basket driver</b></div>
        </div>
        <div class="glass sm q4 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M6 2h4l2 3 2-3h4l2 4-4 3v13H8V9L4 6z"/><line x1="12" y1="5" x2="12" y2="22"/></svg><span class="tag teal">M</span></div>
            <div class="art-nm">Яке Tommy L</div>
            <div class="art-bot"><div class="art-prc">320 лв</div><div class="art-stk ok">4 бр</div></div>
            <div class="art-ctx q4">Размер M най-продаван · <b>size leader</b></div>
        </div>
    </div>

    <!-- ═══ 5. КАКВО ДА ПОРЪЧАШ ═══ -->
    <div class="q-head q5" onclick="goScreenWithHistory('products',{filter:'low'})" style="cursor:pointer">
        <div class="q-badge">5</div>
        <div class="q-ttl">
            <div class="q-nm q5">Какво да поръчаш</div>
            <div class="q-sub">Bestsellers с ниска наличност</div>
        </div>
        <div class="q-total q5">6 артикула</div>
    </div>
    <div class="h-scroll">
        <div class="glass sm q5 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M2 17h3l2-3h7l3 3h5v-2l-3-2h-2l-3-3H9l-2 3H4l-2 2z"/><line x1="2" y1="20" x2="22" y2="20"/></svg><span class="tag hot">24</span></div>
            <div class="art-nm">Nike Air Max 42</div>
            <div class="art-bot"><div class="art-prc">120 лв</div><div class="art-stk danger">0 бр</div></div>
            <div class="art-ctx q5">Топ №1 · <b>поръчай 24 бр</b></div>
        </div>
        <div class="glass sm q5 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M7 2h10l1 5-1 3 2 12h-5l-2-10-2 10H5l2-12-1-3z"/></svg><span class="tag hot">18</span></div>
            <div class="art-nm">Levi's 501 W32</div>
            <div class="art-bot"><div class="art-prc">180 лв</div><div class="art-stk warn">2 бр</div></div>
            <div class="art-ctx q5">Bestseller · <b>поръчай 18 бр</b></div>
        </div>
        <div class="glass sm q5 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M20.38 3.46L16 2a4 4 0 01-8 0L3.62 3.46a2 2 0 00-1.34 2.23l.58 3.47a1 1 0 00.99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 002-2V10h2.15a1 1 0 00.99-.84l.58-3.47a2 2 0 00-1.34-2.23z"/></svg><span class="tag hot">12</span></div>
            <div class="art-nm">H&M бяла M</div>
            <div class="art-bot"><div class="art-prc">24 лв</div><div class="art-stk warn">1 бр</div></div>
            <div class="art-ctx q5">Бърз оборот · <b>поръчай 12 бр</b></div>
        </div>
        <div class="glass sm q5 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo"><svg viewBox="0 0 24 24"><path d="M2 12c0-5 4-9 10-9s10 4 10 9"/><path d="M2 12h20l-2 4H4z"/></svg><span class="tag hot">10</span></div>
            <div class="art-nm">Шапка черна unisex</div>
            <div class="art-bot"><div class="art-prc">18 лв</div><div class="art-stk warn">2 бр</div></div>
            <div class="art-ctx q5">5 търсения /седм · <b>поръчай 10</b></div>
        </div>
    </div>

    <!-- ═══ 6. КАКВО ДА НЕ ПОРЪЧАШ ═══ -->
    <div class="q-head q6" onclick="goScreenWithHistory('products',{filter:'zombie'})" style="cursor:pointer">
        <div class="q-badge">6</div>
        <div class="q-ttl">
            <div class="q-nm q6">Какво да НЕ поръчаш</div>
            <div class="q-sub">Zombie — замразен profit</div>
        </div>
        <div class="q-total q6">7 · 2 480 лв</div>
    </div>
    <div class="h-scroll">
        <div class="glass sm q6 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo" style="opacity:.6"><svg viewBox="0 0 24 24"><path d="M8 2l-5 4 2 4 2-1v13h10V9l2 1 2-4-5-4-2 3h-4z"/></svg><span class="tag dim">78д</span></div>
            <div class="art-nm">Блуза Mango розова XS</div>
            <div class="art-bot"><div class="art-prc">48 лв</div><div class="art-stk ok">5 бр</div></div>
            <div class="art-ctx q6">78 дни · <b>240 лв замразени</b></div>
        </div>
        <div class="glass sm q6 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo" style="opacity:.6"><svg viewBox="0 0 24 24"><path d="M4 10h16v11a1 1 0 01-1 1H5a1 1 0 01-1-1z"/><path d="M8 10V6a4 4 0 018 0v4"/></svg><span class="tag dim">94д</span></div>
            <div class="art-nm">Чанта Parfois кафява</div>
            <div class="art-bot"><div class="art-prc">70 лв</div><div class="art-stk ok">3 бр</div></div>
            <div class="art-ctx q6">94 дни · <b>210 лв замразени</b></div>
        </div>
        <div class="glass sm q6 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo" style="opacity:.6"><svg viewBox="0 0 24 24"><path d="M6 2h4l2 3 2-3h4l2 4-4 3v13H8V9L4 6z"/><line x1="12" y1="5" x2="12" y2="22"/></svg><span class="tag dim">112д</span></div>
            <div class="art-nm">Яке зимно XL</div>
            <div class="art-bot"><div class="art-prc">260 лв</div><div class="art-stk ok">2 бр</div></div>
            <div class="art-ctx q6">112 дни · <b>промоция или мърдай</b></div>
        </div>
        <div class="glass sm q6 art" style="cursor:default">
            <span class="shine"></span><span class="shine shine-bottom"></span>
            <div class="art-photo" style="opacity:.6"><svg viewBox="0 0 24 24"><path d="M9 2h6l1 3-2 2v1l4 14H6l4-14V7L8 5z"/></svg><span class="tag dim">−40%</span></div>
            <div class="art-nm">Рокля Mango XXL</div>
            <div class="art-bot"><div class="art-prc">75 лв</div><div class="art-stk ok">4 бр</div></div>
            <div class="art-ctx q6">Спад 40% · <b>не поръчвай</b></div>
        </div>
    </div>

    <div class="glass view-all" onclick="goScreenWithHistory('products',{filter:'all'})" style="cursor:pointer">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span>Виж всички 247 артикула</span>
        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </div>

</div>
<!-- ═══ S79 A1.7 — SCRHOME END ═══ -->
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




<!-- S79.FIX Bug #1: Menu drawer -->
<div class="drawer-ov" id="menuOv" onclick="closeDrawer('menu')"></div>
<div class="drawer" id="menuDr">
    <div class="drawer-handle"></div>
    <div class="drawer-hdr"><h3>Меню</h3><button class="drawer-close" onclick="closeDrawer('menu')">✕</button></div>
    <div style="padding:0 4px 20px">
        <div class="cat-item" onclick="closeDrawer('menu');location.href='profile.php'">
            <div style="display:flex;align-items:center;gap:12px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M5 21c0-3.87 3.13-7 7-7s7 3.13 7 7"/></svg><span style="font-size:14px;font-weight:600">Профил</span></div>
            <span style="color:var(--text-secondary)">›</span>
        </div>
        <div class="cat-item" onclick="closeDrawer('menu');location.href='settings.php'">
            <div style="display:flex;align-items:center;gap:12px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span style="font-size:14px;font-weight:600">Настройки</span></div>
            <span style="color:var(--text-secondary)">›</span>
        </div>
        <div class="cat-item" onclick="closeDrawer('menu');showStoreSwitcher()">
            <div style="display:flex;align-items:center;gap:12px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2"><path d="M3 9l9-6 9 6v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><span style="font-size:14px;font-weight:600">Смени магазин</span></div>
            <span style="color:var(--text-secondary)">›</span>
        </div>
        <div class="cat-item" onclick="if(confirm('Излез от профила?'))location.href='logout.php'" style="color:#fca5a5;border-top:1px solid rgba(239,68,68,.15)">
            <div style="display:flex;align-items:center;gap:12px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fca5a5" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span style="font-size:14px;font-weight:600">Изход</span></div>
            <span style="color:#fca5a5">›</span>
        </div>
    </div>
</div>

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


<!-- ═══ FLOATING AI BUTTON — REMOVED in S82.SHELL (replaced by partials/chat-input-bar.php) ═══ -->

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
        <button id="wizBackBtn" onclick="wizPrev()" aria-label="Назад" title="Назад" style="background:transparent;border:none;color:var(--text-secondary);cursor:pointer;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;padding:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <h2 id="wizTitle">Нов артикул</h2>
        <button onclick="closeWizard()" aria-label="Затвори" title="Затвори" style="background:transparent;border:none;color:var(--text-secondary);font-size:18px;cursor:pointer;width:32px;height:32px;display:flex;align-items:center;justify-content:center">✕</button>
    </div>
    <div class="wiz-steps" id="wizSteps"></div>
    <div class="wiz-label" id="wizLabel"></div>
    <div class="modal-body" id="wizBody"></div>
</div>

<!-- Hidden file inputs -->
<input type="file" id="photoInput" accept="image/*" capture="environment">

<!-- S73.B.6: Fullscreen Matrix Overlay -->
<div class="mx-overlay" id="mxOverlay">
  <button id="mxFocusBack" onclick="if(document.activeElement&&document.activeElement.blur)document.activeElement.blur()" aria-label="Назад">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <div class="mx-header">
    <button class="mx-close" onclick="mxCancel()" title="Назад — откажи промените"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>Назад</button>
    <div class="mx-title-wrap"><div class="mx-title">Матрица на бройките</div><div class="mx-subtitle" id="mxSubtitle">—</div></div>
  </div>
  <div id="mxInstruction" style="flex-shrink:0;padding:10px 16px;background:rgba(99,102,241,0.08);border-bottom:1px solid rgba(99,102,241,0.2);display:flex;gap:10px;align-items:flex-start;transition:max-height .25s,opacity .25s,padding .25s;overflow:hidden;max-height:160px"><div style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#3b82f6);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;font-size:12px;font-weight:800">i</div><div style="font-size:11px;color:#c7d2fe;line-height:1.55">Въведи бройка за всяка комбинация (важи и за единична вариация — само цвят или само размер). <b style="color:#a5b4fc">МКП</b> (<b>М</b>инимално <b>К</b>оличество за <b>П</b>оръчка) се изчислява автоматично от системата за всеки вариант — когато наличността падне под МКП, артикулът се отбелязва за поръчка от доставчик.</div></div>
<div class="mx-quick">
    <button class="mx-qchip" onclick="mxFillAll(1)">Всички = 1</button>
    <button class="mx-qchip" onclick="mxFillAll(2)">Всички = 2</button>
    <button class="mx-qchip" onclick="mxFillAll(5)">Всички = 5</button>
    <button class="mx-qchip" onclick="mxFillAll(10)">Всички = 10</button>
    <button class="mx-qchip danger" onclick="mxClear()">Изчисти</button>
  </div>
  <div class="mx-body-wrap"><table class="mx-table" id="mxTable"><thead id="mxThead"></thead><tbody id="mxTbody"></tbody></table></div>
  <div class="mx-bottom">
    <div class="mx-stats"><div class="mx-stat"><div class="mx-stat-v" id="mxStatCells">0</div><div class="mx-stat-l">Попълнени</div></div><div class="mx-stat"><div class="mx-stat-v" id="mxStatTotal">0</div><div class="mx-stat-l">Общо бр</div></div><div class="mx-stat" title="МКП = Минимално Количество за Поръчка"><div class="mx-stat-v" id="mxStatMin">0</div><div class="mx-stat-l">МКП общо</div></div></div>
    <div style="display:flex;gap:8px"><button type="button" onclick="mxCancel()" style="flex:1;padding:14px 18px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>Откажи</button><button class="mx-done" onclick="mxDone()" style="flex:2"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Готово</button></div>
  </div>
</div>
<input type="file" id="filePickerInput" accept="image/*,*/*">
<!-- S43: removed dup filePickerInput -->
<input type="file" id="csvInput" accept=".csv,.xlsx,.xls">

<script>
<?php
// S82.CAPACITOR.7 — store name за label печат
$_current_store_name = '';
foreach ($stores as $_s) {
    if ((int)$_s['id'] === (int)$store_id) {
        $_current_store_name = $_s['name'];
        break;
    }
}
// Fallback към tenant името ако store-а няма име
if (!$_current_store_name) {
    $_tenant_row = DB::run("SELECT name FROM tenants WHERE id = ?", [$tenant_id])->fetch();
    $_current_store_name = $_tenant_row['name'] ?? '';
}
?>
// ═══════════════════════════════════════════════════════
// S82.UI THEME TOGGLE — default DARK, persists in localStorage
// ═══════════════════════════════════════════════════════
(function initTheme(){
    try{
        var saved=localStorage.getItem('rms_theme');
        if(saved==='light'){document.documentElement.setAttribute('data-theme','light')}
        document.addEventListener('DOMContentLoaded',function(){
            var sun=document.getElementById('themeIconSun');
            var moon=document.getElementById('themeIconMoon');
            if(!sun||!moon)return;
            var isLight=document.documentElement.getAttribute('data-theme')==='light';
            if(isLight){sun.style.display='';moon.style.display='none'}
            else{sun.style.display='none';moon.style.display=''}
        });
    }catch(_){}
})();
function toggleTheme(){
    var cur=document.documentElement.getAttribute('data-theme')||'dark';
    var nxt=(cur==='light')?'dark':'light';
    if(nxt==='light'){document.documentElement.setAttribute('data-theme','light')}
    else{document.documentElement.removeAttribute('data-theme')}
    try{localStorage.setItem('rms_theme',nxt)}catch(_){}
    var sun=document.getElementById('themeIconSun');
    var moon=document.getElementById('themeIconMoon');
    if(sun&&moon){
        if(nxt==='light'){sun.style.display='';moon.style.display='none'}
        else{sun.style.display='none';moon.style.display=''}
    }
    if(navigator.vibrate)navigator.vibrate(5);
}

// ═══ PHP → JS CONFIG ═══
const CFG = {
    storeId: <?= (int)$store_id ?>,
    storeName: <?= json_encode($_current_store_name, JSON_UNESCAPED_UNICODE) ?>,
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
window._allBizPresets=<?= json_encode($allBizPresets, JSON_UNESCAPED_UNICODE) ?>;
window._bizCompositions=<?= json_encode($bizComps['compositions'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
window._bizCountries=<?= json_encode($bizComps['countries'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
window._tenantCountry=<?= json_encode($tenant['country'] ?? 'BG') ?>;
window._sizePresets={clothing:['XS','S','M','L','XL','2XL','3XL','4XL'],shoes:['36','37','38','39','40','41','42','43','44','45','46'],clothing_eu:['34','36','38','40','42','44','46','48','50','52','54','56'],kids:['80','86','92','98','104','110','116','122','128','134','140','146','152','158','164'],pants:['W28','W29','W30','W31','W32','W33','W34','W36','W38'],rings:['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'],socks:['35-38','39-42','43-46'],hats:['S/M','L/XL','One Size'],bra:['70A','70B','75A','75B','75C','80A','80B','80C','80D','85B','85C','85D']};

<?php
// S70: Load size-presets-data.json (121 biz categories, 50 size groups)
$_sizPresetsFile = __DIR__ . '/size-presets-data.json';
$_sizPresetsData = file_exists($_sizPresetsFile) ? file_get_contents($_sizPresetsFile) : '{"extraGroups":[],"bizKeywords":{}}';
?>
window._BIZ_DATA=<?= $_sizPresetsData ?>;

// ═══════════════════════════════════════════════════════════
// PART 3: JS CORE — Navigation, Home, Search, Drawers, Camera
// ═══════════════════════════════════════════════════════════

// ─── STATE ───
const S = {
    screen: 'home', sort: 'name', filter: 'all', page: 1,
    homeTab: 'all', homePage: 1,
    supId: null, catId: null,
    searchText: '', searchTO: null,
    detailStack: [],
    cameraMode: null, cameraStream: null, barcodeDetector: null, barcodeInterval: null, zxingReader: null,
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
    const q=parseInt(p.store_stock||p.qty||0);
    const sc=stockClass(q,p.min_quantity||0);
    const bc=stockBar(q,p.min_quantity||0);
    const thumb=p.image_url?`<img src="${p.image_url}">`:`<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.25)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>`;
    const disc=(p.discount_pct&&p.discount_pct>0)?`<div class="p-discount">-${p.discount_pct}%</div>`:'';
    // Price display
    let priceH=`<div style="font-size:14px;font-weight:800;color:var(--indigo-300)">${fmtPrice(p.retail_price)}</div>`;
    if(p.discount_pct>0) priceH=`<div style="font-size:14px;font-weight:800;color:var(--indigo-300)">${fmtPrice(p.retail_price*(1-p.discount_pct/100))}</div><div style="font-size:9px;color:var(--text-secondary);text-decoration:line-through">${fmtPrice(p.retail_price)}</div>`;
    // Pills
    let pills='';
    if(p.supplier_name) pills+=`<span class="rc-pill rc-sup">${esc(p.supplier_name)}</span>`;
    if(p.category_name) pills+=`<span class="rc-pill rc-cat">${esc(p.category_name)}</span>`;
    if(q===0) pills+=`<span class="rc-pill rc-danger">ИЗЧЕРПАН</span>`;
    else if(p.min_quantity>0&&q<=p.min_quantity) pills+=`<span class="rc-pill rc-warn">⚠ под минимум</span>`;
    if(!p.image_url) pills+=`<span class="rc-pill rc-orange">без снимка</span>`;
    // Stats columns
    let cols=[];
    // 1. Наличност
    const stockColor=q>0?(q<=(p.min_quantity||0)?'var(--warning)':'var(--success)'):'var(--danger)';
    let stockSub=p.min_quantity>0?`<div class="rc-sub">мин:${p.min_quantity}</div>`:'';
    cols.push(`<div class="rc-stat"><div class="rc-sl">Налич.</div><div class="rc-sv" style="color:${stockColor}">${q}</div>${stockSub}</div>`);
    // 2. Едро
    cols.push(`<div class="rc-stat"><div class="rc-sl">Едро</div><div class="rc-sv" style="color:rgba(165,180,252,.5)">${p.wholesale_price?fmtPrice(p.wholesale_price):'—'}</div></div>`);
    // 3+4. Маржове (owner only)
    if(CFG.canSeeMargin && p.cost_price>0){
        const mr=Math.round((p.retail_price-p.cost_price)/p.retail_price*100);
        const mrc=mr<15?'var(--danger)':mr<25?'var(--warning)':'var(--purple)';
        cols.push(`<div class="rc-stat"><div class="rc-sl">Марж др.</div><div class="rc-sv" style="color:${mrc}">${mr}%</div></div>`);
        if(p.wholesale_price&&p.wholesale_price>0){
            const mw=Math.round((p.wholesale_price-p.cost_price)/p.wholesale_price*100);
            const mwc=mw<10?'var(--danger)':mw<20?'var(--warning)':'var(--purple)';
            cols.push(`<div class="rc-stat"><div class="rc-sl">Марж едр.</div><div class="rc-sv" style="color:${mwc}">${mw}%</div></div>`);
        }
    }
    // 5. 30д продажби
    const sold=p.sold_30d||0;
    cols.push(`<div class="rc-stat"><div class="rc-sl">30д</div><div class="rc-sv" style="color:${sold>0?'var(--success)':'rgba(165,180,252,.3)'}">${sold||'—'}</div></div>`);
    const statsH=cols.join('<div class="rc-sep"></div>');
    return `<div class="rc-card" data-id="${p.id}">
        <div class="stock-bar ${bc}"></div>${disc}
        <div class="rc-top" onclick="openProductDetail(${p.id})">
            <div class="rc-thumb">${thumb}</div>
            <div class="rc-info"><div class="rc-row1"><div class="rc-name">${esc(p.name)}</div><div class="rc-price">${priceH}</div></div><div class="rc-code">Код: <span>${esc(p.code||'—')}</span></div></div>
        </div>
        <div class="rc-pills" onclick="openProductDetail(${p.id})">${pills}</div>
        <div class="rc-stats" onclick="openProductDetail(${p.id})">${statsH}</div>
        <div class="rc-actions">
            <div class="rc-act" onclick="editProduct(${p.id})">✎ Редактирай</div>
            <div class="rc-act" onclick="askAIAboutProduct(${p.id},'${esc(p.name).replace(/'/g,"\\'")}')">✦ AI Съвет</div>
            <div class="rc-act rc-more-trigger" onclick="toggleMoreMenu(event,${p.id},'${esc(p.name).replace(/'/g,"\\'")}')">⋯ Още</div>
        </div>
    </div>`;
}

// S43: AI advice about specific product
function askAIAboutProduct(id, name) {
    S._returnToProductId = id;
    openAIChatOverlay();
    setTimeout(function(){
        if (typeof sendAutoQuestion === 'function') sendAutoQuestion('Анализирай артикул "'+name+'" — наличност, продажби, марж. Какво да направя?');
    }, 400);
}

// S43: More menu
function toggleMoreMenu(e, id, name) {
    e.stopPropagation();
    document.querySelectorAll('.rc-more-dd').forEach(d=>d.remove());
    const rect=e.currentTarget.getBoundingClientRect();
    const dd=document.createElement('div');
    dd.className='rc-more-dd';
    dd.innerHTML=`<div class="rc-dd-item" onclick="openLabels(${id})">🏷 Етикети</div><div class="rc-dd-item" onclick="location.href='sale.php?product=${id}'">💰 Продажба</div><div class="rc-dd-item" onclick="openImageStudio(${id})">✨ AI Снимка</div><div class="rc-dd-item" onclick="duplicateProduct(${id})">📋 Копирай</div><div class="rc-dd-item rc-dd-danger" onclick="deactivateProduct(${id})">🗑 Деактивирай</div>`;
    dd.style.position='fixed';
    dd.style.bottom=(window.innerHeight-rect.top+4)+'px';
    dd.style.right=(window.innerWidth-rect.right)+'px';
    document.body.appendChild(dd);
    setTimeout(()=>document.addEventListener('click',function _h(){dd.remove();document.removeEventListener('click',_h)},{once:true}),10);
}
function duplicateProduct(id){ showToast('Копиране... (скоро)',''); }
function deactivateProduct(id){ if(confirm('Деактивирай артикула?')) showToast('Деактивиран (скоро)',''); }

// ─── NAVIGATION ───
function goScreen(scr, params={}){
    S.screen=scr; S.supId=params.sup||null; S.catId=params.cat||null; S.page=1;
    document.querySelectorAll('.screen-section').forEach(el=>el.classList.remove('active'));
    const map={home:'scrHome',suppliers:'scrSuppliers',categories:'scrCategories',products:'scrProducts'};
    document.getElementById(map[scr])?.classList.add('active');
    document.querySelectorAll('.sn-btn').forEach(b=>b.classList.toggle('active',b.dataset.scr===scr));
    // A4: Read ?filter= from URL (chat action buttons)
    const _urlFilter = new URLSearchParams(window.location.search).get('filter');
    if(_urlFilter){
        const _filterMap = {zero:'out',below_cost:'at_loss'};
        S.filter = _filterMap[_urlFilter] || _urlFilter;
        S.screen = 'products';
        history.replaceState(null,'',location.pathname);
    }
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
        const el1 = document.getElementById('hdrCount');
        if (el1) el1.textContent=(d.counts.total_products||0)+' артикула';
        const el2 = document.getElementById('hTitleCount');
        if (el2) el2.textContent='· '+(d.counts.total_products||0)+' артикула';
    }
    // Init cascade categories (all global)

    // Load signals

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
    // Filter label chip
    const _filterLabels={zombie:'Zombie 45+ дни',out:'Нулева наличност',zero:'Нулева наличност',at_loss:'Под себестойност',below_cost:'Под себестойност',low_margin:'Нисък марж',no_photo:'Без снимка',no_cost:'Без себестойност',low:'Под минимум',top_profit:'Най-печеливши',top_sales:'Топ продажби',new_week:'Нови тази седмица',aging:'90+ дни без продажба',slow_mover:'Бавно движещи',critical_low:'Критично ниски',no_barcode:'Без баркод',no_supplier:'Без доставчик',below_min:'Под минимум'};
    if(S.filter!=='all'){
        const fl=_filterLabels[S.filter]||S.filter;
        titleParts.unshift(fl);
        chipsHtml=`<div class="act-chip" style="background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.25);color:#fca5a5">${fl} <div class="chip-x" onclick="S.filter='all';loadProducts()">x</div></div>`+chipsHtml;
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
let _searchTO=null;
function onLiveSearch(q){
    q=q.trim();
    const clear=document.getElementById('liveSearchClear');
    const results=document.getElementById('liveSearchResults');
    if(clear)clear.style.display=q?'flex':'none';
    if(q.length<1){clearTimeout(_searchTO);results.style.display='none';return}
    clearTimeout(_searchTO);
    _searchTO=setTimeout(async()=>{
        const d=await api('products.php?ajax=search&q='+encodeURIComponent(q)+'&store_id='+CFG.storeId);
        if(!d){results.style.display='none';return}
        if(!d.length){results.innerHTML='<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-bottom:1px solid rgba(99,102,241,.12)"><span style="font-size:10px;font-weight:700;color:var(--text-secondary)">0 резултата</span><div onclick="clearLiveSearch()" style="width:24px;height:24px;border-radius:8px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;cursor:pointer"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke=\x22#818cf8\x22 stroke-width=\x223\x22><path d=\x22M18 6L6 18M6 6l12 12\x22/></svg></div></div><div style="padding:16px;text-align:center;font-size:12px;color:var(--text-secondary)">Нищо за "'+esc(q)+'"</div>';results.style.display='block';return}
        var closeH='<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-bottom:1px solid rgba(99,102,241,.12);position:sticky;top:0;background:rgba(8,8,24,0.97);z-index:1"><span style="font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase">'+d.length+' резултата</span><div onclick="clearLiveSearch()" style="width:24px;height:24px;border-radius:8px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;cursor:pointer"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg></div></div>';
        results.innerHTML=closeH+d.slice(0,15).map(p=>{
            const q=parseInt(p.total_stock||0);
            const sc=q>0?'var(--success)':'var(--danger)';
            const thumb=p.image_url?'<img src="'+p.image_url+'" style="width:100%;height:100%;object-fit:cover;border-radius:8px">':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,.25)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>';
            return '<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid rgba(99,102,241,.06);cursor:pointer" onclick="pickSearchResult('+p.id+')">'+
            '<div style="width:36px;height:36px;border-radius:8px;background:rgba(99,102,241,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">'+thumb+'</div>'+
            '<div style="flex:1;min-width:0"><div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(p.name)+'</div>'+
            '<div style="font-size:9px;color:var(--text-secondary)">'+esc(p.code||'')+' · '+esc(p.supplier_name||'')+'</div></div>'+
            '<div style="text-align:right;flex-shrink:0"><div style="font-size:12px;font-weight:700;color:var(--indigo-300)">'+fmtPrice(p.retail_price)+'</div>'+
            '<div style="font-size:9px;color:'+sc+'">'+q+' бр.</div></div></div>';
        }).join('');
        results.style.display='block';
    },250);
}
function clearLiveSearch(){
    const inp=document.getElementById('liveSearchInput');
    if(inp)inp.value='';
    document.getElementById('liveSearchResults').style.display='none';
    document.getElementById('liveSearchClear').style.display='none';
}
function pickSearchResult(id){
    document.getElementById('liveSearchResults').style.display='none';
    openProductDetail(id);
}
function focusSearch(){document.getElementById('liveSearchInput')?.focus()}
function updateSearchDisplay(){}
async function doSearch(q){
    const d=await api(`products.php?ajax=search&q=${encodeURIComponent(q)}&store_id=${CFG.storeId}`);
    if(!d)return;
    // Show products screen without triggering loadProducts
    if(S.screen!=='products'){
        S.screen='products';
        document.querySelectorAll('.screen-section').forEach(el=>el.classList.remove('active'));
        document.getElementById('scrProducts').classList.add('active');
    }
    document.getElementById('prodTitle').textContent='Търсене: '+q;
    document.getElementById('prodCnt').textContent=d.length+' резултата';
    document.getElementById('prodPag').innerHTML='';
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

// S50: openLabels — full print UI like wizard step 7
async function openLabels(productId) {
    openDrawer('labels');
    document.getElementById('labelsBody').innerHTML='<div style="text-align:center;padding:20px">Зареждам...</div>';
    const d=await api('products.php?ajax=export_labels&product_id='+productId+'&format=json');
    const pd=await api('products.php?ajax=product_detail&id='+productId);
    if(!d||!d.length){document.getElementById('labelsBody').innerHTML='<div style="text-align:center;padding:20px;color:var(--text-secondary)">Няма вариации</div>';return}
    const p=pd?.product||{};
    S._labelProductId=productId;
    S._labelData=d;
    S._labelProduct=p;
    if(!S._labelPrintMode)S._labelPrintMode='eur';
    renderLabelsDrawer();
}
function renderLabelsDrawer(){
    var d=S._labelData||[];
    var p=S._labelProduct||{};
    var pm=S._labelPrintMode||'eur';
    var isBG=(window._tenantCountry||'').toUpperCase()==='BG';
    var beforeDL=new Date()<new Date('2026-08-08');
    var showDual=isBG&&beforeDL;
    var h='<div style="padding:0 4px">';
    // Tabs
    h+='<div style="display:flex;gap:4px;margin-bottom:10px;background:rgba(255,255,255,0.05);border-radius:10px;padding:3px">';
    if(showDual)h+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='dual'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S._labelPrintMode=\'dual\';renderLabelsDrawer()">\u20ac + \u043b\u0432</div>';
    h+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='eur'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S._labelPrintMode=\'eur\';renderLabelsDrawer()">\u0421\u0430\u043c\u043e \u20ac</div>';
    h+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='noprice'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S._labelPrintMode=\'noprice\';renderLabelsDrawer()">\u0411\u0435\u0437 \u0446\u0435\u043d\u0430</div>';
    h+='</div>';
    // Warning dual
    if(showDual&&pm==='dual')h+='<div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:7px 10px;margin-bottom:10px;font-size:10px;color:#fbbf24">\u0414\u0432\u043e\u0439\u043d\u043e \u0438\u0437\u043f\u0438\u0441\u0432\u0430\u043d\u0435 \u0434\u043e 08.08.2026</div>';
    // Header
    h+='<div style="display:flex;align-items:center;padding:4px 10px;gap:4px;margin-bottom:4px">';
    h+='<div style="flex:1;font-size:10px;font-weight:700;color:var(--text-secondary)">\u0412\u0410\u0420\u0418\u0410\u0426\u0418\u042f</div>';
    h+='<div style="width:80px;font-size:9px;font-weight:700;color:var(--indigo-300);text-align:center">\u0411\u0420\u041e\u0419\u041a\u0418</div>';
    h+='<div style="width:30px"></div></div>';
    // x2 / 1:1 buttons
    h+='<div style="display:flex;gap:6px;margin-bottom:6px;padding:0 10px">';
    h+='<button type="button" class="abtn" onclick="lblX2()" style="font-size:10px;padding:5px 12px;width:auto">x2</button>';
    h+='<button type="button" class="abtn" onclick="lblReset()" style="font-size:10px;padding:5px 12px;width:auto;color:#fbbf24;border-color:rgba(245,158,11,.2)">1:1</button>';
    h+='</div>';
    // Rows
    var totalQty=0;
    d.forEach(function(v,i){
        if(!v._printQty&&v._printQty!==0)v._printQty=1;
        totalQty+=v._printQty;
        var label=esc(v.name);
        if(v.size)label='<span style="font-size:13px;font-weight:700;margin-right:4px">'+esc(v.size)+'</span>';
        if(v.color){var cc=CFG.colors.find(function(x){return x.name===v.color});var hex=cc?cc.hex:'#666';label+='<span style="display:inline-flex;align-items:center;gap:3px"><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:'+hex+';border:1px solid rgba(255,255,255,0.2)"></span><span style="font-size:11px">'+esc(v.color)+'</span></span>';}
        if(!v.size&&!v.color)label='<span style="font-size:11px">'+esc(v.name)+'</span>';
        h+='<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;padding:6px 10px;border-radius:8px;background:rgba(17,24,44,0.3);border:1px solid var(--border-subtle)">';
        h+='<div style="flex:1;display:flex;align-items:center;flex-wrap:wrap;gap:4px">'+label+'</div>';
        h+='<div style="display:flex;align-items:center;gap:0">';
        h+='<button type="button" onclick="lblAdj('+i+',-1)" style="width:22px;height:26px;border:1px solid var(--border-subtle);border-radius:4px 0 0 4px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:13px;cursor:pointer;padding:0">\u2212</button>';
        h+='<input type="number" class="fc" id="lbl'+i+'" style="width:32px;padding:2px 0;text-align:center;font-size:12px;font-weight:700;border-radius:0;border-left:0;border-right:0" value="'+v._printQty+'" min="0" onchange="lblRecalc()">';
        h+='<button type="button" onclick="lblAdj('+i+',1)" style="width:22px;height:26px;border:1px solid var(--border-subtle);border-radius:0 4px 4px 0;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:13px;cursor:pointer;padding:0">+</button></div>';
        h+='<div onclick="lblPrint('+i+')" style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;cursor:pointer"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></div></div>';
    });

    h+='<div style="display:flex;gap:8px;margin-top:10px;align-items:stretch">';
    h+='<button type="button" onclick="openPrinterSettings()" title="Настройки принтер" style="width:48px;flex-shrink:0;background:rgba(99,102,241,.1);color:#818cf8;border:1px solid rgba(99,102,241,.25);border-radius:10px;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center">⚙️</button>';
    h+='<button type="button" class="abtn save" style="flex:1;font-size:13px;padding:12px" onclick="lblPrint(-1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:5px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>\u041f\u0435\u0447\u0430\u0442\u0430\u0439 \u0432\u0441\u0438\u0447\u043a\u0438 (<span id="lblTot">'+totalQty+'</span> \u0435\u0442.)</button></div>';
    h+='<button type="button" class="abtn" onclick="lblCSV()" style="margin-top:6px;font-size:11px;padding:8px;border-color:rgba(99,102,241,.15);color:var(--indigo-300)">\u0421\u0432\u0430\u043b\u0438 CSV</button>';
    h+='</div>';
    document.getElementById('labelsBody').innerHTML=h;
}
function lblAdj(i,delta){
    var d=S._labelData;if(!d||!d[i])return;
    d[i]._printQty=Math.max(0,(d[i]._printQty||1)+delta);
    var inp=document.getElementById('lbl'+i);if(inp)inp.value=d[i]._printQty;
    lblRecalc();
}
function lblRecalc(){
    var total=0;(S._labelData||[]).forEach(function(v,i){var inp=document.getElementById('lbl'+i);v._printQty=parseInt(inp?.value)||0;total+=v._printQty});
    var el=document.getElementById('lblTot');if(el)el.textContent=total;
}
function lblX2(){(S._labelData||[]).forEach(function(v,i){v._printQty=Math.max(1,(v._printQty||1)*2);var inp=document.getElementById('lbl'+i);if(inp)inp.value=v._printQty});lblRecalc()}
function lblReset(){(S._labelData||[]).forEach(function(v,i){v._printQty=1;var inp=document.getElementById('lbl'+i);if(inp)inp.value=1});lblRecalc()}
function lblCSV(){location.href='products.php?ajax=export_labels&product_id='+S._labelProductId+'&format=csv'}
function lblPrint(idx){
    // S82.CAPACITOR — BLE print on mobile APK (edit/detail drawer)
    if (window.CapPrinter && window.CapPrinter.isAvailable()) {
        return lblPrintMobile(idx);
    }
    var d=S._labelData||[];
    var p=S._labelProduct||{};
    var pm=S._labelPrintMode||'eur';
    var items=[];
    if(idx===-1){d.forEach(function(v,i){if(v._printQty>0)items.push({v:v,qty:v._printQty})})}
    else if(d[idx]){items.push({v:d[idx],qty:d[idx]._printQty||1})}
    if(!items.length){showToast('Няма етикети','error');return}
    var price=parseFloat(p.retail_price)||0;
    var priceBGN=(price*1.95583).toFixed(2);
    var barcode=p.barcode||('200'+String(p.id||0).padStart(9,'0'));
    var name=p.name||'';
    var code=p.code||'';
    var fmtEur=function(v){return v.toFixed(2).replace('.',',')+' \u20ac'};
    var fmtBgn=function(v){return parseFloat(v).toFixed(2).replace('.',',')+' \u043b\u0432'};
    var labels=[];
    items.forEach(function(item){for(var q=0;q<item.qty;q++){labels.push({size:item.v.size||'',color:item.v.color||'',barcode:item.v.barcode||barcode})}});
    var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Labels</title>';
    html+='<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>';
    html+='<style>@page{size:50mm 30mm;margin:0}*{box-sizing:border-box;margin:0;padding:0}body{font-family:Arial,sans-serif;color:#000}.label{width:50mm;height:30mm;padding:1.5mm 2mm;display:flex;flex-direction:column;justify-content:space-between;page-break-after:always;overflow:hidden}.l-top{display:flex;gap:1.5mm;align-items:flex-start}.l-name{font-size:7pt;font-weight:700;line-height:1.15}.l-code{font-size:5pt;color:#555;margin-top:0.3mm}.l-mid{display:flex;align-items:center;gap:1.5mm}.l-sz{background:#000;color:#fff;font-size:10pt;font-weight:700;padding:0.5mm 2.5mm;border-radius:1mm}.l-clr{font-size:7pt;color:#333}.l-dash{border-top:0.3mm dashed #aaa;padding-top:0.8mm}.l-pr{display:flex;align-items:baseline;gap:1.5mm}.l-eur{font-size:12pt;font-weight:700}.l-bgn{font-size:8pt;font-weight:600;color:#444}.l-eur-only{font-size:14pt;font-weight:700;text-align:center}@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head><body>';
    labels.forEach(function(lb,i){
        html+='<div class="label"><div class="l-top"><svg id="bc'+i+'" style="width:28mm;height:12mm;flex-shrink:0"></svg><div><div class="l-name">'+name+'</div><div class="l-code">'+code+' \u00b7 '+lb.barcode+'</div></div></div>';
        if(pm==='noprice'){
            html+='<div style="display:flex;align-items:center;gap:2mm;justify-content:center;flex:1">';
            if(lb.size)html+='<div class="l-sz" style="font-size:16pt;padding:1mm 4mm">'+lb.size+'</div>';
            if(lb.color)html+='<span style="font-size:10pt">'+lb.color+'</span>';
            html+='</div>';
        }else{
            html+='<div class="l-mid">';if(lb.size)html+='<div class="l-sz">'+lb.size+'</div>';if(lb.color)html+='<span class="l-clr">'+lb.color+'</span>';html+='</div>';
            html+='<div class="l-dash">';
            if(pm==='dual')html+='<div class="l-pr"><span class="l-eur">'+fmtEur(price)+'</span><span style="color:#aaa;font-size:6pt">|</span><span class="l-bgn">'+fmtBgn(priceBGN)+'</span></div>';
            else html+='<div class="l-eur-only">'+fmtEur(price)+'</div>';
            html+='</div>';
        }
        html+='</div>';
    });
    html+='<script>var opts={format:"EAN13",width:1,height:28,displayValue:false,margin:0};for(var i=0;i<'+labels.length+';i++){try{JsBarcode("#bc"+i,"'+barcode+'",opts)}catch(e){}}setTimeout(function(){window.print()},400)<\/script></body></html>';
    // S79FIX_BUG9_QSECTIONS_APPLIED
    // S79FIX_BUG567_ADDCARD_APPLIED
    var w=window.open('','_blank','width=400,height=600');
    if(w){w.document.write(html);w.document.close()}else showToast('Позволи pop-up','error');
}


// ═══ S82.CAPACITOR — Mobile BLE print за edit/detail drawer ═══
async function lblPrintMobile(idx){
    var d = S._labelData || [];
    var p = S._labelProduct || {};
    var items = [];
    if (idx === -1) {
        d.forEach(function(v){ if (v._printQty > 0) items.push({v: v, qty: v._printQty}); });
    } else if (d[idx]) {
        items.push({v: d[idx], qty: d[idx]._printQty || 1});
    }
    if (!items.length) { showToast('Няма етикети', 'error'); return; }

    if (!CapPrinter.hasPairedPrinter()) {
        showToast('Първи път: избери принтера', 'info');
        try {
            await CapPrinter.pair();
            showToast('Принтерът е сдвоен', 'success');
        } catch (e) {
            showToast('Неуспешно сдвояване: ' + (e.message || e), 'error');
            return;
        }
    }

    var price = parseFloat(p.retail_price) || 0;
    var name = p.name || '';
    var code = p.code || '';
    var barcode = p.barcode || ('200' + String(p.id || 0).padStart(9, '0'));
    var storeInfo = {
        name: (typeof CFG !== 'undefined' && CFG.storeName) ? CFG.storeName : '',
        currency: 'EUR'
    };

    var totalCopies = 0;
    items.forEach(function(it){ totalCopies += it.qty; });
    showPrintOverlay('Печат 1 от ' + items.length + '...');

    try {
        for (var i = 0; i < items.length; i++) {
            showPrintOverlay('Печат ' + (i+1) + ' от ' + items.length + '...');
            var it = items[i];
            var sizeVal = it.v.size || '';
            var colorVal = it.v.color || '';
            var labelName = name + (sizeVal ? ' ' + sizeVal : '') + (colorVal ? ' ' + colorVal : '');
            var itemBarcode = it.v.barcode || barcode;
            var product = {
                code: code,
                name: labelName,
                retail_price: price,
                barcode: itemBarcode
            };
            await CapPrinter.print(product, storeInfo, it.qty);
        }
        hidePrintOverlay();
        showToast('Готово: ' + totalCopies + ' етикета', 'success');
    } catch (e) {
        hidePrintOverlay();
        showToast('Грешка: ' + (e.message || e), 'error');
    }
}

// S43+S50+S50b: openImageStudio — full AI Studio in drawer (same as wizard step 2)
async function openImageStudio(productId) {
    openDrawer('studio');
    document.getElementById('studioBody').innerHTML='<div style="text-align:center;padding:20px;font-size:12px;color:var(--text-secondary)">Зареждам...</div>';
    const d = await api('products.php?ajax=product_detail&id='+productId);
    if(!d||d.error){showToast('Грешка','error');return}
    const p = d.product;
    const hasImg = p.image_url && p.image_url.length > 5;
    S._studioProductId = productId;
    // If no photo — show upload first, then Studio
    if (!hasImg) {
        S.wizData._photoDataUrl = null;
        S.wizData._hasPhoto = false;
    } else {
        S.wizData._photoDataUrl = p.image_url;
        S.wizData._hasPhoto = true;
    }
    renderStudioInDrawer(hasImg, p.image_url);
}
function renderStudioInDrawer(hasImg, imgUrl) {
    let h = '<div style="padding:4px 8px">';
    // Photo section
    if (!hasImg) {
        h += '<div style="text-align:center;padding:10px 0 8px">';
        h += '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="rgba(245,158,11,.6)" stroke-width="1.5"><path d="M15 10l4.5-4.5M20 4l-1 1"/><rect x="3" y="8" width="18" height="13" rx="2"/><circle cx="12" cy="15" r="3"/></svg>';
        h += '<div style="font-size:13px;font-weight:700;margin-top:6px">Първо добави снимка</div></div>';
        h += '<div style="display:flex;gap:8px;margin-bottom:12px">';
        h += '<button class="abtn primary" onclick="studioTakePhoto()" style="flex:1">Снимай</button>';
        h += '<button class="abtn" onclick="studioPickPhoto()" style="flex:1">Галерия</button></div>';
    } else {
        h += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">';
        h += '<img src="'+imgUrl+'" style="width:60px;height:60px;border-radius:10px;object-fit:cover;border:1px solid var(--border-subtle)">';
        h += '<button class="abtn" onclick="studioPickPhoto()" style="font-size:10px;padding:6px 12px">Смени снимката</button></div>';
    }
    // Credits
    h += '<div class="credits-bar" style="margin:0 0 10px"><div class="cr-item">Бял фон: <b>'+CFG.aiBg+'</b> (0.05\u20ac)</div><div class="cr-sep"></div><div class="cr-item">AI Магия: <b>'+CFG.aiTryon+'</b> (0.50\u20ac)</div></div>';
    if (!hasImg) {
        h += '<div style="font-size:11px;color:var(--text-secondary);text-align:center;padding:10px">Добави снимка горе, за да отключиш AI обработките</div>';
        h += '</div>';
        document.getElementById('studioBody').innerHTML = h;
        return;
    }
    // ─── OPTION 1: Бял фон ───
    h += '<div style="padding:10px;border-radius:12px;background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);margin-bottom:6px;cursor:pointer" onclick="studioAction(\'bg_removal\')">';
    h += '<div style="display:flex;align-items:center;gap:8px">';
    h += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
    h += '<div style="flex:1"><div style="font-size:13px;font-weight:600">Бял фон</div><div style="font-size:9px;color:var(--text-secondary)">Махва фона, чисто бяло</div></div>';
    h += '<span style="font-size:11px;font-weight:600;color:#22c55e">0.05\u20ac</span></div></div>';
    // ─── OPTION 2: Дрехи на модел ───
    h += '<div style="padding:10px;border-radius:12px;background:rgba(139,92,246,.04);border:1px solid rgba(139,92,246,.2);margin-bottom:6px">';
    h += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">';
    h += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="1.5"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l6.5 4-2-7L22 9h-7z"/></svg>';
    h += '<div style="flex:1"><div style="font-size:13px;font-weight:600">AI Магия — дрехи</div><div style="font-size:9px;color:var(--text-secondary)">Облечи на модел</div></div>';
    h += '<span style="font-size:11px;font-weight:600;color:#a78bfa">0.50\u20ac</span></div>';
    // 6 models
    h += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-bottom:6px">';
    var models=[['woman','Жена',true],['man','Мъж',false],['girl','Момиче',false],['boy','Момче',false],['teen_f','Тийн F',false],['teen_m','Тийн M',false]];
    models.forEach(function(m){
        var sel=S.studioModel===m[0];
        var bg=sel?'rgba(139,92,246,.12);border:1px solid rgba(139,92,246,.35)':'rgba(99,102,241,.05);border:0.5px solid rgba(99,102,241,.15)';
        h+='<div style="text-align:center;padding:7px 2px;border-radius:7px;background:'+bg+';cursor:pointer" onclick="S.studioModel=\''+m[0]+'\';renderStudioInDrawer(true,\''+imgUrl.replace(/'/g,"\\'")+'\')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="'+(sel?'#c4b5fd':'#a5b4fc')+'" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-3.87 3.13-7 7-7s7 3.13 7 7"/></svg><div style="font-size:9px;color:'+(sel?'#c4b5fd':'#a5b4fc')+'">'+m[1]+'</div></div>';
    });
    h += '</div>';
    h += '<div style="margin-bottom:6px"><input type="text" class="fc" id="studioPromptClothes" placeholder="допълни: стояща поза, профил..." style="font-size:11px;padding:6px 10px"></div>';
    h += '<button class="abtn" onclick="studioAction(\'tryon_\'+(S.studioModel||\'woman\')" style="background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:11px">Генерирай на модел</button></div>';
    // ─── OPTION 3: Предмети ───
    h += '<div style="padding:10px;border-radius:12px;background:rgba(234,179,8,.04);border:1px solid rgba(234,179,8,.2);margin-bottom:6px">';
    h += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">';
    h += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.5"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l6.5 4-2-7L22 9h-7z"/></svg>';
    h += '<div style="flex:1"><div style="font-size:13px;font-weight:600">AI Магия — предмети</div><div style="font-size:9px;color:var(--text-secondary)">Бижута, обувки, чанти</div></div>';
    h += '<span style="font-size:11px;font-weight:600;color:#fbbf24">0.50\u20ac</span></div>';
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:6px">';
    ['Бижу на ръка','На кадифе','На мрамор','Макро близък план','На дърво','Lifestyle сцена','Обувка на крак','Чанта на рамо'].forEach(function(label){
        h+='<div style="padding:5px 8px;border-radius:6px;background:rgba(234,179,8,.06);border:0.5px solid rgba(234,179,8,.15);cursor:pointer;font-size:10px;color:#fcd34d" onclick="document.getElementById(\'studioPromptObjects\')||null;if(document.getElementById(\'studioPromptObjects\')){document.getElementById(\'studioPromptObjects\'). value=\''+label+'\';}">'+label+'</div>';
    });
    h += '</div>';
    h += '<div style="margin-bottom:6px"><input type="text" class="fc" id="studioPromptObjects" placeholder="или опиши: пръстен в кутийка..." style="font-size:11px;padding:6px 10px"></div>';
    h += '<button class="abtn" onclick="studioAction(\'object_studio\')" style="background:linear-gradient(135deg,#b45309,#d97706);color:#fff;border:none;font-size:11px">Генерирай студийна снимка</button></div>';
    h += '</div>';
    document.getElementById('studioBody').innerHTML = h;
}
function studioTakePhoto(){closeDrawer('studio');document.getElementById('photoInput').setAttribute('data-studio','1');document.getElementById('photoInput').click()}
function studioPickPhoto(){closeDrawer('studio');document.getElementById('filePickerInput').setAttribute('data-studio','1');document.getElementById('filePickerInput').click()}
async function studioAction(type){
    showToast('AI обработва... 5-15 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:type,product_id:S._studioProductId})});
    if(d&&d.error)showToast(d.error,'error');
    else showToast('Готово!','success');
}
// Studio photo upload handler
async function studioUploadPhoto(file, productId){
    const reader=new FileReader();
    reader.onload=async function(e){
        const base64=e.target.result;
        showToast('Качвам снимката...','');
        const d=await api('products.php?ajax=upload_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({product_id:productId,image:base64})});
        if(d&&d.ok){
            showToast('Снимка добавена!','success');
            // Refresh detail drawer if open
            const detDr=document.getElementById('detailDr');
            if(detDr&&detDr.classList.contains('open')&&detDr.dataset.productId==productId){
                openProductDetail(productId);
            }
            // Update product card thumbnail in list
            const card=document.querySelector('.rc-card[data-id="'+productId+'"] .rc-thumb');
            if(card)card.innerHTML='<img src="'+d.image_url+'" style="width:100%;height:100%;object-fit:cover;border-radius:10px">';
            openImageStudio(productId);
        }else{
            showToast(d?.error||'Грешка при качване','error');
        }
    };
    reader.readAsDataURL(file);
}

// ─── PRODUCT DETAIL ───
async function openProductDetail(id){
    // If detail drawer already open, push current to stack
    const detDr = document.getElementById('detailDr');
    if (detDr && detDr.classList.contains('open')) {
        const curId = detDr.dataset.productId;
        if (curId && parseInt(curId) !== id) S.detailStack.push(parseInt(curId));
    } else {
        S.detailStack = [];
        openDrawer('detail');
    }
    detDr.dataset.productId = id;
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
        d.variations.forEach(v=>{
            const vThumb=v.image_url?`<img src="${v.image_url}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;margin-left:8px;flex-shrink:0">`:'';
            h+=`<div class="p-card" onclick="openProductDetail(${v.id})" style="margin-bottom:3px"><div class="stock-bar ${stockBar(v.total_stock,0)}"></div>${vThumb}<div class="p-info" style="margin-left:10px"><div class="p-name" style="font-size:11px">${esc(v.name)}</div><div class="p-meta">${v.size?`<span>${esc(v.size)}</span>`:''}${v.color?`<span>${esc(v.color)}</span>`:''}</div></div><div class="p-right"><div class="p-price">${fmtPrice(v.retail_price)}</div><div class="p-stock ${stockClass(v.total_stock,0)}">${v.total_stock} бр.</div></div></div>`;
        });
    }

    h+=`<div class="abtn-grid" style="margin-top:14px">`;
    if(CFG.canAdd)h+=`<button class="abtn" onclick="editProduct(${p.id})">✏️ Редактирай</button>`;
    h+=`<button class="abtn" onclick="openAIAnalysis(${p.id})">✦ AI Съвет</button>`;
    h+=`<button class="abtn" onclick="openImageStudio(${p.id})">✨ AI Снимка</button>`;
    h+=`<button class="abtn primary" onclick="location.href='sale.php?product=${p.id}'">💰 Продажба</button>`;
    h+=`<button class="abtn" onclick="openLabels(${p.id})">🏷 Етикет</button>`;
    if(CFG.canAdd)h+=`<button class="abtn" onclick="openProductHistoryS88(${p.id})">📜 История · Върни</button>`;
    h+=`</div>`;
    document.getElementById('detailBody').innerHTML=h;
}

// ─── AI ANALYSIS DRAWER ───
async function openAIAnalysis(id){
    openDrawer('ai');
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
    if(S.zxingReader){try{S.zxingReader.reset()}catch(e){}S.zxingReader=null}
    document.getElementById('scanLine').style.display='none';
}
// S82.CAPACITOR.21 — barcode scan с ZXing fallback за Android WebView
function loadZXing(){
    return new Promise((resolve, reject) => {
        if (window.ZXingBrowser) return resolve(window.ZXingBrowser);
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/umd/index.min.js';
        s.onload = () => resolve(window.ZXingBrowser);
        s.onerror = () => reject(new Error('ZXing не може да се зареди'));
        document.head.appendChild(s);
    });
}

async function handleScannedCode(code){
    clearInterval(S.barcodeInterval);
    S.barcodeInterval = null;
    if (S.zxingReader){
        try { S.zxingReader.reset(); } catch(e){}
        S.zxingReader = null;
    }
    playBeep();
    const d = await api(`products.php?ajax=barcode&code=${encodeURIComponent(code)}&store_id=${CFG.storeId}`);
    closeCamera();
    if (d && !d.error) openProductDetail(d.id);
    else showToast(`Баркод ${code} не е намерен`);
}

async function startBarcodeScanning(){
    const vid = document.getElementById('camVideo');

    // Option 1: native BarcodeDetector (Chrome desktop/mobile)
    if ('BarcodeDetector' in window){
        S.barcodeDetector = new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','qr_code','upc_a','upc_e']});
        S.barcodeInterval = setInterval(async () => {
            try {
                const codes = await S.barcodeDetector.detect(vid);
                if (codes.length > 0) handleScannedCode(codes[0].rawValue);
            } catch(e){}
        }, 300);
        return;
    }

    // Option 2: ZXing fallback (Android WebView, iOS Safari, др.)
    try {
        const ZX = await loadZXing();
        const hints = new Map();
        const formats = [
            ZX.BarcodeFormat.EAN_13, ZX.BarcodeFormat.EAN_8,
            ZX.BarcodeFormat.CODE_128, ZX.BarcodeFormat.CODE_39,
            ZX.BarcodeFormat.UPC_A, ZX.BarcodeFormat.UPC_E,
            ZX.BarcodeFormat.QR_CODE
        ];
        hints.set(ZX.DecodeHintType.POSSIBLE_FORMATS, formats);
        hints.set(ZX.DecodeHintType.TRY_HARDER, true);

        S.zxingReader = new ZX.BrowserMultiFormatReader(hints);
        // Ползваме наличния stream от video tag-а
        await S.zxingReader.decodeFromVideoElement(vid, (result, err) => {
            if (result){
                const code = result.getText();
                handleScannedCode(code);
            }
        });
    } catch(e){
        showToast('Баркод скенерът не може да се зареди: ' + (e.message || e), 'error');
        closeCamera();
    }
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

// ─── SWIPE NAVIGATION — DISABLED (S90.PRODUCTS.SPRINT_B G1) ───
// Removed: swipe between pages was accidentally triggering on normal scroll.
// Tihol confirmed: do NOT add page-level swipe-nav back to products.php.
// `.swipe-row` / `.h-scroll` CSS остава — те са horizontal-scroll карусели за
// content вътре в карта (доставчици, категории), не page navigation.



// ═══════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════
// CASCADE FILTERS + SIGNALS — New for redesign
// ═══════════════════════════════════════════════════════════

let _cascadeCat = 0;
let _cascadeSubcat = 0;
let _qfState = {};

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

// ═══════════════════════════════════════════════════════════
// WIZARD REWRITE — 8 стъпки, info бутони, voice-compatible
// ═══════════════════════════════════════════════════════════

// S92.WIZARD_REWRITE: 6 видими стъпки (Снимка → Цени → Класификация → Детайли → Вариации → Запис).
// Стъпка 3 е логически разделена на 4 sub-pages чрез S.wizSubStep (0..3). Type picker (step 0) остава скрит от индикатора.
const WIZ_LABELS=['Снимка','Цени','Класификация','Детайли','Вариации','Запис'];
// Кратки имена за header label (по-стегнати от WIZ_LABELS които се ползват за иконки).
const WIZ_LABELS_LONG=['Снимка / Име','Цени','Доставчик / Категория','Детайли','Размери / Цветове','Запис'];
// S92.WIZARD_REWRITE: getWizUiIndex(step, subStep) → индекс в WIZ_LABELS, или null ако stepper трябва да е скрит.
function getWizUiIndex(step, subStep){
    if(step===null||step===undefined)return null;
    subStep=subStep||0;
    // step 0 = type picker (Вид), без stepper.
    if(step===0)return null;
    // step 1 = legacy redirect, без stepper.
    if(step===1)return null;
    if(step===2)return 0; // Снимка / Име
    if(step===3){
        // sub 0 = Цени, sub 1 = Класификация, sub 2 = Детайли, sub 3 = Запис
        if(subStep<=0)return 1;
        if(subStep===1)return 2;
        if(subStep===2)return 3;
        return 5;
    }
    if(step===4)return 4; // Вариации
    if(step===5)return 5; // Preview/AI studio = последна стъпка ("Запис")
    if(step===6)return 5; // Print labels = последна стъпка
    return null;
}

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
    var html=text.replace(/\s*\*\s*$/,'&nbsp;<span style="color:#ef4444">*</span>');
    return '<label class="fl">'+html+' '+extra+'</label>';
}

// S90.PRODUCTS.SPRINT_B C4: AI auto-fill hint helpers.
// wizAIHint('name') → returns badge HTML if S.wizData._aiFilled.name === true.
// wizMarkAIFilled('name') → mark a field as AI-filled (call where AI sets the value).
// wizClearAIMark('name') → mark cleared (called from oninput when user edits).
function wizAIHint(key){
    if(!S.wizData||!S.wizData._aiFilled||!S.wizData._aiFilled[key])return '';
    return '<div class="wiz-ai-hint" data-aikey="'+key+'"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5L12 2z"/></svg>AI попълни — натисни за промяна</div>';
}
function wizMarkAIFilled(){
    if(!S.wizData)return;
    if(!S.wizData._aiFilled)S.wizData._aiFilled={};
    for(var i=0;i<arguments.length;i++)S.wizData._aiFilled[arguments[i]]=true;
}
function wizClearAIMark(key){
    if(!S.wizData||!S.wizData._aiFilled||!S.wizData._aiFilled[key])return;
    delete S.wizData._aiFilled[key];
    var el=document.querySelector('.wiz-ai-hint[data-aikey="'+key+'"]');
    if(el)el.style.display='none';
}

// S90.PRODUCTS.SPRINT_B D5: live duplicate detection докато Pesho пише името.
// Debounced 350ms. След 3+ символа AJAX → ако match score ≥ 0.85 → жълт banner с CTA.
var _wizDupeTimer=null;
function wizDupeCheckName(name){
    name=(name||'').trim();
    var banner=document.getElementById('wDupeBanner');
    if(!banner)return;
    if(_wizDupeTimer){clearTimeout(_wizDupeTimer);_wizDupeTimer=null;}
    if(name.length<3){banner.style.display='none';banner.innerHTML='';return;}
    // Не показваме banner ако вече сме потвърдили "не, продължи" за това име.
    if(S._wizDupeDismissed&&S._wizDupeDismissed===name.toLowerCase()){banner.style.display='none';return;}
    _wizDupeTimer=setTimeout(function(){
        var url='products.php?ajax=name_dupe_check&q='+encodeURIComponent(name);
        if(S.wizEditId)url+='&exclude_id='+S.wizEditId;
        api(url).then(function(matches){
            if(!Array.isArray(matches)||!matches.length){banner.style.display='none';banner.innerHTML='';return;}
            var top=matches[0];
            if(!top||top.score<0.85){banner.style.display='none';banner.innerHTML='';return;}
            // Все още се пише — текущото име може да е променено след заявката.
            var cur=(document.getElementById('wName')||{}).value||'';
            if(cur.trim().toLowerCase()!==name.toLowerCase())return;
            var priceTxt=(top.price>0)?(' ('+top.price.toFixed(2)+' '+CFG.currency+')'):'';
            var pct=Math.round(top.score*100);
            banner.style.display='block';
            banner.innerHTML='<div class="wiz-dupe-banner" style="margin-top:8px;padding:10px 12px;border-radius:12px;background:linear-gradient(135deg,rgba(245,158,11,0.16),rgba(245,158,11,0.06));border:1px solid rgba(245,158,11,0.42);color:#fcd34d">'
                +'<div style="display:flex;align-items:flex-start;gap:8px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:2px"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>'
                +'<div style="flex:1;min-width:0;font-size:11.5px;line-height:1.45">Близко до съществуващ артикул: <b style="color:#fff">'+_escDupe(top.name)+'</b>'+priceTxt+' · <span style="color:#fde68a">'+pct+'% близко</span>. Същото ли е?</div></div>'
                +'<div style="display:flex;gap:6px;margin-top:8px"><button type="button" onclick="wizDupeOpenExisting('+top.id+')" style="flex:1;padding:8px;border-radius:8px;background:rgba(99,102,241,0.18);border:1px solid rgba(139,92,246,0.45);color:#c4b5fd;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit">Да, отвори същото</button>'
                +'<button type="button" onclick="wizDupeDismiss()" style="flex:1;padding:8px;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);color:#cbd5e1;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit">Не, продължи</button></div>'
                +'</div>';
        }).catch(function(){});
    },350);
}
function _escDupe(s){return String(s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]});}
function wizDupeDismiss(){
    var nm=(document.getElementById('wName')||{}).value||'';
    S._wizDupeDismissed=nm.trim().toLowerCase();
    var banner=document.getElementById('wDupeBanner');
    if(banner){banner.style.display='none';banner.innerHTML='';}
}
function wizDupeOpenExisting(id){
    if(!id)return;
    closeWizard();
    if(typeof openProductDetail==='function'){openProductDetail(id);return;}
    if(typeof goScreenWithHistory==='function'){goScreenWithHistory('products',{id:id});return;}
    location.hash='#product='+id;
}

// ═══ S82.STUDIO.10: wizDraft cache — auto-saves the wizard state to
// localStorage so accidental refresh / browser close / phone lock doesn't
// lose the user's work. Up to ~5 MB per origin (enough for 30 photos
// downscaled to 80 KB each + form fields). Cleared after successful save.
function _wizDraftKey(){
    var tid = (typeof CFG !== 'undefined' && CFG.tenantId) ? CFG.tenantId : 'anon';
    return '_rms_wizDraft_' + tid;
}
function _wizSaveDraft(){
    // Don't persist drafts that are already saved (post-success step 6)
    // or too thin to be useful.
    if (S.wizStep === 6) return;
    if (S.wizSavedId) return;
    if (!S.wizStep || S.wizStep < 3) return;
    try {
        var draft = {
            t: Date.now(),
            wizStep: S.wizStep,
            wizType: S.wizType,
            wizData: S.wizData,
            wizEditId: S.wizEditId || null
        };
        localStorage.setItem(_wizDraftKey(), JSON.stringify(draft));
    } catch (e) {
        // QuotaExceededError most likely — strip photos and try once more.
        try {
            var trimmed = JSON.parse(JSON.stringify(draft || {}));
            if (trimmed && trimmed.wizData) {
                if (Array.isArray(trimmed.wizData._photos)) trimmed.wizData._photos = [];
                trimmed.wizData._photoDataUrl = null;
            }
            localStorage.setItem(_wizDraftKey(), JSON.stringify(trimmed));
        } catch(_) { console.warn('[wizDraft] save failed:', e); }
    }
}
function _wizClearDraft(){
    try { localStorage.removeItem(_wizDraftKey()); } catch(e) {}
}
function _wizGetDraft(){
    try {
        var s = localStorage.getItem(_wizDraftKey());
        if (!s) return null;
        var d = JSON.parse(s);
        // Drop drafts older than 7 days to avoid stale ghost prompts.
        if (!d || !d.t || Date.now() - d.t > 7 * 24 * 60 * 60 * 1000) {
            _wizClearDraft();
            return null;
        }
        return d;
    } catch(e) { return null; }
}
function _wizDescribeDraft(d){
    if (!d || !d.wizData) return '';
    var nm = d.wizData.name || 'без име';
    var ageMs = Date.now() - (d.t || 0);
    var ageMin = Math.round(ageMs / 60000);
    var age = ageMin < 1 ? 'току-що' : (ageMin < 60 ? ageMin + ' мин' : Math.round(ageMin/60) + ' ч');
    return '"' + nm + '" · ' + age + ' назад';
}
// Auto-save trigger: call _wizSaveDraft at the end of every renderWizard.
// Hook is set inside renderWizard itself.

// ─── MANUAL WIZARD ───
function openManualWizard(){
    var draft = _wizGetDraft();
    if (draft) {
        var msg = 'Намерих незавършен артикул ' + _wizDescribeDraft(draft) + '.\n\nДа продължа от където беше? (Откажи = започни наново)';
        if (confirm(msg)) {
            S.wizStep = draft.wizStep || 0;
            S.wizType = draft.wizType || null;
            S.wizData = draft.wizData || {};
            S.wizEditId = draft.wizEditId || null;
        } else {
            _wizClearDraft();
            S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;S.wizSubStep=0;
        }
    } else {
        S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;S.wizSubStep=0;
    }
    S._wizHistory=[];
    S.wizVoiceMode=false;
    document.getElementById('wizTitle').textContent='Нов артикул';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}

// ─── VOICE WIZARD — same steps, with skip buttons ───
function openVoiceWizard(){
    var draft = _wizGetDraft();
    if (draft) {
        var msg = 'Намерих незавършен артикул ' + _wizDescribeDraft(draft) + '.\n\nДа продължа от където беше? (Откажи = започни наново)';
        if (confirm(msg)) {
            S.wizStep = draft.wizStep || 0;
            S.wizType = draft.wizType || null;
            S.wizData = draft.wizData || {};
            S.wizEditId = draft.wizEditId || null;
        } else {
            _wizClearDraft();
            S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;S.wizSubStep=0;
        }
    } else {
        S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;S.wizSubStep=0;
    }
    S._wizHistory=[];
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
    if(priceMatch){S.wizData.retail_price=parseFloat(priceMatch[1].replace(',','.'));wizMarkAIFilled('retail_price');}
    const tl=text.toLowerCase();
    for(const s of CFG.suppliers){if(tl.includes(s.name.toLowerCase())){S.wizData.supplier_id=s.id;break}}
    for(const c of CFG.categories){if(tl.includes(c.name.toLowerCase())){S.wizData.category_id=c.id;wizMarkAIFilled('category');break}}
    if(!S.wizData.name){
        let name=text.replace(/(\d+[.,]?\d*)\s*(лева|лв|евро|€|eur)?/gi,'').trim();
        for(const s of CFG.suppliers)name=name.replace(new RegExp(s.name,'gi'),'');
        for(const c of CFG.categories)name=name.replace(new RegExp(c.name,'gi'),'');
        name=name.replace(/\s+/g,' ').trim();
        if(name.length>2){S.wizData.name=name;wizMarkAIFilled('name');}
    }
}

function closeWizard(){
    document.getElementById('wizModal').classList.remove('open');
    document.body.style.overflow='';
    S._wizHistory=[];
}

function wizGo(step,_skipHistory,subStep){
    wizCollectData();
    if(step===2&&!S.wizData._hasPhoto){step=3;}
    // S92.WIZARD_REWRITE: track current (step,subStep) tuple in history; default subStep=0 unless explicitly set.
    var prevTuple={step:S.wizStep, sub:S.wizSubStep||0};
    var nextSub=(typeof subStep==='number')?subStep:(step===3?(S.wizSubStep||0):0);
    if(!_skipHistory && (S.wizStep!==step || (step===3 && (S.wizSubStep||0)!==nextSub))){
        if(!Array.isArray(S._wizHistory))S._wizHistory=[];
        S._wizHistory.push(prevTuple);
        if(S._wizHistory.length>32)S._wizHistory.shift();
    }
    S.wizStep=step;
    S.wizSubStep=nextSub;
    renderWizard();
    if(S.wizVoiceMode)setTimeout(()=>voiceForStep(step),400);
}
// S92.WIZARD_REWRITE: navigate within step 3 sub-pages (0=Цени, 1=Класификация, 2=Детайли, 3=Запис).
function wizSubGo(sub){
    wizGo(3, false, Math.max(0, Math.min(3, sub|0)));
}
// S90.PRODUCTS.SPRINT_B C5: back arrow in wizard header — пазим стъпки в history.
// S92.WIZARD_REWRITE: history items могат да са number (legacy) или {step,sub} (нов формат).
function wizPrev(){
    if(Array.isArray(S._wizHistory)&&S._wizHistory.length){
        var prev=S._wizHistory.pop();
        if(prev && typeof prev==='object'){
            wizGo(prev.step|0, true, prev.sub|0);
        }else{
            wizGo(prev|0, true);
        }
        return;
    }
    if(S.wizStep>0){
        wizGo(Math.max(0,S.wizStep-1),true);
        return;
    }
    closeWizard();
}


// ═══ S73.B.6: Matrix Overlay Functions ═══
function autoMin(qty){if(qty<=0)return 0;if(qty<=3)return 1;return Math.round(qty/2.5)}
function openMxOverlay(){
  setTimeout(function(){
    var ov=document.getElementById('mxOverlay');
    if(!ov||ov._mxFocusInit)return;
    ov._mxFocusInit=true;
    ov.addEventListener('focusin',function(e){
      if(e.target&&e.target.tagName==='INPUT'){ov.classList.add('mx-focused');}
    });
    ov.addEventListener('focusout',function(){
      setTimeout(function(){
        if(!ov.querySelector('input:focus')){ov.classList.remove('mx-focused');}
      },80);
    });
  },120);

  try{history.pushState({modal:'matrix'},'','#matrix')}catch(_){}
  S._mxSnapshot=JSON.stringify(S.wizData._matrix||{});
  // Намираме axes с стойности — първите 2 с данни стават rows × cols
  var activeAxes=(S.wizData.axes||[]).filter(function(a){return a.values&&a.values.length>0});
  if(!activeAxes.length){showToast('Избери стойности първо','error');return}
  var rowAx=activeAxes[0];
  var colAx=activeAxes[1]||null;
  // Редът на axes в S.wizData.axes е приоритет — първият = rows, вторият = cols
  var sizes=rowAx.values;
  var colors=colAx?colAx.values:['—'];
  var isColorCol=colAx&&/цвят|color|десен/i.test(colAx.name);
  var _rowLbl=esc(rowAx.name);var _colLbl=colAx?esc(colAx.name):'';
  document.getElementById('mxSubtitle').innerHTML=colAx?('<b>'+sizes.length+'</b> '+_rowLbl+' × <b>'+colors.length+'</b> '+_colLbl+' = <b>'+(sizes.length*colors.length)+'</b> клетки'):('<b>'+sizes.length+'</b> '+_rowLbl);
  // Build thead
  var thead='<tr><th class="mx-corner"></th>';
  colors.forEach(function(c){var dot='';if(isColorCol){var cc=CFG.colors.find(function(x){return x.name===c});if(cc)dot='<span class="v-dot" style="background:'+cc.hex+'"></span>'}thead+='<th class="mx-head-cell">'+dot+esc(c==='—'?'':c)+'</th>'});
  thead+='</tr>';
  document.getElementById('mxThead').innerHTML=thead;
  // Build tbody
  var tb='';
  sizes.forEach(function(sz,si){
    tb+='<tr><th class="mx-row-head">'+esc(sz)+'</th>';
    colors.forEach(function(c,ci){
      var key='mx_'+si+'_'+ci;
      var cell=(S.wizData._matrix&&S.wizData._matrix[key])||{};
      var q=cell.qty!==undefined?cell.qty:'';
      var m=cell.min!==undefined?cell.min:'';
      var has=q!==''&&q!==null&&q>0;
      // S82.STUDIO.10: per-cell МКП stepper restored — user wants the auto-calc
       // "type 5 → MKП auto = 2" with +/- adjustment, same as before. Works for both
       // 2-axis matrix and single-axis (only colors / only sizes).
      tb+='<td class="mx-cell'+(has?' has-value':'')+'"><div class="mx-cell-inputs"><input type="number" class="mx-cell-qty" data-key="'+key+'" data-t="qty" value="'+q+'" min="0" inputmode="numeric" placeholder="0 бр."><div class="mx-cell-lbl">МКП</div><div class="mx-cell-min-row"><button class="mx-min-step" onclick="mxStepMin(\''+key+'\',-1)">▼</button><input type="number" class="mx-cell-min" data-key="'+key+'" data-t="min" value="'+m+'" min="0" inputmode="numeric" placeholder="0"><button class="mx-min-step" onclick="mxStepMin(\''+key+'\',1)">▲</button></div></div></td>';
    });
    tb+='</tr>';
  });
  document.getElementById('mxTbody').innerHTML=tb;
  // Attach handlers
  document.querySelectorAll('.mx-cell-qty').forEach(function(inp){
    inp.addEventListener('input',function(){
      var key=inp.dataset.key;var v=parseInt(inp.value)||0;
      if(!S.wizData._matrix)S.wizData._matrix={};
      if(!S.wizData._matrix[key])S.wizData._matrix[key]={};
      S.wizData._matrix[key].qty=v>0?v:'';
      if(v>0){var mn=autoMin(v);S.wizData._matrix[key].min=mn;var mi=document.querySelector('.mx-cell-min[data-key="'+key+'"]');if(mi)mi.value=mn;}
      else{S.wizData._matrix[key].min='';var mi2=document.querySelector('.mx-cell-min[data-key="'+key+'"]');if(mi2)mi2.value='';}
      var td=inp.closest('.mx-cell');if(v>0)td.classList.add('has-value');else td.classList.remove('has-value');
      mxUpdateStats();
    });
  });
  document.querySelectorAll('.mx-cell-min').forEach(function(inp){
    inp.addEventListener('input',function(){
      var key=inp.dataset.key;var v=parseInt(inp.value)||0;
      if(!S.wizData._matrix)S.wizData._matrix={};
      if(!S.wizData._matrix[key])S.wizData._matrix[key]={};
      S.wizData._matrix[key].min=v>0?v:'';
      mxUpdateStats();
    });
  });
  mxUpdateStats();
  document.getElementById('mxOverlay').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeMxOverlay(){document.getElementById('mxOverlay').classList.remove('open');document.body.style.overflow=''}
function mxCancel(){if(S._mxSnapshot!==undefined){S.wizData._matrix=JSON.parse(S._mxSnapshot);S._mxSnapshot=undefined}closeMxOverlay();renderWizard();if(navigator.vibrate)navigator.vibrate(5)}
function mxStepMin(key,dir){var inp=document.querySelector('.mx-cell-min[data-key="'+key+'"]');if(!inp)return;var v=Math.max(0,(parseInt(inp.value)||0)+dir);inp.value=v;if(!S.wizData._matrix)S.wizData._matrix={};if(!S.wizData._matrix[key])S.wizData._matrix[key]={};S.wizData._matrix[key].min=v>0?v:'';mxUpdateStats();if(navigator.vibrate)navigator.vibrate(3)}
function mxFillAll(qty){
  var szAx=null,clAx=null;(S.wizData.axes||[]).forEach(function(ax){var n=ax.name.toLowerCase();if(!szAx&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))szAx=ax;else if(!clAx&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))clAx=ax});
  if(!szAx||!clAx)return;
  if(!S.wizData._matrix)S.wizData._matrix={};
  szAx.values.forEach(function(_,si){clAx.values.forEach(function(_,ci){var key='mx_'+si+'_'+ci;S.wizData._matrix[key]={qty:qty,min:autoMin(qty)}})});
  openMxOverlay();
  if(navigator.vibrate)navigator.vibrate(10);
}
function mxClear(){S.wizData._matrix={};openMxOverlay();if(navigator.vibrate)navigator.vibrate(12)}
function mxUpdateStats(){var f=0,t=0,ms=0;var m=S.wizData._matrix||{};Object.keys(m).forEach(function(k){var c=m[k]||{};var q=parseInt(c.qty)||0;var mn=parseInt(c.min)||0;if(q>0)f++;t+=q;ms+=mn});document.getElementById('mxStatCells').textContent=f;document.getElementById('mxStatTotal').textContent=t;document.getElementById('mxStatMin').textContent=ms}
function mxDone(){S._mxSnapshot=undefined;closeMxOverlay();renderWizard();if(navigator.vibrate)navigator.vibrate([5,30,10])}

async function renderWizard(){
    if(S.wizStep===6)wizCollectData();
    // S82.STUDIO.10: persist draft on every render (covers axes/matrix/photo/form changes).
    if (typeof _wizSaveDraft === 'function') _wizSaveDraft();
    let sb='';
    // S92.WIZARD_REWRITE: 6-step indicator + sub-step aware uiIdx.
    const uiIdx=getWizUiIndex(S.wizStep, S.wizSubStep);
    for(let i=0;i<6;i++){
        let cls=uiIdx!==null&&i<uiIdx?'done':(i===uiIdx?'active':'');
        sb+='<div class="wiz-step '+cls+'"></div>';
    }
    document.getElementById('wizSteps').innerHTML=sb;
    const _lbl=uiIdx!==null?(WIZ_LABELS_LONG[uiIdx]||WIZ_LABELS[uiIdx]):'';
    const _num=uiIdx!==null?(uiIdx+1)+' · ':'';
    document.getElementById('wizLabel').innerHTML=_num+'<b>'+_lbl+'</b>';
    document.getElementById('wizBody').innerHTML=renderWizPage(S.wizStep);
    if(S._lastWizStep!==S.wizStep){document.getElementById('wizBody').scrollTop=0;S._lastWizStep=S.wizStep;}
    // S70: Init HSL picker if on color tab
    setTimeout(function(){if(document.getElementById('wizHslCanvas'))wizInitHslPicker()},50);
    // S75_typeguard: блокира input на всички полета ако wizType е null
    if(S.wizStep===3){
        setTimeout(function(){
            document.querySelectorAll('#wizBody input[type="text"],#wizBody input[type="number"],#wizBody select').forEach(function(el){
                if(el.id==='wNewUnit')return;
                el.removeEventListener('focus',wizTypeGuard);
                el.addEventListener('focus',wizTypeGuard);
            });
        },60);
    }
    // S88B-1: Step 3 init — restore values, set _selectedId on search dropdowns, load subcats, compute markup.
    if(S.wizStep===3){
        const _el=id=>document.getElementById(id);
        if(_el('wName')&&S.wizData.name)_el('wName').value=S.wizData.name;
        if(_el('wCode')&&S.wizData.code)_el('wCode').value=S.wizData.code;
        if(_el('wPrice')&&S.wizData.retail_price)_el('wPrice').value=S.wizData.retail_price;
        if(_el('wWprice')&&S.wizData.wholesale_price)_el('wWprice').value=S.wizData.wholesale_price;
        if(_el('wCostPrice')&&S.wizData.cost_price)_el('wCostPrice').value=S.wizData.cost_price;
        if(_el('wBarcode')&&S.wizData.barcode)_el('wBarcode').value=S.wizData.barcode;
        if(_el('wColor')&&S.wizData.color)_el('wColor').value=S.wizData.color;
        if(_el('wSize')&&S.wizData.size)_el('wSize').value=S.wizData.size;
        if(_el('wOrigin')&&S.wizData.origin_country)_el('wOrigin').value=S.wizData.origin_country;
        var supEl=_el('wSupDD');
        if(supEl&&S.wizData.supplier_id){supEl._selectedId=S.wizData.supplier_id;}
        var catEl=_el('wCatDD');
        if(catEl&&S.wizData.category_id){catEl._selectedId=S.wizData.category_id;}
        if(S.wizData.category_id&&typeof wizLoadSubcats==='function'){
            await wizLoadSubcats(S.wizData.category_id);
        }
        if(typeof wizUpdateMarkup==='function')wizUpdateMarkup();
        // S90.PRODUCTS.SPRINT_B D3: prefetch на supplier-филтрираните категории
        // веднага след render — иначе първото отваряне на dropdown показва пълния списък.
        if(S.wizData.supplier_id&&typeof wizPrefetchSupplierCats==='function'){
            wizPrefetchSupplierCats(S.wizData.supplier_id);
        }
    }
    // Legacy supplier→category cascade (kept defensively for the old #wSup/#wCat selects if any code path still renders them).
    if(false&&S.wizStep===3){
        const wSup=document.getElementById('wSup');
        const wCat=document.getElementById('wCat');
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
        };if(S.wizData.supplier_id)await wSup.onchange()}
        // When category changes → reload subcategories
        if(wCat){wCat.onchange=async function(){
            const id=this.value;const sel=document.getElementById('wSubcat');
            sel.innerHTML='<option value="">\u2014 Няма \u2014</option>';
            if(!id)return;
            const d=await api('products.php?ajax=subcategories&parent_id='+id);
            if(d&&d.length)d.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)});
            // Re-select saved subcategory
            if(S.wizData.subcategory_id)sel.value=S.wizData.subcategory_id;
        };
        // S73: always trigger subcategory load if category selected (after supplier rebuild)
        if(S.wizData.category_id)await wCat.onchange();
        }
    }
}

function renderWizPage(step){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo('+(step+1)+')" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // ═══ STEP 0: ВИД (S88B-1) — реален UI с 2 cards + Копирай от последния ═══
    if(step===0){
        var hasLast=false;try{hasLast=!!localStorage.getItem('_rms_lastWizProductFields');}catch(e){}
        var copyBtn = hasLast
            ? '<button type="button" onclick="wizCopyPrevProductFull()" style="width:100%;padding:13px;border-radius:14px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 0 14px rgba(139,92,246,0.18),inset 0 1px 0 rgba(255,255,255,0.05);margin-top:14px">📋 Копирай от последния</button>'
            : '<div style="margin-top:14px;padding:10px;text-align:center;font-size:10px;color:#64748b;border-radius:10px;background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.06)">📋 Копирай от последния — налично след първия запис</div>';
        return '<div class="wiz-page active" style="padding:18px 14px">'+
            '<div style="text-align:center;font-size:15px;font-weight:600;color:#fff;margin-bottom:18px;letter-spacing:0.01em">Какво искаш да добавиш?</div>'+
            '<button type="button" onclick="wizPickType(\'single\')" style="width:100%;padding:20px 16px;margin-bottom:12px;border-radius:18px;background:linear-gradient(180deg,rgba(59,130,246,0.16),rgba(37,99,235,0.06));border:1px solid rgba(59,130,246,0.5);color:#fff;font-family:inherit;cursor:pointer;display:flex;align-items:center;gap:14px;text-align:left;box-shadow:0 0 18px rgba(59,130,246,0.22),inset 0 1px 0 rgba(255,255,255,0.06);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)">'+
                '<div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,0.18);border:1px solid rgba(59,130,246,0.4);display:flex;align-items:center;justify-content:center;flex-shrink:0">'+
                    '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#93c5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>'+
                '</div>'+
                '<div style="flex:1;min-width:0">'+
                    '<div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:3px">Единичен</div>'+
                    '<div style="font-size:11px;color:#bfdbfe;line-height:1.4">Един артикул без размер/цвят</div>'+
                '</div>'+
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#93c5fd" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>'+
            '</button>'+
            '<button type="button" onclick="wizPickType(\'variant\')" style="width:100%;padding:20px 16px;border-radius:18px;background:linear-gradient(180deg,rgba(217,70,239,0.14),rgba(168,85,247,0.06));border:1px solid rgba(217,70,239,0.5);color:#fff;font-family:inherit;cursor:pointer;display:flex;align-items:center;gap:14px;text-align:left;box-shadow:0 0 18px rgba(217,70,239,0.22),inset 0 1px 0 rgba(255,255,255,0.06);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)">'+
                '<div style="width:48px;height:48px;border-radius:14px;background:rgba(217,70,239,0.18);border:1px solid rgba(217,70,239,0.4);display:flex;align-items:center;justify-content:center;flex-shrink:0">'+
                    '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f0abfc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="9" height="9" rx="2"/><rect x="13" y="2" width="9" height="9" rx="2"/><rect x="2" y="13" width="9" height="9" rx="2"/><rect x="13" y="13" width="9" height="9" rx="2"/></svg>'+
                '</div>'+
                '<div style="flex:1;min-width:0">'+
                    '<div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:3px">С вариации</div>'+
                    '<div style="font-size:11px;color:#fbcfe8;line-height:1.4">Размер и/или цвят (повече варианти)</div>'+
                '</div>'+
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f0abfc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>'+
            '</button>'+
            copyBtn+
            '<div style="display:flex;gap:8px;margin-top:18px">'+
                '<button type="button" onclick="closeWizard()" style="flex:1;height:42px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Отказ</button>'+
            '</div>'+
        '</div>';
    }

    // ═══ STEP 1: defensive redirect to STEP 0 (legacy paths) ═══
    if(step===1){
        setTimeout(function(){wizGo(0)},0);
        return '<div class="wiz-page active"><div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:12px">Зареждане...</div></div>';
    }

    // ═══ STEP 2: СНИМКА (S88B-1) — conditional render single/variant-B1/variant-B2 ═══
    if(step===2){
        return renderWizPhotoStep();
    }

    // ═══ STEP 3: split на 4 sub-pages (S92.WIZARD_REWRITE) ═══
    // sub 0 = Цени, sub 1 = Класификация, sub 2 = Детайли, sub 3 = Идентификация / Запис.
    // Всяко sub-page render-ва само своята част, всички DOM IDs (wName, wPrice, wSupDD, wCatDD, wCode, wBarcode, ...)
    // се запазват за wizCollectData/wizSave compatibility. Назад/Напред между sub-pages чрез wizSubGo().
    if(step===3){
        const sub=S.wizSubStep||0;
        const nm=S.wizData.name||'';
        const pr=S.wizData.retail_price||'';
        const bc=S.wizData.barcode||'';
        const cm=S.wizData.composition||'';
        const qt=(S.wizData.quantity===undefined?1:S.wizData.quantity);
        const un=S.wizData.unit||'бр';
        const isSingle=(S.wizType==='single');
        const nextStep=(S.wizType==='variant')?4:5;
        let hasLast=false;try{hasLast=!!(localStorage.getItem('_rms_lastWizProducts'));}catch(e){}

        const mic=(f)=>'<button type="button" class="wiz-mic" onclick="wizMic(\''+f+'\')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></button>';
        // S88B-1 / Task D: ↻ "като предния" per-field copy button. Hidden when no previous artikel exists.
        const cpyHas=(function(){try{return !!localStorage.getItem('_rms_lastWizProductFields')}catch(e){return false}})();
        const cpy=(f)=>cpyHas?'<button type="button" onclick="wizCopyFieldFromPrev(\''+f+'\')" title="Копирай това поле от последния" style="width:30px;height:36px;border-radius:9px;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);color:#a5b4fc;font-size:14px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">↻</button>':'';

        const units=(CFG&&CFG.units&&CFG.units.length)?CFG.units:['бр','сет','кг','м','л'];
        // S76.2d: dropdown + inline add
        var _unitOpts='';units.forEach(function(u){_unitOpts+='<option value="'+u+'" '+(u===un?'selected':'')+'>'+u+'</option>'});
        var unitChips='<div style="display:flex;gap:8px;align-items:stretch"><div style="flex:1;position:relative"><select class="fc" id="wUnit" onchange="S.wizData.unit=this.value" style="width:100%;appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:32px;cursor:pointer;font-family:inherit">'+_unitOpts+'</select><svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#a5b4fc" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div><button type="button" onclick="toggleInl(\'wizUnitAdd\')" style="padding:0 14px;height:auto;border-radius:10px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:0.02em;white-space:nowrap;box-shadow:0 0 10px rgba(139,92,246,0.18),inset 0 1px 0 rgba(255,255,255,0.05);display:inline-flex;align-items:center;gap:5px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Добави</button></div><div id="wizUnitAdd" style="display:none;margin-top:8px"><div style="display:flex;gap:6px;align-items:stretch"><input type="text" class="fc" id="wNewUnit" placeholder="напр. метър, кг..." style="flex:1;font-family:inherit"><button type="button" onclick="wizAddUnitFromChip()" style="padding:0 14px;border-radius:10px;background:linear-gradient(180deg,rgba(34,197,94,0.12),rgba(22,163,74,0.05));border:1px solid rgba(34,197,94,0.4);color:#86efac;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;box-shadow:0 0 10px rgba(34,197,94,0.15),inset 0 1px 0 rgba(255,255,255,0.04);display:inline-flex;align-items:center;gap:5px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Запази</button></div></div>';

        const _hasPhoto=!!S.wizData._photoDataUrl;
        // S82.COLOR.4: photo mode toggle (single | multi). Multi is meaningful only for variant type.
        var _photoMode = S.wizData._photoMode;
        if (!_photoMode) {
            try { _photoMode = localStorage.getItem('_rms_photoMode') || 'single'; } catch(e) { _photoMode = 'single'; }
            S.wizData._photoMode = _photoMode;
        }
        if (S.wizType !== 'variant') _photoMode = 'single';
        var _photoModeToggle = '';
        if (S.wizType === 'variant') {
            _photoModeToggle =
                '<div class="photo-mode-toggle">' +
                    '<button type="button" class="pmt-opt' + (_photoMode==='single'?' active':'') + '" onclick="wizSetPhotoMode(\'single\')"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Една снимка</button>' +
                    '<button type="button" class="pmt-opt' + (_photoMode==='multi'?' active':'') + '" onclick="wizSetPhotoMode(\'multi\')"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Различни цветове</button>' +
                '</div>';
        }
        var photoBlock = '';
        if (_photoMode === 'multi') {
            var _photos = Array.isArray(S.wizData._photos) ? S.wizData._photos : [];
            var _gridH = '<div class="photo-multi-grid">';
            _photos.forEach(function(p, i){
                var conf = (p.ai_confidence === null || p.ai_confidence === undefined) ? null : p.ai_confidence;
                var confLabel = '';
                var confCls = 'photo-color-conf';
                if (conf === null) { confLabel = 'AI...'; confCls += ' detecting'; }
                else if (conf >= 0.75) { confLabel = Math.round(conf*100) + '%'; }
                else if (conf >= 0.5)  { confLabel = Math.round(conf*100) + '%'; confCls += ' warn'; }
                else { confLabel = '?'; confCls += ' warn'; }
                var swHex = p.ai_hex || '#666';
                var nm = (p.ai_color || '').replace(/"/g,'&quot;');
                _gridH +=
                    '<div class="photo-multi-cell">' +
                        '<div class="photo-multi-thumb">' +
                            '<img class="ph-img" src="' + p.dataUrl + '" alt="">' +
                            '<span class="ph-num">' + (i+1) + '</span>' +
                            '<button type="button" class="ph-rm" onclick="wizPhotoMultiRemove(' + i + ')">×</button>' +
                        '</div>' +
                        '<div class="photo-color-input">' +
                            '<span class="photo-color-swatch" style="background:' + swHex + '"></span>' +
                            '<input type="text" value="' + nm + '" placeholder="цвят..." oninput="wizPhotoSetColor(' + i + ',this.value)">' +
                            '<span class="' + confCls + '">' + confLabel + '</span>' +
                        '</div>' +
                    '</div>';
            });
            _gridH +=
                '<div class="photo-multi-cell">' +
                    '<div class="photo-empty-add" onclick="wizPhotoMultiPick()">' +
                        '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                        '<span>Добави</span>' +
                    '</div>' +
                '</div>';
            _gridH += '</div>';
            var _info = '<div class="photo-multi-info">Снимки по цвят: <b>' + _photos.length + '</b> · AI разпознава цветовете автоматично</div>';
            photoBlock = '<div class="v4-pz">' + _photoModeToggle + _info + _gridH + '</div>';
        } else {
            var _photoContent = _hasPhoto
                ? '<img src="' + S.wizData._photoDataUrl + '" onclick="document.getElementById(\'filePickerInput\').click()" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:14px;cursor:pointer;margin-bottom:10px">'
                : '<div class="v4-pz-top"><div class="v4-pz-ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div><div style="flex:1;min-width:0"><div class="v4-pz-title">Снимай артикула</div><div class="v4-pz-sub">AI анализира снимката</div></div></div>';
            var _photoBtns = '<div class="v4-pz-btns"><button type="button" onclick="document.getElementById(\'photoInput\').click()" class="v4-pz-btn primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Снимай</button><button type="button" onclick="document.getElementById(\'filePickerInput\').click()" class="v4-pz-btn sec"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Галерия</button></div>';
            var _photoTips = '<div class="v4-pz-tips"><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Равна светла повърхност</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Без други предмети</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Добро осветление</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Ясна, неразмазана</span></div>';
            photoBlock = '<div class="v4-pz">' + _photoModeToggle + _photoContent + _photoBtns + _photoTips + '</div>';
        }

        // S88B-1: Step 3 copyPrev card stub removed. Bulk-copy lives on Step 0 (wizCopyPrevProductFull) and per-field ↻ buttons (Task D).
        const copyPrev='';

        const _ttCls=S.wizType?'':' needs-select';const _ttWarn=S.wizType?'':'<div class="v4-tt-warn">▲ Избери първо тип на артикула</div>';const typeToggle='<div class="v4-type-toggle'+_ttCls+'"><button type="button" class="v4-tt-opt'+(S.wizType==="single"?" active":"")+'" onclick="wizSwitchType(\'single\')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/></svg><span>Единичен</span></button><button type="button" class="v4-tt-opt'+(S.wizType==="variant"?" active":"")+'" onclick="wizSwitchType(\'variant\')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="9" height="9" rx="2"/><rect x="13" y="2" width="9" height="9" rx="2"/><rect x="2" y="13" width="9" height="9" rx="2"/><rect x="13" y="13" width="9" height="9" rx="2"/></svg><span>С варианти</span></button></div>'+_ttWarn;

        // S82.COLOR.4: prominent AI Studio CTA + _aiState/_aiCTA*/_aiAutoTrigger removed (replaced by photo-mode toggle + step 5 final prompt).
        var _mqVal=(S.wizData.min_quantity===undefined||S.wizData.min_quantity===null||S.wizData.min_quantity==='')?1:S.wizData.min_quantity;
        const qtyBlock=isSingle
            ? '<div class="fg">'+fieldLabel('Брой','name')+'<div style="display:flex;border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;height:42px"><button type="button" onclick="var e=document.getElementById(\'wSingleQty\');e.value=Math.max(0,(parseInt(e.value)||0)-1);S.wizData.quantity=parseInt(e.value)||0" style="width:46px;background:rgba(99,102,241,0.08);border:none;border-right:1px solid rgba(255,255,255,0.08);color:#a5b4fc;font-size:18px;cursor:pointer">−</button><input type="number" inputmode="numeric" id="wSingleQty" value="'+qt+'" oninput="S.wizData.quantity=parseInt(this.value)||0" style="flex:1;background:transparent;border:none;color:#fff;font-size:15px;font-weight:500;text-align:center;outline:none"><button type="button" onclick="var e=document.getElementById(\'wSingleQty\');e.value=(parseInt(e.value)||0)+1;S.wizData.quantity=parseInt(e.value)||0" style="width:46px;background:rgba(99,102,241,0.08);border:none;border-left:1px solid rgba(255,255,255,0.08);color:#a5b4fc;font-size:18px;cursor:pointer">+</button></div></div>'+'<div class="fg">'+fieldLabel('Мин. количество','name','<span class="hint">(за сигнали)</span>')+'<div style="display:flex;border:1px solid rgba(245,158,11,0.15);border-radius:12px;overflow:hidden;height:42px;background:rgba(245,158,11,0.03)"><button type="button" onclick="var e=document.getElementById(\'wMinQty\');e.value=Math.max(0,(parseInt(e.value)||0)-1);S.wizData.min_quantity=parseInt(e.value)||0" style="width:46px;background:rgba(245,158,11,0.08);border:none;border-right:1px solid rgba(245,158,11,0.12);color:#fbbf24;font-size:18px;cursor:pointer">−</button><input type="number" inputmode="numeric" id="wMinQty" value="'+_mqVal+'" oninput="S.wizData.min_quantity=parseInt(this.value)||0" style="flex:1;background:transparent;border:none;color:#fff;font-size:15px;font-weight:500;text-align:center;outline:none"><button type="button" onclick="var e=document.getElementById(\'wMinQty\');e.value=(parseInt(e.value)||0)+1;S.wizData.min_quantity=parseInt(e.value)||0" style="width:46px;background:rgba(245,158,11,0.08);border:none;border-left:1px solid rgba(245,158,11,0.12);color:#fbbf24;font-size:18px;cursor:pointer">+</button></div>'+cpy('min_quantity')+'</div>'
            : '';

        const stickyFooter='<div style="padding:16px 12px;margin:24px 0 64px;background:rgba(10,11,20,0.95);border-top:1px solid rgba(99,102,241,0.15);display:flex;gap:8px"><button type="button" onclick="closeWizard()" style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);font-size:16px;cursor:pointer" title="Назад">‹</button><button type="button" onclick="showToast(\'Печат — S73.B.5\')" style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;cursor:pointer;display:flex;align-items:center;justify-content:center" title="Печат"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button><button type="button" onclick="wizSave()" style="flex:1;height:42px;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);border:1px solid #16a34a;color:#fff;font-size:12px;font-weight:500;cursor:pointer;box-shadow:0 4px 14px rgba(22,163,74,0.4);display:flex;align-items:center;justify-content:center;gap:6px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Запази</button><button type="button" onclick="wizGo('+nextStep+')" style="flex:1;height:42px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#4338ca);border:1px solid #6366f1;color:#fff;font-size:12px;font-weight:500;cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.4);display:flex;align-items:center;justify-content:center;gap:5px">Напред ›</button></div>';

        return '<div class="wiz-page active">'+
            typeToggle+
            copyPrev+
            '<div class="glass v4-glass-pro" style="padding:18px 16px 16px;margin-bottom:14px">'+
              '<span class="shine shine-top"></span><span class="shine shine-bottom"></span>'+
              '<span class="glow glow-top"></span><span class="glow glow-bottom"></span>'+
              '<span class="glow glow-bright glow-top"></span><span class="glow glow-bright glow-bottom"></span>'+
              // S88B-1: photoBlock moved to Step 2. Step 3 = data fields per audit §10.3 layout.
              // Име
              '<div class="fg">'+fieldLabel('Име *','name')+'<div style="display:flex;gap:6px;align-items:center"><input type="text" class="fc" id="wName" oninput="S.wizData.name=this.value.trim();wizClearAIMark(\'name\');wizDupeCheckName(this.value)" value="'+esc(nm)+'" placeholder="напр. Дънки Mustang син деним" style="flex:1">'+mic('name')+'</div>'+wizAIHint('name')+'<div id="wDupeBanner" style="display:none"></div></div>'+
              // S92.PRODUCTS.PRICE_LAYOUT: 3 цени pa отделни редове (Z Flip6 ~373px не побираше).
              // Row 1: Цена дребно (full). Row 2: Доставна + ПЕЧАЛБА (50/50, mathematically свързани).
              // Row 3: Цена едро (full). Тихол: "цена на дребно цена на едро да не са едно до друго".
              // Цена дребно — full row
              '<div class="fg">'+fieldLabel('Цена дребно *','price')+'<div style="display:flex;gap:4px;align-items:center"><input type="number" step="0.01" inputmode="decimal" class="fc" id="wPrice" oninput="S.wizData.retail_price=parseFloat(this.value)||0;wizClearAIMark(\'retail_price\');wizUpdateMarkup()" value="'+pr+'" placeholder="0.00" style="flex:1;min-width:0">'+mic('retail_price')+cpy('retail_price')+'</div>'+wizAIHint('retail_price')+'</div>'+
              // Доставна + ПЕЧАЛБА % row (margin = (retail-cost)/retail*100, информативно — не се записва)
              '<div style="display:flex;gap:8px;align-items:flex-end">'+
                '<div class="fg" style="flex:1;min-width:0">'+fieldLabel('Доставна цена','cost_price','<span class="hint">(на доставчик)</span>')+'<div style="display:flex;gap:4px;align-items:center"><input type="number" step="0.01" inputmode="decimal" class="fc" id="wCostPrice" oninput="S.wizData.cost_price=parseFloat(this.value)||0;wizClearAIMark(\'cost_price\');wizUpdateMarkup()" value="'+(S.wizData.cost_price||'')+'" placeholder="0.00" style="flex:1;min-width:0">'+mic('cost_price')+cpy('cost_price')+'</div>'+wizAIHint('cost_price')+'</div>'+
                '<div class="fg" style="flex:1;min-width:0">'+fieldLabel('ПЕЧАЛБА %','markup_pct','<span class="hint">(не се записва)</span>')+'<div style="display:flex;gap:4px;align-items:center"><input type="number" step="1" inputmode="numeric" class="fc" id="wMarkupPct" oninput="wizApplyMarkup()" '+(parseFloat(S.wizData.cost_price)>0?'':'disabled')+' placeholder="'+(parseFloat(S.wizData.cost_price)>0?'auto':'(въведи доставна)')+'" style="flex:1;min-width:0">'+cpy('markup_pct')+'</div></div>'+
              '</div>'+
              // Цена едро — full row (скрит при skipWholesale)
              '<div class="fg"'+(CFG.skipWholesale?' style="display:none"':'')+'>'+fieldLabel('Цена едро','wholesale')+'<div style="display:flex;gap:4px;align-items:center"><input type="number" step="0.01" inputmode="decimal" class="fc" id="wWprice" oninput="S.wizData.wholesale_price=parseFloat(this.value)||0;wizClearAIMark(\'wholesale_price\')" value="'+(S.wizData.wholesale_price||'')+'" placeholder="0.00" style="flex:1;min-width:0">'+mic('wholesale_price')+'</div>'+wizAIHint('wholesale_price')+'</div>'+
              // Брой + Мин количество (single only)
              qtyBlock+
              // Доставчик dropdown (with fuzzy search + inline add)
              '<div class="fg" style="position:relative">'+fieldLabel('Доставчик','supplier')+
                '<div style="display:flex;gap:6px;align-items:center">'+
                  '<input type="text" class="fc" id="wSupDD" autocomplete="off" value="'+esc((function(){if(!S.wizData.supplier_id)return"";var s=(CFG.suppliers||[]).find(function(x){return x.id==S.wizData.supplier_id});return s?s.name:""})())+'" placeholder="търси или избери..." style="flex:1" '+
                    'onfocus="this._focused=true;wizSearchDropdown(\'wSupDD\',\'wSupDDList\',CFG.suppliers||[])" '+
                    'onblur="setTimeout(function(){var l=document.getElementById(\'wSupDDList\');if(l)l.style.display=\'none\'},180)" '+
                    'oninput="wizSearchDropdown(\'wSupDD\',\'wSupDDList\',CFG.suppliers||[])">'+
                  mic('supplier')+
                  '<button type="button" onclick="toggleInl(\'inlSup\')" style="width:34px;height:38px;border-radius:10px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit" title="Нов доставчик">+</button>'+
                  cpy('supplier_id')+
                '</div>'+
                '<div id="wSupDDList" class="wiz-dd-list" style="display:none;position:absolute;left:0;right:88px;top:60px;background:#0f1224;border:1px solid rgba(99,102,241,0.4);border-radius:10px;max-height:200px;overflow-y:auto;z-index:50;font-size:12px"></div>'+
                '<div id="inlSup" class="inline-add"><input type="text" id="inlSupName" placeholder="Нов доставчик"><button type="button" onclick="wizAddInline(\'supplier\')">+ Добави</button></div>'+
              '</div>'+
              // Категория dropdown — S90.PRODUCTS.SPRINT_B D3: filter-ва се по избран доставчик
              '<div class="fg" style="position:relative">'+fieldLabel('Категория','category',(S.wizData.supplier_id?'<span class="hint">(само от избрания доставчик)</span>':''))+
                '<div style="display:flex;gap:6px;align-items:center">'+
                  '<input type="text" class="fc" id="wCatDD" autocomplete="off" value="'+esc((function(){if(!S.wizData.category_id)return"";var c=(CFG.categories||[]).find(function(x){return x.id==S.wizData.category_id});return c?c.name:""})())+'" placeholder="търси или избери..." style="flex:1" '+
                    'onfocus="this._focused=true;wizSearchDropdown(\'wCatDD\',\'wCatDDList\',wizCatsForSupplier())" '+
                    'onblur="setTimeout(function(){var l=document.getElementById(\'wCatDDList\');if(l)l.style.display=\'none\'},180)" '+
                    'oninput="wizClearAIMark(\'category\');wizSearchDropdown(\'wCatDD\',\'wCatDDList\',wizCatsForSupplier())">'+
                  mic('category')+
                  '<button type="button" onclick="toggleInl(\'inlCat\')" style="width:34px;height:38px;border-radius:10px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit" title="Нова категория">+</button>'+
                  cpy('category_id')+
                '</div>'+
                '<div id="wCatDDList" class="wiz-dd-list" style="display:none;position:absolute;left:0;right:88px;top:60px;background:#0f1224;border:1px solid rgba(99,102,241,0.4);border-radius:10px;max-height:200px;overflow-y:auto;z-index:50;font-size:12px"></div>'+
                '<div id="inlCat" class="inline-add"><input type="text" id="inlCatName" placeholder="Нова категория"><button type="button" onclick="wizAddInline(\'category\')">+ Добави</button></div>'+
                wizAIHint('category')+
              '</div>'+
              // Подкатегория (select; disabled until category selected)
              '<div class="fg">'+fieldLabel('Подкатегория','subcategory')+
                '<div style="display:flex;gap:6px;align-items:center">'+
                  '<select class="fc" id="wSubcat" '+(S.wizData.category_id?'':'disabled')+' onchange="S.wizData.subcategory_id=this.value||null" style="flex:1;appearance:none;-webkit-appearance:none;padding-right:32px;cursor:pointer;font-family:inherit"><option value="">'+(S.wizData.category_id?'— Няма —':'— Избери първо категория —')+'</option></select>'+
                  mic('subcategory')+
                  '<button type="button" onclick="if(S.wizData.category_id)toggleInl(\'inlSubcat\');else showToast(\'Избери първо категория\',\'error\')" style="width:34px;height:38px;border-radius:10px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit" title="Нова подкатегория">+</button>'+
                  cpy('subcategory_id')+
                '</div>'+
                '<div id="inlSubcat" class="inline-add"><input type="text" id="inlSubcatName" placeholder="Нова подкатегория"><button type="button" onclick="wizAddSubcat()">+ Добави</button></div>'+
              '</div>'+
              // Цвят + Размер (single only — text fields, products.color/size schema columns)
              (isSingle ?
                '<div style="display:flex;gap:8px;align-items:flex-end">'+
                  '<div class="fg" style="flex:1;min-width:0">'+fieldLabel('Цвят','name')+'<div style="display:flex;gap:4px;align-items:center"><input type="text" class="fc" id="wColor" value="'+esc(S.wizData.color||'')+'" placeholder="напр. Черен" oninput="S.wizData.color=this.value;wizClearAIMark(\'color\')" style="flex:1;min-width:0">'+cpy('color')+'</div>'+wizAIHint('color')+'</div>'+
                  '<div class="fg" style="flex:1;min-width:0">'+fieldLabel('Размер','name')+'<div style="display:flex;gap:4px;align-items:center"><input type="text" class="fc" id="wSize" value="'+esc(S.wizData.size||'')+'" placeholder="напр. M" oninput="S.wizData.size=this.value" style="flex:1;min-width:0">'+cpy('size')+'</div></div>'+
                '</div>'
                : '')+
              // Състав/Материя
              '<div class="fg">'+fieldLabel('Състав / Материя','name','<span class="hint">(за AI)</span>')+'<div style="display:flex;gap:6px;align-items:center"><input type="text" class="fc" id="wComposition" value="'+esc(cm)+'" placeholder="напр. 98% памук, 2% еластан" oninput="S.wizData.composition=this.value" style="flex:1">'+mic('composition')+cpy('composition')+'</div></div>'+
              // Произход
              '<div class="fg">'+fieldLabel('Произход','name','<span class="hint">(държава)</span>')+'<div style="display:flex;gap:6px;align-items:center"><input type="text" class="fc" id="wOrigin" value="'+esc(S.wizData.origin_country||'')+'" placeholder="напр. България, Турция, Италия" oninput="S.wizData.origin_country=this.value" style="flex:1">'+mic('origin')+cpy('origin_country')+'</div></div>'+
              // Мерна единица
              '<div class="fg">'+fieldLabel('Мерна единица','unit')+'<div style="display:flex;gap:5px;flex-wrap:wrap">'+unitChips+'</div></div>'+
              // S90.PRODUCTS.SPRINT_B C3: Артикулен номер и Баркод — всеки в собствен qcard.glass.
              // S90.PRODUCTS.SPRINT_B C2: scanner икона на двете полета.
              '<div class="wiz-id-card glass sm" style="padding:12px 14px;margin-top:10px;border-radius:14px">'+
                '<div class="fg" style="margin:0">'+fieldLabel('Артикулен номер','code','<span class="hint">(вътрешен код · авто ако празно)</span>')+'<div style="display:flex;gap:6px;align-items:center"><input type="text" class="fc" id="wCode" value="'+esc(S.wizData.code||'')+'" placeholder="напр. ДЪMUSI-42" oninput="S.wizData.code=this.value.trim()" style="flex:1">'+mic('code')+'<button type="button" class="abtn" onclick="wizScanBarcode(\'wCode\',\'Сканирай артикулен номер\')" style="width:auto;padding:8px 12px;background:rgba(99,102,241,0.12);border-color:rgba(99,102,241,0.4)" title="Сканирай артикулен номер" aria-label="Сканирай артикулен номер"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></button></div></div>'+
              '</div>'+
              '<div class="wiz-id-card glass sm" style="padding:12px 14px;margin-top:10px;border-radius:14px">'+
                '<div class="fg" style="margin:0">'+fieldLabel('Баркод','barcode','<span class="hint">(EAN/UPC · авто ако празно)</span>')+'<div style="display:flex;gap:6px;align-items:center"><input type="text" class="fc" id="wBarcode" oninput="S.wizData.barcode=this.value.trim()" value="'+esc(bc)+'" placeholder="сканирай или въведи" style="flex:1">'+mic('barcode')+'<button type="button" class="abtn" onclick="wizScanBarcode(\'wBarcode\',\'Сканирай баркод\')" style="width:auto;padding:8px 12px;background:rgba(34,197,94,0.12);border-color:rgba(34,197,94,0.4)" title="Сканирай баркод" aria-label="Сканирай баркод"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></button></div></div>'+
              '</div>'+
            '</div>'+
            '<div style="display:flex;gap:8px;margin-top:14px">'+
              '<button type="button" onclick="closeWizard()" style="flex:1;height:42px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>Назад</button>'+
              '<button type="button" onclick="showToast(\'Печат — S73.B.6\')" style="width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit" title="Печат"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button>'+
              '<button type="button" onclick="wizCollectData();if(!S.wizData.name){showToast(\'Въведи наименование\',\'error\');document.getElementById(\'wName\').focus();return}if(!S.wizData.retail_price){showToast(\'Въведи цена\',\'error\');document.getElementById(\'wPrice\').focus();return}wizSave()" class="v4-foot-save" style="flex:1.3;height:42px;border-radius:12px;background:linear-gradient(180deg,rgba(34,197,94,0.12),rgba(22,163,74,0.05));border:1px solid rgba(34,197,94,0.4);color:#86efac;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit;letter-spacing:0.02em;position:relative;overflow:hidden;box-shadow:0 0 14px rgba(34,197,94,0.18),inset 0 1px 0 rgba(255,255,255,0.04)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Запази</button>'+
              (S.wizType==='variant'?'<button type="button" onclick="wizCollectData();if(!S.wizData.name){showToast(\'Въведи наименование\',\'error\');document.getElementById(\'wName\').focus();return}if(!S.wizData.retail_price){showToast(\'Въведи цена\',\'error\');document.getElementById(\'wPrice\').focus();return}wizGo(4)" class="v4-foot-next" style="flex:1.3;height:42px;border-radius:12px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;font-family:inherit;letter-spacing:0.02em;position:relative;overflow:hidden;box-shadow:0 0 14px rgba(139,92,246,0.22),inset 0 1px 0 rgba(255,255,255,0.05)">Напред<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>':'')+
            '</div>'+
            vskip+
            '</div>';
    }
    return renderWizPagePart2(step);
}
function renderWizPagePart2(step){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo('+(step+1)+')" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // ═══ STEP 4: ВАРИАЦИИ (S73.B.6 — 1:1 от add-product-variations.html) ═══
    if(step===4){
        if(S.wizType==='single'){var _sfBody='<div class="glass v4-glass-pro" style="padding:18px 16px 16px;margin-bottom:14px"><span class="shine shine-top"></span><span class="shine shine-bottom"></span><span class="glow glow-top"></span><span class="glow glow-bottom"></span><span class="glow glow-bright glow-top"></span><span class="glow glow-bright glow-bottom"></span><div style="display:flex;align-items:center;gap:12px;margin-bottom:6px"><div style="width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.15));border:1px solid rgba(139,92,246,0.4);display:flex;align-items:center;justify-content:center;box-shadow:0 0 18px rgba(99,102,241,0.25),inset 0 1px 0 rgba(255,255,255,0.08);flex-shrink:0"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/></svg></div><div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:700;letter-spacing:-0.01em;background:linear-gradient(135deg,#fff,#a5b4fc);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">Единичен артикул</div><div style="font-size:10.5px;color:rgba(226,232,240,0.55);margin-top:2px">Без вариации — продължи напред</div></div></div></div>';var _sfFoot='<div style="display:flex;gap:8px;margin-top:14px">'+'<button type="button" onclick="wizGo(3)" style="flex:1;height:42px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>Назад</button>'+'<button type="button" onclick="showToast(\'Печат — S76\')" style="width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit" title="Печат"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button>'+'<button type="button" onclick="wizSave()" class="v4-foot-save" style="flex:1.3;height:42px;border-radius:12px;background:linear-gradient(180deg,rgba(34,197,94,0.12),rgba(22,163,74,0.05));border:1px solid rgba(34,197,94,0.4);color:#86efac;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit;letter-spacing:0.02em;position:relative;overflow:hidden;box-shadow:0 0 14px rgba(34,197,94,0.18),inset 0 1px 0 rgba(255,255,255,0.04)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Запази</button>'+'<button type="button" onclick="wizGoPreview()" class="v4-foot-next" style="flex:1.3;height:42px;border-radius:12px;background:linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08));border:1px solid rgba(139,92,246,0.5);color:#c4b5fd;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;font-family:inherit;letter-spacing:0.02em;position:relative;overflow:hidden;box-shadow:0 0 14px rgba(139,92,246,0.22),inset 0 1px 0 rgba(255,255,255,0.05)">Напред<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>'+'</div>';return '<div class="wiz-page active">'+_sfBody+_sfFoot+'</div>';}

        // Init axes
        if(!S.wizData.axes||!S.wizData.axes.length){
            S.wizData.axes=[];
            if(window._bizVariants&&window._bizVariants.variant_fields){
                window._bizVariants.variant_fields.forEach(function(f){S.wizData.axes.push({name:f,values:[]})});
            }
            if(!S.wizData.axes.length){S.wizData.axes.push({name:'Вариация 1',values:[]});S.wizData.axes.push({name:'Вариация 2',values:[]})}
        }
        // S82.COLOR.4: auto-populate color axis from either legacy _aiDetectedColors or new _photos[].ai_color (once)
        var _detectedColors = [];
        if (Array.isArray(S.wizData._aiDetectedColors)) {
            S.wizData._aiDetectedColors.forEach(function(c){ if (c && c.name) _detectedColors.push({name: c.name, hex: c.hex || '#666'}); });
        }
        if (Array.isArray(S.wizData._photos)) {
            S.wizData._photos.forEach(function(p){
                var n = (p.ai_color || '').trim();
                if (!n) return;
                if (!_detectedColors.find(function(x){return x.name.toLowerCase()===n.toLowerCase()})) {
                    _detectedColors.push({name: n, hex: p.ai_hex || '#666'});
                }
            });
        }
        if (_detectedColors.length && !S.wizData._aiColorsApplied) {
            var _colorAxisIdx = -1;
            S.wizData.axes.forEach(function(ax, i){
                var n = (ax.name || '').toLowerCase();
                if (n.indexOf('цвят') !== -1 || n.indexOf('color') !== -1) _colorAxisIdx = i;
            });
            if (_colorAxisIdx === -1 && S.wizData.axes.length) {
                // Rename the first generic "Вариация N" axis to "Цвят" if no colour axis exists
                var _ax0 = S.wizData.axes[0];
                if (/^вариация\s*\d+$/i.test(_ax0.name)) { _ax0.name = 'Цвят'; _colorAxisIdx = 0; }
            }
            if (_colorAxisIdx !== -1) {
                var _existing = new Set(S.wizData.axes[_colorAxisIdx].values || []);
                _detectedColors.forEach(function(c){
                    if (!_existing.has(c.name)) {
                        S.wizData.axes[_colorAxisIdx].values.push(c.name);
                        _existing.add(c.name);
                    }
                });
                S.wizData._aiColorsApplied = true;
            }
        }
        if(S._wizActiveTab===undefined)S._wizActiveTab=0;

        // Load pinned groups
        if(!S._wizPinnedGroups){
            try{S._wizPinnedGroups=JSON.parse(localStorage.getItem('_rms_pinnedGroups_'+CFG.storeId))}catch(e){}
            if(!S._wizPinnedGroups){
                var def=_getSizePresetsOrdered().slice(0,3);
                S._wizPinnedGroups=def.map(function(g){return{id:g.id,label:g.label,vals:g.vals.slice(),_origVals:g.vals.slice()}});
            }
        }
        if(S._wizEditingGroup===undefined)S._wizEditingGroup=null;

        // Preview pill
        var prodName=S.wizData.name||'Нов артикул';
        var prodPrice=S.wizData.retail_price?(parseFloat(S.wizData.retail_price).toFixed(2)+' лв'):'—';
        var previewH='<div class="v-preview-pill"><div class="v-preview-thumb"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div><div class="v-preview-info"><div class="v-preview-name">'+esc(prodName)+'</div><div class="v-preview-meta">'+prodPrice+'</div></div></div>';

        // Tabs
        var tabsH='<div class="v-axis-tabs">';
        S.wizData.axes.forEach(function(ax,ti){
            var isAct=S._wizActiveTab===ti;
            var nm=ax.name.toLowerCase();
            var isSz=nm.indexOf('размер')!==-1||nm.indexOf('size')!==-1;
            var isCl=nm.indexOf('цвят')!==-1||nm.indexOf('color')!==-1;
            var isDef=/^вариация\s*\d+$/i.test(ax.name);var icn=isSz?'<svg viewBox="0 0 24 24"><path d="M3 3h18v6H3zM3 15h18v6H3z"/></svg>':(isCl?'<svg viewBox="0 0 24 24"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/></svg>':(isDef?'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-dasharray="3 3"/></svg>':'<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>'));
            var _nmL=ax.name.toLowerCase();
            var _tabSemantic=(_nmL.indexOf('размер')!==-1||_nmL.indexOf('size')!==-1)?'Размер':((_nmL.indexOf('цвят')!==-1||_nmL.indexOf('color')!==-1)?'Цвят':(/^вариация\s*\d+$/i.test(ax.name)?'—':ax.name));
            var _tabTop='Вариация '+(ti+1);
            tabsH+='<button class="v-axis-tab'+(isAct?' active':'')+'" onclick="S._wizActiveTab='+ti+';S._wizEditingGroup=null;renderWizard()"><span class="v-tab-top">'+esc(_tabTop)+'</span><span class="v-tab-bottom">'+icn+esc(_tabSemantic)+'<span class="v-axis-tab-count">'+(ax.values.length||0)+'</span></span></button>';
        });
        tabsH+='<button class="v-axis-tab-add" onclick="wizAddAxisFromTab()">+</button>';
        tabsH+='</div>';

        var ax=S.wizData.axes[S._wizActiveTab];
        if(!ax){S._wizActiveTab=0;ax=S.wizData.axes[0]}
        var ai=S._wizActiveTab;
        var nm=ax.name.toLowerCase();
        var isSize=nm.indexOf('размер')!==-1||nm.indexOf('size')!==-1||nm.indexOf('ръст')!==-1;
        var isColor=nm.indexOf('цвят')!==-1||nm.indexOf('color')!==-1||nm.indexOf('десен')!==-1;

        // Selected bar
        var selH='<div class="v-sel-bar">';
        if(ax.values.length){
            ax.values.forEach(function(v,vi){
                var dot='';
                if(isColor){var cc=CFG.colors.find(function(x){return x.name===v});if(cc)dot='<span class="v-dot" style="background:'+cc.hex+'"></span>';}
                selH+='<div class="v-sel-chip" onclick="S.wizData.axes['+ai+'].values.splice('+vi+',1);renderWizard()">'+dot+'<span>'+esc(v)+'</span><span class="v-sel-chip-x">✕</span></div>';
            });
            selH+='<button class="v-clear-btn" onclick="event.stopPropagation();S.wizData.axes['+ai+'].values=[];renderWizard()">Изчисти</button>';
        }else{
            selH+='<div class="v-sel-empty">Избери от групите, търси, въведи ръчно или с глас</div>';
        }
        selH+='</div>';
        // S90.PRODUCTS.SPRINT_B C1: бутон "+ Добави на ръка" под списъка с избрани стойности.
        // Pesho не пише по принцип, но за rare custom размери (ENI 38W, EU44.5) е нужно ръчно.
        var _addLbl=isSize?'размер':(isColor?'цвят':(ax.name||'стойност'));
        var _addPh=isSize?'напр. 38W, EU44.5, M-tall':(isColor?'напр. шампанско':'напр. '+(_addLbl));
        selH+='<div class="v-add-manual" style="display:flex;gap:6px;padding:8px 14px 0">'+
              '<input type="text" id="axVal'+ai+'" class="v-custom-input" placeholder="'+_addPh+'" '+
              'onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizAddAxisValue('+ai+')}" '+
              'style="flex:1;padding:10px 14px;font-size:14px">'+
              '<button class="v-custom-btn" onclick="wizAddAxisValue('+ai+')" title="Добави '+esc(_addLbl)+'" style="padding:10px;flex-shrink:0;width:42px;height:42px">'+
              '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'+
              '</button>'+
              '</div>';

        // Picker body
        var pickH='<div class="v-picker-body">';
        // Search
        pickH+='<div class="v-picker-search"><span class="v-picker-search-ic"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span><input type="text" id="wizSearch'+ai+'" placeholder="Търси размер или група..." oninput="wizSearchPresets('+ai+',this.value)" autocomplete="off"></div><div id="wizSearchRes'+ai+'"></div>';

        // Pinned groups показваме само на таба според axisHint (или на първия ако няма hint)
        if(true){
            var existingSet=new Set(ax.values);
            var allPinned=S._wizPinnedGroups||[];
            var pinned=allPinned.filter(function(pg){
                var hint=(pg.axisHint===undefined||pg.axisHint===null)?0:pg.axisHint;
                return hint===ai;
            });
            // Map filtered индекси към оригиналните за правилно премахване/редакция
            var _pgIdxMap=[];
            allPinned.forEach(function(pg,origIdx){
                var hint=(pg.axisHint===undefined||pg.axisHint===null)?0:pg.axisHint;
                if(hint===ai)_pgIdxMap.push(origIdx);
            });
            pinned.forEach(function(pg,pgLocalIdx){
                var pgi=_pgIdxMap[pgLocalIdx]; // реалния index в S._wizPinnedGroups
                var maxShow=15;
                var isEditing=S._wizEditingGroup!==null&&S._wizEditingGroup===pgi;
                var selCount=pg.vals.filter(function(v){return existingSet.has(v)}).length;
                var showAll=pg._showAll||false;
                var valsToShow=showAll?pg.vals:pg.vals.slice(0,maxShow);
                var hasMore=pg.vals.length>maxShow&&!showAll;
                var starred=pgi===0?' starred':'';
                var isOpen=true;
                pickH+='<div class="v-pgroup'+(isOpen?' open':'')+'">';
                pickH+='<div class="v-pgroup-head" onclick="this.parentElement.classList.toggle(\'open\')">';
                pickH+='<div class="v-pgroup-title'+starred+'">'+esc(pg.label)+'</div>';
                pickH+='<div class="v-pgroup-count'+(selCount>0?' has':'')+'">'+selCount+'/'+pg.vals.length+'</div>';
                pickH+='<span class="v-pgroup-arr">▶</span></div>';
                pickH+='<div class="v-pgroup-body">';
                if(isEditing){
                    pg.vals.forEach(function(v,vi){
                        pickH+='<span class="v-chip" style="background:rgba(245,158,11,0.08);border-color:rgba(245,158,11,0.2);color:#fcd34d" onclick="wizPinnedRemoveValue('+pgi+','+vi+')">'+esc(v)+' ✕</span>';
                    });
                    pickH+='<div style="width:100%;display:flex;gap:4px;margin-top:6px"><input type="text" class="v-custom-input" id="editGrpVal'+pgi+'" placeholder="Добави..." style="font-size:12px;padding:8px 12px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizPinnedAddValue('+pgi+')}"><button class="v-custom-btn" onclick="wizPinnedAddValue('+pgi+')"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Добави</button></div>';
                    pickH+='<div style="width:100%;text-align:right;margin-top:4px"><span style="font-size:10px;color:rgba(245,158,11,0.5);cursor:pointer" onclick="wizPinnedResetGroup('+pgi+')">Фабрични настройки</span></div>';
                }else{
                    valsToShow.forEach(function(v){
                        var isSel=existingSet.has(v);
                        pickH+='<span class="v-chip'+(isSel?' selected':'')+'" onclick="wizTogglePresetInline('+ai+',\''+v.replace(/'/g,"\\'")+'\',this)">'+esc(v)+'</span>';
                    });
                    if(hasMore)pickH+='<span class="v-chip" style="border-style:dashed;color:var(--indigo-400)" onclick="S._wizPinnedGroups['+pgi+']._showAll=true;renderWizard()">+още '+(pg.vals.length-maxShow)+'</span>';
                    if(showAll&&pg.vals.length>maxShow)pickH+='<span class="v-chip" style="border-style:dashed" onclick="S._wizPinnedGroups['+pgi+']._showAll=false;renderWizard()">Прибери</span>';
                }
                // Actions footer — ПОД chips
                pickH+='</div>';
                pickH+='<div class="v-pgroup-footer" onclick="event.stopPropagation()">';
                var _icArrUp='<svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>';
                var _icArrDn='<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';
                var _icAll='<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
                var _icEdit='<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>';
                var _icAdd='<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                var _icDone='<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
                var _icDel='<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>';
                pickH+=pgi>0?'<span class="v-pgroup-act" onclick="wizMovePinnedGroup('+pgi+',-1)" title="Нагоре">'+_icArrUp+'</span>':'<span style="visibility:hidden"></span>';
                pickH+=pgi<pinned.length-1?'<span class="v-pgroup-act" onclick="wizMovePinnedGroup('+pgi+',1)" title="Надолу">'+_icArrDn+'</span>':'<span style="visibility:hidden"></span>';
                pickH+='<span class="v-pgroup-act" onclick="wizPinnedSelectAll('+pgi+')">'+_icAll+'всички</span>';
                pickH+='<span class="v-pgroup-act warn" onclick="S._wizEditingGroup='+(isEditing?'null':String(pgi))+';renderWizard()">'+(isEditing?_icDone+'готово':_icAdd+'добави')+'</span>';
                pickH+='<span class="v-pgroup-act danger" onclick="wizPinnedRemoveGroup('+pgi+')" title="Премахни">'+_icDel+'</span>';
                pickH+='</div></div>';
            });
        }

        // COLORS: chips + HEX picker
        if(isColor){
            var existingSet=new Set(ax.values);
            pickH+='<div class="v-pgroup open">';
            pickH+='<div class="v-pgroup-head"><div class="v-pgroup-title">Цветове</div><div class="v-pgroup-count">'+existingSet.size+'/'+CFG.colors.length+'</div><span class="v-pgroup-arr">\u25BC</span></div>';
            pickH+='<div class="v-pgroup-body">';
            var _colorEditMode=S._wizEditingColors||false;
            CFG.colors.forEach(function(c){
                var isSel=existingSet.has(c.name);
                var chipCls=_colorEditMode?' v-chip':' v-chip'+(isSel?' selected':'');
                var chipStyle=_colorEditMode?'background:rgba(245,158,11,0.08);border-color:rgba(245,158,11,0.25);color:#fcd34d':'';
                var chipClick=_colorEditMode?'wizColorEditPrompt(\''+c.name.replace(/'/g,"\\'")+'\',\''+c.hex+'\')':'wizTogglePresetInline('+ai+',\''+c.name.replace(/'/g,"\\'")+'\',this)';
                pickH+='<span class="'+chipCls+'" style="'+chipStyle+'" onclick="'+chipClick+'"><span class="v-dot" style="background:'+c.hex+'"></span>'+esc(c.name)+(_colorEditMode?' \u270E':'')+'</span>';
            });
            pickH+='</div>';
            pickH+='<div class="v-pgroup-footer" onclick="event.stopPropagation()">';
            pickH+='<span style="visibility:hidden"></span>';
            pickH+='<span style="visibility:hidden"></span>';
            pickH+='<span class="v-pgroup-act" onclick="wizColorSelectAll()"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>всички</span>';
            pickH+='<span class="v-pgroup-act warn" onclick="wizColorAddPrompt()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>добави</span>';
            pickH+='<span class="v-pgroup-act'+(_colorEditMode?' danger':'')+'" onclick="S._wizEditingColors='+(_colorEditMode?'false':'true')+';renderWizard()">'+(_colorEditMode?'<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>готово':'<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>редакт.')+'</span>';
            pickH+='</div></div>';
            // HEX picker
            pickH+='<div class="v-pgroup open" style="margin-top:10px"><div class="v-pgroup-head"><div class="v-pgroup-title">Избери от палитра</div></div><div class="v-pgroup-body" style="flex-direction:column;align-items:stretch"><div style="font-size:10px;color:var(--text-secondary);margin-bottom:6px;line-height:1.4">Плъзни пръст по палитрата за точен цвят. Или напиши име (шампанско, мента...) и цветът се подбира автоматично.</div><canvas id="wizHslCanvas" width="280" height="160" style="width:100%;height:120px;border-radius:8px;cursor:crosshair;touch-action:none;border:1px solid rgba(255,255,255,0.08)"></canvas><input type="range" id="wizHueSlider" min="0" max="360" value="0" style="width:100%;margin:6px 0;accent-color:var(--indigo-400)" oninput="wizDrawHsl()"><div style="display:flex;align-items:center;gap:6px;margin-top:4px;width:100%"><div id="wizColorPreview" style="width:32px;height:32px;border-radius:8px;background:#ff0000;border:1px solid rgba(255,255,255,0.15);flex-shrink:0"></div><div style="flex:1;min-width:0"><div id="wizHexVal" style="font-size:12px;font-weight:700;color:var(--indigo-300)">#FF0000</div><div id="wizColorSuggest" style="font-size:9px;color:var(--text-secondary)">Червен</div></div></div><div style="display:flex;align-items:stretch;gap:6px;margin-top:8px;width:100%"><input type="text" class="v-custom-input" id="wizHexName" placeholder="Име на цвят (напр. шампанско)..." style="flex:1;font-size:12px;padding:10px 14px" oninput="wizNameToHex(this.value)"><button class="v-custom-btn" onclick="wizAddHexColor()" style="flex-shrink:0"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Добави</button></div></div></div>';
        }


        // Добави още preset групи (EU облекло, Дънки, Обувки...)
        pickH+='<div class="mod-prod-more-groups" onclick="wizShowMoreGroups('+ai+')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Още групи за твоя бизнес</div>';

        // Remove custom axis
        if(!isSize&&!isColor){
            pickH+='<div style="text-align:center;margin-top:10px"><span style="font-size:10px;color:rgba(239,68,68,0.6);cursor:pointer" onclick="if(confirm(\'Премахни вариацията?\')){S.wizData.axes.splice('+ai+',1);S._wizActiveTab=0;renderWizard()}">Премахни "'+esc(ax.name)+'"</span></div>';
        }

        // Create new group
        pickH+='<div style="padding:10px 12px;margin-top:10px;border-radius:14px;border:1px dashed rgba(255,255,255,0.08);background:rgba(255,255,255,0.015)"><div style="font-size:10px;font-weight:700;color:rgba(255,255,255,0.5);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em">+ Нова вариация</div><div style="font-size:10px;color:var(--text-secondary);margin-bottom:8px">Напр. Материя, Модел, Обем...</div><div style="display:flex;gap:4px"><input type="text" class="v-custom-input" id="newGrpName" placeholder="Име..." style="font-size:12px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizCreateCustomGroup()}"><button class="v-custom-btn" onclick="wizCreateCustomGroup()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Създай</button></div>';
        if(S._wizNewCustomGroup){
            var ncg=S._wizNewCustomGroup;
            pickH+='<div style="margin-top:8px;padding:8px;border-radius:10px;background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.2)"><div style="font-size:12px;font-weight:700;color:var(--success);margin-bottom:3px">Нова вариация: <b>'+esc(ncg.name)+'</b></div><div style="font-size:10px;color:rgba(134,239,172,0.7);margin-bottom:6px">Стойности: '+(ncg.values||[]).map(esc).join(', ')+'</div><div style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:6px">';
            (ncg.values||[]).forEach(function(v,vi){
                pickH+='<span class="v-chip" style="background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.2);color:#86efac;cursor:pointer" onclick="S._wizNewCustomGroup.values.splice('+vi+',1);renderWizard()">'+esc(v)+' ✕</span>';
            });
            pickH+='</div><div style="display:flex;gap:4px;margin-bottom:6px"><input type="text" class="v-custom-input" id="ncgValInput" placeholder="Стойност..." style="font-size:11px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizAddCustomGroupValue()}"><button class="v-custom-btn" onclick="wizAddCustomGroupValue()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button></div><button class="abtn" style="width:100%;font-size:11px;padding:8px" onclick="wizSaveCustomGroupToAxes()">Запамети вариацията</button></div>';
        }
        pickH+='</div>';

        pickH+='</div>';

        // Matrix CTA / Summary
        var combos=wizCountCombinations();
        var hasMatrix=S.wizData._matrix&&Object.keys(S.wizData._matrix).filter(function(k){return S.wizData._matrix[k]&&S.wizData._matrix[k].qty>0}).length>0;
        var mcH='';
        // S73.B.9: Матрица винаги когато има поне една стойност
        var totalValues=0;(S.wizData.axes||[]).forEach(function(a){totalValues+=a.values.length});
        if(totalValues>0){
            if(hasMatrix){
                var filled=0,total=0,minSum=0;
                Object.keys(S.wizData._matrix).forEach(function(k){var c=S.wizData._matrix[k]||{};var q=parseInt(c.qty)||0;var mn=parseInt(c.min)||0;if(q>0)filled++;total+=q;minSum+=mn});
                mcH='<div class="v-matrix-summary"><div class="v-ms-check"><svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div><div class="v-ms-text"><div class="v-ms-title">'+filled+' комбинации · общо '+total+' бр</div><div class="v-ms-sub">Мин. нива: '+minSum+' бр</div></div><button class="v-ms-edit" onclick="openMxOverlay()">Редактирай</button></div>';
            }else{
                var szAx=null,clAx=null;(S.wizData.axes||[]).forEach(function(a){var n=a.name.toLowerCase();if(!szAx&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))szAx=a;else if(!clAx&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))clAx=a});
                var szN=szAx?szAx.values.length:0,clN=clAx?clAx.values.length:0;
                mcH='<div class="glass sm v-matrix-cta-wrap"><span class="shine shine-top"></span><span class="shine shine-bottom"></span><span class="glow glow-top"></span><span class="glow glow-bottom"></span><div class="v-mc-row"><div class="v-mc-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div><div class="v-mc-text"><div class="v-mc-title">Матрица размер × цвят</div><div class="v-mc-sub"><b>'+szN+' размера</b> × <b>'+clN+' цвята</b> = <b>'+combos+' комбинации</b></div></div></div><button class="v-matrix-cta" onclick="openMxOverlay()"><div class="v-matrix-cta-info"><div class="v-matrix-cta-pill">'+combos+'</div><span>Въведи колко бройки имаш и мин. количества за поръчки</span></div><span class="v-matrix-cta-arrow">→</span></button></div>';
            }
        }

''; // S73.B.13: footer via helper
        var _footer=_v4ComputeFooter(S._wizActiveTab);
        // S82.STUDIO.2: AI prompt card lives at the bottom of step 4 now (was step 5,
        // which the user couldn't make sense of). Card shows only after at least one
        // axis has values and after at least one quantity is in the matrix — otherwise
        // the user would see a save button before they have anything to save.
        var _step4AnyVals = (S.wizData.axes||[]).some(function(a){return a.values&&a.values.length>0});
        var _step4HasQty = false;
        if (S.wizData._matrix) {
            for (var _mk in S.wizData._matrix) {
                var _mc = S.wizData._matrix[_mk];
                var _mq = (_mc && typeof _mc === 'object') ? _mc.qty : _mc;
                if (parseInt(_mq) > 0) { _step4HasQty = true; break; }
            }
        }
        var _aiCardH = '';
        // S82.STUDIO.7: card is now ALWAYS visible whenever an axis has values
        // (not gated on qty). User reported "the card never appeared even after I
        // entered qtys" — likely a state-detection edge case. Safer to always show
        // the card and let the buttons handle the empty-qty case via toast.
        if (_step4AnyVals) {
            // Compute compact summary for the card.
            var _step4SumQty = 0, _step4SumCells = 0;
            if (S.wizData._matrix) {
                for (var _k2 in S.wizData._matrix) {
                    var _c2 = S.wizData._matrix[_k2];
                    var _q2 = parseInt((_c2 && typeof _c2 === 'object') ? _c2.qty : _c2) || 0;
                    if (_q2 > 0) { _step4SumQty += _q2; _step4SumCells++; }
                }
            }
            console.log('[S82.STUDIO.7] step4 render — _step4HasQty=' + _step4HasQty + ', cells=' + _step4SumCells + ', total=' + _step4SumQty + ', _matrix=', S.wizData._matrix);
            var _mqVal4=(S.wizData.min_quantity===undefined||S.wizData.min_quantity===null||S.wizData.min_quantity==='')?1:S.wizData.min_quantity;
            // Buttons disabled when no qty — visually greyed out + onclick toast.
            var _btnDisabled = !_step4HasQty;
            var _yesAttr = _btnDisabled ? 'onclick="wizStep4NeedQty()"' : 'onclick="wizFinalAIYes()"';
            var _noAttr  = _btnDisabled ? 'onclick="wizStep4NeedQty()"' : 'onclick="wizFinalAINo()"';
            var _btnCls  = _btnDisabled ? ' disabled' : '';
            // S82.STUDIO.9: removed the min-qty stepper (lives in matrix overlay now);
            // bullet list replaced with a 4-icon visual feature grid (more illustrative).
            _aiCardH =
                '<div id="wizStep4AICard" class="step4-ai-card' + (_btnDisabled ? ' awaiting-qty' : '') + '">' +
                    (_step4HasQty
                        ? '<div class="s4ai-summary">' +
                              '<svg viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
                              '<span><b>' + _step4SumCells + '</b> комбинации · общо <b>' + _step4SumQty + '</b> бр. · МКП авто-изчислено</span>' +
                          '</div>'
                        : '<div class="s4ai-summary warn" onclick="if(typeof openMxOverlay===\'function\')openMxOverlay()" style="cursor:pointer">' +
                              '<svg viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
                              '<span>Първо <b>въведи бройки</b> за всеки вариант → tap тук</span>' +
                          '</div>') +
                    '<div class="s4ai-prompt">' +
                        '<div class="s4ai-prompt-title">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>' +
                            '✨ Искаш ли AI обработка?' +
                        '</div>' +
                        '<div class="s4ai-feature-grid">' +
                            '<div class="s4ai-feat">' +
                                '<div class="s4ai-feat-ico bg"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="24" height="24" rx="3"/><circle cx="11" cy="11" r="2"/><path d="m28 20-7-7L4 28"/></svg></div>' +
                                '<div class="s4ai-feat-lbl">Бял фон</div>' +
                            '</div>' +
                            '<div class="s4ai-feat">' +
                                '<div class="s4ai-feat-ico magic"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="16" cy="9" r="4"/><path d="M7 28v-3a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5v3"/><path d="M22 16l3-3M25 16l-3-3"/></svg></div>' +
                                '<div class="s4ai-feat-lbl">AI Магия<br><span class="s4ai-feat-sub">модел носи</span></div>' +
                            '</div>' +
                            '<div class="s4ai-feat">' +
                                '<div class="s4ai-feat-ico seo"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19 4H8a3 3 0 0 0-3 3v18a3 3 0 0 0 3 3h16a3 3 0 0 0 3-3V11z"/><polyline points="19 4 19 11 27 11"/><line x1="10" y1="17" x2="22" y2="17"/><line x1="10" y1="22" x2="18" y2="22"/></svg></div>' +
                                '<div class="s4ai-feat-lbl">SEO описание<br><span class="s4ai-feat-sub">за онлайн</span></div>' +
                            '</div>' +
                            '<div class="s4ai-feat">' +
                                '<div class="s4ai-feat-ico exp"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4v18M9 15l7 7 7-7"/><line x1="5" y1="28" x2="27" y2="28"/></svg></div>' +
                                '<div class="s4ai-feat-lbl">Експорт<br><span class="s4ai-feat-sub">CSV / PDF</span></div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="s4ai-prompt-actions">' +
                            '<button type="button" class="s4ai-btn yes' + _btnCls + '" ' + _yesAttr + '>Да, отвори AI Studio</button>' +
                            '<button type="button" class="s4ai-btn no' + _btnCls + '" ' + _noAttr + '>Не, само запази</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }
        return '<div class="wiz-page active" style="padding-bottom:160px">'+
            previewH+
            '<div class="glass v-var-card"><span class="shine shine-top"></span><span class="shine shine-bottom"></span><span class="glow glow-top"></span><span class="glow glow-bottom"></span><span class="glow glow-bright glow-top"></span><span class="glow glow-bright glow-bottom"></span>'+tabsH+selH+pickH+'</div>'+
            _aiCardH+
            '<div id="v4Footer" class="mod-prod-v4-footer">'+_footer+'</div>'+
            vskip+'</div>';
    }

    // ═══ STEP 5: МАТРИЦА + БРОЙКИ + ЗАПИС (S70 — replaces old step 5+6) ═══
    if(step===5){
        wizCollectData();

        var combos=wizBuildCombinations();
        var hasCombos=combos.length>1||(combos[0]&&combos[0].parts&&combos[0].parts.length>0);

        // Find size and color axes
        var sizeAxis=null,colorAxis=null,otherAxes=[];
        (S.wizData.axes||[]).forEach(function(ax){
            var n=ax.name.toLowerCase();
            if(!sizeAxis&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))sizeAxis=ax;
            else if(!colorAxis&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))colorAxis=ax;
            else if(ax.values.length)otherAxes.push(ax);
        });

        // Unit selector — S76.2b: премахнат (вече е в step 3 chip-ове)
        var unitH='';

        var matrixH='';

        if(!hasCombos){
            // Single product — just qty + min
            matrixH='<div style="padding:10px 12px;border-radius:10px;background:rgba(99,102,241,0.04);border:1px solid rgba(99,102,241,0.08);margin-bottom:10px">';
            matrixH+='<div style="font-size:12px;font-weight:600;color:var(--indigo-300);margin-bottom:6px">Наличност</div>';
            matrixH+='<div style="display:flex;gap:8px">';
            matrixH+='<div style="flex:1"><div style="font-size:9px;font-weight:700;color:var(--indigo-300);margin-bottom:3px">БРОЙКА</div><div style="display:flex;align-items:center;gap:0">';
            matrixH+='<button type="button" onclick="var i=document.getElementById(\'wSingleQty\');i.value=Math.max(0,parseInt(i.value||0)-1)" style="width:28px;height:36px;border:1px solid var(--border-subtle);border-radius:8px 0 0 8px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:16px;cursor:pointer">\u2212</button>';
            matrixH+='<input type="number" class="fc" id="wSingleQty" value="1" min="0" style="width:50px;text-align:center;font-size:15px;font-weight:700;border-radius:0;border-left:0;border-right:0">';
            matrixH+='<button type="button" onclick="var i=document.getElementById(\'wSingleQty\');i.value=parseInt(i.value||0)+1" style="width:28px;height:36px;border:1px solid var(--border-subtle);border-radius:0 8px 8px 0;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:16px;cursor:pointer">+</button></div></div>';
            matrixH+='<div style="flex:1"><div style="font-size:9px;font-weight:700;color:rgba(245,158,11,0.8);margin-bottom:3px">МИН.</div><input type="number" class="fc" id="wSingleMin" value="1" min="0" style="text-align:center;font-size:15px;font-weight:700"></div>';
            matrixH+='</div></div>';

        }else if(sizeAxis&&sizeAxis.values.length&&colorAxis&&colorAxis.values.length){
            // MATRIX: size × color with qty in each cell
            var sizes=sizeAxis.values;
            var colors=colorAxis.values;
            matrixH+='<div style="margin-bottom:10px">';
            matrixH+='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px"><div style="font-size:12px;font-weight:600;color:var(--indigo-300)">Матрица: '+sizes.length+' размера \u00d7 '+colors.length+' цвята = '+sizes.length*colors.length+' варианта</div>';
            matrixH+='</div>';
            matrixH+='<div style="font-size:9px;color:var(--text-secondary);margin-bottom:6px">Tap клетка: въведи бройка. Празна = не съществува. С глас: "S черно 2 червено 3"</div>';

            // Scrollable matrix
            matrixH+='<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--border-subtle);border-radius:10px">';
            matrixH+='<table style="border-collapse:collapse;width:100%;min-width:'+(colors.length*56+60)+'px">';
            // Header row with colors
            matrixH+='<tr><td style="padding:4px 6px;font-size:9px;font-weight:700;color:var(--text-secondary);position:sticky;left:0;background:#080818;z-index:1;min-width:50px"></td>';
            colors.forEach(function(c,ci){
                var cc=CFG.colors.find(function(x){return x.name===c});
                var dot=cc?'<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'+cc.hex+';margin-right:2px;border:1px solid rgba(255,255,255,0.15)"></span>':'';
                matrixH+='<td style="padding:4px 3px;text-align:center;font-size:8px;font-weight:600;color:var(--text-secondary);min-width:48px">'+dot+esc(c)+'</td>';
            });
            matrixH+='</tr>';

            // Size rows
            sizes.forEach(function(sz,si){
                matrixH+='<tr style="border-top:1px solid rgba(99,102,241,0.06)">';
                matrixH+='<td style="padding:4px 6px;font-size:11px;font-weight:700;color:var(--indigo-300);position:sticky;left:0;background:#080818;z-index:1">'+esc(sz)+'</td>';
                colors.forEach(function(c,ci){
                    var cellId='mx_'+si+'_'+ci;
                    var val=S.wizData._matrix&&S.wizData._matrix[cellId]!==undefined?S.wizData._matrix[cellId]:'';
                    var hasVal=val!==''&&val!==null;
                    var bgc=hasVal?'rgba(99,102,241,0.08)':'rgba(239,68,68,0.03)';
                    var brc=hasVal?'rgba(99,102,241,0.15)':'rgba(99,102,241,0.05)';
                    matrixH+='<td style="padding:2px;text-align:center">';
                    matrixH+='<input type="number" min="0" class="fc" id="'+cellId+'" value="'+(hasVal?val:'')+'" placeholder="\u2715" style="width:44px;padding:4px 2px;text-align:center;font-size:12px;font-weight:700;border-radius:6px;background:'+bgc+';border-color:'+brc+'" oninput="wizMatrixChanged(\''+cellId+'\',this.value)" onfocus="this.select()">';
                    matrixH+='</td>';
                });
                matrixH+='</tr>';
            });
            matrixH+='</table></div>';

            // Quick actions for matrix
            matrixH+='<div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">';
            matrixH+='<span style="font-size:9px;padding:4px 8px;border-radius:6px;border:1px solid var(--border-subtle);color:var(--indigo-400);cursor:pointer" onclick="wizMatrixFillAll(1)">Всички = 1</span>';
            matrixH+='<span style="font-size:9px;padding:4px 8px;border-radius:6px;border:1px solid var(--border-subtle);color:var(--indigo-400);cursor:pointer" onclick="wizMatrixFillAll(2)">Всички = 2</span>';
            matrixH+='<span style="font-size:9px;padding:4px 8px;border-radius:6px;border:1px solid var(--border-subtle);color:var(--indigo-400);cursor:pointer" onclick="wizMatrixFillAll(5)">Всички = 5</span>';
            matrixH+='<span style="font-size:9px;padding:4px 8px;border-radius:6px;border:1px solid rgba(239,68,68,0.2);color:var(--danger);cursor:pointer" onclick="wizMatrixClear()">Изчисти</span>';
            matrixH+='</div></div>';

        }else{
            // Has variations but no proper size×color matrix — show list
            matrixH+='<div style="margin-bottom:10px">';
            matrixH+='<div style="font-size:12px;font-weight:600;color:var(--indigo-300);margin-bottom:6px">Бройки по вариация</div>';
            matrixH+='<div style="font-size:9px;color:var(--text-secondary);margin-bottom:6px"><b style="color:var(--indigo-300)">Бройка</b> = наличност, <b style="color:rgba(245,158,11,0.8)">Мин.</b> = предупреждение при ниска</div>';
            combos.forEach(function(v,idx){
                var parts=v.parts||[];
                var labelH='';
                parts.forEach(function(p){
                    var n=p.axis.toLowerCase();
                    if(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1){
                        var cc=CFG.colors.find(function(x){return x.name===p.value});
                        var hex=cc?cc.hex:'#666';
                        labelH+='<span style="display:inline-flex;align-items:center;gap:3px;margin-right:4px"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'+hex+';border:1px solid rgba(255,255,255,0.2)"></span><span style="font-size:11px">'+esc(p.value)+'</span></span>';
                    }else{
                        labelH+='<span style="font-size:12px;font-weight:700;margin-right:4px">'+esc(p.value)+'</span>';
                    }
                });
                if(!labelH)labelH='<span style="font-size:11px">'+esc(v.axisValues||'')+'</span>';
                matrixH+='<div style="display:flex;gap:4px;align-items:center;margin-bottom:3px;padding:5px 8px;border-radius:8px;background:rgba(17,24,44,0.3);border:1px solid var(--border-subtle)" id="comboRow'+idx+'">';
                matrixH+='<div style="flex:1;display:flex;align-items:center;flex-wrap:wrap">'+labelH+'</div>';
                matrixH+='<div style="display:flex;align-items:center;gap:0">';
                matrixH+='<button type="button" onclick="wizComboQty('+idx+',-1)" style="width:22px;height:28px;border:1px solid var(--border-subtle);border-radius:4px 0 0 4px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:14px;cursor:pointer;padding:0">\u2212</button>';
                matrixH+='<input type="number" class="fc" style="width:34px;padding:3px 0;text-align:center;font-size:13px;font-weight:700;border-radius:0;border-left:0;border-right:0" value="1" min="0" data-combo="'+idx+'" data-field="qty">';
                matrixH+='<button type="button" onclick="wizComboQty('+idx+',1)" style="width:22px;height:28px;border:1px solid var(--border-subtle);border-radius:0 4px 4px 0;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:14px;cursor:pointer;padding:0">+</button></div>';
                matrixH+='<input type="number" class="fc" style="width:40px;padding:3px;text-align:center;font-size:11px;font-weight:600;border-radius:6px;border-color:rgba(245,158,11,0.3)" value="1" min="0" data-combo="'+idx+'" data-field="min">';
                matrixH+='</div>';
            });
            matrixH+='</div>';
        }

        // Min quantity (global)
        var minQtyH='<div class="fg" style="margin-bottom:8px">'+fieldLabel('Минимално количество (глобално)','min_qty')+
        '<input type="number" class="fc" id="wMinQty" value="'+(S.wizData.min_quantity||0)+'" oninput="S.wizData.min_quantity=parseInt(this.value)||0" placeholder="0" style="font-size:12px"></div>';

        // AI description
        var descH='<div class="fg" style="margin-top:8px">'+fieldLabel('AI SEO описание','description')+
        '<textarea class="fc" id="wDesc" rows="4" placeholder="Натисни бутона за AI описание..." style="font-size:12px" '+(S.wizData.description?'':'readonly')+'>'+(S.wizData.description?esc(S.wizData.description):'')+'</textarea>'+
        '<div style="display:flex;gap:6px;margin-top:4px">'+
        '<span onclick="document.getElementById(\'wDesc\').removeAttribute(\'readonly\');document.getElementById(\'wDesc\').focus()" style="font-size:11px;color:#818cf8;cursor:pointer">\u270E Редактирай</span>'+
        '<button type="button" class="abtn" onclick="wizGenDescription()" style="font-size:10px;padding:5px 12px;border-color:rgba(99,102,241,0.2)">AI генерирай</button></div></div>';

        // Summary line
        var sumLine='<div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px;padding:8px 10px;border-radius:8px;background:rgba(99,102,241,0.04)"><b style="color:var(--text-primary)">'+esc(S.wizData.name||'')+'</b> \u00b7 '+fmtPrice(S.wizData.retail_price)+' \u00b7 Код: '+esc(S.wizData.code||'AI генерира')+'</div>';

        // S82.COLOR.4: final AI Studio prompt — Yes opens Studio after save, No saves directly.
        var finalPromptH =
            '<div class="s82-finalprompt">' +
                '<div class="s82-finalprompt-title">' +
                    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>' +
                    '✨ Искаш ли AI обработка?' +
                '</div>' +
                '<ul class="s82-finalprompt-list">' +
                    '<li>Махане на фона на снимките</li>' +
                    '<li>AI магия (модел носи дрехата)</li>' +
                    '<li>SEO описание за онлайн магазин</li>' +
                    '<li>Експорт CSV/PDF/Excel</li>' +
                '</ul>' +
                '<div class="s82-finalprompt-actions">' +
                    '<button type="button" class="s82-finalprompt-btn yes" onclick="wizFinalAIYes()">Да, отвори AI Studio</button>' +
                    '<button type="button" class="s82-finalprompt-btn no" onclick="wizFinalAINo()">Не, запази</button>' +
                '</div>' +
            '</div>';

        return '<div class="wiz-page active">'+
        '<div style="font-size:14px;font-weight:700;margin-bottom:4px">Бройки и запис</div>'+
        sumLine+unitH+matrixH+minQtyH+descH+
        finalPromptH+
        '<button class="abtn" onclick="wizGo(4)" style="margin-top:10px">\u2190 Назад към вариации</button>'+
        vskip+'</div>';
    }

    // ═══ STEP 6: ПЕЧАТ НА ЕТИКЕТИ ═══
    if(step===6){
        var pid=S.wizSavedId||0;
        var combos=S.wizData._printCombos||[];
        var isBG=(window._tenantCountry||'').toUpperCase()==='BG';
        var beforeDL=new Date()<new Date('2026-08-08');
        var showDual=isBG&&beforeDL;
        if(!S.wizData._printMode)S.wizData._printMode=showDual?'dual':'eur';
        var pm=S.wizData._printMode;
        var sup=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
        var supName=sup?sup.name:'';
        var supCity=sup?sup.city||'':'';

        var tabsH='<div style="display:flex;gap:4px;margin-bottom:10px;background:rgba(255,255,255,0.05);border-radius:10px;padding:3px">';
        if(showDual)tabsH+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='dual'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S.wizData._printMode=\'dual\';renderWizard()">€ + лв</div>';
        tabsH+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='eur'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S.wizData._printMode=\'eur\';renderWizard()">Само €</div>';
        tabsH+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='noprice'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S.wizData._printMode=\'noprice\';renderWizard()">Без цена</div>';
        tabsH+='</div>';

        var warnH='';
        if(showDual&&pm==='dual')warnH='<div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:7px 10px;margin-bottom:10px;display:flex;align-items:center;gap:6px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span style="font-size:10px;color:#fbbf24">Двойно изписване до 08.08.2026. След тази дата тази опция ще изчезне автоматично.</span></div>';

        var totalQty=0;
        var listH='<div style="font-size:11px;color:#64748b;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center"><span>Вариации за печат:</span><button type="button" class="abtn" onclick="wizLabelsX2()" style="font-size:10px;padding:4px 10px;border-color:rgba(99,102,241,0.2)">x2</button><button type="button" class="abtn" onclick="wizLabelsReset()" style="font-size:10px;padding:4px 10px;border-color:rgba(245,158,11,0.2);color:#fbbf24;margin-left:4px">1:1</button></div>';
        combos.forEach(function(c,i){
            var parts=c.parts||[];
            var labelH='';
            parts.forEach(function(p){
                var n=p.axis.toLowerCase();
                if(n.includes('размер')||n.includes('size'))labelH+='<span style="font-size:13px;font-weight:700;margin-right:4px">'+esc(p.value)+'</span>';
                else if(n.includes('цвят')||n.includes('color')){
                    var cc=CFG.colors.find(function(x){return x.name===p.value});
                    var hex=cc?cc.hex:'#666';
                    labelH+='<span style="display:inline-flex;align-items:center;gap:3px"><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:'+hex+';border:1px solid rgba(255,255,255,0.2)"></span><span style="font-size:11px">'+esc(p.value)+'</span></span>';
                }else{
                    labelH+='<span style="font-size:11px;margin-right:4px">'+esc(p.value)+'</span>';
                }
            });
            if(!labelH)labelH='<span style="font-size:12px;font-weight:600">Единичен</span>';
            var qty=c.printQty||1;
            totalQty+=qty;
            listH+='<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;padding:6px 10px;border-radius:8px;background:rgba(17,24,44,0.3);border:1px solid var(--border-subtle)">'+
            '<div style="flex:1;display:flex;align-items:center;flex-wrap:wrap;gap:4px">'+labelH+'</div>'+
            '<div style="display:flex;align-items:center;gap:0">'+
            '<button type="button" onclick="wizLblAdj('+i+',-1)" style="width:22px;height:26px;border:1px solid var(--border-subtle);border-radius:4px 0 0 4px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:13px;cursor:pointer;padding:0">\u2212</button>'+
            '<input type="number" class="fc" id="lblQty'+i+'" style="width:32px;padding:2px 0;text-align:center;font-size:12px;font-weight:700;border-radius:0;border-left:0;border-right:0" value="'+qty+'" min="0" onchange="wizLblRecalc()">'+
            '<button type="button" onclick="wizLblAdj('+i+',1)" style="width:22px;height:26px;border:1px solid var(--border-subtle);border-radius:0 4px 4px 0;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:13px;cursor:pointer;padding:0">+</button></div>'+
            '<div onclick="wizPrintLabels('+i+')" style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;cursor:pointer"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></div></div>';
        });

        var btnH='<button type="button" class="abtn save" style="margin-top:12px;font-size:14px;padding:12px" onclick="wizPrintLabels(-1)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:5px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Печатай всички (<span id="lblTotal">'+totalQty+'</span> ет.)</button>';

        return '<div class="wiz-page active"><div style="text-align:center;padding:16px 0 10px">'+
        '<div style="width:48px;height:48px;border-radius:50%;background:rgba(34,197,94,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 8px"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>'+
        '<div style="font-size:15px;font-weight:700;color:var(--success)">Артикулът е записан!</div>'+
        '<div style="font-size:12px;color:var(--text-secondary);margin-top:2px">'+esc(S.wizData.name||'')+' \u00b7 '+fmtPrice(S.wizData.retail_price)+'</div></div>'+
        tabsH+warnH+listH+btnH+
        '<button type="button" class="abtn" onclick="wizDownloadCSV()" style="margin-top:8px;font-size:12px;padding:10px;width:100%;border-color:rgba(99,102,241,0.15);color:var(--indigo-300)">Свали CSV за онлайн магазин</button>'+
        '<div style="display:flex;gap:8px;margin-top:10px">'+
        '<button class="abtn" onclick="closeWizard();openManualWizard()" style="flex:1;font-size:12px;padding:10px;border-color:rgba(99,102,241,0.2)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="2" style="vertical-align:-1px;margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Добави нов</button>'+
        '<button class="abtn" onclick="closeWizard()" style="flex:1;font-size:12px;padding:10px;color:var(--text-secondary)">Затвори</button></div>'+
        (_fromInventory?'<button class="abtn" onclick="wizReturnToInventory()" style="margin-top:8px;font-size:13px;padding:12px;border-color:rgba(34,197,94,0.3);color:#4ade80"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px"><polyline points="15 18 9 12 15 6"/></svg>Към инвентаризацията</button>':'')+'</div>';
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
    '<div style="padding:8px;border-radius:10px;border:1px dashed rgba(255,255,255,0.08);text-align:center;margin-bottom:6px;cursor:pointer" onclick="wizGo(6)"><span style="font-size:11px;color:#4b5563">Пропусни →</span></div>'+

    '<button class="abtn" onclick="wizGo(5)" style="margin-top:4px">← Назад</button>'+
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
    // Drawer entry — delegate to the unified wizard auto-flow
    closeDrawer && closeDrawer('studio');
    return wizAIProcessPhoto();
}

// S82.AI_STUDIO — auto-flow: bg removal + colour detection in parallel.
// Reads S.wizData._photoDataUrl, posts to /ai-image-processor.php and
// /ai-color-detect.php, then updates S.wizData._photoDataUrl with the
// bg-removed PNG and stores detected colours in S.wizData._aiDetectedColors.
// Renders wizard at each state transition so the user sees progress.
async function wizAIProcessPhoto(){
    if(!S.wizData._photoDataUrl){showToast('Първо добави снимка','error');return}
    // Convert data URL → Blob → File for multipart upload
    let blob;
    try{
        const r = await fetch(S.wizData._photoDataUrl);
        blob = await r.blob();
    }catch(e){showToast('Грешка при четене на снимката','error');return}
    const file = new File([blob], 'wiz-photo.' + (blob.type==='image/png'?'png':blob.type==='image/webp'?'webp':'jpg'), {type: blob.type || 'image/jpeg'});
    S.wizData._aiState = 'processing';
    S.wizData._aiError = null;
    renderWizard();
    const fd1 = new FormData(); fd1.append('image', file);
    const fd2 = new FormData(); fd2.append('image', file);
    try{
        const [bgRes, colorRes] = await Promise.allSettled([
            fetch('/ai-image-processor.php', {method:'POST', body: fd1, credentials:'same-origin'}).then(r=>r.json()),
            fetch('/ai-color-detect.php',     {method:'POST', body: fd2, credentials:'same-origin'}).then(r=>r.json()),
        ]);
        let bgOk = bgRes.status==='fulfilled' && bgRes.value && bgRes.value.ok;
        let colorOk = colorRes.status==='fulfilled' && colorRes.value && colorRes.value.ok;
        // bg removal — replace preview if successful
        if (bgOk) {
            S.wizData._photoBgRemoved = bgRes.value.url;
            S.wizData._photoDataUrl   = bgRes.value.url; // use new URL going forward (saved to product on wizSave)
        }
        // colours — store for step 4 auto-fill
        if (colorOk) {
            S.wizData._aiDetectedColors = colorRes.value.colors || [];
            S.wizData._aiColorsApplied  = false; // re-apply on next render of step 4
        }
        // Combined error reporting
        if (!bgOk && !colorOk) {
            S.wizData._aiState = 'error';
            const reason = (bgRes.value && bgRes.value.reason) || (colorRes.value && colorRes.value.reason) || 'AI грешка.';
            S.wizData._aiError = reason;
            showToast(reason, 'error');
        } else if (bgOk && colorOk) {
            S.wizData._aiState = 'done';
            const remaining = (bgRes.value.remaining!=null) ? bgRes.value.remaining : '?';
            showToast('AI готово ✓ (остават '+remaining+' AI кредита днес)', 'success');
        } else {
            // Partial success — still show as done but with a hint
            S.wizData._aiState = 'done';
            showToast(bgOk ? 'Бял фон ✓ (цветове неуспешни)' : 'Цветове ✓ (бял фон неуспешен)', 'success');
        }
        // S82.UI.FIX2: auto-advance to Variants step if variant type + colours detected
        if (S.wizType === 'variant' && colorOk && S.wizStep === 3) {
            setTimeout(function(){ wizGo(4); }, 1200);
        }
    }catch(e){
        console.error('AI process error', e);
        S.wizData._aiState = 'error';
        S.wizData._aiError = 'Мрежова грешка.';
        showToast('Мрежова грешка', 'error');
    }
    renderWizard();
}

// === S82.COLOR.4 — Photo mode + Camera loop + AI color detect ===

function wizSetPhotoMode(mode) {
    if (mode !== 'single' && mode !== 'multi') return;
    S.wizData._photoMode = mode;
    try { localStorage.setItem('_rms_photoMode', mode); } catch(e) {}
    if (navigator.vibrate) navigator.vibrate(8);
    renderWizard();
}

function wizPhotoMultiPick() {
    if (document.getElementById('rmsPickerDrawer')) {
        document.getElementById('rmsPickerDrawer').remove();
    }
    var dr = document.createElement('div');
    dr.id = 'rmsPickerDrawer';
    dr.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);z-index:9998;display:flex;align-items:flex-end;justify-content:center';
    dr.onclick = function(e) { if (e.target === dr) dr.remove(); };
    dr.innerHTML = '<div style="background:var(--bg-card,#0a0b14);border:1px solid var(--border-subtle);border-radius:18px 18px 0 0;padding:18px 14px calc(18px + env(safe-area-inset-bottom,0));width:100%;max-width:480px">' +
        '<div style="font-size:13px;font-weight:800;color:var(--text-primary);text-align:center;margin-bottom:12px">Добави снимка</div>' +
        '<div class="cam-drawer-tip">' +
            '<div class="cam-drawer-tip-icon">💡</div>' +
            '<div class="cam-drawer-tip-text">' +
                '<b>Ако се отвори селфи камерата:</b> излез, обърни я веднъж в нормалната <span class="cam-drawer-tip-app">📷 Camera</span> и Самсунг ще запомни задната завинаги. ' +
                '<span class="cam-drawer-tip-or">Иначе — обръщай я с <span class="cam-drawer-tip-flip">🔄</span> в Camera всеки път.</span>' +
            '</div>' +
        '</div>' +
        '<div style="display:flex;gap:8px">' +
            '<button type="button" onclick="document.getElementById(\'rmsPickerDrawer\').remove();wizPhotoCameraLoop()" style="flex:1;padding:14px 8px;border-radius:14px;background:linear-gradient(135deg,var(--indigo-500,#6366f1),var(--indigo-600,#4f46e5));border:1px solid var(--indigo-400,#818cf8);color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:6px"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Снимай</button>' +
            '<button type="button" onclick="document.getElementById(\'rmsPickerDrawer\').remove();wizPhotoMultiGalleryPick()" style="flex:1;padding:14px 8px;border-radius:14px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:var(--text-primary);font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:6px"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Галерия</button>' +
        '</div>' +
        '<button type="button" onclick="document.getElementById(\'rmsPickerDrawer\').remove()" style="width:100%;margin-top:10px;padding:11px;border-radius:12px;background:transparent;border:1px solid rgba(255,255,255,0.08);color:var(--text-secondary);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">Откажи</button>' +
    '</div>';
    document.body.appendChild(dr);
}

function wizPhotoMultiGalleryPick() {
    if (document.getElementById('_rmsGalPicker')) document.getElementById('_rmsGalPicker').remove();
    var inp = document.createElement('input');
    inp.type = 'file'; inp.id = '_rmsGalPicker'; inp.accept = 'image/*'; inp.multiple = true;
    inp.style.display = 'none';
    inp.onchange = async function(e) {
        var files = Array.from(e.target.files || []);
        await wizPhotoMultiAdd(files);
        inp.remove();
    };
    document.body.appendChild(inp);
    inp.click();
}

// S82.COLOR.11: native Samsung Camera per shot (real HDR / scene optimizer)
// S82.COLOR.17: native phone camera (full Samsung HDR) + drawer-level tip
// + 1000px @ q=0.80 downscale (~80 KB per photo).
//
// User feedback on COLOR.16 fullscreen setup wizard: "много е сложно" — too
// heavy for a 50+yo workflow. Solution: keep the camera loop minimal (just
// open native camera immediately), and put a one-line friendly tip ABOVE
// the Снимай/Галерия buttons in the picker drawer where the user actually
// reads it BEFORE tapping Снимай.
var _camPending = null;

function wizPhotoCameraLoop() {
    if (document.getElementById('rmsCamLoop')) document.getElementById('rmsCamLoop').remove();
    _camPending = null;
    var ov = document.createElement('div');
    ov.id = 'rmsCamLoop'; ov.className = 'cam-loop-ov show';
    var photoCount = (Array.isArray(S.wizData._photos) ? S.wizData._photos.length : 0) + 1;
    ov.innerHTML =
        '<div class="cam-loop-counter" id="rmsCamCounter">Снимай цвят ' + photoCount + '</div>' +
        '<div id="rmsCamStage" class="cam-loop-stage"></div>' +
        '<input type="file" id="rmsCamInput" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">' +
        '<div class="cam-loop-controls" id="rmsCamControls"></div>';
    document.body.appendChild(ov);
    document.getElementById('rmsCamInput').addEventListener('change', wizCamLoopOnFile);
    wizCamRenderEmpty();
    wizCamShoot(); // straight to native camera
}

function wizCamRenderEmpty() {
    var stage = document.getElementById('rmsCamStage');
    var taken = (Array.isArray(S.wizData._photos) ? S.wizData._photos.length : 0);
    var hint = taken
        ? 'Снимка ' + taken + ' добавена. Tap кръглия бутон за следващата.'
        : 'Tap кръглия бутон, за да отвориш камерата на телефона.';
    if (stage) {
        stage.innerHTML =
            '<div class="cam-loop-empty">' +
                '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>' +
                '<div class="cam-loop-empty-msg">' + hint + '</div>' +
            '</div>';
    }
    var ctl = document.getElementById('rmsCamControls');
    if (ctl) {
        ctl.innerHTML =
            '<button type="button" class="cam-loop-btn cancel" onclick="wizCamLoopClose()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
            '<button type="button" class="cam-loop-btn shoot" onclick="wizCamShoot()"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="9"/></svg></button>' +
            (taken ? '<button type="button" class="cam-loop-btn done" onclick="wizCamLoopFinish()"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Готово</button>' : '');
    }
}

function wizCamShoot() {
    // Synchronous click — preserves user gesture chain (iOS Safari requires this).
    var inp = document.getElementById('rmsCamInput');
    if (inp) inp.click();
}

function wizCamLoopOnFile(e) {
    var f = e.target.files && e.target.files[0];
    e.target.value = '';
    if (!f) {
        wizCamRenderEmpty();
        return;
    }
    var fr = new FileReader();
    fr.onload = async function() {
        var dataUrl = fr.result;
        // S82.COLOR.16: aggressive downscale to 1000px @ q=0.80 → ~80 KB per photo.
        // RIOT-equivalent compression — sweet spot for online-store storage cost.
        try { dataUrl = await _downscaleDataUrl(dataUrl, 1000, 0.80); } catch(err) { console.warn('downscale err:', err); }
        _camPending = dataUrl;
        var stage = document.getElementById('rmsCamStage');
        if (stage) stage.innerHTML = '<img class="cam-loop-preview" src="' + dataUrl + '" alt="">';
        var ctl = document.getElementById('rmsCamControls');
        if (ctl) {
            ctl.innerHTML =
                '<button type="button" class="cam-loop-btn retake" onclick="wizCamRetake()"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/></svg>Нова снимка</button>' +
                '<button type="button" class="cam-loop-btn next" onclick="wizCamAccept(true)">Следваща запис<svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>' +
                '<button type="button" class="cam-loop-btn done" onclick="wizCamAccept(false)"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Готово</button>';
        }
        if (navigator.vibrate) navigator.vibrate(6);
    };
    fr.readAsDataURL(f);
}

function wizCamRetake() {
    _camPending = null;
    wizCamShoot();
}

async function wizCamAccept(continueShooting) {
    if (!_camPending) return;
    if (!Array.isArray(S.wizData._photos)) S.wizData._photos = [];
    S.wizData._photos.push({ dataUrl: _camPending, file: null, ai_color: null, ai_hex: null, ai_confidence: null });
    _camPending = null;
    S.wizData._aiColorsApplied = false;
    if (continueShooting && S.wizData._photos.length < 30) {
        var ctr = document.getElementById('rmsCamCounter');
        if (ctr) ctr.textContent = 'Снимай цвят ' + (S.wizData._photos.length + 1);
        wizCamShoot();
    } else {
        wizCamLoopClose();
        wizPhotoDetectColors();
    }
}

function wizCamLoopFinish() {
    wizCamLoopClose();
    wizPhotoDetectColors();
}

function wizCamLoopClose() {
    _camPending = null;
    var ov = document.getElementById('rmsCamLoop');
    if (ov) ov.remove();
    if (typeof renderWizard === 'function') renderWizard();
}

function _downscaleDataUrl(dataUrl, maxDim, quality) {
    return new Promise(function(resolve) {
        var img = new Image();
        img.onload = function() {
            var w = img.width, h = img.height;
            var scale = Math.min(1, maxDim / Math.max(w, h));
            var dw = Math.round(w * scale), dh = Math.round(h * scale);
            var c = document.createElement('canvas');
            c.width = dw; c.height = dh;
            c.getContext('2d').drawImage(img, 0, 0, dw, dh);
            resolve(c.toDataURL('image/jpeg', quality));
        };
        img.onerror = function(){ resolve(dataUrl); };
        img.src = dataUrl;
    });
}

// === S82.COLOR.10: AI processing overlay — visible during Gemini Vision color detect ===
function wizShowAIWorking(count) {
    if (document.getElementById('rmsAIWorking')) document.getElementById('rmsAIWorking').remove();
    var ov = document.createElement('div');
    ov.id = 'rmsAIWorking';
    ov.className = 'ai-working-ov';
    ov.innerHTML =
        '<div class="ai-working-card">' +
            '<div class="ai-working-orb"><div></div><div></div><div></div></div>' +
            '<div class="ai-working-title">✨ AI анализира</div>' +
            '<div class="ai-working-msg">Разпознавам цветовете на ' + count + ' ' + (count === 1 ? 'снимка' : 'снимки') + '...</div>' +
            '<div class="ai-working-hint">Обикновено отнема 3-8 секунди</div>' +
        '</div>';
    document.body.appendChild(ov);
}

function wizHideAIWorking() {
    var ov = document.getElementById('rmsAIWorking');
    if (ov) ov.remove();
}

async function wizPhotoMultiAdd(files) {
    if (!Array.isArray(S.wizData._photos)) S.wizData._photos = [];
    var room = 30 - S.wizData._photos.length;
    if (room <= 0) {
        if (typeof showToast === 'function') showToast('Максимум 30 снимки', 'error');
        return;
    }
    var accepted = files.slice(0, room);
    for (var i = 0; i < accepted.length; i++) {
        var file = accepted[i];
        try {
            var dataUrl = await new Promise(function(res, rej) {
                var fr = new FileReader();
                fr.onload = function() { res(fr.result); };
                fr.onerror = rej;
                fr.readAsDataURL(file);
            });
            // S82.COLOR.16: aggressive downscale 1000px @ q=0.80 → ~80 KB (RIOT-equivalent for online-store storage).
            dataUrl = await _downscaleDataUrl(dataUrl, 1000, 0.80);
            S.wizData._photos.push({ dataUrl: dataUrl, file: null, ai_color: null, ai_hex: null, ai_confidence: null });
        } catch (err) { console.warn('[S82.COLOR.7] Read err:', err); }
    }
    S.wizData._aiColorsApplied = false;
    renderWizard();
    wizPhotoDetectColors();
}

function wizPhotoMultiRemove(idx) {
    if (!Array.isArray(S.wizData._photos)) return;
    if (idx < 0 || idx >= S.wizData._photos.length) return;
    if (!confirm('Премахни снимка №' + (idx+1) + '?')) return;
    S.wizData._photos.splice(idx, 1);
    // Re-applying colours after removal needs a refresh — drop the applied flag.
    S.wizData._aiColorsApplied = false;
    renderWizard();
}

function wizPhotoSetColor(idx, value) {
    if (!Array.isArray(S.wizData._photos)) return;
    if (idx < 0 || idx >= S.wizData._photos.length) return;
    S.wizData._photos[idx].ai_color = (value || '').trim();
    S.wizData._aiColorsApplied = false;
}

function _markPhotosFailed(indices) {
    indices.forEach(function(idx){
        if (!S.wizData._photos[idx]) return;
        S.wizData._photos[idx].ai_color = '';
        S.wizData._photos[idx].ai_hex = '#666';
        S.wizData._photos[idx].ai_confidence = 0; // 0 = "tried, gave up" — stops the AI… spinner
    });
}

async function wizPhotoDetectColors() {
    if (!Array.isArray(S.wizData._photos) || !S.wizData._photos.length) return;
    var todo = [];
    var todoIndices = [];
    S.wizData._photos.forEach(function(p, i) {
        if (p.ai_confidence === null || p.ai_confidence === undefined) {
            todo.push(p);
            todoIndices.push(i);
        }
    });
    if (!todo.length) return;
    if (typeof wizShowAIWorking === 'function') wizShowAIWorking(todo.length);
    var fd = new FormData();
    var totalKB = 0;
    todo.forEach(function(p, i) {
        var arr = p.dataUrl.split(',');
        var mime = (arr[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
        var bstr = atob(arr[1]);
        var n = bstr.length;
        totalKB += n / 1024;
        var u8 = new Uint8Array(n);
        while (n--) u8[n] = bstr.charCodeAt(n);
        fd.append('image_' + i, new Blob([u8], { type: mime }), 'photo_' + i + '.jpg');
    });
    fd.append('count', String(todo.length));
    console.log('[S82.COLOR.7] AI detect: posting', todo.length, 'photos, total ~' + Math.round(totalKB) + ' KB');
    var r, j;
    try {
        r = await fetch('ai-color-detect.php?multi=1', { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch (err) {
        console.error('[S82.COLOR.7] AI fetch err:', err);
        if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
        if (typeof showToast === 'function') showToast('AI: мрежова грешка', 'error');
        _markPhotosFailed(todoIndices);
        renderWizard();
        return;
    }
    try { j = await r.json(); } catch(e) { j = null; }
    console.log('[S82.COLOR.7] AI response status=' + r.status, j);
    if (!r.ok) {
        if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
        var reason = (j && j.reason) || ('HTTP ' + r.status + (r.status === 413 || r.status === 400 ? ' — снимките са твърде големи' : ''));
        if (typeof showToast === 'function') showToast('AI: ' + reason, 'error');
        _markPhotosFailed(todoIndices);
        renderWizard();
        return;
    }
    var results = (j && (j.results || j.colors)) || null;
    if (!Array.isArray(results) || !results.length) {
        if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
        if (typeof showToast === 'function') showToast('AI не разпозна цветове', 'error');
        _markPhotosFailed(todoIndices);
        renderWizard();
        return;
    }
    // Apply by res.idx if Gemini provided it, else by sequence position.
    var applied = 0;
    results.forEach(function(res, i) {
        var targetIdx;
        if (typeof res.idx === 'number' && res.idx >= 0 && res.idx < todoIndices.length) {
            targetIdx = todoIndices[res.idx];
        } else {
            targetIdx = todoIndices[i];
        }
        if (targetIdx === undefined || !S.wizData._photos[targetIdx]) return;
        S.wizData._photos[targetIdx].ai_color = (res.color_bg || res.name || res.color || '').toString().trim();
        S.wizData._photos[targetIdx].ai_hex = res.hex || '#666';
        S.wizData._photos[targetIdx].ai_confidence = (typeof res.confidence === 'number') ? res.confidence : 0.5;
        applied++;
    });
    // Anything still null after the apply pass — mark as failed so the spinner stops.
    todoIndices.forEach(function(idx){
        if (!S.wizData._photos[idx]) return;
        if (S.wizData._photos[idx].ai_confidence === null || S.wizData._photos[idx].ai_confidence === undefined) {
            S.wizData._photos[idx].ai_color = '';
            S.wizData._photos[idx].ai_hex = '#666';
            S.wizData._photos[idx].ai_confidence = 0;
        }
    });
    S.wizData._aiColorsApplied = false;
    if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
    if (typeof showToast === 'function') showToast('AI разпозна ' + applied + '/' + todoIndices.length + ' цвята', applied ? 'success' : 'error');
    renderWizard();
}

// Step 5 final AI prompt → answer 'yes' / 'no'
function wizFinalAIYes() {
    S.wizData._openStudioAfterSave = true;
    if (typeof wizSave === 'function') wizSave();
}

function wizFinalAINo() {
    S.wizData._openStudioAfterSave = false;
    if (typeof wizSave === 'function') wizSave();
}

// ═══════════════════════════════════════════════════════════════════════════
// S82.STUDIO.1.a — AI Studio modal (scaffold + plan lock + credits + bg removal)
// Opens after wizSave success when user picked "Да, отвори AI Studio" in step 5.
// Phase 1 ships: modal frame, plan-aware lock (FREE), credits bar (live from
// ?ajax=ai_credits), single-photo bg removal via ai-image-processor.php.
// Subsequent phases will add: AI Magic + Studio presets (Phase 2), SEO desc
// (Phase 3), print + CSV/PDF + buy-credits modal (Phase 4).
// ═══════════════════════════════════════════════════════════════════════════
var _studioState = null; // { productId, credits, photos[] }

async function openStudioModal(productId) {
    if (document.getElementById('aiStudioModal')) {
        document.getElementById('aiStudioModal').remove();
    }
    _studioState = { productId: productId, credits: null, photos: [] };
    // Pull a snapshot of any photos the wizard captured (for bulk bg removal).
    if (S && S.wizData && Array.isArray(S.wizData._photos)) {
        _studioState.photos = S.wizData._photos.slice();
    } else if (S && S.wizData && S.wizData._photoDataUrl) {
        _studioState.photos = [{ dataUrl: S.wizData._photoDataUrl, ai_color: null, ai_hex: null, ai_confidence: null }];
    }
    // Build the shell immediately so the user sees feedback while we fetch credits.
    var ov = document.createElement('div');
    ov.id = 'aiStudioModal';
    ov.className = 'studio-modal-ov show';
    ov.onclick = function(e){ if (e.target === ov) closeStudioModal(); };
    ov.innerHTML =
        '<div class="glass studio-modal" id="aiStudioCard">' +
            '<span class="shine shine-top"></span><span class="shine shine-bottom"></span>' +
            '<span class="glow glow-top"></span><span class="glow glow-bottom"></span>' +
            '<div class="studio-modal-hdr">' +
                '<button type="button" class="studio-mh-close" onclick="closeStudioModal()" aria-label="Затвори">✕</button>' +
                '<h2>✨ AI Studio</h2>' +
                '<button type="button" class="studio-mh-help" onclick="alert(\'AI Studio: автоматично махане на фон, AI магия за дрехи, SEO описание, печат, експорт — всичко за един артикул.\')" aria-label="Какво е това">?</button>' +
            '</div>' +
            '<div class="studio-modal-body" id="studioModalBody">' +
                '<div class="studio-loading"><div class="studio-spin"></div><div>Зареждам AI Studio...</div></div>' +
            '</div>' +
        '</div>';
    document.body.appendChild(ov);
    // Fetch credits + plan, then render the appropriate body (lock or sections).
    try {
        var r = await fetch('products.php?ajax=ai_credits', { credentials: 'same-origin' });
        var j = await r.json();
        _studioState.credits = j;
        if (j && j.is_locked) {
            studioRenderLock(j);
        } else {
            studioRenderSections(j);
        }
    } catch (err) {
        console.error('[S82.STUDIO.1] credits fetch failed:', err);
        document.getElementById('studioModalBody').innerHTML =
            '<div class="studio-error">Не можах да заредя AI Studio. Опитай отново след малко.</div>';
    }
}

function closeStudioModal() {
    var ov = document.getElementById('aiStudioModal');
    if (ov) ov.remove();
    _studioState = null;
}

// ─── Plan-aware lock (shown to FREE tenants) ─────────────────────────────
function studioRenderLock(credits) {
    var body = document.getElementById('studioModalBody');
    if (!body) return;
    body.innerHTML =
        '<div class="studio-lock">' +
            '<div class="studio-lock-ico">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' +
            '</div>' +
            '<div class="studio-lock-title">AI Studio е в START план</div>' +
            '<div class="studio-lock-sub">Включи 50 безплатни AI снимки на месец със START · 4 месеца триал PRO без карта (300 снимки/мес)</div>' +
            '<div class="studio-lock-features">' +
                '<div class="studio-lock-feat"><svg viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Махане фон</div>' +
                '<div class="studio-lock-feat"><svg viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>SEO описание</div>' +
                '<div class="studio-lock-feat"><svg viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Облечи на модел</div>' +
                '<div class="studio-lock-feat"><svg viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Студийна снимка</div>' +
            '</div>' +
            '<button type="button" class="studio-lock-cta" onclick="window.location.href=\'/billing.php\'">Виж планове · 4 месеца безплатно</button>' +
            '<button type="button" class="studio-lock-skip" onclick="closeStudioModal()">Сега не · отиди в артикулите</button>' +
        '</div>';
}

// ─── Studio sections (shown to START / PRO / GOD tenants) ─────────────────
function studioRenderSections(credits) {
    var body = document.getElementById('studioModalBody');
    if (!body) return;
    var planLabel = (credits.plan || 'free').toUpperCase();
    if (credits.plan === 'god') planLabel = 'PRO';
    var planClass = (credits.plan === 'pro' || credits.plan === 'god') ? 'pro' : 'start';
    var photos = _studioState.photos;
    var firstPhoto = photos.length ? photos[0].dataUrl : null;
    var doneAny = photos.some(function(p){ return p._bgState === 'done'; });
    var donePhoto = doneAny ? photos.find(function(p){ return p._bgState === 'done'; }).dataUrl : null;

    body.innerHTML =
        // ─── CREDITS BAR ───
        '<button type="button" class="studio-credits-bar" id="studioCreditsBar" onclick="studioOpenBuyCredits()">' +
            '<span class="studio-cr-plan ' + planClass + '">' + planLabel + '</span>' +
            '<div class="studio-cr-content">' +
                '<div class="studio-cr-line">' +
                    '<span class="studio-cr-item"><b>' + credits.bg_remaining + '</b> бял фон</span>' +
                    '<span class="studio-cr-sep"></span>' +
                    '<span class="studio-cr-item tryon"><b>' + credits.tryon_remaining + '</b> AI магия</span>' +
                '</div>' +
                '<div class="studio-cr-sub">' + (credits.plan === 'god' ? 'Неограничени · god mode' : 'от ' + credits.bg_total + ' / ' + credits.tryon_total + ' включени · купи още') + '</div>' +
            '</div>' +
            '<div class="studio-cr-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></div>' +
        '</button>' +

        // ─── IMAGE COMPARE (before / after) ───
        (firstPhoto ?
        '<div class="studio-compare">' +
            '<div class="studio-cmp-cell">' +
                '<span class="studio-cmp-pill before">Оригинал</span>' +
                '<img src="' + firstPhoto + '" alt="">' +
            '</div>' +
            '<div class="studio-cmp-cell">' +
                '<span class="studio-cmp-pill after">След AI</span>' +
                (donePhoto ? '<img src="' + donePhoto + '" alt="" style="background:#fff">' : '<div class="studio-cmp-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><div>tap "Махни фона"</div></div>') +
            '</div>' +
        '</div>' : '') +

        // ─── SECTION 1: Бял фон ───
        '<div class="studio-section">' +
            '<div class="studio-sect-head">' +
                '<div class="studio-sect-ico bg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>' +
                '<div class="studio-sect-text">' +
                    '<div class="studio-sect-title">Махане на бял фон</div>' +
                    '<div class="studio-sect-sub">Чист бял студиен изглед · birefnet AI</div>' +
                '</div>' +
                '<div class="studio-sect-price">0.03€</div>' +
            '</div>' +
            '<div class="studio-bg-grid" id="studioBgGrid"></div>' +
            (photos.length > 1 ? '<button type="button" class="studio-bulk-btn" onclick="studioBgRemoveAll()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Махни фона на ВСИЧКИ ' + photos.length + ' снимки</button>' : '') +
        '</div>' +

        // ─── SECTION 2: AI Магия (placeholder + UI grid) ───
        '<div class="studio-section">' +
            '<div class="studio-sect-head">' +
                '<div class="studio-sect-ico magic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>' +
                '<div class="studio-sect-text">' +
                    '<div class="studio-sect-title">AI Магия — облечи на модел</div>' +
                    '<div class="studio-sect-sub">Дрехите остават БЕЗ деформация</div>' +
                '</div>' +
                '<div class="studio-sect-price tryon">0.40€</div>' +
            '</div>' +
            '<div class="studio-models-grid">' +
                ['Жена','Мъж','Момиче','Момче','Тиин Ж','Тиин М'].map(function(lbl, i){
                    var sel = (_studioState.magicModel === i) ? ' sel' : '';
                    return '<button type="button" class="studio-model-btn' + sel + '" onclick="studioPickMagicModel(' + i + ')">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="6" r="3"/><path d="M5 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg>' +
                        '<div class="studio-model-lbl">' + lbl + '</div>' +
                    '</button>';
                }).join('') +
            '</div>' +
            '<div class="studio-prompt-row">' +
                '<input type="text" id="studioMagicPrompt" placeholder="допълни: стояща поза, лятна сцена, профил..." class="studio-prompt-input">' +
            '</div>' +
            '<button type="button" class="studio-gen-btn magic" onclick="studioGenerateMagic()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Генерирай на ' + (['Жена','Мъж','Момиче','Момче','Тиин Ж','Тиин М'][_studioState.magicModel || 0]) + '</button>' +
        '</div>' +

        // ─── SECTION 3: Студийна снимка (preset chips) ───
        '<div class="studio-section">' +
            '<div class="studio-sect-head">' +
                '<div class="studio-sect-ico studio"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>' +
                '<div class="studio-sect-text">' +
                    '<div class="studio-sect-title">Студийна снимка</div>' +
                    '<div class="studio-sect-sub">Бижута · обувки · чанти · аксесоари</div>' +
                '</div>' +
                '<div class="studio-sect-price tryon">0.40€</div>' +
            '</div>' +
            '<div class="studio-preset-grid">' +
                [
                    {l:'Бижу на ръка', i:'<circle cx="12" cy="12" r="3"/>'},
                    {l:'На кадифе', i:'<rect x="3" y="6" width="18" height="13" rx="2"/>'},
                    {l:'На мрамор', i:'<path d="M3 12h18M3 6h18M3 18h18"/>'},
                    {l:'Макро', i:'<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>'},
                    {l:'На дърво', i:'<path d="M12 2v20M2 12h20"/>'},
                    {l:'Lifestyle', i:'<path d="M3 12c0-5 4-9 9-9s9 4 9 9"/><path d="M3 12c0 5 4 9 9 9s9-4 9-9"/>'},
                    {l:'Обувка крак', i:'<path d="M3 12L12 4l9 8M5 10v10h14V10"/>'},
                    {l:'Чанта рамо', i:'<rect x="6" y="8" width="12" height="12" rx="2"/><path d="M9 8V5a3 3 0 0 1 6 0v3"/>'}
                ].map(function(p, i){
                    var sel = (_studioState.studioPreset === i) ? ' sel' : '';
                    return '<button type="button" class="studio-preset-chip' + sel + '" onclick="studioPickStudioPreset(' + i + ')">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' + p.i + '</svg>' +
                        p.l +
                    '</button>';
                }).join('') +
            '</div>' +
            '<div class="studio-prompt-row">' +
                '<input type="text" id="studioStudioPrompt" placeholder="или опиши: пръстен в червена кутийка..." class="studio-prompt-input">' +
            '</div>' +
            '<button type="button" class="studio-gen-btn studio" onclick="studioGenerateStudio()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Генерирай студийна снимка</button>' +
        '</div>' +

        // ─── SECTION 4: SEO description ───
        '<div class="studio-section">' +
            '<div class="studio-sect-head">' +
                '<div class="studio-sect-ico seo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg></div>' +
                '<div class="studio-sect-text">' +
                    '<div class="studio-sect-title">AI SEO описание</div>' +
                    '<div class="studio-sect-sub">За онлайн магазин · WooCommerce · Shopify</div>' +
                '</div>' +
            '</div>' +
            '<textarea class="studio-seo-textarea" id="studioSEOArea" placeholder="Tap \'Генерирай\' за AI описание..." oninput="studioUpdateSEOStats()">' + (S.wizData.description || '').replace(/</g, '&lt;') + '</textarea>' +
            '<div class="studio-seo-stats" id="studioSEOStats">0 думи · 0 символа</div>' +
            '<div class="studio-seo-actions">' +
                '<button type="button" class="studio-seo-btn primary" onclick="studioGenerateSEO()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/></svg>Генерирай ново</button>' +
                '<button type="button" class="studio-seo-btn" onclick="document.getElementById(\'studioSEOArea\').focus()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Редактирай</button>' +
            '</div>' +
        '</div>' +

        // ─── EXPORT GRID ───
        '<div class="studio-section">' +
            '<div class="studio-sect-head">' +
                '<div class="studio-sect-ico exp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></div>' +
                '<div class="studio-sect-text">' +
                    '<div class="studio-sect-title">Експорт &amp; печат</div>' +
                    '<div class="studio-sect-sub">Етикет · CSV за магазин · PDF каталог</div>' +
                '</div>' +
            '</div>' +
            '<div class="studio-export-row">' +
                '<button type="button" class="studio-export-btn" onclick="studioExportLabel()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg><div class="seb-lbl">Етикет</div><div class="seb-sub">Bluetooth</div></button>' +
                '<button type="button" class="studio-export-btn" onclick="studioExportCSV()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg><div class="seb-lbl">CSV</div><div class="seb-sub">Woo / Shopify</div></button>' +
                '<button type="button" class="studio-export-btn" onclick="studioExportPDF()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 13h2a2 2 0 0 1 0 4h-2v-4z"/></svg><div class="seb-lbl">PDF</div><div class="seb-sub">Каталог</div></button>' +
            '</div>' +
        '</div>' +

        '<button type="button" class="studio-done-btn" onclick="closeStudioModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Готово · затвори</button>';

    studioRenderBgGrid();
    studioUpdateSEOStats();
}

// ── Magic / Studio model + preset selectors ──
function studioPickMagicModel(idx) {
    if (!_studioState) return;
    _studioState.magicModel = idx;
    document.querySelectorAll('.studio-model-btn').forEach(function(b, i){ b.classList.toggle('sel', i === idx); });
    var lbls = ['Жена','Мъж','Момиче','Момче','Тиин Ж','Тиин М'];
    var btn = document.querySelector('.studio-gen-btn.magic');
    if (btn) btn.lastChild.textContent = 'Генерирай на ' + lbls[idx];
    if (navigator.vibrate) navigator.vibrate(5);
}

function studioPickStudioPreset(idx) {
    if (!_studioState) return;
    _studioState.studioPreset = idx;
    document.querySelectorAll('.studio-preset-chip').forEach(function(b, i){ b.classList.toggle('sel', i === idx); });
    if (navigator.vibrate) navigator.vibrate(5);
}

// ── AI Magic / Studio generation (placeholders until nano-banana-pro endpoint exists) ──
function studioGenerateMagic() {
    if (typeof showToast === 'function') showToast('AI Магия — backend (nano-banana-pro) идва. UI готов.', 'error');
}
function studioGenerateStudio() {
    if (typeof showToast === 'function') showToast('Студийна снимка — backend (nano-banana-pro) идва. UI готов.', 'error');
}

// ── SEO description: textarea stats + Gemini generate ──
function studioUpdateSEOStats() {
    var ta = document.getElementById('studioSEOArea');
    var st = document.getElementById('studioSEOStats');
    if (!ta || !st) return;
    var t = (ta.value || '').trim();
    var w = t ? t.split(/\s+/).length : 0;
    st.textContent = w + ' думи · ' + t.length + ' символа' + (w >= 40 ? ' · ✓ SEO ок' : '');
    S.wizData.description = ta.value;
}

async function studioGenerateSEO() {
    var ta = document.getElementById('studioSEOArea');
    if (!ta) return;
    var name = S.wizData.name || '';
    if (!name) { if (typeof showToast === 'function') showToast('Първо въведи име на артикула', 'error'); return; }
    ta.value = ''; ta.placeholder = 'AI генерира...';
    studioUpdateSEOStats();
    var cats = (typeof CFG !== 'undefined' && CFG.categories) ? CFG.categories.find(function(c){return c.id == S.wizData.category_id;}) : null;
    var sups = (typeof CFG !== 'undefined' && CFG.suppliers) ? CFG.suppliers.find(function(s){return s.id == S.wizData.supplier_id;}) : null;
    var axes = '';
    if (S.wizData.axes) S.wizData.axes.forEach(function(a){ if (a.values && a.values.length) axes += a.name + ': ' + a.values.join(', ') + '. '; });
    try {
        var r = await fetch('products.php?ajax=ai_description', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ name: name, category: cats ? cats.name : '', supplier: sups ? sups.name : '', axes: axes, composition: S.wizData.composition || '' })
        });
        var j = await r.json();
        if (j && j.description) {
            ta.value = j.description;
            S.wizData.description = j.description;
            studioUpdateSEOStats();
            if (typeof showToast === 'function') showToast('AI описание готово ✓', 'success');
        } else {
            ta.placeholder = 'Описанието не можа да се генерира';
            if (typeof showToast === 'function') showToast('AI: грешка', 'error');
        }
    } catch (err) {
        console.warn('[S82.STUDIO.D] SEO gen err:', err);
        ta.placeholder = 'Мрежова грешка';
        if (typeof showToast === 'function') showToast('Мрежова грешка', 'error');
    }
}

// ── Exports ──
async function studioExportLabel() {
    var pid = _studioState ? _studioState.productId : null;
    if (!pid) { if (typeof showToast === 'function') showToast('Артикулът не е запазен', 'error'); return; }
    if (window.CapPrinter && typeof window.CapPrinter.print === 'function' && typeof window.CapPrinter._isCapacitor === 'function' && window.CapPrinter._isCapacitor()) {
        try {
            var prod = {
                code: S.wizData.code || ('PRD' + pid),
                name: S.wizData.name || '',
                retail_price: parseFloat(S.wizData.retail_price) || 0,
                barcode: S.wizData.barcode || ('200' + String(pid).padStart(9, '0'))
            };
            var store = { name: (typeof CFG !== 'undefined' && CFG.storeName) || 'Магазин', currency: (typeof CFG !== 'undefined' && CFG.currency) || 'EUR' };
            var copies = parseInt(prompt('Колко етикета?', '1')) || 1;
            await window.CapPrinter.print(prod, store, copies);
            if (typeof showToast === 'function') showToast('Печатам ' + copies + ' етикет(а) ✓', 'success');
        } catch (err) {
            console.error('[S82.STUDIO.D] print err:', err);
            if (typeof showToast === 'function') showToast('Печат: ' + (err.message || 'грешка'), 'error');
        }
    } else {
        if (typeof showToast === 'function') showToast('Печатът работи само в мобилното приложение (DTM-5811 BLE)', 'error');
    }
}

function studioExportCSV() {
    var pid = _studioState ? _studioState.productId : null;
    if (!pid) { if (typeof showToast === 'function') showToast('Артикулът не е запазен', 'error'); return; }
    // Build minimal WooCommerce-compatible CSV row.
    var rows = [];
    var headers = ['SKU','Name','Type','Regular price','Description','Categories','Stock'];
    rows.push(headers);
    var basicRow = [
        S.wizData.code || ('PRD' + pid),
        S.wizData.name || '',
        'simple',
        (S.wizData.retail_price || 0).toString(),
        (S.wizData.description || '').replace(/\n/g, ' '),
        (typeof CFG !== 'undefined' && CFG.categories) ? (CFG.categories.find(function(c){return c.id==S.wizData.category_id;}) || {}).name || '' : '',
        ''
    ];
    rows.push(basicRow);
    var csv = rows.map(function(r){
        return r.map(function(c){ var s = String(c == null ? '' : c); if (/[",\n]/.test(s)) s = '"' + s.replace(/"/g, '""') + '"'; return s; }).join(',');
    }).join('\n');
    var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'product_' + pid + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
    if (typeof showToast === 'function') showToast('CSV изтеглен ✓', 'success');
}

function studioExportPDF() {
    var pid = _studioState ? _studioState.productId : null;
    if (!pid) { if (typeof showToast === 'function') showToast('Артикулът не е запазен', 'error'); return; }
    // Simple print-ready HTML page in a new window — user can save as PDF.
    var w = window.open('', '_blank');
    if (!w) { if (typeof showToast === 'function') showToast('Pop-up блокиран', 'error'); return; }
    var photos = (_studioState.photos || []).map(function(p){ return '<img src="' + p.dataUrl + '" style="width:200px;height:200px;object-fit:cover;border:1px solid #ccc;margin:4px">'; }).join('');
    var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + (S.wizData.name || 'Артикул') + '</title>' +
        '<style>body{font-family:system-ui,sans-serif;padding:30px;color:#222;max-width:800px;margin:auto}' +
        'h1{font-size:22px;margin-bottom:8px}.meta{color:#666;font-size:12px;margin-bottom:18px}' +
        '.gallery{display:flex;flex-wrap:wrap;gap:6px;margin:14px 0}' +
        '.desc{font-size:13px;line-height:1.6;margin-top:10px}' +
        '.price{font-size:24px;font-weight:700;color:#16a34a;margin:10px 0}</style></head><body>' +
        '<h1>' + (S.wizData.name || '') + '</h1>' +
        '<div class="meta">SKU: ' + (S.wizData.code || ('PRD' + pid)) + ' · Баркод: ' + (S.wizData.barcode || '') + '</div>' +
        '<div class="price">' + (S.wizData.retail_price || 0) + ' лв</div>' +
        '<div class="gallery">' + photos + '</div>' +
        '<div class="desc">' + (S.wizData.description || '').replace(/</g, '&lt;').replace(/\n/g, '<br>') + '</div>' +
        '<script>setTimeout(function(){window.print();},400);<\/script>' +
        '</body></html>';
    w.document.write(html); w.document.close();
}

// ── Buy credits modal (Stripe placeholder) ──
function studioOpenBuyCredits() {
    if (document.getElementById('studioBuyModal')) document.getElementById('studioBuyModal').remove();
    var ov = document.createElement('div');
    ov.id = 'studioBuyModal'; ov.className = 'studio-buy-ov';
    ov.onclick = function(e){ if (e.target === ov) studioCloseBuyCredits(); };
    ov.innerHTML =
        '<div class="glass studio-buy-card">' +
            '<span class="shine shine-top"></span><span class="shine shine-bottom"></span>' +
            '<span class="glow glow-top"></span><span class="glow glow-bottom"></span>' +
            '<div class="studio-buy-hdr">' +
                '<div class="studio-buy-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>' +
                '<h3>Купи AI кредити</h3>' +
                '<button type="button" class="studio-buy-x" onclick="studioCloseBuyCredits()">✕</button>' +
            '</div>' +
            '<div class="studio-buy-sub">Месечен PRO лимит: 300 фон + 20 AI магия. Допълнителните кредити НЕ изтичат — остават за следващите месеци.</div>' +
            '<button type="button" class="studio-pack" onclick="studioBuyPack(5)">' +
                '<div class="sp-price">5€</div>' +
                '<div class="sp-info"><div class="sp-main"><b>100</b> фон или <b>10</b> магия</div><div class="sp-sub">Достатъчно за малка партида</div></div>' +
            '</button>' +
            '<button type="button" class="studio-pack popular" onclick="studioBuyPack(15)">' +
                '<div class="sp-price">15€</div>' +
                '<div class="sp-info"><div class="sp-main"><b>350</b> фон или <b>35</b> магия</div><div class="sp-sub">17% спестяване</div></div>' +
                '<div class="sp-tag">препоръчан</div>' +
            '</button>' +
            '<button type="button" class="studio-pack" onclick="studioBuyPack(40)">' +
                '<div class="sp-price">40€</div>' +
                '<div class="sp-info"><div class="sp-main"><b>1000</b> фон или <b>100</b> магия</div><div class="sp-sub">33% спестяване · цял сезон</div></div>' +
            '</button>' +
            '<button type="button" class="studio-buy-cancel" onclick="studioCloseBuyCredits()">Затвори</button>' +
        '</div>';
    document.body.appendChild(ov);
}
function studioCloseBuyCredits() {
    var ov = document.getElementById('studioBuyModal');
    if (ov) ov.remove();
}
function studioBuyPack(amt) {
    if (typeof showToast === 'function') showToast('Stripe плащания идват в S88. Засега — пиши на support.', '');
}

function studioRenderBgGrid() {
    var grid = document.getElementById('studioBgGrid');
    if (!grid) return;
    if (!_studioState.photos.length) {
        grid.innerHTML = '<div class="studio-bg-empty">Няма снимки за обработка. Добави снимки в стъпка 3 на wizard-а.</div>';
        return;
    }
    var html = '';
    _studioState.photos.forEach(function(p, i){
        var statusBadge = '';
        if (p._bgState === 'processing') statusBadge = '<div class="studio-bg-status processing">⏳ Обработва...</div>';
        else if (p._bgState === 'done') statusBadge = '<div class="studio-bg-status done">✓ Готово</div>';
        else if (p._bgState === 'error') statusBadge = '<div class="studio-bg-status error" title="' + (p._bgError||'грешка').replace(/"/g,'&quot;') + '">⚠ Грешка</div>';
        html +=
            '<div class="studio-bg-cell">' +
                '<div class="studio-bg-thumb"><img src="' + (p.dataUrl) + '" alt=""></div>' +
                statusBadge +
                (p._bgState !== 'done' && p._bgState !== 'processing' ? '<button type="button" class="studio-bg-btn" onclick="studioBgRemoveOne(' + i + ')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Махни фона</button>' : '') +
            '</div>';
    });
    grid.innerHTML = html;
}

async function studioBgRemoveOne(idx) {
    if (!_studioState || !_studioState.photos[idx]) return;
    var p = _studioState.photos[idx];
    if (p._bgState === 'processing') return;
    p._bgState = 'processing'; p._bgError = null;
    studioRenderBgGrid();
    try {
        // Convert dataUrl → Blob → File for multipart upload to ai-image-processor.php
        var arr = p.dataUrl.split(',');
        var mime = (arr[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
        var bstr = atob(arr[1]);
        var n = bstr.length;
        var u8 = new Uint8Array(n);
        while (n--) u8[n] = bstr.charCodeAt(n);
        var blob = new Blob([u8], { type: mime });
        var fd = new FormData();
        fd.append('image', blob, 'photo_' + idx + '.jpg');
        var r = await fetch('ai-image-processor.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        var j; try { j = await r.json(); } catch(e) { j = null; }
        if (!r.ok || !j || !j.ok) {
            var reason = (j && j.reason) || ('HTTP ' + r.status);
            p._bgState = 'error';
            p._bgError = reason;
            studioRenderBgGrid();
            if (typeof showToast === 'function') showToast('Bg: ' + reason, 'error');
            // Refresh credits — quota may have ticked up if it was a quota error
            await studioRefreshCredits();
            return;
        }
        // Success: replace dataUrl with the bg-removed URL.
        p.dataUrl = j.url;
        p._bgState = 'done';
        if (S.wizData._photos && S.wizData._photos[idx]) S.wizData._photos[idx].dataUrl = j.url;
        else if (idx === 0 && S.wizData._photoDataUrl) S.wizData._photoDataUrl = j.url;
        studioRenderBgGrid();
        await studioRefreshCredits();
        if (typeof showToast === 'function') showToast('Бял фон ✓ (остават ' + (j.remaining != null ? j.remaining : '?') + ')', 'success');
    } catch (err) {
        console.error('[S82.STUDIO.1] bg remove error:', err);
        p._bgState = 'error'; p._bgError = err.message || 'мрежова грешка';
        studioRenderBgGrid();
        if (typeof showToast === 'function') showToast('Bg: мрежова грешка', 'error');
    }
}

async function studioBgRemoveAll() {
    if (!_studioState || !_studioState.photos.length) return;
    var todo = [];
    _studioState.photos.forEach(function(p, i){ if (p._bgState !== 'done' && p._bgState !== 'processing') todo.push(i); });
    if (!todo.length) { if (typeof showToast === 'function') showToast('Всички снимки вече са обработени', ''); return; }
    if (!confirm('Махни фона на ' + todo.length + ' снимки? Ще се изхарчат ' + todo.length + ' кредита.')) return;
    for (var k = 0; k < todo.length; k++) {
        await studioBgRemoveOne(todo[k]);
    }
}

async function studioRefreshCredits() {
    try {
        var r = await fetch('products.php?ajax=ai_credits', { credentials: 'same-origin' });
        var j = await r.json();
        _studioState.credits = j;
        var bar = document.getElementById('studioCreditsBar');
        if (bar) {
            var bg = bar.querySelector('.studio-cr-item:not(.tryon) b');
            var ty = bar.querySelector('.studio-cr-item.tryon b');
            if (bg) bg.textContent = j.bg_remaining;
            if (ty) ty.textContent = j.tryon_remaining;
        }
    } catch(e) {}
}

// Phase 4 placeholder — opens "Buy credits" modal (Stripe in S88).
function studioOpenBuyCredits() {
    if (typeof showToast === 'function') showToast('Купи кредити — идва в S82.STUDIO.4', '');
    // TODO Phase 4: render the 3-pack modal (€5 / €15 / €40).
}

async function doStudioTryon(){
    const prompt=document.getElementById('studioPromptClothes')?.value||'';
    showToast('AI генерира... 10-20 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'tryon_'+S.studioModel,prompt})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Генерирано ✓','success');wizGo(6)}
}

async function doStudioObjects(){
    const prompt=document.getElementById('studioPromptObjects')?.value||S.studioPreset||'';
    showToast('AI генерира... 10-20 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'object_studio',prompt})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Генерирано ✓','success');wizGo(6)}
}

// ─── HELPERS ───

// S90.PRODUCTS.SPRINT_B C2: scanner-ът работи и за артикулен номер.
// targetField: 'wBarcode' (default) или 'wCode'. title: header текст.
function wizScanBarcode(targetField,title){
    targetField = targetField || 'wBarcode';
    title = title || 'Сканирай баркод';
    const ov=document.createElement('div');ov.className='preset-ov';ov.id='barcodeScanOv';
    ov._scanTarget = targetField;
    ov.innerHTML='<style>#wizBcVid::-webkit-media-controls,#wizBcVid::-webkit-media-controls-panel,#wizBcVid::-webkit-media-controls-overlay-play-button,#wizBcVid::-webkit-media-controls-play-button,#wizBcVid::-webkit-media-controls-start-playback-button{display:none!important;-webkit-appearance:none!important}</style>'+
    '<div class="preset-box" style="text-align:center;padding:16px">'+
    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><span style="font-size:15px;font-weight:700">'+title+'</span><span style="font-size:22px;cursor:pointer" onclick="closeBarcodeScan()">✕</span></div>'+
    '<div id="wizBcWrap" style="position:relative;width:100%;aspect-ratio:16/10;max-height:250px;border-radius:12px;background:#000;overflow:hidden">'+
      '<video id="wizBcVid" autoplay playsinline muted disablepictureinpicture style="width:100%;height:100%;object-fit:cover;visibility:hidden"></video>'+
      '<div id="wizBcLoad" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;gap:8px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9" stroke-dasharray="40 20"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></circle></svg>Стартиране на камерата…</div>'+
    '</div>'+
    '<div style="margin-top:8px;font-size:11px;color:var(--text-secondary)">Насочи камерата към баркода</div>'+
    '<button class="abtn" onclick="closeBarcodeScan()" style="margin-top:10px">Затвори</button></div>';
    document.body.appendChild(ov);
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(stream=>{
        const vid=document.getElementById('wizBcVid');
        if(!vid){stream.getTracks().forEach(t=>t.stop());return}
        vid.srcObject=stream;vid._stream=stream;
        try{vid.muted=true;vid.playsInline=true}catch(_){}
        function _bcReveal(){var ld=document.getElementById('wizBcLoad');if(ld)ld.style.display='none';vid.style.visibility='visible'}
        vid.addEventListener('loadedmetadata',function(){_bcReveal();try{var _pp2=vid.play();if(_pp2&&_pp2.catch)_pp2.catch(function(){})}catch(_){}},{once:true});
        try{var _pp=vid.play();if(_pp&&_pp.then){_pp.then(_bcReveal).catch(function(){})}else{_bcReveal()}}catch(_){_bcReveal()}
        if('BarcodeDetector' in window){
            const det=new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','upc_a','upc_e']});
            vid._bcInterval=setInterval(async()=>{
                try{const codes=await det.detect(vid);
                if(codes.length){clearInterval(vid._bcInterval);const val=codes[0].rawValue;
                const target=ov._scanTarget||'wBarcode';
                const el=document.getElementById(target);if(el)el.value=val;
                if(target==='wCode'){S.wizData.code=val;showToast('Артикулен номер: '+val,'success');}
                else{S.wizData.barcode=val;showToast('Баркод: '+val,'success');}
                closeBarcodeScan();}
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


// ═══ S69: Size Preset Groups — ordered by business type ═══
// S70: Size groups from JSON + keyword-based business matching
var _SIZE_GROUPS=[
{id:'letters',label:'Дрехи — букви',values:['XS','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL','8XL']},
{id:'eu_clothing',label:'Дрехи — EU номера',values:['34','36','38','40','42','44','46','48','50','52','54','56','58','60','62','64','66','68']},
{id:'shoes_eu',label:'Обувки — EU',values:['35','36','37','38','39','40','41','42','43','44','45','46','47']},
{id:'shoes_kids',label:'Детски обувки',values:['19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35']},
{id:'kids_height',label:'Детски — ръст (cm)',values:['50','56','62','68','74','80','86','92','98','104','110','116','122','128','134','140','146','152','158','164','170']},
{id:'kids_age',label:'Детски — възраст',values:['0-3м','3-6м','6-9м','9-12м','12-18м','18-24м','2-3г','3-4г','4-5г','5-6г','6-7г','7-8г','8-9г','9-10г','10-11г','11-12г','13-14г','15-16г']},
{id:'pants_waist',label:'Панталони — талия',values:['W26','W27','W28','W29','W30','W31','W32','W33','W34','W36','W38','W40']},
{id:'pants_length',label:'Панталони — дължина',values:['L28','L30','L32','L34','L36']},
{id:'jeans',label:'Дънки (талия/дължина)',values:['26/30','27/30','28/30','28/32','29/32','30/30','30/32','30/34','31/32','32/30','32/32','32/34','33/32','34/32','34/34','36/32','36/34','38/32']},
{id:'bra',label:'Сутиени',values:['65A','65B','65C','65D','70A','70B','70C','70D','70E','75A','75B','75C','75D','75E','75F','80A','80B','80C','80D','80E','80F','85A','85B','85C','85D','85E','85F','90B','90C','90D','90E','90F','95B','95C','95D','95E','100C','100D','100E','100F','105D','105E','110D','110E','115D','115E','120D','120E','125D','130D']},
{id:'underwear',label:'Бельо',values:['XS','S','M','L','XL','2XL','3XL']},
{id:'socks',label:'Чорапи',values:['35-38','39-42','43-46']},
{id:'socks_num',label:'Чорапи — номера',values:['36-38','39-41','42-44','45-47']},
{id:'tights',label:'Чорапогащи',values:['1','2','3','4','5','S','M','L','XL']},
{id:'hats',label:'Шапки',values:['S/M','L/XL','One Size','54','55','56','57','58','59','60']},
{id:'gloves',label:'Ръкавици',values:['XS','S','M','L','XL','6','6.5','7','7.5','8','8.5','9','9.5','10']},
{id:'belts',label:'Колани',values:['80','85','90','95','100','105','110','115','120','S','M','L','XL']},
{id:'rings',label:'Пръстени',values:['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII','XIII','XIV','XV','XVI','XVII','XVIII','XIX','XX']},
{id:'rings_mm',label:'Пръстени — мм',values:['14','14.5','15','15.5','16','16.5','17','17.5','18','18.5','19','19.5','20','20.5','21']},
{id:'bracelets',label:'Гривни',values:['15cm','16cm','17cm','18cm','19cm','20cm','21cm','S','M','L']},
{id:'necklaces',label:'Колиета',values:['40cm','42cm','45cm','50cm','55cm','60cm','70cm','80cm']},
{id:'one_size',label:'Универсален',values:['One Size']},
{id:'bedding',label:'Спално бельо',values:['Единичен','Двоен','Полуторен','Кралски','70x140','90x200','140x200','160x200','180x200','200x200']},
{id:'towels',label:'Кърпи',values:['30x50','50x90','70x140','100x150']},
{id:'volume_ml',label:'Обем (мл)',values:['30ml','50ml','75ml','100ml','150ml','200ml','250ml','300ml','500ml','750ml','1000ml']},
{id:'weight_g',label:'Тегло (гр)',values:['50г','100г','150г','200г','250г','300г','500г','750г','1000г']}
];

// S70: Merge extra groups from JSON
if(window._BIZ_DATA && window._BIZ_DATA.extraGroups){
    window._BIZ_DATA.extraGroups.forEach(function(eg){
        if(!_SIZE_GROUPS.find(function(g){return g.id===eg.id})){
            _SIZE_GROUPS.push({id:eg.id, label:eg.label, values:eg.values});
        }
    });
}

// S70: Keyword-based business type matching (replaces _BIZ_SIZE_ORDER)
function _getSizePresetsOrdered(){
    var bt=(CFG.businessType||'').toLowerCase();
    var bk=(window._BIZ_DATA&&window._BIZ_DATA.bizKeywords)||{};
    
    // Score each biz category by keyword matches
    var scored=[];
    for(var key in bk){
        var entry=bk[key];
        var score=0;
        (entry.keywords||[]).forEach(function(kw){
            if(bt.indexOf(kw)!==-1) score++;
        });
        if(score>0) scored.push({key:key, score:score, groups:entry.groups||[]});
    }
    scored.sort(function(a,b){return b.score-a.score});
    
    // Merge groups from top N matches (not just winner)
    var orderedIds=[];
    var topN=scored.slice(0,3); // top 3 matches
    topN.forEach(function(match){
        (match.groups||[]).forEach(function(gid){
            if(orderedIds.indexOf(gid)===-1) orderedIds.push(gid);
        });
    });
    
    // Fallback if nothing matched
    if(!orderedIds.length){
        orderedIds=_SIZE_GROUPS.map(function(g){return g.id});
    }
    
    var groups=[];
    var usedIds={};
    
    // First: biz-coefficients preset (tenant-specific) with star
    if(window._bizVariants&&window._bizVariants.variant_presets){
        for(var k in window._bizVariants.variant_presets){
            if(k.toLowerCase().indexOf('размер')!==-1||k.toLowerCase().indexOf('size')!==-1){
                var v=window._bizVariants.variant_presets[k];
                if(v&&v.length) groups.push({label:'За твоя бизнес ★',vals:v,id:'_biz_custom'});
            }
        }
    }
    
    // Then: ordered groups from keyword matches
    orderedIds.forEach(function(gid){
        var g=_SIZE_GROUPS.find(function(sg){return sg.id===gid});
        if(g){groups.push({label:g.label,vals:g.values,id:g.id});usedIds[gid]=true}
    });
    
    // Finally: all remaining groups
    _SIZE_GROUPS.forEach(function(g){
        if(!usedIds[g.id]) groups.push({label:g.label,vals:g.values,id:g.id});
    });
    
    return groups;
}


// S69: Toggle preset value inline (no overlay)

// S70v2: Pinned groups management (persistent per tenant)

function _wizSavePinnedGroups(){
    try{localStorage.setItem('_rms_pinnedGroups_'+CFG.storeId,JSON.stringify(S._wizPinnedGroups||[]))}catch(e){}
}

function wizPinnedSelectAll(pgi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    var ax=S.wizData.axes[S._wizActiveTab];if(!ax)return;
    var allSel=pg.vals.every(function(v){return ax.values.indexOf(v)!==-1});
    if(allSel){pg.vals.forEach(function(v){var idx=ax.values.indexOf(v);if(idx!==-1)ax.values.splice(idx,1)})}
    else{pg.vals.forEach(function(v){if(ax.values.indexOf(v)===-1)ax.values.push(v)})}
    renderWizard();
}

function wizColorAddPrompt(){
    var name=prompt('Име на цвят:');if(!name)return;name=name.trim();if(!name)return;
    var hex=prompt('HEX код (напр. #FF5733):','#');if(!hex)return;hex=hex.trim();
    if(!/^#[0-9a-fA-F]{6}$/.test(hex)){showToast('Невалиден HEX','error');return}
    var fd=new FormData();fd.append('name',name);fd.append('hex',hex);
    fetch('products.php?ajax=add_color',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.error){showToast(d.error,'error');return}
        // Update CFG.colors — merge с custom
        if(d.added){
            var existing=CFG.colors.findIndex(function(c){return c.name.toLowerCase()===d.added.name.toLowerCase()});
            if(existing>=0)CFG.colors[existing]=d.added;
            else CFG.colors.push(d.added);
        }
        renderWizard();
        showToast('"'+name+'" добавен \u2713','success');
    }).catch(function(){showToast('Грешка','error')});
}
function wizColorEditPrompt(oldName,oldHex){
    var name=prompt('Ново име (празно = премахване):',oldName);
    if(name===null)return; // cancel
    name=name.trim();
    if(!name){
        // Delete
        if(!confirm('Премахни цвят "'+oldName+'"?'))return;
        var fd=new FormData();fd.append('name',oldName);
        fetch('products.php?ajax=delete_color',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
            if(d.error){showToast(d.error,'error');return}
            CFG.colors=CFG.colors.filter(function(c){return c.name.toLowerCase()!==oldName.toLowerCase()});
            renderWizard();
            showToast('Премахнат \u2713','success');
        });
        return;
    }
    var hex=prompt('HEX:',oldHex);
    if(!hex)return;hex=hex.trim();
    if(!/^#[0-9a-fA-F]{6}$/.test(hex)){showToast('Невалиден HEX','error');return}
    // Delete + add (name може да се е променило)
    var fd=new FormData();fd.append('name',oldName);
    fetch('products.php?ajax=delete_color',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(){
        var fd2=new FormData();fd2.append('name',name);fd2.append('hex',hex);
        return fetch('products.php?ajax=add_color',{method:'POST',body:fd2}).then(function(r){return r.json()});
    }).then(function(d){
        CFG.colors=CFG.colors.filter(function(c){return c.name.toLowerCase()!==oldName.toLowerCase()});
        if(d.added)CFG.colors.push(d.added);
        renderWizard();
        showToast('Обновен \u2713','success');
    });
}
function wizColorSelectAll(){
    var ax=S.wizData.axes[S._wizActiveTab];if(!ax)return;
    var allSel=CFG.colors.every(function(c){return ax.values.indexOf(c.name)!==-1});
    if(allSel){CFG.colors.forEach(function(c){var idx=ax.values.indexOf(c.name);if(idx!==-1)ax.values.splice(idx,1)})}
    else{CFG.colors.forEach(function(c){if(ax.values.indexOf(c.name)===-1)ax.values.push(c.name)})}
    renderWizard();
}

function wizPinnedRemoveGroup(pgi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    if(!confirm('Премахни групата "'+pg.label+'" от бързия достъп?\n\nВинаги можеш да я добавиш отново.'))return;
    // Deselect values from this group
    var ax=S.wizData.axes[S._wizActiveTab];
    if(ax){pg.vals.forEach(function(v){var idx=ax.values.indexOf(v);if(idx!==-1)ax.values.splice(idx,1)})}
    S._wizPinnedGroups.splice(pgi,1);
    _wizSavePinnedGroups();
    renderWizard();
}

function wizPinnedEditGroup(pgi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    var input=prompt('Редактирай стойности за "'+pg.label+'".\nРазделени със запетая:\n\n'+pg.vals.join(', '));
    if(input===null)return;
    var newVals=input.split(',').map(function(v){return v.trim()}).filter(function(v){return v.length>0});
    if(!newVals.length){showToast('Трябва поне една стойност','error');return}
    pg.vals=newVals;
    _wizSavePinnedGroups();
    renderWizard();
    showToast('Групата е обновена','success');
}

function wizResetPinnedGroups(){
    if(!confirm('Върни фабричните настройки на групите?\n\nТова ще изтрие промените ти.'))return;
    S._wizPinnedGroups=null;
    try{localStorage.removeItem('_rms_pinnedGroups_'+CFG.storeId)}catch(e){}
    renderWizard();
    showToast('Групите са върнати','success');
}

function wizShowMoreGroups(axIdx){
    var allGroups=_getSizePresetsOrdered();
    var pinned=S._wizPinnedGroups||[];
    var pinnedIds=pinned.map(function(p){return p.id});
    // Filter out already pinned
    var available=allGroups.filter(function(g){return pinnedIds.indexOf(g.id)===-1});
    if(!available.length){showToast('Всички групи вече са добавени','');return}
    // Build selection list
    var html='<div style="max-height:60vh;overflow-y:auto;padding:8px">';
    html+='<div style="font-size:12px;font-weight:700;color:var(--indigo-300);margin-bottom:8px">Избери група за добавяне:</div>';
    available.forEach(function(g,gi){
        html+='<div style="padding:10px 12px;margin-bottom:4px;border-radius:8px;background:rgba(17,24,44,0.5);border:1px solid var(--border-subtle);cursor:pointer;display:flex;align-items:center;justify-content:space-between" onclick="wizAddPinnedGroup(\''+g.id.replace(/'/g,"\\'")+'\',\''+g.label.replace(/'/g,"\\'")+'\');closePresetPicker()">';
        html+='<div><div style="font-size:12px;font-weight:600;color:var(--text-primary)">'+esc(g.label)+'</div><div style="font-size:9px;color:var(--text-secondary)">'+g.vals.length+' стойности</div></div>';
        html+='<span style="font-size:9px;color:var(--indigo-400);font-weight:600">+ Добави</span></div>';
    });
    html+='</div>';
    // Show as overlay
    var ov=document.createElement('div');ov.className='preset-ov';ov.id='presetPickerOv';
    ov.innerHTML='<div class="preset-box"><div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0 10px"><span style="font-size:15px;font-weight:700">Добави група</span><span style="font-size:22px;cursor:pointer" onclick="closePresetPicker()">\u2715</span></div>'+html+'</div>';
    ov.addEventListener('click',function(e){if(e.target===ov)closePresetPicker()});
    document.body.appendChild(ov);
}

function wizAddPinnedGroup(groupId,groupLabel){
    var allGroups=_getSizePresetsOrdered();
    var g=allGroups.find(function(gg){return gg.id===groupId});
    if(!g){
        // Try from _SIZE_GROUPS directly
        var sg=_SIZE_GROUPS.find(function(s){return s.id===groupId});
        if(sg)g={id:sg.id,label:sg.label,vals:sg.values};
    }
    if(!g){showToast('Групата не е намерена','error');return}
    if(!S._wizPinnedGroups)S._wizPinnedGroups=[];
    // Check not already pinned
    if(S._wizPinnedGroups.find(function(p){return p.id===groupId})){showToast('Вече е добавена','');return}
    S._wizPinnedGroups.push({id:g.id,label:g.label,vals:g.vals.slice()});
    _wizSavePinnedGroups();
    renderWizard();
    showToast('"'+g.label+'" добавена','success');
}

// S70v3: Pinned groups + edit + reorder + custom groups

function _wizSavePinnedGroups(){
    try{localStorage.setItem('_rms_pinnedGroups_'+CFG.storeId,JSON.stringify(S._wizPinnedGroups||[]))}catch(e){}
}

function wizPinnedSelectAll(pgi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    var ax=S.wizData.axes[S._wizActiveTab];if(!ax)return;
    var allSel=pg.vals.every(function(v){return ax.values.indexOf(v)!==-1});
    if(allSel){pg.vals.forEach(function(v){var idx=ax.values.indexOf(v);if(idx!==-1)ax.values.splice(idx,1)})}
    else{pg.vals.forEach(function(v){if(ax.values.indexOf(v)===-1)ax.values.push(v)})}
    renderWizard();
}

function wizColorSelectAll(){
    var ax=S.wizData.axes[S._wizActiveTab];if(!ax)return;
    var allSel=CFG.colors.every(function(c){return ax.values.indexOf(c.name)!==-1});
    if(allSel){CFG.colors.forEach(function(c){var idx=ax.values.indexOf(c.name);if(idx!==-1)ax.values.splice(idx,1)})}
    else{CFG.colors.forEach(function(c){if(ax.values.indexOf(c.name)===-1)ax.values.push(c.name)})}
    renderWizard();
}

function wizPinnedRemoveGroup(pgi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    if(!confirm('Премахни групата "'+pg.label+'" от бързия достъп?\n\nМожеш да я добавиш отново по всяко време.'))return;
    var ax=S.wizData.axes[S._wizActiveTab];
    if(ax){pg.vals.forEach(function(v){var idx=ax.values.indexOf(v);if(idx!==-1)ax.values.splice(idx,1)})}
    S._wizPinnedGroups.splice(pgi,1);
    S._wizEditingGroup=null;
    _wizSavePinnedGroups();
    renderWizard();
}

function wizPinnedRemoveValue(pgi,vi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    var removed=pg.vals.splice(vi,1)[0];
    // Also remove from selected if present
    var ax=S.wizData.axes[S._wizActiveTab];
    if(ax&&removed){var idx=ax.values.indexOf(removed);if(idx!==-1)ax.values.splice(idx,1)}
    _wizSavePinnedGroups();
    renderWizard();
}

function wizPinnedAddValue(pgi){
    var inp=document.getElementById('editGrpVal'+pgi);
    if(!inp)return;
    var raw=inp.value.trim();
    if(!raw)return;
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    // Support comma-separated
    var vals=raw.split(',').map(function(v){return v.trim()}).filter(function(v){return v.length>0});
    // S88.BUG#4: only fuzzy match for SINGLE-value entry (skip on bulk paste)
    if (vals.length === 1 && typeof fuzzyConfirmAdd === 'function') {
        var single = vals[0];
        fuzzyConfirmAdd((pg.label||'размер').toLowerCase(), single, pg.vals||[],
            function(existing){
                var existingName = (typeof existing === 'string') ? existing : (existing.name || existing);
                if (pg.vals.indexOf(existingName) === -1) pg.vals.push(existingName);
                inp.value='';
                _wizSavePinnedGroups();
                renderWizard();
                showToast('Използван: '+existingName,'success');
            },
            function(){
                if (pg.vals.indexOf(single) === -1) pg.vals.push(single);
                inp.value='';
                _wizSavePinnedGroups();
                renderWizard();
                showToast('Добавен ✓','success');
            }
        );
        return;
    }
    vals.forEach(function(v){if(pg.vals.indexOf(v)===-1)pg.vals.push(v)});
    inp.value='';
    _wizSavePinnedGroups();
    renderWizard();
    showToast(vals.length+' добавени','success');
}

function wizPinnedResetGroup(pgi){
    var pg=S._wizPinnedGroups[pgi];if(!pg)return;
    if(!confirm('Върни оригиналните стойности на "'+pg.label+'"?'))return;
    // Find original from _SIZE_GROUPS or _getSizePresetsOrdered
    var orig=null;
    var allGrp=_getSizePresetsOrdered();
    for(var j=0;j<allGrp.length;j++){if(allGrp[j].id===pg.id){orig=allGrp[j].vals.slice();break}}
    if(!orig){
        var sg=_SIZE_GROUPS.find(function(g){return g.id===pg.id});
        if(sg)orig=sg.values.slice();
    }
    if(orig){pg.vals=orig;pg._origVals=orig.slice();_wizSavePinnedGroups();renderWizard();showToast('Възстановено','success')}
    else{showToast('Не намерих оригиналните стойности','error')}
}

function wizMovePinnedGroup(pgi,dir){
    var pinned=S._wizPinnedGroups;if(!pinned)return;
    var newIdx=pgi+dir;
    if(newIdx<0||newIdx>=pinned.length)return;
    var tmp=pinned[pgi];pinned[pgi]=pinned[newIdx];pinned[newIdx]=tmp;
    _wizSavePinnedGroups();
    renderWizard();
}

function wizShowMoreGroups(axIdx){
    var allGroups=_getSizePresetsOrdered();
    var pinned=S._wizPinnedGroups||[];
    var pinnedIds=pinned.map(function(p){return p.id});
    var available=allGroups.filter(function(g){return pinnedIds.indexOf(g.id)===-1});
    if(!available.length){showToast('Всички групи вече са добавени','');return}
    var html='<div style="max-height:60vh;overflow-y:auto;padding:8px">';
    html+='<div style="font-size:12px;font-weight:700;color:var(--indigo-300);margin-bottom:8px">Избери група:</div>';
    available.forEach(function(g){
        html+='<div style="padding:10px 12px;margin-bottom:4px;border-radius:8px;background:rgba(17,24,44,0.5);border:1px solid var(--border-subtle);cursor:pointer;display:flex;align-items:center;justify-content:space-between" onclick="wizAddPinnedGroup(\''+g.id.replace(/'/g,"\\'")+'\');closePresetPicker()">';
        html+='<div><div style="font-size:12px;font-weight:600;color:var(--text-primary)">'+esc(g.label)+'</div><div style="font-size:9px;color:var(--text-secondary)">'+g.vals.length+' стойности</div></div>';
        html+='<span style="font-size:10px;color:var(--indigo-400);font-weight:600">+ Добави</span></div>';
    });
    html+='</div>';
    var ov=document.createElement('div');ov.className='preset-ov';ov.id='presetPickerOv';
    ov.innerHTML='<div class="preset-box"><div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0 10px"><span style="font-size:15px;font-weight:700">Добави група</span><span style="font-size:22px;cursor:pointer" onclick="closePresetPicker()">\u2715</span></div>'+html+'</div>';
    ov.addEventListener('click',function(e){if(e.target===ov)closePresetPicker()});
    document.body.appendChild(ov);
}

function wizAddPinnedGroup(groupId){
    var allGroups=_getSizePresetsOrdered();
    var g=allGroups.find(function(gg){return gg.id===groupId});
    if(!g){var sg=_SIZE_GROUPS.find(function(s){return s.id===groupId});if(sg)g={id:sg.id,label:sg.label,vals:sg.values}}
    if(!g){showToast('Не е намерена','error');return}
    if(!S._wizPinnedGroups)S._wizPinnedGroups=[];
    if(S._wizPinnedGroups.find(function(p){return p.id===groupId})){showToast('Вече е добавена','');return}
    S._wizPinnedGroups.push({id:g.id,label:g.label,vals:g.vals.slice(),_origVals:g.vals.slice()});
    _wizSavePinnedGroups();
    renderWizard();
    showToast('"'+g.label+'" добавена','success');
}

function wizResetPinnedGroups(){
    if(!confirm('Върни всички групи към фабричните настройки?'))return;
    S._wizPinnedGroups=null;
    try{localStorage.removeItem('_rms_pinnedGroups_'+CFG.storeId)}catch(e){}
    renderWizard();
    showToast('Възстановено','success');
}

// Custom group creation flow
function wizCreateCustomGroup(){
    var inp=document.getElementById('newGrpName');
    if(!inp)return;
    var name=inp.value.trim();
    if(!name){showToast('Напиши име на групата','error');return}
    S._wizNewCustomGroup={name:name,values:[]};
    inp.value='';
    renderWizard();
    showToast('Групата "'+name+'" е създадена. Добави стойности.','success');
    setTimeout(function(){
        var el=document.getElementById('ncgValInput');
        if(el){el.scrollIntoView({behavior:'smooth',block:'center'});el.focus();}
    },100);
}

function wizAddCustomGroupValue(){
    var inp=document.getElementById('ncgValInput');
    if(!inp||!S._wizNewCustomGroup)return;
    var raw=inp.value.trim();
    if(!raw)return;
    var vals=raw.split(',').map(function(v){return v.trim()}).filter(function(v){return v.length>0});
    vals.forEach(function(v){if(S._wizNewCustomGroup.values.indexOf(v)===-1)S._wizNewCustomGroup.values.push(v)});
    inp.value='';
    renderWizard();
    setTimeout(function(){
        var el=document.getElementById('ncgValInput');
        if(el){el.scrollIntoView({behavior:'smooth',block:'center'});el.focus();}
    },100);
}

function wizSaveCustomGroupToAxes(){
    if(!S._wizNewCustomGroup||!S._wizNewCustomGroup.values.length){showToast('Добави поне една стойност','error');return}
    var ncg=S._wizNewCustomGroup;
    // Ако активният таб е празен (Вариация 1/2 без стойности) — ЗАМЕСТИ го
    var active=S.wizData.axes[S._wizActiveTab];
    var isEmpty=active&&(!active.values||active.values.length===0);
    var isDefault=active&&/^(размер|size|цвят|color|вариация\s*\d+)$/i.test(active.name);
    var axisHint=S._wizActiveTab; // Запомняме кой таб (0/1) е ползван при създаване
    if(isEmpty&&isDefault){
        active.name=ncg.name;
        active.values=ncg.values.slice();
    }else{
        S.wizData.axes.push({name:ncg.name,values:ncg.values.slice()});
        S._wizActiveTab=S.wizData.axes.length-1;
        axisHint=S._wizActiveTab;
    }
    if(!S._wizPinnedGroups)S._wizPinnedGroups=[];
    S._wizPinnedGroups.push({id:'custom_'+Date.now(),label:ncg.name,vals:ncg.values.slice(),_origVals:ncg.values.slice(),axisHint:axisHint});
    _wizSavePinnedGroups();
    S._wizNewCustomGroup=null;
    renderWizard();
    showToast('"'+ncg.name+'" добавена ✓','success');
}

// S70: New Step 4 helper functions

// Toggle group expand/collapse
function wizToggleGroup(axIdx,grpIdx){
    var body=document.getElementById('grpBody'+axIdx+'_'+grpIdx);
    var arr=document.getElementById('grpArr'+axIdx+'_'+grpIdx);
    if(!body)return;
    if(body.style.display==='none'||!body.style.display){
        body.style.display='flex';if(arr)arr.textContent='\u25BC';
    }else{
        body.style.display='none';if(arr)arr.textContent='\u25B6';
    }
}

// Select all values in a group
function wizSelectAllGroup(axIdx,grpIdx){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var isSize=ax.name.toLowerCase().indexOf('размер')!==-1||ax.name.toLowerCase().indexOf('size')!==-1;
    var isColor=ax.name.toLowerCase().indexOf('цвят')!==-1||ax.name.toLowerCase().indexOf('color')!==-1;
    var groups=isSize?_getSizePresetsOrdered():[{label:'Основни цветове',vals:CFG.colors.map(function(c){return c.name})}];
    var g=groups[grpIdx];if(!g)return;
    var allSelected=g.vals.every(function(v){return ax.values.indexOf(v)!==-1});
    if(allSelected){
        // Deselect all from this group
        g.vals.forEach(function(v){var idx=ax.values.indexOf(v);if(idx!==-1)ax.values.splice(idx,1)});
    }else{
        // Select all from this group
        g.vals.forEach(function(v){if(ax.values.indexOf(v)===-1)ax.values.push(v)});
    }
    renderWizard();
}

// Remove group from view (deselect all its values)
function wizRemoveGroup(axIdx,grpIdx){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var isSize=ax.name.toLowerCase().indexOf('размер')!==-1||ax.name.toLowerCase().indexOf('size')!==-1;
    var isColor=ax.name.toLowerCase().indexOf('цвят')!==-1||ax.name.toLowerCase().indexOf('color')!==-1;
    var groups=isSize?_getSizePresetsOrdered():[{label:'Основни цветове',vals:CFG.colors.map(function(c){return c.name})}];
    var g=groups[grpIdx];if(!g)return;
    g.vals.forEach(function(v){var idx=ax.values.indexOf(v);if(idx!==-1)ax.values.splice(idx,1)});
    // Hide the group wrapper
    var wrap=document.getElementById('grpWrap'+axIdx+'_'+grpIdx);
    if(wrap)wrap.style.display='none';
    renderWizard();
}

// Show all groups expanded
function wizShowAllGroups(axIdx){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    // Find all group wrappers and expand them
    var i=0;
    while(true){
        var wrap=document.getElementById('grpWrap'+axIdx+'_'+i);
        if(!wrap)break;
        wrap.style.display='';
        var body=document.getElementById('grpBody'+axIdx+'_'+i);
        var arr=document.getElementById('grpArr'+axIdx+'_'+i);
        if(body){body.style.display='flex'}
        if(arr){arr.textContent='\u25BC'}
        i++;
    }
}

// Add axis from tab "+" button
function wizAddAxisFromTab(){
    var name=prompt('Име на нова вариация (напр. Модел, Серия, Материал):');
    if(!name||!name.trim())return;
    if(!S.wizData.axes)S.wizData.axes=[];
    S.wizData.axes.push({name:name.trim(),values:[]});
    S._wizActiveTab=S.wizData.axes.length-1;
    renderWizard();
}

// Copy variations from previous product
function wizCopyPrevProduct(){
    // Get last saved product's axes from localStorage
    var prev=null;
    try{prev=JSON.parse(localStorage.getItem('_rms_lastWizAxes'))}catch(e){}
    if(prev&&prev.length){
        S.wizData.axes=JSON.parse(JSON.stringify(prev));
        showToast('Копирано от предишен продукт','success');
        renderWizard();
    }else{
        showToast('Няма предишен продукт','error');
    }
}

// Save axes to localStorage on successful save (called from wizSave)
function _wizSaveAxesToLocal(){
    try{localStorage.setItem('_rms_lastWizAxes',JSON.stringify(S.wizData.axes||[]))}catch(e){}
}

// Search presets by group name AND value
function wizSearchPresets(axIdx,q){
    var res=document.getElementById('wizSearchRes'+axIdx);
    if(!res)return;
    if(!q||q.length<1){res.innerHTML='';return}
    var ql=q.toLowerCase();
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var nm=ax.name.toLowerCase();
    var isSize=nm.indexOf('размер')!==-1||nm.indexOf('size')!==-1||nm.indexOf('ръст')!==-1;
    var isColor=nm.indexOf('цвят')!==-1||nm.indexOf('color')!==-1||nm.indexOf('десен')!==-1;

    // Search through ALL size groups (not just business-matched)
    var allGroups=isSize?_SIZE_GROUPS.map(function(g){return{label:g.label,vals:g.values,id:g.id}}):[{label:'Цветове',vals:CFG.colors.map(function(c){return c.name}),id:'_colors'}];
    
    var html='';
    allGroups.forEach(function(g,gi){
        var gnl=g.label.toLowerCase();
        var nameMatch=gnl.indexOf(ql)!==-1;
        var valMatches=g.vals.filter(function(v){return v.toLowerCase().indexOf(ql)!==-1});
        if(!nameMatch&&!valMatches.length)return;
        
        var isExpanded=nameMatch;
        var matchCount=nameMatch?g.vals.length:valMatches.length;
        
        html+='<div style="margin-bottom:4px;border:1px solid rgba(99,102,241,0.1);border-radius:8px;overflow:hidden">';
        html+='<div style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;cursor:pointer;background:rgba(15,15,40,0.4);font-size:11px;color:var(--text-secondary)" onclick="var b=this.nextElementSibling;b.style.display=b.style.display===\'none\'?\'flex\':\'none\'"><span>'+esc(g.label)+' <span style="font-size:9px;color:var(--indigo-400)">('+matchCount+')</span></span><span style="font-size:8px">'+(isExpanded?'\u25BC':'\u25B6')+'</span></div>';
        html+='<div style="'+(isExpanded?'display:flex;':'display:none;')+'flex-wrap:wrap;gap:3px;padding:6px 8px">';
        g.vals.forEach(function(v){
            var isSel=ax.values.indexOf(v)!==-1;
            var isMatch=nameMatch||valMatches.indexOf(v)!==-1;
            var opacity=isMatch?'':'opacity:0.25;';
            var sw='';
            if(isColor){var cc=CFG.colors.find(function(x){return x.name===v});if(cc)sw='<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:'+cc.hex+';margin-right:3px;border:1px solid rgba(255,255,255,0.15)"></span>'}
            html+='<span class="preset-chip'+(isSel?' sel':'')+'" style="padding:4px 9px;font-size:10px;'+opacity+'" onclick="wizTogglePresetInline('+axIdx+',\''+v.replace(/'/g,"\\'")+'\',this)">'+sw+esc(v)+'</span>';
        });
        html+='</div></div>';
    });
    
    if(!html)html='<div style="font-size:10px;color:var(--text-secondary);padding:6px;text-align:center">Няма резултати</div>';
    res.innerHTML=html;
}



// S70: Matrix helper functions

// Init matrix data store
function _wizInitMatrix(){
    if(!S.wizData._matrix)S.wizData._matrix={};
}

function wizMatrixChanged(cellId,val){
    _wizInitMatrix();
    if(val===''||val===null){delete S.wizData._matrix[cellId]}
    else{S.wizData._matrix[cellId]=parseInt(val)||0}
}

function wizMatrixFillAll(qty){
    _wizInitMatrix();
    var sizeAxis=null,colorAxis=null;
    (S.wizData.axes||[]).forEach(function(ax){
        var n=ax.name.toLowerCase();
        if(!sizeAxis&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))sizeAxis=ax;
        else if(!colorAxis&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))colorAxis=ax;
    });
    if(!sizeAxis||!colorAxis)return;
    sizeAxis.values.forEach(function(sz,si){
        colorAxis.values.forEach(function(c,ci){
            var cellId='mx_'+si+'_'+ci;
            S.wizData._matrix[cellId]=qty;
            var inp=document.getElementById(cellId);
            if(inp){inp.value=qty;inp.style.background='rgba(99,102,241,0.08)';inp.style.borderColor='rgba(99,102,241,0.15)'}
        });
    });
    showToast('Всички = '+qty,'success');
}

function wizMatrixClear(){
    if(!confirm('Изчисти всички бройки?'))return;
    S.wizData._matrix={};
    document.querySelectorAll('[id^="mx_"]').forEach(function(inp){
        inp.value='';inp.style.background='rgba(239,68,68,0.03)';inp.style.borderColor='rgba(99,102,241,0.05)';
    });
    showToast('Изчистено','success');
}

// Voice matrix fill via Gemini
function wizVoiceMatrix(){
    openVoice('Кажи размери, цветове и бройки. Напр: "S черно 2 червено 3, M черно 5"',function(text){
        wizProcessVoiceMatrix(text);
    });
}

async function wizProcessVoiceMatrix(text){
    _wizInitMatrix();
    var sizeAxis=null,colorAxis=null;
    (S.wizData.axes||[]).forEach(function(ax){
        var n=ax.name.toLowerCase();
        if(!sizeAxis&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))sizeAxis=ax;
        else if(!colorAxis&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))colorAxis=ax;
    });
    if(!sizeAxis||!colorAxis){showToast('Трябват размери и цветове','error');return}

    var prompt='Потребителят диктува бройки за продуктови вариации.\n';
    prompt+='Налични размери: '+sizeAxis.values.join(', ')+'\n';
    prompt+='Налични цветове: '+colorAxis.values.join(', ')+'\n';
    prompt+='Текст от потребителя: "'+text+'"\n\n';
    prompt+='Разпознай размерите, цветовете и бройките. "ес"=S, "ем"=M, "ел"=L, "хл"/"хикс ел"=XL, "два хл"=2XL.\n';
    prompt+='Цветовете може да са на множествено: "черни"="Черен", "бели"="Бял", "червени"="Червен".\n';
    prompt+='Върни САМО JSON масив без markdown: [{"size":"S","color":"Черен","qty":2},...]';

    showToast('AI обработва...','');
    var keys=[window._geminiKey1||'',window._geminiKey2||''];
    var apiKey=keys[0]||keys[1];
    if(!apiKey){
        // Fallback: use our backend
        var d=await api('products.php?ajax=ai_assist',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question:'MATRIX_PARSE:'+JSON.stringify({sizes:sizeAxis.values,colors:colorAxis.values,text:text})})});
        if(d&&d.message){
            try{
                var clean=d.message.replace(/```json\s*|\s*```/g,'').trim();
                var items=JSON.parse(clean);
                if(Array.isArray(items)){
                    _wizApplyMatrixItems(items,sizeAxis,colorAxis);
                    return;
                }
            }catch(e){}
        }
        showToast('Не успях да разпозная. Попълни ръчно.','error');
        return;
    }

    try{
        var url='https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key='+apiKey;
        var resp=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({contents:[{parts:[{text:prompt}]}],generationConfig:{temperature:0.1,maxOutputTokens:1024}})});
        var data=await resp.json();
        var txt=(data.candidates&&data.candidates[0]&&data.candidates[0].content&&data.candidates[0].content.parts&&data.candidates[0].content.parts[0]&&data.candidates[0].content.parts[0].text)||'';
        txt=txt.replace(/```json\s*|\s*```/g,'').trim();
        var items=JSON.parse(txt);
        if(Array.isArray(items)){
            _wizApplyMatrixItems(items,sizeAxis,colorAxis);
        }else{
            showToast('Не разпознах формата','error');
        }
    }catch(e){
        showToast('Грешка при обработка','error');
        console.error(e);
    }
}

function _wizApplyMatrixItems(items,sizeAxis,colorAxis){
    var applied=0;
    items.forEach(function(item){
        // Fuzzy match size
        var si=-1;
        sizeAxis.values.forEach(function(sv,idx){if(sv.toLowerCase()===String(item.size||'').toLowerCase())si=idx});
        if(si===-1)sizeAxis.values.forEach(function(sv,idx){if(sv.toLowerCase().indexOf(String(item.size||'').toLowerCase())===0)si=idx});
        // Fuzzy match color
        var ci=-1;
        colorAxis.values.forEach(function(cv,idx){if(cv.toLowerCase()===String(item.color||'').toLowerCase())ci=idx});
        if(ci===-1)colorAxis.values.forEach(function(cv,idx){if(cv.toLowerCase().indexOf(String(item.color||'').toLowerCase())===0)ci=idx});
        if(si!==-1&&ci!==-1){
            var cellId='mx_'+si+'_'+ci;
            var qty=parseInt(item.qty)||1;
            S.wizData._matrix[cellId]=qty;
            var inp=document.getElementById(cellId);
            if(inp){inp.value=qty;inp.style.background='rgba(34,197,94,0.1)';inp.style.borderColor='rgba(34,197,94,0.3)'}
            applied++;
        }
    });
    if(applied>0){
        showToast(applied+' варианта попълнени с глас','success');
    }else{
        showToast('Не успях да match-на. Опитай пак или попълни ръчно.','error');
    }
}



// S70: HSL Color Picker + HEX display + name suggestion
var _wizPickedHex='#FF0000';

function wizDrawHsl(){
    var canvas=document.getElementById('wizHslCanvas');
    if(!canvas)return;
    var ctx=canvas.getContext('2d');
    var w=canvas.width,h=canvas.height;
    var hue=parseInt(document.getElementById('wizHueSlider')?.value||0);
    // Draw saturation (x) x lightness (y) grid for current hue
    for(var x=0;x<w;x++){
        for(var y=0;y<h;y++){
            var s=x/w*100;
            var l=100-y/h*100;
            ctx.fillStyle='hsl('+hue+','+s+'%,'+l+'%)';
            ctx.fillRect(x,y,1,1);
        }
    }
}

function wizInitHslPicker(){
    var canvas=document.getElementById('wizHslCanvas');
    if(!canvas)return;
    wizDrawHsl();
    // Touch/mouse events
    function pickColor(e){
        e.preventDefault();
        var rect=canvas.getBoundingClientRect();
        var touch=e.touches?e.touches[0]:e;
        var x=Math.max(0,Math.min(touch.clientX-rect.left,rect.width-1));
        var y=Math.max(0,Math.min(touch.clientY-rect.top,rect.height-1));
        var ctx=canvas.getContext('2d');
        var px=ctx.getImageData(x*canvas.width/rect.width,y*canvas.height/rect.height,1,1).data;
        var hex='#'+((1<<24)+(px[0]<<16)+(px[1]<<8)+px[2]).toString(16).slice(1).toUpperCase();
        _wizPickedHex=hex;
        var prev=document.getElementById('wizColorPreview');
        if(prev)prev.style.background=hex;
        var val=document.getElementById('wizHexVal');
        if(val)val.textContent=hex;
        var sug=document.getElementById('wizColorSuggest');
        if(sug)sug.textContent=_wizSuggestColorName(px[0],px[1],px[2]);
        // Auto-fill name from suggestion always
        var nameInp=document.getElementById('wizHexName');
        if(nameInp){nameInp.value=_wizSuggestColorName(px[0],px[1],px[2])}
    }
    canvas.addEventListener('touchstart',pickColor,{passive:false});
    canvas.addEventListener('touchmove',pickColor,{passive:false});
    canvas.addEventListener('mousedown',function(e){pickColor(e);canvas._dragging=true});
    canvas.addEventListener('mousemove',function(e){if(canvas._dragging)pickColor(e)});
    canvas.addEventListener('mouseup',function(){canvas._dragging=false});
}

function _wizSuggestColorName(r,g,b){
    // Simple nearest-color matching
    var colors=[
        {n:'Бял',r:255,g:255,b:255},{n:'Черен',r:0,g:0,b:0},
        {n:'Червен',r:220,g:38,b:38},{n:'Син',r:37,g:99,b:235},
        {n:'Зелен',r:22,g:163,b:74},{n:'Жълт',r:234,g:179,b:8},
        {n:'Розов',r:236,g:72,b:153},{n:'Оранжев',r:249,g:115,b:22},
        {n:'Лилав',r:139,g:92,b:246},{n:'Сив',r:120,g:113,b:108},
        {n:'Кафяв',r:146,g:64,b:14},{n:'Бежов',r:212,g:184,b:150},
        {n:'Тъмносин',r:30,g:58,b:95},{n:'Бордо',r:127,g:29,b:29},
        {n:'Тюркоаз',r:20,g:184,b:166},{n:'Корал',r:249,g:113,b:113},
        {n:'Маслинен',r:107,g:120,b:33},{n:'Пудра',r:232,g:196,b:184},
        {n:'Графит',r:55,g:65,b:81},{n:'Сребрист',r:192,g:192,b:192},
        {n:'Златист',r:212,g:169,b:68},{n:'Екрю',r:254,g:243,b:199},
        {n:'Крем',r:255,g:253,b:208},{n:'Тъмнозелен',r:21,g:71,b:52},
        {n:'Небесно синьо',r:135,g:206,b:235},{n:'Пастелно розов',r:255,g:209,b:220}
    ];
    var best=null,bestDist=Infinity;
    colors.forEach(function(c){
        var d=Math.sqrt(Math.pow(r-c.r,2)+Math.pow(g-c.g,2)+Math.pow(b-c.b,2));
        if(d<bestDist){bestDist=d;best=c.n}
    });
    return best||'Цвят';
}


// S70: Name -> HEX reverse lookup
function wizNameToHex(name){
    if(!name||name.length<2)return;
    var nl=name.toLowerCase().trim();
    // Check known colors
    var knownColors=[
        {n:'бял',h:'#FFFFFF'},{n:'черен',h:'#1A1A1A'},{n:'червен',h:'#DC2626'},{n:'син',h:'#2563EB'},
        {n:'зелен',h:'#16A34A'},{n:'жълт',h:'#EAB308'},{n:'розов',h:'#EC4899'},{n:'оранжев',h:'#F97316'},
        {n:'лилав',h:'#8B5CF6'},{n:'сив',h:'#6B7280'},{n:'кафяв',h:'#92400E'},{n:'бежов',h:'#D4B896'},
        {n:'тъмносин',h:'#1E3A5F'},{n:'бордо',h:'#7F1D1D'},{n:'тюркоаз',h:'#14B8A6'},
        {n:'корал',h:'#FB923C'},{n:'маслинен',h:'#65A30D'},{n:'пудра',h:'#F9A8D4'},
        {n:'графит',h:'#374151'},{n:'екрю',h:'#FEF3C7'},{n:'крем',h:'#FFFDD0'},
        {n:'сребрист',h:'#C0C0C0'},{n:'златист',h:'#D4A944'},
        {n:'шампанско',h:'#F7E7CE'},{n:'пепел от рози',h:'#C9A9A6'},
        {n:'тъмнозелен',h:'#15472D'},{n:'небесно',h:'#87CEEB'},
        {n:'мента',h:'#98FB98'},{n:'лавандула',h:'#E6E6FA'},{n:'индиго',h:'#4B0082'},
        {n:'бургунди',h:'#800020'},{n:'марсала',h:'#986868'},{n:'праскова',h:'#FFDAB9'},
        {n:'слонова кост',h:'#FFFFF0'},{n:'охра',h:'#CC7722'},{n:'теракота',h:'#E2725B'},
        {n:'петрол',h:'#006D6F'},{n:'малина',h:'#E30B5C'},{n:'сьомга',h:'#FA8072'},
        {n:'карамел',h:'#FFD59A'},{n:'мока',h:'#967969'},{n:'капучино',h:'#A58D7F'},
        {n:'navy',h:'#1E40AF'},{n:'olive',h:'#808000'},{n:'teal',h:'#008080'},
        {n:'khaki',h:'#C3B091'},{n:'ivory',h:'#FFFFF0'},{n:'charcoal',h:'#36454F'}
    ];
    // Also check CFG.colors
    for(var i=0;i<CFG.colors.length;i++){
        if(CFG.colors[i].name.toLowerCase()===nl){
            _wizUpdatePickerFromHex(CFG.colors[i].hex);
            return;
        }
    }
    // Fuzzy match
    var best=null,bestLen=0;
    knownColors.forEach(function(c){
        if(nl.indexOf(c.n)!==-1||c.n.indexOf(nl)!==-1){
            if(c.n.length>bestLen){bestLen=c.n.length;best=c}
        }
    });
    if(best){
        _wizUpdatePickerFromHex(best.h);
    }
}

function _wizUpdatePickerFromHex(hex){
    _wizPickedHex=hex;
    var prev=document.getElementById('wizColorPreview');
    if(prev)prev.style.background=hex;
    var val=document.getElementById('wizHexVal');
    if(val)val.textContent=hex.toUpperCase();
    var sug=document.getElementById('wizColorSuggest');
    if(sug){
        var r=parseInt(hex.slice(1,3),16)||0;
        var g=parseInt(hex.slice(3,5),16)||0;
        var b=parseInt(hex.slice(5,7),16)||0;
        sug.textContent=_wizSuggestColorName(r,g,b);
    }
}

function wizAddHexColor(){
    var hex=_wizPickedHex||'#000000';
    var nameInp=document.getElementById('wizHexName');
    var name=nameInp?.value.trim();
    if(!name){showToast('Дай име на цвета','error');return}
    var ax=S.wizData.axes[S._wizActiveTab];
    if(!ax){showToast('Няма активна вариация','error');return}
    // S88.BUG#4: fuzzy match against existing colors (CFG + already in axis)
    var candidates = (CFG.colors||[]).map(function(c){return c.name||c}).concat(ax.values||[]);
    fuzzyConfirmAdd('цвят', name, candidates,
        function(existing){
            var existingName = (typeof existing === 'string') ? existing : (existing.name || existing);
            if (ax.values.indexOf(existingName) === -1){
                ax.values.push(existingName);
                showToast('Използван: '+existingName,'success');
            } else {
                showToast(existingName+' вече е избран','');
            }
            if(nameInp)nameInp.value='';
            renderWizard();
        },
        function(){
            if(ax.values.indexOf(name)===-1){
                ax.values.push(name);
                if(!CFG.colors.find(function(c){return c.name===name})){
                    CFG.colors.push({name:name,hex:hex,_custom:true});
                }
                _wizSaveCustomColors();
                showToast(name+' добавен','success');
                if(nameInp)nameInp.value='';
                renderWizard();
            }else{
                showToast('Вече е добавен','error');
            }
        }
    );
}

function _wizSaveCustomColors(){
    try{
        var custom=CFG.colors.filter(function(c){return c._custom});
        localStorage.setItem('_rms_customColors_'+CFG.storeId,JSON.stringify(custom));
    }catch(e){}
}

function _wizLoadCustomColors(){
    try{
        var saved=JSON.parse(localStorage.getItem('_rms_customColors_'+CFG.storeId));
        if(saved&&saved.length){
            saved.forEach(function(c){
                if(!CFG.colors.find(function(x){return x.name===c.name})){
                    c._custom=true;
                    CFG.colors.push(c);
                }
            });
        }
    }catch(e){}
}
_wizLoadCustomColors();

function wizTogglePresetInline(axIdx,val,chip){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var idx=ax.values.indexOf(val);
    if(idx>=0){ax.values.splice(idx,1);chip.classList.remove('selected')}
    else{ax.values.push(val);chip.classList.add('selected')}
    if(navigator.vibrate)navigator.vibrate(5);
    _v4UpdateAfterToggle(axIdx);
}
function _v4ComputeFooter(axIdx){
    var ax=S.wizData.axes[axIdx]||{name:'',values:[]};
    var hasVals=ax.values&&ax.values.length>0;
    // S82.STUDIO.2: any-axis-has-values check — fixes the "no Save button on
    // empty Вариация 2 tab" bug where users with colours-only products got stuck.
    var anyAxisHasVals=(S.wizData.axes||[]).some(function(a){return a.values&&a.values.length>0});
    // Find first OTHER empty axis to surface as a suggestion (still not required).
    var nextEmptyIdx=-1;
    for(var i=0;i<S.wizData.axes.length;i++){
        if(i===axIdx)continue;
        var a=S.wizData.axes[i];
        if(!a.values||a.values.length===0){nextEmptyIdx=i;break}
    }
    var nextAx=nextEmptyIdx>=0?S.wizData.axes[nextEmptyIdx]:null;
    var ftBack='<button type="button" onclick="wizGo(3)" style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit" title="Назад"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>';
    var ftMid;
    if(!hasVals && !anyAxisHasVals){
        // Nothing entered anywhere — keep the original "pick a value" hint.
        var _axLbl=/^(размер|size|цвят|color|вариация\s*\d+)$/i.test(ax.name)?('Вариация '+(axIdx+1)):ax.name;
        ftMid='<div style="flex:1;display:flex;align-items:center;justify-content:center;height:44px;font-size:11px;color:rgba(255,255,255,0.4);padding:0 10px;text-align:center;font-style:italic">Избери '+esc(_axLbl.toLowerCase())+' за да продължиш</div>';
    }else{
        // S82.STUDIO.6: at least one axis has values. Show actions REGARDLESS of which tab
        // the user is on — Колко бр.? and Към запис must always be reachable, otherwise
        // colours-only flow gets stuck on the empty Вариация 2 tab with no buttons.
        var bMatrix='<button type="button" class="mod-prod-mx-cta" onclick="openMxOverlay()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Колко бр.?</button>';
        var bNext='';
        if(nextEmptyIdx>=0 && hasVals){
            var _nextLbl=/^(размер|size|цвят|color|вариация\s*\d+)$/i.test(nextAx.name)?('Вариация '+(nextEmptyIdx+1)):nextAx.name;
            bNext='<button type="button" onclick="S._wizActiveTab='+nextEmptyIdx+';renderWizard()" style="flex:1;height:44px;border-radius:12px;background:linear-gradient(135deg,hsl(255 70% 52%),hsl(222 70% 42%));border:1px solid hsl(255 70% 55%);color:#fff;font-size:11px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px hsl(255 70% 40% / 0.4),inset 0 1px 0 rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;gap:4px;font-family:inherit">'+esc(_nextLbl)+'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>';
        }
        var bSave='<button type="button" onclick="wizScrollToAIPrompt()" style="flex:1;height:44px;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);border:1px solid #22c55e;color:#fff;font-size:11px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(22,163,74,0.4),inset 0 1px 0 rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;gap:4px;font-family:inherit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Към запис</button>';
        ftMid=bMatrix+bNext+bSave;
    }
    return ftBack+ftMid;
}

// S82.STUDIO.7: tapped a disabled YES/NO button — guide user to enter qtys first.
function wizStep4NeedQty() {
    if (typeof showToast === 'function') showToast('Първо въведи бройки за всеки вариант. Отварям матрицата...', 'error');
    if (typeof openMxOverlay === 'function') setTimeout(openMxOverlay, 350);
}

// S82.STUDIO.6: scroll/flash the AI prompt card. If it doesn't exist (qty=0 yet)
// — explain it's because no quantities are entered AND auto-open the matrix overlay.
function wizScrollToAIPrompt() {
    var card = document.getElementById('wizStep4AICard');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('flash-attention');
        setTimeout(function(){ card.classList.remove('flash-attention'); }, 1600);
        return;
    }
    // No card — qty is still empty. Tell the user clearly what to do.
    var anyVals = (S.wizData.axes||[]).some(function(a){return a.values&&a.values.length>0;});
    if (!anyVals) {
        if (typeof showToast === 'function') showToast('Първо избери поне един цвят или размер.', 'error');
        return;
    }
    if (typeof showToast === 'function') showToast('Първо въведи бройки за всеки вариант. Отварям матрицата...', '');
    if (typeof openMxOverlay === 'function') setTimeout(openMxOverlay, 350);
}
function _v4UpdateAfterToggle(axIdx){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var nm=ax.name.toLowerCase();
    var isColor=nm.indexOf('цвят')!==-1||nm.indexOf('color')!==-1;
    // Refresh footer
    var ft=document.getElementById('v4Footer');
    if(ft)ft.innerHTML=_v4ComputeFooter(axIdx);
    // Tab count
    var tabs=document.querySelectorAll('.v-axis-tab');
    if(tabs[axIdx]){var cn=tabs[axIdx].querySelector('.v-axis-tab-count');if(cn)cn.textContent=ax.values.length||0}
    // Selected bar
    var sb=document.querySelector('.v-sel-bar');
    if(sb){
        var h='';
        if(ax.values.length){
            ax.values.forEach(function(v,vi){
                var dot='';
                if(isColor){var cc=CFG.colors.find(function(x){return x.name===v});if(cc)dot='<span class="v-dot" style="background:'+cc.hex+'"></span>';}
                h+='<div class="v-sel-chip" onclick="S.wizData.axes['+axIdx+'].values.splice('+vi+',1);renderWizard()">'+dot+'<span>'+esc(v)+'</span><span class="v-sel-chip-x">\u2715</span></div>';
            });
            h+='<button class="v-clear-btn" onclick="event.stopPropagation();S.wizData.axes['+axIdx+'].values=[];renderWizard()">Изчисти</button>';
        }else{
            h='<div class="v-sel-empty">Избери от групите, търси, въведи ръчно или с глас</div>';
        }
        sb.innerHTML=h;
    }
    // Preset group counts (only for size axis)
    if(!isColor){
        document.querySelectorAll('.v-pgroup').forEach(function(g,pgi){
            var pg=S._wizPinnedGroups&&S._wizPinnedGroups[pgi];if(!pg)return;
            var sel=pg.vals.filter(function(v){return ax.values.indexOf(v)>=0}).length;
            var cn=g.querySelector('.v-pgroup-count');
            if(cn){cn.textContent=sel+'/'+pg.vals.length;if(sel>0)cn.classList.add('has');else cn.classList.remove('has');}
        });
    }
    // Matrix CTA — need full re-render ако combos threshold се пресече
    _v4RefreshMatrixCta();
}
function _v4RefreshMatrixCta(){
    var wrap=document.querySelector('.v-matrix-cta-wrap, .v-matrix-summary');
    if(!wrap)return;
    // Trigger light re-render of matrix CTA section
    var combos=wizCountCombinations();
    var szAx=null,clAx=null;(S.wizData.axes||[]).forEach(function(a){var n=a.name.toLowerCase();if(!szAx&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))szAx=a;else if(!clAx&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))clAx=a});
    var szN=szAx?szAx.values.length:0,clN=clAx?clAx.values.length:0;
    var pillEl=wrap.querySelector('.v-matrix-cta-pill');if(pillEl)pillEl.textContent=combos;
    var subEl=wrap.querySelector('.v-mc-sub');if(subEl)subEl.innerHTML='<b>'+szN+' размера</b> \u00d7 <b>'+clN+' цвята</b> = <b>'+combos+' комбинации</b>';
}
function _wizUpdateSummaryBar(axIdx){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var nm=ax.name.toLowerCase();
    var isColor=nm.indexOf('цвят')!==-1||nm.indexOf('color')!==-1||nm.indexOf('десен')!==-1;
    var bar=document.getElementById('wizSumBar');
    if(!bar)return;
    if(!ax.values.length){
        bar.innerHTML='<div style="padding:8px 10px;border-radius:8px;background:rgba(99,102,241,0.03);border:1px dashed rgba(99,102,241,0.12);color:var(--text-secondary);font-size:11px">Избери от групите, търси, въведи ръчно или с глас</div>';
        return;
    }
    var h='';
    ax.values.forEach(function(v,vi){
        var sw='';
        if(isColor){var cc=CFG.colors.find(function(x){return x.name===v});if(cc)sw='<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'+cc.hex+';margin-right:3px;border:1px solid rgba(255,255,255,0.2)"></span>'}
        h+='<span style="display:inline-flex;align-items:center;padding:4px 8px;border-radius:8px;background:rgba(99,102,241,0.15);color:#a5b4fc;font-size:11px;font-weight:500;cursor:pointer" onclick="S.wizData.axes['+axIdx+'].values.splice('+vi+',1);renderWizard()">'+sw+esc(v)+' <span style="margin-left:3px;opacity:0.5;font-size:9px">\u2715</span></span>';
    });
    h+='<span style="margin-left:auto;padding:4px 8px;border-radius:8px;background:rgba(239,68,68,0.1);color:#fca5a5;font-size:10px;font-weight:600;cursor:pointer" onclick="S.wizData.axes['+axIdx+'].values=[];renderWizard()">Изчисти</span>';
    bar.innerHTML=h;
}
function _wizUpdateTabCount(axIdx){
    var ax=S.wizData.axes[axIdx];if(!ax)return;
    var tabEl=document.querySelector('[data-tabc="'+axIdx+'"]');
    if(tabEl)tabEl.textContent=ax.values.length||'';
}


function openPresetPicker(axIdx,isSize){
    const ax=S.wizData.axes[axIdx];if(!ax)return;
    const existing=new Set(ax.values);
    let presets=[];
    if(isSize){
        presets=_getSizePresetsOrdered();
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

function wizAxisSuggest(axIdx,q){
    const ax=S.wizData.axes[axIdx];if(!ax)return;
    const list=document.getElementById('axSug'+axIdx);if(!list)return;
    const lq=q.toLowerCase().trim();
    if(!lq){list.style.display='none';return}
    const existing=new Set(ax.values.map(function(v){return v.toLowerCase()}));
    const nm=ax.name.toLowerCase();
    const isSize=nm.includes('размер')||nm.includes('size')||nm.includes('ръст')||nm.includes('бюст')||nm.includes('тяло');
    const isColor=nm.includes('цвят')||nm.includes('color')||nm.includes('десен');
    var allPresets=[];
    var myPresets=[];
    if(window._bizVariants&&window._bizVariants.variant_presets){
        for(var k in window._bizVariants.variant_presets){
            if(k===ax.name||k.toLowerCase()===nm){myPresets=window._bizVariants.variant_presets[k];break}
            if(nm.includes(k.toLowerCase())||k.toLowerCase().includes(nm)){myPresets=window._bizVariants.variant_presets[k];break}
        }
    }
    var globalPresets=[];
    if(window._allBizPresets){
        if(isSize)globalPresets=window._allBizPresets.sizes||[];
        else if(isColor)globalPresets=window._allBizPresets.colors||[];
        else globalPresets=window._allBizPresets.other||[];
    }
    myPresets.forEach(function(v){if(allPresets.indexOf(v)===-1)allPresets.push(v)});
    globalPresets.forEach(function(v){if(allPresets.indexOf(v)===-1)allPresets.push(v)});
    if(!allPresets.length&&isColor&&CFG.colors){allPresets=CFG.colors.map(function(cc){return cc.name})}
    var filtered=allPresets.filter(function(v){return v.toLowerCase().indexOf(lq)!==-1&&!existing.has(v.toLowerCase())});
    filtered.sort(function(a,b){var aM=myPresets.indexOf(a)!==-1?0:1;var bM=myPresets.indexOf(b)!==-1?0:1;return aM-bM});
    if(!filtered.length){list.style.display='none';return}
    list.innerHTML=filtered.slice(0,12).map(function(v){
        var isMyPreset=myPresets.indexOf(v)!==-1;
        var sw='';
        if(isColor){var cc=CFG.colors.find(function(x){return x.name===v});if(cc)sw='<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:'+cc.hex+';margin-right:6px;border:1px solid rgba(255,255,255,0.2)"></span>'}
        return '<div class="wiz-dd-item" onmousedown="event.preventDefault()" onclick="wizPickAxisVal('+axIdx+',\''+v.replace(/'/g,"\\'")+'\')">'+sw+(isMyPreset?'<b>'+v+'</b>':v)+'</div>';
    }).join('');
    list.style.display='block';
}
function wizPickAxisVal(axIdx,val){
    const ax=S.wizData.axes[axIdx];if(!ax)return;
    if(!ax.values.includes(val))ax.values.push(val);
    const inp=document.getElementById('axVal'+axIdx);if(inp)inp.value='';
    const list=document.getElementById('axSug'+axIdx);if(list)list.style.display='none';
    renderWizard();
}
function wizAddAxisValue(axIdx){
    const inp=document.getElementById('axVal'+axIdx);
    const val=inp?.value.trim();
    if(!val)return;
    // S88.BUG#4: fuzzy match against already-added values on this axis
    const ax=S.wizData.axes[axIdx]; if(!ax) return;
    fuzzyConfirmAdd((ax.name||'размер').toLowerCase(), val, ax.values||[],
        function(existing){
            const existingName = (typeof existing === 'string') ? existing : (existing.name || existing);
            if (ax.values.indexOf(existingName) === -1) ax.values.push(existingName);
            if(inp) inp.value='';
            renderWizard();
        },
        function(){
            ax.values.push(val);
            if(inp) inp.value='';
            renderWizard();
        }
    );
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

function wizSearchDropdown(inputId,listId,items,onSelect){
    const inp=document.getElementById(inputId);
    const list=document.getElementById(listId);
    if(!inp||!list)return;
    const q=inp.value.toLowerCase().trim();
    const filtered=q?items.filter(i=>i.name.toLowerCase().includes(q)):items;
    if(!q&&!inp._focused){list.style.display='none';return}
    list.innerHTML=filtered.slice(0,15).map(i=>{
        const eName=i.name.replace(/'/g,"\\'");
        return '<div class="wiz-dd-item" onmousedown="event.preventDefault()" onclick="wizPickDD(\''+inputId+'\',\''+listId+'\','+i.id+',\''+eName+'\')">'+i.name+'</div>';
    }).join('')||(q?'<div style="padding:10px;font-size:11px;color:var(--text-secondary)">Няма резултат</div>':'');
    list.style.display=(filtered.length||q)?'block':'none';
}
function wizPickDD(inputId,listId,id,name){
    const inp=document.getElementById(inputId);
    const list=document.getElementById(listId);
    if(inp){inp.value=name;inp._selectedId=id}
    if(list)list.style.display='none';
    if(inputId==='wCatDD'){S.wizData.category_id=id;wizLoadSubcats(id)}
    if(inputId==='wSupDD'){
        var prevSup=S.wizData.supplier_id;
        S.wizData.supplier_id=id;
        // S92.PRODUCTS.D3_FLICKER: смяна на доставчик → targeted DOM update вместо renderWizard()
        // (full re-render причиняваше видим flash). Pre-fetch + clear invalid category + refresh
        // hint label-а + (ако е отворен) категория dropdown list-а.
        if(prevSup!==id){
            wizPrefetchSupplierCats(id).then(function(cats){
                if(S.wizData.category_id){
                    var stillValid=cats.some(function(c){return c.id==S.wizData.category_id});
                    if(!stillValid){
                        S.wizData.category_id=null;S.wizData.subcategory_id=null;
                        var ci=document.getElementById('wCatDD');if(ci){ci.value='';ci._selectedId=null}
                        var su=document.getElementById('wSubcat');if(su)su.innerHTML='<option value="">— Избери първо категория —</option>';
                    }
                }
                wizSyncSupplierHint();
                var catList=document.getElementById('wCatDDList');
                if(catList&&catList.style.display==='block'){
                    wizSearchDropdown('wCatDD','wCatDDList',wizCatsForSupplier());
                }
            });
        }
    }
}
// S92.PRODUCTS.D3_FLICKER: добавя/премахва "(само от избрания доставчик)" hint-а
// до label-а на Категория, без да пипа останалата стъпка.
function wizSyncSupplierHint(){
    var ci=document.getElementById('wCatDD');if(!ci)return;
    var fg=ci.closest('.fg');if(!fg)return;
    var label=fg.querySelector('label.fl');if(!label)return;
    var hint=label.querySelector('.hint');
    if(S.wizData&&S.wizData.supplier_id){
        if(!hint){
            var span=document.createElement('span');
            span.className='hint';
            span.textContent='(само от избрания доставчик)';
            label.appendChild(document.createTextNode(' '));
            label.appendChild(span);
        }
    } else if(hint){
        hint.remove();
    }
}

// S90.PRODUCTS.SPRINT_B D3: списък с категории, филтриран по текущ доставчик.
// Връща синхронно от cache (или CFG.categories ако supplier-ът няма cache yet).
function wizCatsForSupplier(){
    var sup=S.wizData&&S.wizData.supplier_id;
    if(!sup){
        return (CFG.categories||[]).filter(function(c){return !c.parent_id});
    }
    if(S._wizSupCatCache&&S._wizSupCatCache.sup==sup){
        return S._wizSupCatCache.cats||[];
    }
    // Cache miss → fire-and-forget prefetch + return full list този път.
    wizPrefetchSupplierCats(sup);
    return (CFG.categories||[]).filter(function(c){return !c.parent_id});
}

async function wizPrefetchSupplierCats(sup){
    if(!sup){S._wizSupCatCache=null;return [];}
    if(S._wizSupCatCache&&S._wizSupCatCache.sup==sup)return S._wizSupCatCache.cats;
    var d=await api('products.php?ajax=categories&store_id='+CFG.storeId+'&sup='+sup);
    var filtered=(d||[]).filter(function(c){return !c.parent_id});
    S._wizSupCatCache={sup:sup,cats:filtered};
    return filtered;
}
async function wizLoadSubcats(catId){
    const sel=document.getElementById('wSubcat');if(!sel)return;
    sel.innerHTML='<option value="">— Зарежда... —</option>';
    const subs=await api('products.php?ajax=subcategories&parent_id='+catId);
    sel.innerHTML='<option value="">— Няма —</option>';
    if(subs&&subs.length){subs.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)});}
    if(S.wizData.subcategory_id)sel.value=S.wizData.subcategory_id;
}
function wizFilterSelect(selId,q){}

function wizCollectData(){
    const el=id=>document.getElementById(id);
    if(el('wName')){const v=el('wName').value.trim();if(v)S.wizData.name=v}
    if(el('wCode'))S.wizData.code=el('wCode').value.trim();
    if(el('wPrice')){const v=parseFloat(el('wPrice').value);if(v)S.wizData.retail_price=v}
    if(el('wWprice'))S.wizData.wholesale_price=parseFloat(el('wWprice').value)||0;
    if(el('wCostPrice'))S.wizData.cost_price=parseFloat(el('wCostPrice').value)||0;
    if(el('wBarcode'))S.wizData.barcode=el('wBarcode').value.trim();
    if(el('wSupDD'))S.wizData.supplier_id=el('wSupDD')._selectedId||S.wizData.supplier_id||null;
    if(el('wCatDD'))S.wizData.category_id=el('wCatDD')._selectedId||S.wizData.category_id||null;
    if(el('wSubcat'))S.wizData.subcategory_id=el('wSubcat').value||null;
    if(el('wUnit'))S.wizData.unit=el('wUnit').value||'бр';
    if(el('wMinQty'))S.wizData.min_quantity=parseInt(el('wMinQty').value)||0;
    if(el('wDesc'))S.wizData.description=el('wDesc').value;
    if(el('wOrigin'))S.wizData.origin_country=el('wOrigin').value;
    if(el('wComposition'))S.wizData.composition=el('wComposition').value;
    if(S.wizStep===6&&S.wizData._printCombos){
        S.wizData._printCombos.forEach(function(c,i){
            var inp=document.getElementById('lblQty'+i);
            if(inp)c.printQty=parseInt(inp.value)||0;
        });
    }
}

function wizQtyAdj(idx,delta){
    var row=document.getElementById('comboRow'+idx);if(!row)return;
    var qtyInp=row.querySelector('[data-field="qty"]');
    var minInp=row.querySelector('[data-field="min"]');
    if(!qtyInp)return;
    var oldVal=parseInt(qtyInp.value)||0;
    var nv=Math.max(0,oldVal+delta);
    qtyInp.value=nv;
    if(minInp&&parseInt(minInp.value||0)===oldVal){minInp.value=nv}
}
async function wizGoPreview(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');wizGo(3);return}
    if(!S.wizData.retail_price){showToast('Въведи цена','error');wizGo(3);return}
    if(!S.wizData.code&&S.wizData.name){
        const d=await api('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:S.wizData.name})});
        if(d?.code)S.wizData.code=d.code;
    }
    // S88B-1: Step 2 is now Photo, not AI Studio. AI Studio opens via openStudioModal modal post-save.
    wizGo(5);
}

async function wizGenDescription(){
    const name=S.wizData.name||'';
    if(!name)return;
    var descEl=document.getElementById('wDesc');
    if(descEl)descEl.placeholder='AI генерира описание...';
    var cats=CFG.categories.find(function(c){return c.id==S.wizData.category_id});
    var sups=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
    var cat=cats?cats.name:'';
    var sup=sups?sups.name:'';
    var axes='';
    if(S.wizData.axes){S.wizData.axes.forEach(function(a){if(a.values.length)axes+=a.name+': '+a.values.join(', ')+'. '})}
    var d=await api('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes,composition:S.wizData.composition||''})});
    if(d&&d.description){
        if(descEl){descEl.value=d.description;descEl.removeAttribute('readonly')}
        S.wizData.description=d.description;
    }else{
        if(descEl)descEl.placeholder='Описанието не можа да се генерира';
    }
}

async function wizSave(){
    // S75.2: wizType first check
    if(!S.wizType){showToast('Избери първо: Единичен или С варианти','error');var tg=document.querySelector('.v4-type-toggle');if(tg){tg.classList.add('pulsing-strong');setTimeout(function(){tg.classList.remove('pulsing-strong')},1600);}if(navigator.vibrate)navigator.vibrate([50,30,50]);return;}
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');return}
    let combos=wizBuildCombinations();
    document.querySelectorAll('[data-combo][data-field="qty"]').forEach(inp=>{
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

    // S70: Read matrix quantities into combos. _matrix[cellId] is an OBJECT {qty, min}.
    // S82.UI.FIX1: was reading the object as a number → parseInt(obj) === NaN → all qty=0 on save.
        if(S.wizData._matrix&&Object.keys(S.wizData._matrix).length){
            var _sAxis=null,_cAxis=null;
            (S.wizData.axes||[]).forEach(function(ax){var n=ax.name.toLowerCase();if(!_sAxis&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))_sAxis=ax;else if(!_cAxis&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))_cAxis=ax});
            // Single-axis (color-only or size-only) matrix support
            var _onlyAxis = !_sAxis && _cAxis ? _cAxis : (!_cAxis && _sAxis ? _sAxis : null);
            if(_sAxis&&_cAxis){
                combos=[];
                _sAxis.values.forEach(function(sz,si){_cAxis.values.forEach(function(cl,ci){
                    var cellId='mx_'+si+'_'+ci;
                    var cell=S.wizData._matrix[cellId];
                    var rawQ = (cell && typeof cell === 'object') ? cell.qty : cell;
                    var q = parseInt(rawQ) || 0;
                    if (q > 0) {
                        combos.push({parts:[{axis:'Размер',value:sz},{axis:'Цвят',value:cl}],qty:q,axisValues:sz+' / '+cl});
                    }
                })});
            } else if (_onlyAxis) {
                combos=[];
                var _axName = (_onlyAxis === _cAxis) ? 'Цвят' : 'Размер';
                _onlyAxis.values.forEach(function(v,vi){
                    // Try both row and column key conventions just in case
                    var candidates = ['mx_0_'+vi, 'mx_'+vi+'_0'];
                    var q = 0;
                    for (var k=0; k<candidates.length; k++) {
                        var cell = S.wizData._matrix[candidates[k]];
                        var rawQ = (cell && typeof cell === 'object') ? cell.qty : cell;
                        var n = parseInt(rawQ) || 0;
                        if (n > 0) { q = n; break; }
                    }
                    if (q > 0) {
                        combos.push({parts:[{axis:_axName,value:v}],qty:q,axisValues:v});
                    }
                });
            }
        }else{
            document.querySelectorAll('[data-combo][data-field="qty"]').forEach(function(inp){var ci=parseInt(inp.dataset.combo);if(combos[ci])combos[ci].qty=parseInt(inp.value)||0});
        }

        const variants=combos.map(c=>{
        const sizeVal=c.parts?.find(p=>p.axis.toLowerCase().includes('размер')||p.axis.toLowerCase().includes('size'))?.value||null;
        const colorVal=c.parts?.find(p=>p.axis.toLowerCase().includes('цвят')||p.axis.toLowerCase().includes('color'))?.value||null;
        const extras=c.parts?.filter(p=>{const n=p.axis.toLowerCase();return !n.includes('размер')&&!n.includes('size')&&!n.includes('цвят')&&!n.includes('color')}).map(p=>p.value)||[];
        const finalSize=[sizeVal,...extras].filter(Boolean).join(' / ')||null;
        return{size:finalSize,color:colorVal,qty:c.qty||0};
    });

    // S82.STUDIO.5: log full state for debug — user reports the 0-qty popup fires
    // even when matrix has values.
    console.log('[S82.STUDIO.5] wizSave — _matrix:', S.wizData._matrix, 'axes:', JSON.stringify((S.wizData.axes||[]).map(function(a){return {name:a.name, vals:a.values};})), 'combos:', JSON.parse(JSON.stringify(combos)));
    var _totalQty = S.wizType==='variant'
        ? variants.reduce(function(s,v){return s+(parseInt(v.qty)||0)},0)
        : (parseInt(singleQty)||0);
    console.log('[S82.STUDIO.5] _totalQty=' + _totalQty + ', variants:', variants);
    // S82.STUDIO.5: only show the popup when matrix is genuinely empty — if _matrix
    // has at least one cell with qty>0, trust it (the variants array may have failed to
    // pick it up for some other reason and we don't want to scare the user).
    var _matrixHasQty = false;
    if (S.wizData._matrix) {
        for (var _mk5 in S.wizData._matrix) {
            var _mc5 = S.wizData._matrix[_mk5];
            var _mq5 = (_mc5 && typeof _mc5 === 'object') ? _mc5.qty : _mc5;
            if (parseInt(_mq5) > 0) { _matrixHasQty = true; break; }
        }
    }
    if (!S.wizEditId && _totalQty === 0 && !_matrixHasQty) {
        if (!confirm('Няма въведени бройки. Сигурен ли си, че искаш да запишеш артикула с 0 количество?')) return;
    }

    const payload={
        name:S.wizData.name,barcode:S.wizData.barcode,
        retail_price:S.wizData.retail_price,wholesale_price:S.wizData.wholesale_price,
        cost_price:parseFloat(S.wizData.cost_price)||0,
        supplier_id:S.wizData.supplier_id||null,
        category_id:S.wizData.category_id||null,
        // S88B-1 / Q2: subcategory IS a category (categories.parent_id). Server picks subcategory_id over category_id when present.
        subcategory_id:S.wizData.subcategory_id||null,
        code:S.wizData.code,unit:S.wizData.unit,min_quantity:S.wizData.min_quantity,
        description:S.wizData.description,
        origin_country:S.wizData.origin_country||null,
        composition:S.wizData.composition||null,
        is_domestic:S.wizData.is_domestic?1:0,
        // S88B-1: single-mode color/size go to products.color/size columns (NOT axes).
        color:S.wizType==='variant'?null:(S.wizData.color||null),
        size:S.wizType==='variant'?null:(S.wizData.size||null),
        product_type:S.wizType==='variant'?'variant':'simple',
        sizes,colors,variants:S.wizType==='variant'?variants:[{size:null,color:null,qty:singleQty}],
        initial_qty:singleQty,
        id:S.wizEditId||undefined,action:S.wizEditId?'edit':'create'
    };

    showToast('Запазвам...','');
    try{
        let r=await api('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        // S88.BUG#6: duplicate guard. r.duplicate === true → user picks via modal.
        if (r && r.duplicate === true && Array.isArray(r.matches) && r.matches.length){
            const choice = await showDuplicatesModalS88(r.matches, r.fields||[]);
            if (choice === 'cancel') { showToast('Отказан','error'); return; }
            if (choice === 'open' && r.matches[0]?.id){
                closeWizard();
                if (typeof openProductDetail === 'function') openProductDetail(r.matches[0].id);
                return;
            }
            // 'save' → resubmit with confirm_duplicate
            payload.confirm_duplicate = 1;
            r = await api('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        }
        if(r&&(r.success||r.id)){
            showToast('Артикулът е добавен!','success');
            S.wizSavedId=r.id;S.wizEditId=r.id;_wizSaveAxesToLocal();
            // S88B-1 / Task E: persist field snapshot for "Копирай от последния" + per-field ↻.
            // Stores both *_id (for save flow) and *_name (for dropdown label rehydration on next wizard open).
            try {
                var supName='', catName='', subName='';
                if (S.wizData.supplier_id){var _s=(CFG.suppliers||[]).find(function(x){return x.id==S.wizData.supplier_id});if(_s)supName=_s.name;}
                if (S.wizData.category_id){var _c=(CFG.categories||[]).find(function(x){return x.id==S.wizData.category_id});if(_c)catName=_c.name;}
                if (S.wizData.subcategory_id){var _sub=document.getElementById('wSubcat');if(_sub){for(var i=0;i<_sub.options.length;i++){if(_sub.options[i].value==S.wizData.subcategory_id){subName=_sub.options[i].textContent;break}}}}
                var snap={
                    retail_price: S.wizData.retail_price||null,
                    cost_price: S.wizData.cost_price||null,
                    // S92.PRODUCTS.PRICE_LAYOUT: snapshot стойност = MARGIN (печалба върху retail),
                    // консистентна с UI label-а ПЕЧАЛБА %. Field key остава markup_pct legacy.
                    markup_pct: (parseFloat(S.wizData.cost_price)>0&&parseFloat(S.wizData.retail_price)>0) ? Math.round(((S.wizData.retail_price-S.wizData.cost_price)/S.wizData.retail_price)*100) : null,
                    min_quantity: S.wizData.min_quantity||null,
                    supplier_id: S.wizData.supplier_id||null,
                    supplier_name: supName,
                    category_id: S.wizData.category_id||null,
                    category_name: catName,
                    subcategory_id: S.wizData.subcategory_id||null,
                    subcategory_name: subName,
                    color: S.wizType==='single' ? (S.wizData.color||null) : null,
                    size: S.wizType==='single' ? (S.wizData.size||null) : null,
                    composition: S.wizData.composition||null,
                    origin_country: S.wizData.origin_country||null
                };
                localStorage.setItem('_rms_lastWizProductFields', JSON.stringify(snap));
            } catch(e) { /* localStorage quota or denied — non-fatal */ }
            // S82.STUDIO.10: clear the auto-saved draft now that the artikel is in DB.
            if (typeof _wizClearDraft === 'function') _wizClearDraft();
            if(S.wizData._photoDataUrl&&S.wizData._photoDataUrl.startsWith('data:')){
                api('products.php?ajax=upload_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({product_id:r.id,image:S.wizData._photoDataUrl})}).then(function(img){if(img&&img.ok)console.log('Photo saved')}).catch(function(){});
            }
            // S88B-1 / Task G + Task H: ensure variant-mode parent gets a main image when only multi-photos were uploaded.
            // Picks photo with is_main=true (set via Q7 Make-Main button) or falls back to the first photo.
            if (S.wizType==='variant' && !S.wizData._photoDataUrl && Array.isArray(S.wizData._photos) && S.wizData._photos.length) {
                var _mainPhoto = S.wizData._photos.find(function(p){return p && p.is_main && p.dataUrl})
                              || S.wizData._photos.find(function(p){return p && p.dataUrl});
                if (_mainPhoto && _mainPhoto.dataUrl && _mainPhoto.dataUrl.startsWith('data:')) {
                    api('products.php?ajax=upload_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({product_id:r.id,image:_mainPhoto.dataUrl})}).then(function(img){if(img&&img.ok)console.log('Parent photo (is_main) saved')}).catch(function(){});
                }
            }
            // S88.BUG1: per-variation images from multi-photo wizard
            if (r.variant_ids_by_color && Array.isArray(S.wizData._photos)) {
                S.wizData._photos.forEach(function(p) {
                    if (!p.ai_color || !p.dataUrl || !p.dataUrl.startsWith('data:')) return;
                    var key = p.ai_color.toLowerCase().trim();
                    var cids = r.variant_ids_by_color[key];
                    if (!cids || !cids.length) return;
                    cids.forEach(function(cid) {
                        api('products.php?ajax=upload_image', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({product_id: cid, image: p.dataUrl})
                        }).catch(function(){});
                    });
                });
            }
            // Build _printCombos for Step 6 (always — AI Studio also exposes a print export later).
            var _pc=[];
            if(S.wizData._matrix&&Object.keys(S.wizData._matrix).length){
                var _sAx=null,_cAx=null;
                (S.wizData.axes||[]).forEach(function(ax){var n=ax.name.toLowerCase();if(!_sAx&&(n.indexOf('размер')!==-1||n.indexOf('size')!==-1))_sAx=ax;else if(!_cAx&&(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1))_cAx=ax});
                if(_sAx&&_cAx){
                    _sAx.values.forEach(function(sz,si){_cAx.values.forEach(function(cl,ci){
                        var cellId='mx_'+si+'_'+ci;
                        var _cell=S.wizData._matrix[cellId];
                        var qty=(_cell&&typeof _cell==='object')?_cell.qty:_cell;
                        if(qty!==undefined&&qty!==null&&qty!==''&&parseInt(qty)>0){
                            _pc.push({parts:[{axis:'Размер',value:sz},{axis:'Цвят',value:cl}],printQty:parseInt(qty)||1});
                        }
                    })});
                }
            }else{
                _pc=wizBuildCombinations();
                document.querySelectorAll('[data-combo][data-field="qty"]').forEach(function(inp){var ci=parseInt(inp.dataset.combo);if(_pc[ci])_pc[ci].printQty=parseInt(inp.value)||1;});
            }
            if(!_pc.length){
                _pc=[{parts:[],printQty:parseInt(document.getElementById('wSingleQty')?.value)||1}];
            }
            S.wizData._printCombos=_pc;
            // S82.STUDIO.2: branch AFTER printCombos are built.
            // YES path → open AI Studio directly (no print step flash).
            // NO path → wizGo(6) print step as before.
            if (S.wizData._openStudioAfterSave && typeof openStudioModal === 'function') {
                S.wizData._openStudioAfterSave = false; // one-shot
                openStudioModal(r.id);
                // Hide the wizard frame underneath so when modal closes user is back on products list.
                try { closeWizard(); } catch(_){}
                loadScreen();
            } else {
                wizGo(6);
                loadScreen();
            }
        }else{showToast(r?.error||'Грешка','error')}
    }catch(e){showToast('Мрежова грешка','error')}
}

function wizAddSubcat(){
    const name=document.getElementById('inlSubcatName')?.value.trim();
    const parentId=document.getElementById('wCatDD')?._selectedId||S.wizData.category_id;
    if(!name||!parentId){showToast('Избери категория и въведи име','error');return}
    // S88.BUG#4: fuzzy match against existing subcategories of this parent
    const subSel = document.getElementById('wSubcat');
    const existingSubs = [];
    if (subSel) {
        for (let i=0; i<subSel.options.length; i++){
            const o = subSel.options[i];
            if (o.value) existingSubs.push({ id: o.value, name: o.textContent });
        }
    }
    const doCreate = function(){
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
    };
    fuzzyConfirmAdd('подкатегория', name, existingSubs,
        function(existing){
            if (subSel){ subSel.value = existing.id; }
            S.wizData.subcategory_id = existing.id;
            document.getElementById('inlSubcat')?.classList.remove('open');
            showToast('Използвана: '+existing.name,'success');
        },
        doCreate
    );
}

function wizTypeGuard(e){
    if(S.wizType)return;
    e.preventDefault();
    if(e.target&&e.target.blur)e.target.blur();
    showToast('Избери първо: Единичен или С варианти','error');
    var tg=document.querySelector('.v4-type-toggle');
    if(tg){tg.classList.add('pulsing-strong');setTimeout(function(){tg.classList.remove('pulsing-strong')},1600);try{tg.scrollIntoView({behavior:'smooth',block:'center'})}catch(_){tg.scrollIntoView()}}
    if(navigator.vibrate)navigator.vibrate([50,30,50]);
}
function wizSwitchType(t){
    if(S.wizType===t)return;
    S.wizType=t;
    renderWizard();
    if(navigator.vibrate)navigator.vibrate(6);
}
// S88B-1: card-tap on Step 0 → switch type AND advance to photo step.
function wizPickType(t){
    S.wizType = t;
    S.wizStep = 2;
    renderWizard();
    if(navigator.vibrate)navigator.vibrate(8);
}
// S92.PRODUCTS.PRICE_LAYOUT: ПЕЧАЛБА % = MARGIN формула (retail-cost)/retail*100,
// не markup (retail-cost)/cost. UI label-а е "ПЕЧАЛБА %" — печалба върху продажната цена.
// Стойността е COMPUTED ONLY, не се персистира в DB. Field id-то остана wMarkupPct
// за обратна съвместимост с copy-from-prev / AI fill референсите.
function wizUpdateMarkup(){
    var costEl=document.getElementById('wCostPrice');
    var retailEl=document.getElementById('wPrice');
    var mEl=document.getElementById('wMarkupPct');
    if(!mEl)return;
    var cost=parseFloat(costEl?costEl.value:0)||0;
    var retail=parseFloat(retailEl?retailEl.value:0)||0;
    if(cost>0){
        mEl.disabled=false;
        if(retail>0){
            mEl.value=Math.round(((retail-cost)/retail)*100);
            mEl.placeholder='auto';
        }else{
            mEl.value='';
            mEl.placeholder='въведи и retail';
        }
    }else{
        mEl.value='';
        mEl.disabled=true;
        mEl.placeholder='(въведи доставна)';
    }
}
// S92.PRODUCTS.PRICE_LAYOUT: typing margin m → retail = cost / (1 - m/100). Inverse на margin
// формулата. m≥100 е невалидно (печалба не може да е 100%+ върху продажната — при m=100
// cost=0). При невалидни стойности оставяме retail непроменен.
function wizApplyMarkup(){
    var costEl=document.getElementById('wCostPrice');
    var mEl=document.getElementById('wMarkupPct');
    var retailEl=document.getElementById('wPrice');
    if(!costEl||!mEl||!retailEl)return;
    var cost=parseFloat(costEl.value)||0;
    var m=parseFloat(mEl.value);
    if(cost>0&&!isNaN(m)&&m<100){
        var retail=Math.round(cost/(1-m/100)*100)/100;
        retailEl.value=retail;
        S.wizData.retail_price=retail;
    }
}
// S88B-1 / Q7: mark a photo as main (variant + multi-mode B2). Used for parent products.image_url.
function wizSetMainPhoto(idx){
    if(!Array.isArray(S.wizData._photos))return;
    S.wizData._photos.forEach(function(p,i){p.is_main=(i===idx)});
    renderWizard();
    showToast('Главна снимка избрана','success');
    if(navigator.vibrate)navigator.vibrate(8);
}
// S88B-1: bulk copy-from-last — populates ALL fields except Name/Photo/Barcode/Code (+Color/Size if variant).
function wizCopyPrevProductFull(){
    var prev=null;try{prev=JSON.parse(localStorage.getItem('_rms_lastWizProductFields'))}catch(e){}
    if(!prev||typeof prev!=='object'){showToast('Няма предишен артикул','error');return}
    var skip=['name','barcode','code','_photoDataUrl','_photos'];
    if(S.wizType==='variant'){skip.push('color');skip.push('size')}
    Object.keys(prev).forEach(function(k){
        if(skip.indexOf(k)!==-1)return;
        // *_name fields are display-only labels for *_id rehydration; copy alongside.
        S.wizData[k]=prev[k];
    });
    renderWizard();
    showToast('Копиран целия профил','success');
    if(navigator.vibrate)navigator.vibrate([8,30,8]);
}
// S88B-1: per-field ↻ copy — single-field variant of wizCopyPrevProductFull.
function wizCopyFieldFromPrev(field){
    var prev=null;try{prev=JSON.parse(localStorage.getItem('_rms_lastWizProductFields'))}catch(e){}
    if(!prev){showToast('Няма предишен артикул','error');return}
    var v=prev[field];
    if(v===undefined||v===null||v===''){showToast('Това поле е празно в последния','info');return}
    S.wizData[field]=v;
    // For *_id fields, also restore the display label if available.
    if(field==='supplier_id'&&prev.supplier_name){var sd=document.getElementById('wSupDD');if(sd){sd.value=prev.supplier_name;sd._selectedId=v}}
    if(field==='category_id'&&prev.category_name){var cd=document.getElementById('wCatDD');if(cd){cd.value=prev.category_name;cd._selectedId=v;if(typeof wizLoadSubcats==='function')wizLoadSubcats(v);}}
    if(field==='subcategory_id'&&prev.subcategory_name){var sub=document.getElementById('wSubcat');if(sub){var found=false;for(var i=0;i<sub.options.length;i++){if(sub.options[i].value==v){sub.value=v;found=true;break}}if(!found){var o=document.createElement('option');o.value=v;o.textContent=prev.subcategory_name;sub.appendChild(o);sub.value=v}}}
    // For text/numeric fields, refresh DOM input.
    var fieldToInput={retail_price:'wPrice',cost_price:'wCostPrice',markup_pct:'wMarkupPct',min_quantity:'wMinQty',composition:'wComposition',origin_country:'wOrigin',color:'wColor',size:'wSize'};
    var inpId=fieldToInput[field];
    if(inpId){var el=document.getElementById(inpId);if(el)el.value=v;}
    showToast('Копирано от последния','success');
    if(navigator.vibrate)navigator.vibrate(5);
}
// S88B-1: Step 2 (photo) renderer — extracted from old Step 3 photoBlock.
function renderWizPhotoStep(){
    if(!S.wizType){setTimeout(function(){wizGo(0)},0);return '<div class="wiz-page active"><div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:12px">Зареждане...</div></div>';}
    var _photoMode=S.wizData._photoMode;
    if(!_photoMode){try{_photoMode=localStorage.getItem('_rms_photoMode')||'single'}catch(e){_photoMode='single'}S.wizData._photoMode=_photoMode}
    if(S.wizType!=='variant')_photoMode='single';
    var _hasPhoto=!!S.wizData._photoDataUrl;
    var _photoModeToggle='';
    if(S.wizType==='variant'){
        _photoModeToggle=
            '<div class="photo-mode-toggle" style="display:flex;gap:6px;margin-bottom:12px;padding:4px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06)">'+
                '<button type="button" class="pmt-opt'+(_photoMode==='single'?' active':'')+'" onclick="wizSetPhotoMode(\'single\')" style="flex:1;padding:10px;border-radius:9px;background:'+(_photoMode==='single'?'linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08))':'transparent')+';border:1px solid '+(_photoMode==='single'?'rgba(139,92,246,0.5)':'transparent')+';color:'+(_photoMode==='single'?'#c4b5fd':'rgba(255,255,255,0.55)')+';font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Само главна снимка</button>'+
                '<button type="button" class="pmt-opt'+(_photoMode==='multi'?' active':'')+'" onclick="wizSetPhotoMode(\'multi\')" style="flex:1;padding:10px;border-radius:9px;background:'+(_photoMode==='multi'?'linear-gradient(180deg,rgba(217,70,239,0.18),rgba(168,85,247,0.08))':'transparent')+';border:1px solid '+(_photoMode==='multi'?'rgba(217,70,239,0.5)':'transparent')+';color:'+(_photoMode==='multi'?'#f0abfc':'rgba(255,255,255,0.55)')+';font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Снимки на вариации</button>'+
            '</div>';
    }
    var photoBlock='';
    if(_photoMode==='multi'){
        var _photos=Array.isArray(S.wizData._photos)?S.wizData._photos:[];
        var _gridH='<div class="photo-multi-grid">';
        _photos.forEach(function(p,i){
            var conf=(p.ai_confidence===null||p.ai_confidence===undefined)?null:p.ai_confidence;
            var confLabel='';var confCls='photo-color-conf';
            if(conf===null){confLabel='AI...';confCls+=' detecting'}
            else if(conf>=0.75){confLabel=Math.round(conf*100)+'%'}
            else if(conf>=0.5){confLabel=Math.round(conf*100)+'%';confCls+=' warn'}
            else{confLabel='?';confCls+=' warn'}
            var swHex=p.ai_hex||'#666';
            var nm=(p.ai_color||'').replace(/"/g,'&quot;');
            var isMain=!!p.is_main;
            var mainBadge=isMain?'<span style="position:absolute;top:6px;left:6px;padding:2px 7px;border-radius:7px;background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#0f1224;font-size:9px;font-weight:800;letter-spacing:0.04em;box-shadow:0 2px 8px rgba(251,191,36,0.5);z-index:2">★ ГЛАВНА</span>':'';
            var cellBorder=isMain?'border:2px solid #fbbf24;box-shadow:0 0 14px rgba(251,191,36,0.35)':'';
            var mainBtn=isMain
                ? '<div style="margin-top:6px;font-size:10px;color:#fbbf24;text-align:center;font-weight:600">★ Главна снимка</div>'
                : '<button type="button" onclick="wizSetMainPhoto('+i+')" style="margin-top:6px;width:100%;padding:7px;border-radius:8px;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.3);color:#fcd34d;font-size:10px;font-weight:600;cursor:pointer;font-family:inherit">★ Направи главна</button>';
            _gridH+=
                '<div class="photo-multi-cell" style="position:relative;'+cellBorder+'">'+
                    '<div class="photo-multi-thumb" style="position:relative">'+
                        '<img class="ph-img" src="'+p.dataUrl+'" alt="">'+
                        '<span class="ph-num">'+(i+1)+'</span>'+
                        mainBadge+
                        '<button type="button" class="ph-rm" onclick="wizPhotoMultiRemove('+i+')">×</button>'+
                    '</div>'+
                    '<div class="photo-color-input">'+
                        '<span class="photo-color-swatch" style="background:'+swHex+'"></span>'+
                        '<input type="text" value="'+nm+'" placeholder="цвят..." oninput="wizPhotoSetColor('+i+',this.value)">'+
                        '<span class="'+confCls+'">'+confLabel+'</span>'+
                    '</div>'+
                    mainBtn+
                '</div>';
        });
        _gridH+=
            '<div class="photo-multi-cell">'+
                '<div class="photo-empty-add" onclick="wizPhotoMultiPick()">'+
                    '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'+
                    '<span>Добави</span>'+
                '</div>'+
            '</div>';
        _gridH+='</div>';
        var _info='<div class="photo-multi-info">Снимки по цвят: <b>'+_photos.length+'</b> · AI разпознава цветовете автоматично</div>';
        photoBlock='<div class="v4-pz">'+_photoModeToggle+_info+_gridH+'</div>';
    }else{
        var _photoContent=_hasPhoto
            ? '<img src="'+S.wizData._photoDataUrl+'" onclick="document.getElementById(\'filePickerInput\').click()" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:14px;cursor:pointer;margin-bottom:10px">'
            : '<div class="v4-pz-top"><div class="v4-pz-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div><div style="flex:1;min-width:0"><div class="v4-pz-title">Снимай артикула</div><div class="v4-pz-sub">AI анализира снимката</div></div></div>';
        var _photoBtns='<div class="v4-pz-btns"><button type="button" onclick="document.getElementById(\'photoInput\').click()" class="v4-pz-btn primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Снимай</button><button type="button" onclick="document.getElementById(\'filePickerInput\').click()" class="v4-pz-btn sec"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Галерия</button></div>';
        var _photoTips='<div class="v4-pz-tips"><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Равна светла повърхност</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Без други предмети</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Добро осветление</span></div>';
        photoBlock='<div class="v4-pz">'+_photoModeToggle+_photoContent+_photoBtns+_photoTips+'</div>';
    }
    var skipNote='<div style="text-align:center;font-size:11px;color:#94a3b8;margin-top:14px;padding:10px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.08)">💡 Снимката е по желание — името е задължително</div>';
    var hasAny = _hasPhoto || (_photoMode==='multi' && Array.isArray(S.wizData._photos) && S.wizData._photos.length);
    var hasName = !!(S.wizData.name && S.wizData.name.trim());
    var nextLabel = hasName ? 'Напред' : 'Въведи име първо';
    var nextDis = hasName ? '' : 'opacity:0.5;pointer-events:none;';
    var footer='<div style="display:flex;gap:8px;margin-top:16px">'+
        '<button type="button" onclick="wizGo(0)" style="flex:1;height:44px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>Назад</button>'+
        '<button type="button" onclick="wizCollectData();if(!S.wizData.name){showToast(\'Въведи име\',\'error\');document.getElementById(\'wName\').focus();return}wizGo(3,false,0)" style="flex:1.4;height:44px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#4338ca);border:1px solid #6366f1;color:#fff;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit;box-shadow:0 4px 14px rgba(99,102,241,0.4);'+nextDis+'">'+nextLabel+'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>'+
    '</div>';
    // S92.WIZARD_REWRITE: Name + mic on this step (Step 1 in brief = Идентификация: снимка + име).
    var nameBlock=
        '<div class="glass v4-glass-pro" style="padding:14px 14px 12px;margin-bottom:10px">'+
            '<span class="shine shine-top"></span><span class="shine shine-bottom"></span>'+
            '<div class="fg" style="margin:0">'+
                '<label class="fl">Име&nbsp;<span style="color:#ef4444">*</span></label>'+
                '<div style="display:flex;gap:6px;align-items:center">'+
                    '<input type="text" class="fc" id="wName" oninput="S.wizData.name=this.value.trim();wizClearAIMark(\'name\');wizDupeCheckName(this.value);wizMaybeAdvancePhotoStep()" value="'+esc(S.wizData.name||'')+'" placeholder="напр. Дънки Mustang син деним" style="flex:1">'+
                    '<button type="button" class="wiz-mic" onclick="wizMic(\'name\')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></button>'+
                '</div>'+
                '<div id="wDupeBanner" style="display:none"></div>'+
            '</div>'+
        '</div>';
    return '<div class="wiz-page active" style="padding:18px 14px">'+
        '<div style="text-align:center;font-size:14px;font-weight:600;color:#fff;margin-bottom:6px">Идентификация на артикула</div>'+
        '<div style="text-align:center;font-size:11px;color:#94a3b8;margin-bottom:14px">Снимка + име — после AI помага с останалото</div>'+
        nameBlock+
        '<div class="glass v4-glass-pro" style="padding:18px 14px;margin-bottom:8px">'+
            '<span class="shine shine-top"></span><span class="shine shine-bottom"></span>'+
            '<span class="glow glow-top"></span><span class="glow glow-bottom"></span>'+
            photoBlock+
        '</div>'+
        skipNote+
        footer+
    '</div>';
}
// S92.WIZARD_REWRITE: voice/manual auto-advance hook for the Photo+Name step.
// Triggers only ако името е попълнено и Тихол вече е спрял да пише за >900мс.
var _wizAdvPhotoTimer=null;
function wizMaybeAdvancePhotoStep(){
    if(S.wizStep!==2)return;
    if(_wizAdvPhotoTimer){clearTimeout(_wizAdvPhotoTimer);_wizAdvPhotoTimer=null}
    var nm=(document.getElementById('wName')||{}).value||'';
    if(nm.trim().length<3)return;
    if(!S.wizVoiceMode)return; // manual режим — не auto-advance, остави Тихол да реши
    _wizAdvPhotoTimer=setTimeout(function(){
        if(S.wizStep!==2)return;
        var n=(document.getElementById('wName')||{}).value||'';
        if(n.trim().length<3)return;
        S.wizData.name=n.trim();
        wizGo(3,false,0);
    },1100);
}
function wizSelectUnit(btn,unit){
    S.wizData.unit=unit;
    document.querySelectorAll('.v4-unit-chip').forEach(function(c){
        c.style.background='rgba(255,255,255,0.03)';
        c.style.border='1px solid rgba(255,255,255,0.08)';
        c.style.color='rgba(255,255,255,0.6)';
        c.style.boxShadow='none';
    });
    btn.style.background='linear-gradient(135deg,#4338ca,#3730a3)';
    btn.style.border='1px solid #6366f1';
    btn.style.color='#fff';
    btn.style.boxShadow='0 0 10px rgba(99,102,241,0.3)';
    if(navigator.vibrate)navigator.vibrate(5);
}
function wizAddUnitFromChip(){
    var inp=document.getElementById('wNewUnit');
    if(!inp)return;
    var unit=(inp.value||'').trim();
    if(!unit){inp.focus();return;}
    if(CFG.units&&CFG.units.indexOf(unit)>=0){
        S.wizData.unit=unit;inp.value='';renderWizard();
        showToast('"'+unit+'" вече съществува','info');return;
    }
    api('products.php?ajax=add_unit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'unit='+encodeURIComponent(unit)}).then(function(d){
        if(d&&d.units){CFG.units=d.units;S.wizData.unit=d.added||unit;inp.value='';renderWizard();showToast('"'+unit+'" добавена','success');}
        else{showToast('Грешка','error');}
    }).catch(function(){showToast('Грешка','error');});
}
function wizDeleteUnit(unit){
    if(!confirm('Изтрий "'+unit+'"?'))return;
    api('products.php?ajax=delete_unit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'unit='+encodeURIComponent(unit)}).then(function(d){
        if(d&&d.units){
            CFG.units=d.units;
            if(S.wizData.unit===unit)S.wizData.unit=d.units[0]||'бр';
            renderWizard();
            showToast('"'+unit+'" изтрита','success');
        }else{showToast('Грешка','error');}
    }).catch(function(){showToast('Грешка','error');});
}
function wizAddUnitPrompt(){wizAddUnitFromChip()}
function wizAddUnit(){wizAddUnitFromChip()}

// Photo handlers

document.getElementById('filePickerInput').addEventListener('change',async function(){
    if(this.getAttribute('data-studio')==='1'){
        this.removeAttribute('data-studio');
        if(this.files?.[0]) studioUploadPhoto(this.files[0], S._studioProductId);
        this.value='';
        return;
    }
    document.getElementById('photoInput').files = this.files;
    document.getElementById('photoInput').dispatchEvent(new Event('change'));
    this.value='';
});
// S43: removed duplicate filePickerInput listener
document.getElementById('photoInput').addEventListener('change',async function(){
    if(!this.files?.[0])return;
    // Studio mode — upload to product
    if(this.getAttribute('data-studio')==='1'){
        this.removeAttribute('data-studio');
        studioUploadPhoto(this.files[0], S._studioProductId);
        this.value='';
        return;
    }
    const preview=document.getElementById('wizPhotoPreview');
    const result=document.getElementById('wizScanResult');
    if(preview)preview.innerHTML='<div style="font-size:12px;color:var(--text-secondary);margin-top:8px">Зареждам...</div>';
    const reader=new FileReader();
    reader.onload=e=>{
        S.wizData._photoDataUrl=e.target.result;
        S.wizData._hasPhoto=true;
        if(document.getElementById('wizPhotoPreview'))document.getElementById('wizPhotoPreview').innerHTML='<img src="'+e.target.result+'" style="max-width:100%;max-height:150px;border-radius:10px;border:1px solid var(--border-subtle);margin-top:8px">';
        if(result)result.innerHTML='<div style="font-size:12px;color:var(--success);margin-top:6px">Снимката е заредена</div>';
        showToast('Снимка добавена','success');
        // S73.B.35: Rerender за да се покаже снимката в photo zone на Step 1
        renderWizard();
        // S82.COLOR.4: _aiAutoTrigger auto-trigger of wizAIProcessPhoto removed.
    };
    reader.readAsDataURL(this.files[0]);
    this.value='';
});

// Inline add helpers
function toggleInl(id){document.getElementById(id)?.classList.toggle('open')}

// ───────── S88.BUG#4: fuzzy match (Levenshtein, 80% threshold) ─────────
function _levenshtein(a, b){
    a = (a||'').toLowerCase().trim(); b = (b||'').toLowerCase().trim();
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;
    const m = a.length, n = b.length;
    const dp = new Array(n + 1);
    for (let j = 0; j <= n; j++) dp[j] = j;
    for (let i = 1; i <= m; i++){
        let prev = dp[0]; dp[0] = i;
        for (let j = 1; j <= n; j++){
            const tmp = dp[j];
            dp[j] = (a.charCodeAt(i-1) === b.charCodeAt(j-1))
                ? prev
                : Math.min(prev, dp[j], dp[j-1]) + 1;
            prev = tmp;
        }
    }
    return dp[n];
}
// Returns null or {match:<original candidate>, name:<display name>, score:0-100}
function fuzzyMatch80(input, candidates){
    if (!input || !candidates || !candidates.length) return null;
    const inp = String(input).toLowerCase().trim();
    if (!inp) return null;
    let best = null, bestScore = 0;
    for (const c of candidates){
        if (c == null) continue;
        const name = (typeof c === 'string') ? c : (c.name || '');
        const cand = String(name).toLowerCase().trim();
        if (!cand) continue;
        let sim;
        if (cand === inp) { sim = 1; }
        else {
            const dist = _levenshtein(inp, cand);
            const maxLen = Math.max(inp.length, cand.length);
            sim = maxLen ? 1 - (dist / maxLen) : 0;
        }
        if (sim > bestScore) { bestScore = sim; best = { match: c, name: name, score: Math.round(sim * 100) }; }
    }
    return (best && best.score >= 80) ? best : null;
}
// onUseExisting(matchedCandidate), onAddNew() — call exactly one.
function fuzzyConfirmAdd(label, input, candidates, onUseExisting, onAddNew){
    const m = fuzzyMatch80(input, candidates);
    if (!m){ onAddNew(); return; }
    const msg = 'Вече има "' + m.name + '" (' + m.score + '% близко до "' + input + '").\n\n'
              + 'OK = използвай съществуващото "' + m.name + '"\n'
              + 'Откажи = добави "' + input + '" като ново';
    if (confirm(msg)) onUseExisting(m.match);
    else onAddNew();
}
// ───────── /S88.BUG#4 ─────────

// ───────── S88.BUG#6: duplicates modal (3 options) ─────────
function showDuplicatesModalS88(matches, fields){
    return new Promise(function(resolve){
        var existing = document.getElementById('s88DupModal');
        if (existing) existing.remove();
        var fieldNames = { name:'име', code:'код', barcode:'баркод' };
        var labelMap = (fields||[]).map(function(f){return fieldNames[f]||f;}).join(', ');
        var first = matches[0] || {};
        var listH = matches.slice(0,5).map(function(m){
            var byTxt = fieldNames[m.by] || m.by;
            return '<div style="padding:8px 10px;border:1px solid rgba(99,102,241,0.2);border-radius:8px;margin:4px 0;background:rgba(99,102,241,0.06);font-size:11px;color:#cbd5e1">'
                + '<b style="color:#fff">'+(m.name||'')+'</b>'
                + (m.code ? ' · код <span style="color:#a5b4fc">'+m.code+'</span>' : '')
                + (m.barcode ? ' · ШК <span style="color:#a5b4fc">'+m.barcode+'</span>' : '')
                + ' <span style="opacity:.7">(съвпада по '+byTxt+')</span></div>';
        }).join('');
        var ov = document.createElement('div');
        ov.id = 's88DupModal';
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px';
        ov.innerHTML =
            '<div style="background:#0f1224;border:1px solid rgba(99,102,241,0.4);border-radius:16px;padding:18px;max-width:380px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,0.6)">'
            + '<div style="font-size:14px;font-weight:700;color:#fff;margin-bottom:6px">⚠ Дубликат в '+labelMap+'</div>'
            + '<div style="font-size:11px;color:#a5b4fc;margin-bottom:10px">Има артикул(и) с подобни данни:</div>'
            + listH
            + '<div style="display:flex;flex-direction:column;gap:6px;margin-top:14px">'
            + '<button id="s88DupSave" style="padding:10px;border-radius:10px;background:linear-gradient(135deg,#16a34a,#15803d);border:1px solid #16a34a;color:#fff;font-size:12px;font-weight:700;cursor:pointer">✓ Запази въпреки това</button>'
            + '<button id="s88DupOpen" style="padding:10px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#4338ca);border:1px solid #6366f1;color:#fff;font-size:12px;font-weight:700;cursor:pointer">📂 Отвори съществуващия (#'+(first.id||'?')+')</button>'
            + '<button id="s88DupCancel" style="padding:10px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.15);color:#cbd5e1;font-size:12px;font-weight:600;cursor:pointer">✕ Отказ</button>'
            + '</div></div>';
        document.body.appendChild(ov);
        var done = function(v){ ov.remove(); resolve(v); };
        document.getElementById('s88DupSave').onclick   = function(){ done('save'); };
        document.getElementById('s88DupOpen').onclick   = function(){ done('open'); };
        document.getElementById('s88DupCancel').onclick = function(){ done('cancel'); };
        ov.onclick = function(e){ if (e.target === ov) done('cancel'); };
    });
}
// ───────── /S88.BUG#6 ─────────

// ───────── S88.BUG#3: "..." menu + "📋 Като предния" duplicate flow ─────────
function openMoreAddOptionsS88(anchor){
    var existing = document.getElementById('s88MoreMenu');
    if (existing){ existing.remove(); return; }
    var ov = document.createElement('div');
    ov.id = 's88MoreMenu';
    ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.4);display:flex;align-items:flex-end;justify-content:center;padding:0';
    ov.innerHTML =
        '<div style="background:#0f1224;border-top:1px solid rgba(99,102,241,0.4);border-radius:18px 18px 0 0;width:100%;max-width:480px;padding:14px 14px 22px">'
        + '<div style="width:38px;height:4px;background:rgba(255,255,255,0.2);border-radius:2px;margin:0 auto 10px"></div>'
        + '<button id="s88MoreLikePrev" style="width:100%;padding:14px;border-radius:12px;background:linear-gradient(135deg,rgba(99,102,241,0.18),rgba(67,56,202,0.06));border:1px solid rgba(139,92,246,0.5);color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:10px">📋 Като предния</button>'
        + '<button id="s88MoreClose" style="width:100%;margin-top:8px;padding:12px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.12);color:#cbd5e1;font-size:12px;font-weight:600;cursor:pointer">Отказ</button>'
        + '</div>';
    document.body.appendChild(ov);
    var close = function(){ ov.remove(); };
    document.getElementById('s88MoreClose').onclick = close;
    ov.onclick = function(e){ if (e.target === ov) close(); };
    document.getElementById('s88MoreLikePrev').onclick = function(){ close(); openLikePreviousWizardS88(); };
}

async function openLikePreviousWizardS88(){
    var d = await api('products.php?ajax=last_product');
    if (!d || d.error){
        showToast('Няма предишен артикул за копиране','error');
        return;
    }
    renderLikePrevPageS88(d);
}

// Kept as no-op for backward compat — old banner/checkbox is now part of renderLikePrevPageS88.
function injectLikePrevControlsS88(){}

// ───────── S88.KP — "Като предния" full-page wizard (DESIGN_LAW §2,§7,§8,§10,§16) ─────────

function kpClose(){
    var m = document.getElementById('kpModal');
    if (m) m.remove();
    document.body.style.overflow = '';
    window._kpState = null;
}

function kpFieldDef(idx){
    // S88B.KP: BIBLE 7.2.8 v1.3 — 10 копирани полета (9-10 read-only display)
    var fields = [
        { key:'retail',           label:'Цена дребно',  fmt:'price', editable:true },
        { key:'cost',             label:'Доставна',     fmt:'price', editable:true },
        { key:'_margin',          label:'Печалба',      fmt:'margin', computed:true },
        { key:'supplier_name',    label:'Доставчик',    fmt:'text',  editable:true },
        { key:'category_name',    label:'Категория',    fmt:'text',  editable:true },
        { key:'subcategory_name', label:'Подкатегория', fmt:'text',  editable:true },
        { key:'composition',      label:'Материя',      fmt:'text',  editable:true },
        { key:'origin',           label:'Произход',     fmt:'text',  editable:true },
        { key:'wholesale',        label:'Цена едро',    fmt:'price', editable:true },
        { key:'_variation',       label:'Тип артикул',  fmt:'variation', computed:true }
    ];
    return fields[idx];
}

function kpFieldHtml(f, idx, st){
    var editIco = '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
    if (f.computed && f.key === '_margin'){
        var retail = parseFloat(st.retail)||0, cost = parseFloat(st.cost)||0;
        var marginAbs = retail - cost;
        var marginPct = retail > 0 ? Math.round((marginAbs/retail)*100) : 0;
        return '<span class="kp-cf-label">'+f.label+'</span>'
            + '<span class="kp-cf-value computed tabular-nums" id="kpMargin">'+marginPct+' % ('+marginAbs.toFixed(2)+' €)</span>'
            + '<span class="kp-cf-auto">авто</span>';
    }
    // S88B.KP: 10-то поле — Тип артикул (read-only display, source-derived)
    if (f.computed && f.key === '_variation'){
        var disp;
        if (st.type === 'variant'){
            var nColors = (st.colors||[]).length;
            var maxSizes = 0;
            (st.colors||[]).forEach(function(c){
                var sz = (st.sizesByColor||{})[c] || [];
                if (sz.length > maxSizes) maxSizes = sz.length;
            });
            disp = 'Вариационен (' + nColors + ' цвята × ' + maxSizes + ' размера)';
        } else {
            disp = 'Единичен';
        }
        return '<span class="kp-cf-label">'+f.label+'</span>'
            + '<span class="kp-cf-value computed" id="kpV'+idx+'">'+esc(disp)+'</span>'
            + '<span class="kp-cf-auto">авто</span>';
    }
    var v = st[f.key];
    var disp;
    if (f.fmt === 'price'){
        var p = parseFloat(v)||0;
        disp = (p > 0) ? (p.toFixed(2) + ' €') : '—';
    }
    else disp = (v === null || v === undefined || v === '') ? '—' : String(v);
    var num = (f.fmt === 'price') ? ' tabular-nums' : '';
    var btn = f.editable
        ? ('<button class="kp-cf-edit" type="button" onclick="kpEditField('+idx+',\''+f.key+'\',\''+f.fmt+'\')">'+editIco+'</button>')
        : '';
    return '<span class="kp-cf-label">'+f.label+'</span>'
        + '<span class="kp-cf-value'+num+'" id="kpV'+idx+'">'+esc(disp)+'</span>'
        + btn;
}

function kpEditField(idx, key, fmt){
    var st = window._kpState; if (!st) return;
    var row = document.getElementById('kpCf'+idx); if (!row) return;
    var label = kpFieldDef(idx).label;
    var cur = st[key];
    if (fmt === 'price') cur = (parseFloat(cur)||0).toFixed(2);
    if (cur === null || cur === undefined) cur = '';
    var inputType = (fmt === 'price') ? 'number' : 'text';
    var extra = (fmt === 'price') ? ' step="0.01" inputmode="decimal"' : '';
    row.innerHTML = '<span class="kp-cf-label">'+label+'</span>'
        + '<input class="kp-cf-input" id="kpEdit'+idx+'" type="'+inputType+'"'+extra+' value="'+esc(String(cur))+'" '
        + 'onkeydown="if(event.key===\'Enter\')this.blur();if(event.key===\'Escape\'){window._kpEditCancel='+idx+';this.blur()}" '
        + 'onblur="kpEditCommit('+idx+',\''+key+'\',\''+fmt+'\')">'
        + '<button class="kp-cf-edit" type="button" onmousedown="event.preventDefault();kpEditCommit('+idx+',\''+key+'\',\''+fmt+'\')">'
        + '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></button>';
    var el = document.getElementById('kpEdit'+idx);
    if (el){ el.focus(); if (el.select) el.select(); }
}

function kpEditCommit(idx, key, fmt){
    var st = window._kpState; if (!st) return;
    var row = document.getElementById('kpCf'+idx); if (!row) return;
    var el = document.getElementById('kpEdit'+idx);
    if (window._kpEditCancel === idx){
        window._kpEditCancel = null;
    } else if (el){
        var v = el.value;
        if (fmt === 'price') v = parseFloat(v)||0;
        st[key] = v;
    }
    row.innerHTML = kpFieldHtml(kpFieldDef(idx), idx, st);
    if (key === 'retail' || key === 'cost'){
        var mrow = document.getElementById('kpCf2');
        if (mrow) mrow.innerHTML = kpFieldHtml(kpFieldDef(2), 2, st);
    }
}

function kpToggleCopied(head){
    head.classList.toggle('expanded');
    var b = document.getElementById('kpCfBody');
    if (b) b.classList.toggle('expanded');
}

function kpSwatchClass(name){
    var n = (name||'').toLowerCase().trim();
    var map = {
        'черно':'c-black','черен':'c-black','black':'c-black',
        'бяло':'c-white','бял':'c-white','white':'c-white',
        'син':'c-blue','синьо':'c-blue','blue':'c-blue','navy':'c-blue',
        'червен':'c-red','червено':'c-red','red':'c-red',
        'бежов':'c-beige','беж':'c-beige','beige':'c-beige',
        'розов':'c-pink','розово':'c-pink','pink':'c-pink',
        'зелен':'c-green','зелено':'c-green','green':'c-green',
        'жълт':'c-yellow','жълто':'c-yellow','yellow':'c-yellow',
        'виолетов':'c-violet','лилав':'c-violet','violet':'c-violet','purple':'c-violet',
        'сив':'c-gray','сиво':'c-gray','gray':'c-gray','grey':'c-gray',
        'кафяв':'c-brown','кафяво':'c-brown','brown':'c-brown'
    };
    return map[n] || 'c-gray';
}

function kpVariantSectionHtml(st){
    var totalArt = 0;
    st.colors.forEach(function(c){ var sz = st.sizesByColor[c]||[]; totalArt += sz.length; });
    var head = '<div class="kp-section-head">'
        + '<div class="kp-section-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>'
        + '<div class="kp-section-text">'
            + '<div class="kp-section-title">Вариации</div>'
            + '<div class="kp-section-sub">'+st.colors.length+' цвята · <span class="tabular-nums">'+totalArt+'</span> артикула</div>'
        + '</div>'
        + '<div class="kp-section-pill">'+st.colors.length+' ЦВЯТА</div>'
        + '</div>';
    var cards = st.colors.map(function(color, ci){
        var sizes = (st.sizesByColor[color]||[]);
        var rows = sizes.map(function(sz, si){
            var cellId = 'mx_'+si+'_'+ci;
            var qty = (st.matrix[cellId] && st.matrix[cellId].qty) || 0;
            return '<div class="kp-size-row">'
                + '<div class="kp-sz-label">'+esc(sz||'—')+'</div>'
                + '<div class="kp-sz-stepper">'
                    + '<button class="kp-sz-btn" type="button" onclick="kpAdj(\''+cellId+'\',-1)">−</button>'
                    + '<span class="kp-sz-qty" id="kpQ_'+cellId+'">'+qty+'</span>'
                    + '<button class="kp-sz-btn" type="button" onclick="kpAdj(\''+cellId+'\',1)">+</button>'
                + '</div>'
            + '</div>';
        }).join('');
        var cls = kpSwatchClass(color);
        var totalForColor = sizes.reduce(function(s, sz, si){
            var k='mx_'+si+'_'+ci; return s + ((st.matrix[k]&&st.matrix[k].qty)||0);
        }, 0);
        return '<div class="glass sm kp-color-card kp-cardin" id="kpColorCard_'+ci+'">'
            + '<span class="shine"></span><span class="shine shine-bottom"></span>'
            + '<span class="glow"></span><span class="glow glow-bottom"></span>'
            + '<div class="kp-cc-head">'
                + '<div class="kp-swatch '+cls+'"></div>'
                + '<div class="kp-cc-name">'+esc(color||'—')+'</div>'
                + '<div class="kp-cc-total tabular-nums" id="kpColorTotal_'+ci+'">'+totalForColor+' БР</div>'
                + '<button class="kp-cc-remove" type="button" onclick="kpRemoveColor('+ci+')"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
            + '</div>'
            + '<div class="kp-cc-body">' + rows + '</div>'
        + '</div>';
    }).join('');
    var addColor = '<div class="kp-add-color" onclick="kpAddColor()">'
        + '<div class="kp-ac-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>'
        + '<div class="kp-ac-text"><div class="kp-t1">Добави цвят</div><div class="kp-t2">Tap за нов цвят</div></div>'
        + '</div>';
    return '<div id="kpVariantSection">' + head + cards + addColor + '</div>';
}

function kpSingleSectionHtml(st){
    // S88B.KP: BIBLE 7.2.8 v1.3 — бройките винаги 0 (нов продукт = 0 до доставка). No copy-qty checkbox.
    return '<div id="kpSingleSection">'
        + '<div class="kp-section-head">'
            + '<div class="kp-section-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/></svg></div>'
            + '<div class="kp-section-text"><div class="kp-section-title">Бройка</div><div class="kp-section-sub">Колко имаш на склад</div></div>'
            + '<div class="kp-section-pill">ЕДИНИЧЕН</div>'
        + '</div>'
        + '<div class="glass sm kp-color-card kp-cardin">'
            + '<span class="shine"></span><span class="shine shine-bottom"></span>'
            + '<span class="glow"></span><span class="glow glow-bottom"></span>'
            + '<div class="kp-cc-body" style="padding:14px">'
                + '<div class="kp-size-row" style="border-bottom:0;padding:6px 0 0">'
                    + '<div class="kp-sz-label" style="min-width:80px">Брой</div>'
                    + '<div class="kp-sz-stepper">'
                        + '<button class="kp-sz-btn" type="button" onclick="kpSingleAdj(\'qty\',-1)">−</button>'
                        + '<span class="kp-sz-qty" id="kpSingleQtyView">0</span>'
                        + '<button class="kp-sz-btn" type="button" onclick="kpSingleAdj(\'qty\',1)">+</button>'
                    + '</div>'
                + '</div>'
                + '<div class="kp-size-row" style="border-bottom:0;padding:10px 0 0">'
                    + '<div class="kp-sz-label" style="min-width:80px">Минимум</div>'
                    + '<div class="kp-sz-stepper">'
                        + '<button class="kp-sz-btn" type="button" onclick="kpSingleAdj(\'min\',-1)">−</button>'
                        + '<span class="kp-sz-qty" id="kpSingleMinView">0</span>'
                        + '<button class="kp-sz-btn" type="button" onclick="kpSingleAdj(\'min\',1)">+</button>'
                    + '</div>'
                + '</div>'
            + '</div>'
        + '</div>'
        + '</div>';
}

function kpSingleAdj(which, delta){
    if (which === 'qty'){
        var qInp = document.getElementById('wSingleQty'); if (!qInp) return;
        var nv = Math.max(0, (parseInt(qInp.value)||0) + delta);
        qInp.value = nv;
        var v = document.getElementById('kpSingleQtyView'); if (v) v.textContent = nv;
    } else if (which === 'min'){
        var mInp = document.getElementById('wMinQty'); if (!mInp) return;
        var nv = Math.max(0, (parseInt(mInp.value)||0) + delta);
        mInp.value = nv;
        var v = document.getElementById('kpSingleMinView'); if (v) v.textContent = nv;
    }
    if (navigator.vibrate) navigator.vibrate(5);
}

function kpCopyQtyToggle(checked){
    var st = window._kpState; if (!st) return;
    st.copyQty = checked;
    var nv = checked ? (st.srcQty||0) : 0;
    var qInp = document.getElementById('wSingleQty'); if (qInp) qInp.value = nv;
    var v = document.getElementById('kpSingleQtyView'); if (v) v.textContent = nv;
}

function kpAdj(cellId, delta){
    var st = window._kpState; if (!st) return;
    if (!st.matrix[cellId]) st.matrix[cellId] = {qty:0};
    st.matrix[cellId].qty = Math.max(0, (parseInt(st.matrix[cellId].qty)||0) + delta);
    var span = document.getElementById('kpQ_'+cellId);
    if (span) span.textContent = st.matrix[cellId].qty;
    var parts = cellId.split('_');
    var ci = parseInt(parts[2])||0;
    var color = st.colors[ci];
    var sizes = (st.sizesByColor[color]||[]);
    var total = sizes.reduce(function(s, sz, si){
        var k='mx_'+si+'_'+ci; return s + ((st.matrix[k]&&st.matrix[k].qty)||0);
    }, 0);
    var t = document.getElementById('kpColorTotal_'+ci);
    if (t) t.textContent = total + ' БР';
    if (navigator.vibrate) navigator.vibrate(4);
}

function kpRemoveColor(ci){
    var st = window._kpState; if (!st) return;
    if (!confirm('Премахни този цвят?')) return;
    var color = st.colors[ci];
    st.colors.splice(ci, 1);
    if (color) delete st.sizesByColor[color];
    // Re-key matrix to drop entries for removed color column
    var newMx = {};
    Object.keys(st.matrix).forEach(function(k){
        var p = k.split('_'); var si = parseInt(p[1])||0, c = parseInt(p[2])||0;
        if (c === ci) return;
        var newCi = c > ci ? c - 1 : c;
        newMx['mx_'+si+'_'+newCi] = st.matrix[k];
    });
    st.matrix = newMx;
    var sec = document.getElementById('kpVariantSection');
    if (sec){
        var tmp = document.createElement('div');
        tmp.innerHTML = kpVariantSectionHtml(st);
        sec.replaceWith(tmp.firstChild);
    }
}

function kpAddColor(){
    var st = window._kpState; if (!st) return;
    var name = (prompt('Цвят (напр. Черно):')||'').trim();
    if (!name) return;
    if (st.colors.indexOf(name) !== -1){ showToast('Този цвят вече съществува','error'); return; }
    st.colors.push(name);
    var anySizes = Object.values(st.sizesByColor)[0] || ['M'];
    st.sizesByColor[name] = anySizes.slice();
    var sec = document.getElementById('kpVariantSection');
    if (sec){
        var tmp = document.createElement('div');
        tmp.innerHTML = kpVariantSectionHtml(st);
        sec.replaceWith(tmp.firstChild);
    }
}

function kpPhotoPick(){
    var inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/*';
    inp.onchange = function(e){
        var f = e.target.files && e.target.files[0]; if (!f) return;
        var rd = new FileReader();
        rd.onload = function(){
            window._kpState.photoDataUrl = rd.result;
            var hero = document.getElementById('kpPhotoHero');
            if (hero){
                hero.classList.add('has-photo');
                hero.innerHTML = '<img src="'+rd.result+'" alt=""><div class="kp-photo-overlay"><div class="kp-photo-overlay-text">Tap за смяна</div></div>';
            }
        };
        rd.readAsDataURL(f);
    };
    inp.click();
}

function kpVoiceName(){
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR){ showToast('Гласът не се поддържа в този браузър','error'); return; }
    var rec = new SR();
    rec.lang = 'bg-BG'; rec.interimResults = false; rec.maxAlternatives = 1;
    var btn = document.querySelector('.kp-voice-mic');
    if (btn) btn.style.transform = 'scale(1.1)';
    rec.onresult = function(ev){
        var t = (ev.results[0][0].transcript) || '';
        var inp = document.getElementById('wName');
        if (inp){ inp.value = t; inp.focus(); }
    };
    rec.onerror = function(){ showToast('Грешка при гласа','error'); };
    rec.onend = function(){ if (btn) btn.style.transform = ''; };
    try { rec.start(); } catch(_) {}
}

// S88B.KP: voice-fill за артикулен номер (BIBLE 7.2.8 v1.3 — voice fallback за code)
function kpVoiceCode(){
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR){ showToast('Гласът не се поддържа в този браузър','error'); return; }
    var rec = new SR();
    rec.lang = 'bg-BG'; rec.interimResults = false; rec.maxAlternatives = 1;
    rec.onresult = function(ev){
        var t = (ev.results[0][0].transcript) || '';
        // Strip non-alphanumeric (artikulen kod = digits/letters); keep dash
        var clean = t.replace(/[^A-Za-z0-9\-]/g, '').trim();
        var inp = document.getElementById('wCode');
        if (inp){ inp.value = clean || t; inp.focus(); }
    };
    rec.onerror = function(){ showToast('Грешка при гласа','error'); };
    try { rec.start(); } catch(_) {}
}

function kpCollectIntoWizData(){
    var st = window._kpState; if (!st) return null;
    if (!window.S) window.S = {};
    if (!S.wizData) S.wizData = {};
    S.wizEditId = null; S.wizSavedId = null;
    S.wizType = st.type;
    var nameEl = document.getElementById('wName');
    S.wizData.name = nameEl ? (nameEl.value||'').trim() : '';
    S.wizData.code = (document.getElementById('wCode')||{}).value || '';
    S.wizData.barcode = ((document.getElementById('wBarcode')||{}).value || '').trim();
    S.wizData.retail_price = parseFloat(st.retail)||0;
    S.wizData.cost_price = parseFloat(st.cost)||0;
    S.wizData.wholesale_price = parseFloat(st.wholesale)||0;
    S.wizData.supplier_id = st.supplier_id || null;
    S.wizData.category_id = st.category_id || null;
    S.wizData.subcategory_id = st.subcategory_id || null;
    S.wizData.unit = 'бр';
    S.wizData.min_quantity = parseInt((document.getElementById('wMinQty')||{}).value)||0;
    S.wizData.composition = st.composition || '';
    S.wizData.origin_country = st.origin || '';
    S.wizData.is_domestic = 0;
    S.wizData.description = '';
    S.wizData._photoDataUrl = st.photoDataUrl || null;
    if (st.type === 'variant'){
        var allSizes = [];
        st.colors.forEach(function(c){
            (st.sizesByColor[c]||[]).forEach(function(s){
                if (s && allSizes.indexOf(s) === -1) allSizes.push(s);
            });
        });
        S.wizData.axes = [
            { name: 'Размер', values: allSizes },
            { name: 'Цвят',   values: st.colors.slice() }
        ];
        S.wizData._matrix = {};
        st.colors.forEach(function(c, ci){
            var sizes = st.sizesByColor[c]||[];
            sizes.forEach(function(sz, localSi){
                var localKey = 'mx_'+localSi+'_'+ci;
                var qty = (st.matrix[localKey] && st.matrix[localKey].qty) || 0;
                if (qty > 0){
                    var globalSi = allSizes.indexOf(sz);
                    if (globalSi >= 0) S.wizData._matrix['mx_'+globalSi+'_'+ci] = { qty: qty };
                }
            });
        });
    } else {
        S.wizData.axes = [];
        S.wizData._matrix = {};
        S.wizData.quantity = parseInt((document.getElementById('wSingleQty')||{}).value)||0;
    }
    S.wizData._likePrevSource = { id: st.srcId, sourceQty: st.srcQty, copyQty: !!st.copyQty, imageUrl: st.photoUrl||'' };
    if (st.photoUrl && !st.photoDataUrl){
        return fetch(st.photoUrl).then(function(r){return r.blob()}).then(function(b){
            return new Promise(function(res){
                var rd = new FileReader();
                rd.onload = function(){ S.wizData._photoDataUrl = rd.result; res(); };
                rd.readAsDataURL(b);
            });
        }).catch(function(){});
    }
    return null;
}

async function kpSave(){
    var st = window._kpState; if (!st) return;
    var nameEl = document.getElementById('wName');
    if (!nameEl || !nameEl.value.trim()){ showToast('Въведи име','error'); if (nameEl) nameEl.focus(); return; }
    var p = kpCollectIntoWizData();
    if (p && p.then) await p;
    if (typeof wizSave === 'function'){
        await wizSave();
        if (S.wizSavedId){
            setTimeout(function(){ kpClose(); }, 250);
        }
    }
}

async function kpSaveThenAIStudio(){
    await kpSave();
    if (S.wizSavedId){
        if (typeof openAIStudio === 'function') openAIStudio(S.wizSavedId);
        else if (typeof openStudioModal === 'function') openStudioModal(S.wizSavedId);
    }
}

async function kpPrintNow(){
    var st = window._kpState; if (!st) return;
    var nameEl = document.getElementById('wName');
    if (!nameEl || !nameEl.value.trim()){ showToast('Въведи име първо','error'); if (nameEl) nameEl.focus(); return; }
    if (!window.S) window.S = {}; if (!S.wizData) S.wizData = {};
    var combos = [];
    if (st.type === 'variant'){
        st.colors.forEach(function(color, ci){
            (st.sizesByColor[color]||[]).forEach(function(sz, si){
                var k = 'mx_'+si+'_'+ci, q = (st.matrix[k]&&st.matrix[k].qty)||0;
                if (q > 0) combos.push({size:sz, color:color, qty:q, printQty:q});
            });
        });
    } else {
        var q = parseInt((document.getElementById('wSingleQty')||{}).value)||1;
        combos.push({size:null, color:null, qty:q, printQty:q});
    }
    if (!combos.length){ showToast('Няма бройки за печат','error'); return; }
    S.wizData._printCombos = combos;
    S.wizData.name = nameEl.value.trim();
    S.wizData.code = (document.getElementById('wCode')||{}).value || '';
    S.wizData.retail_price = parseFloat(st.retail)||0;
    if (typeof wizPrintLabels === 'function') wizPrintLabels(-1);
    else showToast('Печат не е наличен','error');
}

function renderLikePrevPageS88(d){
    var prev = document.getElementById('kpModal');
    if (prev) prev.remove();

    var hasVariants = !!(d.has_variants && d.variant_axes && d.variant_axes.colors && d.variant_axes.colors.length);
    var st = window._kpState = {
        srcId: d.id,
        type: hasVariants ? 'variant' : 'single',
        photoUrl: d.image_url || '',
        photoDataUrl: null,
        srcQty: parseInt(d.source_qty)||0,
        copyQty: false,
        retail: parseFloat(d.retail_price)||0,
        cost: parseFloat(d.cost_price)||0,
        wholesale: parseFloat(d.wholesale_price)||0,
        supplier_id: d.supplier_id || null,
        supplier_name: d.supplier_name || '',
        category_id: d.category_id || null,
        category_name: d.category_name || '',
        subcategory_id: d.subcategory_id || null,
        subcategory_name: d.subcategory_name || '',
        composition: d.composition || '',
        origin: d.origin_country || '',
        min_quantity: parseInt(d.min_quantity)||0,
        colors: hasVariants ? (d.variant_axes.colors||[]).slice() : [],
        sizesByColor: hasVariants ? Object.assign({}, d.variant_axes.by_color||{}) : {},
        matrix: {}
    };

    var subParts = [];
    if (d.name) subParts.push(d.name);
    if (st.supplier_name) subParts.push(st.supplier_name);
    var catLine = st.category_name || '';
    if (catLine && st.subcategory_name) catLine += ' / ' + st.subcategory_name;
    if (catLine) subParts.push(catLine);
    var subTitle = subParts.join(' · ');

    // S88B.KP: BIBLE 7.2.8.5 v1.3 — снимка copy by default; tap за смяна (no opt-in checkbox).
    // На save: kpCollectIntoWizData fetch-ва source image_url ако user не е tap-нал смяна.
    var photoHero = d.image_url
        ? ('<div class="kp-photo-hero kp-cardin has-photo" id="kpPhotoHero" onclick="kpPhotoPick()">'
            + '<img src="'+esc(d.image_url)+'" alt="">'
            + '<div class="kp-photo-overlay"><div class="kp-photo-overlay-text">Tap за смяна</div></div>'
            + '</div>')
        : ('<div class="kp-photo-hero kp-cardin" id="kpPhotoHero" onclick="kpPhotoPick()">'
            + '<div class="kp-ph-icon"><svg viewBox="0 0 24 24"><path d="M9 3l-1.5 2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3.5L15 3H9zm3 5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm0 2a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg></div>'
            + '<div class="kp-ph-label">Tap за снимка</div>'
            + '<div class="kp-ph-hint">камера · галерия · може да се пропусне</div>'
            + '</div>');

    var voiceSupported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
    var voiceBtn = '<button class="kp-voice-mic" type="button" '+(voiceSupported?'onclick="kpVoiceName()"':'disabled')+' aria-label="voice">'
        + '<svg viewBox="0 0 24 24"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/></svg>'
        + '</button>';

    // S88B.KP: per BIBLE 7.2.8 v1.3 — code input стартира ПРАЗЕН; visible с placeholder + voice fallback
    var voiceCodeBtn = '<button class="kp-voice-mic" type="button" '+(voiceSupported?'onclick="kpVoiceCode()"':'disabled')+' aria-label="voice code">'
        + '<svg viewBox="0 0 24 24"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2z"/></svg>'
        + '</button>';
    var nameCard = '<div class="glass kp-name-card kp-cardin">'
        + '<span class="shine"></span><span class="shine shine-bottom"></span>'
        + '<span class="glow"></span><span class="glow glow-bottom"></span>'
        + '<div class="kp-label-row">'
            + '<div class="kp-label">Име на артикула <span class="kp-req">*</span></div>'
            + voiceBtn
        + '</div>'
        + '<input type="text" class="kp-input" id="wName" placeholder="Напиши име или диктувай…" autocomplete="off" autofocus value="'+esc((d.name||'')+' (копие)')+'">'
        + '<div class="kp-label-row" style="margin-top:14px">'
            + '<div class="kp-label">Артикулен номер</div>'
            + voiceCodeBtn
        + '</div>'
        + '<input type="text" class="kp-input" id="wCode" placeholder="Скенирай barcode или въведи" autocomplete="off" inputmode="text" value="">'
        + '<input type="hidden" id="wBarcode" value="">'
        + '<input type="hidden" id="wSubcat" value="'+esc(st.subcategory_id||'')+'">'
        + '<input type="hidden" id="wSingleQty" value="0">'
        + '<input type="hidden" id="wMinQty" value="0">'
        + '</div>';

    // S88B.KP: BIBLE 7.2.8 v1.3 — 10 копирани полета (8 editable + 2 computed read-only)
    var fields = [0,1,2,3,4,5,6,7,8,9].map(function(i){
        return '<div class="kp-cf-row" id="kpCf'+i+'">' + kpFieldHtml(kpFieldDef(i), i, st) + '</div>';
    }).join('');

    var copiedCard = '<div class="glass q-gain sm kp-copied-card kp-cardin">'
        + '<span class="shine"></span><span class="shine shine-bottom"></span>'
        + '<span class="glow"></span><span class="glow glow-bottom"></span>'
        + '<div class="kp-copied-head" onclick="kpToggleCopied(this)">'
            + '<div class="kp-copied-ico"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>'
            + '<div class="kp-copied-text">'
                + '<div class="kp-copied-t1">Копирано от последния</div>'
                + '<div class="kp-copied-t2">10 полета · tap за преглед / редакция</div>'
            + '</div>'
            + '<div class="kp-chev"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>'
        + '</div>'
        + '<div class="kp-copied-fields" id="kpCfBody">' + fields + '</div>'
        + '</div>';

    var section = hasVariants ? kpVariantSectionHtml(st) : kpSingleSectionHtml(st);

    var bottomBar = '<div class="kp-bottom-bar">'
        + '<button type="button" class="kp-btn-print" onclick="kpPrintNow()">'
            + '<svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>'
            + 'ПЕЧАТ'
        + '</button>'
        + '<button type="button" class="kp-btn-ai" onclick="kpSaveThenAIStudio()">'
            + '<svg class="kp-ai-spark" viewBox="0 0 24 24"><path d="M12 2l1.4 4.6L18 8l-4.6 1.4L12 14l-1.4-4.6L6 8l4.6-1.4L12 2zm6 12l.7 2.3L21 17l-2.3.7L18 20l-.7-2.3L15 17l2.3-.7L18 14zM5 13l.5 1.5L7 15l-1.5.5L5 17l-.5-1.5L3 15l1.5-.5L5 13z"/></svg>'
            + 'AI STUDIO'
        + '</button>'
        + '</div>';

    var ov = document.createElement('div');
    ov.id = 'kpModal';
    ov.innerHTML = '<div class="kp-app">'
        + '<header class="kp-header">'
            + '<button class="kp-back" type="button" aria-label="Назад" onclick="kpClose()"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>'
            + '<div class="kp-title-area">'
                + '<div class="kp-title">Като предния</div>'
                + '<div class="kp-subtitle">'+esc(subTitle)+'</div>'
            + '</div>'
            + '<button class="kp-save" type="button" onclick="kpSave()">'
                + '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>'
                + 'ЗАПАЗИ'
            + '</button>'
        + '</header>'
        + photoHero
        + nameCard
        + copiedCard
        + section
        + bottomBar
        + '</div>';
    document.body.appendChild(ov);
    document.body.style.overflow = 'hidden';
    showToast('Като предния — провери преди save','success');
}
// ───────── /S88.BUG#3 + /S88.KP ─────────

// ───────── S88.BUG#7: history timeline + per-change revert ─────────
async function openProductHistoryS88(productId){
    var d = await api('products.php?ajax=product_history&id='+productId);
    if (!d){ showToast('Грешка при четене на история','error'); return; }
    var rows = d.rows || [];
    var existing = document.getElementById('s88HistModal');
    if (existing) existing.remove();
    var ov = document.createElement('div');
    ov.id = 's88HistModal';
    ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;padding:14px';
    var labels = {
        name:'име', code:'код', barcode:'баркод',
        retail_price:'цена', cost_price:'себестойност', wholesale_price:'едр.цена',
        category_id:'категория', supplier_id:'доставчик',
        unit:'мерна', min_quantity:'мин.кол.', location:'място',
        description:'описание', size:'размер', color:'цвят',
        vat_rate:'ДДС', origin_country:'страна', composition:'материя',
        is_domestic:'местно'
    };
    var listH = rows.map(function(r, idx){
        var actBg = r.action === 'create' ? 'rgba(34,197,94,0.12)' : (r.action === 'delete' ? 'rgba(239,68,68,0.12)' : 'rgba(99,102,241,0.10)');
        var actCol = r.action === 'create' ? '#86efac' : (r.action === 'delete' ? '#fca5a5' : '#a5b4fc');
        var actIcon = r.action === 'create' ? '➕' : (r.action === 'delete' ? '🗑' : '✏️');
        var diffH = '';
        if (r.action === 'update' && r.old_values && r.new_values){
            try{
                var oldV = JSON.parse(r.old_values), newV = JSON.parse(r.new_values);
                var changes = [];
                for (var k in newV){
                    if (oldV[k] === undefined) continue;
                    var a = oldV[k], b = newV[k];
                    if ((a==null?'':String(a)) !== (b==null?'':String(b))){
                        changes.push('<div style="font-size:10.5px;color:#cbd5e1;margin-top:3px"><span style="color:#a5b4fc">'+(labels[k]||k)+':</span> <span style="color:#fca5a5;text-decoration:line-through">'+esc(String(a==null?'∅':a))+'</span> → <span style="color:#86efac">'+esc(String(b==null?'∅':b))+'</span></div>');
                    }
                }
                diffH = changes.slice(0,8).join('');
                if (changes.length > 8) diffH += '<div style="font-size:9px;color:#94a3b8;margin-top:3px">…и още '+(changes.length-8)+' промени</div>';
            }catch(_){}
        }
        var canRevert = (r.action === 'update' && r.old_values);
        var revertBtn = canRevert
            ? '<button onclick="revertChangeS88('+r.id+','+productId+')" style="margin-top:6px;padding:6px 10px;border-radius:8px;background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(217,119,6,0.06));border:1px solid rgba(245,158,11,0.45);color:#fcd34d;font-size:11px;font-weight:700;cursor:pointer">↶ Върни и презапиши</button>'
            : '';
        var sourceTag = r.source_detail && r.source_detail.indexOf('revert_of:') === 0
            ? '<span style="margin-left:6px;padding:1px 6px;border-radius:6px;background:rgba(245,158,11,0.15);color:#fcd34d;font-size:9px;font-weight:700">REVERT</span>'
            : '';
        return '<div style="padding:10px;border:1px solid rgba(99,102,241,0.18);border-radius:10px;margin:6px 0;background:rgba(255,255,255,0.02)">'
             + '<div style="display:flex;align-items:center;justify-content:space-between;font-size:11px">'
             +   '<span style="padding:2px 8px;border-radius:6px;background:'+actBg+';color:'+actCol+';font-weight:700">'+actIcon+' '+r.action+'</span>'
             +   sourceTag
             +   '<span style="color:#94a3b8;font-size:10px">'+esc(r.created_at||'')+(r.user_name?' · '+esc(r.user_name):'')+'</span>'
             + '</div>'
             + diffH
             + revertBtn
             + '</div>';
    }).join('');
    if (!rows.length) listH = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px">Няма записани промени.</div>';
    ov.innerHTML =
        '<div style="background:#0f1224;border:1px solid rgba(99,102,241,0.4);border-radius:16px;padding:14px;max-width:420px;width:100%;max-height:80vh;display:flex;flex-direction:column">'
      + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px"><div style="font-size:14px;font-weight:700;color:#fff">📜 История · Артикул #'+productId+'</div><button onclick="document.getElementById(\'s88HistModal\').remove()" style="background:none;border:none;color:#cbd5e1;font-size:18px;cursor:pointer">✕</button></div>'
      + '<div style="overflow-y:auto;flex:1;padding-right:4px">' + listH + '</div>'
      + '</div>';
    document.body.appendChild(ov);
    ov.onclick = function(e){ if (e.target === ov) ov.remove(); };
}

async function revertChangeS88(historyId, productId){
    if (!confirm('Върни тази промяна? (Текущото състояние ще се запази в история, така че можеш да го върнеш отново.)')) return;
    var fd = new FormData(); fd.append('history_id', historyId);
    var r = await fetch('products.php?ajax=revert_change', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function(x){return x.json()}).catch(function(){return null});
    if (!r || r.error){ showToast('Грешка: '+(r&&r.error?r.error:'unknown'),'error'); return; }
    showToast('Върнато ✓','success');
    document.getElementById('s88HistModal')?.remove();
    if (typeof openProductDetail === 'function') openProductDetail(productId);
}
// ───────── /S88.BUG#7 ─────────

async function wizAddInline(type){
    if(type==='supplier'){
        const n=document.getElementById('inlSupName')?.value.trim();
        if(!n)return;
        // S88.BUG#4: fuzzy match before creating new supplier
        fuzzyConfirmAdd('доставчик', n, CFG.suppliers||[],
            function(existing){
                S.wizData.supplier_id=existing.id;
                const ss=document.getElementById('wSup'); if(ss)ss.value=existing.id;
                const sd=document.getElementById('wSupDD'); if(sd){sd.value=existing.name; sd._selectedId=existing.id;}
                document.getElementById('inlSup')?.classList.remove('open');
                showToast('Използван: '+existing.name,'success');
                wizMarkDone&&wizMarkDone('supplier'); wizHighlightNext&&wizHighlightNext();
            },
            async function(){
                const d=await api('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(n)});
                if(d?.id){if(d.duplicate){showToast('Доставчик "'+d.name+'" вече съществува','error');S.wizData.supplier_id=d.id;const ss=document.getElementById('wSup');if(ss)ss.value=d.id;document.getElementById('inlSup')?.classList.remove('open')}else{CFG.suppliers.push({id:d.id,name:d.name});S.wizData.supplier_id=d.id;showToast('Добавен ✓','success');renderWizard();if(!S._wizMicVoiceAdd){openSupCatModal(d.id,d.name)}S._wizMicVoiceAdd=false}}
            }
        );
    }else{
        const n=document.getElementById('inlCatName')?.value.trim();
        if(!n)return;
        // S88.BUG#4: fuzzy match before creating new category (only top-level, parent_id null)
        const topCats = (CFG.categories||[]).filter(c => !c.parent_id);
        fuzzyConfirmAdd('категория', n, topCats,
            function(existing){
                S.wizData.category_id=existing.id;
                const cs=document.getElementById('wCat'); if(cs)cs.value=existing.id;
                const cd=document.getElementById('wCatDD'); if(cd){cd.value=existing.name; cd._selectedId=existing.id;}
                document.getElementById('inlCat')?.classList.remove('open');
                showToast('Използвана: '+existing.name,'success');
                if(typeof wizLoadSubcats==='function') wizLoadSubcats(existing.id);
                wizMarkDone&&wizMarkDone('category'); wizHighlightNext&&wizHighlightNext();
            },
            async function(){
                const d=await api('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(n)});
                if(d?.id){if(d.duplicate){showToast('Категория "'+d.name+'" вече съществува','error');S.wizData.category_id=d.id;const cs=document.getElementById('wCat');if(cs)cs.value=d.id;document.getElementById('inlCat')?.classList.remove('open')}else{CFG.categories.push({id:d.id,name:d.name});S.wizData.category_id=d.id;showToast('Добавена ✓','success');renderWizard()}}
            }
        );
    }
}

// Edit existing product
async function editProduct(id){
    closeDrawer('detail');
    const d=await api('products.php?ajax=product_detail&id='+id);
    if(!d||d.error)return;
    const p=d.product;
    // S73: reconstruct axes from variations
    let reAxes=[];
    let reType='single';
    const vars=(d.variations||[]);
    if(vars.length>0){
        reType='variant';
        const sizes=[],colors=[];
        vars.forEach(v=>{
            if(v.size&&!sizes.includes(v.size))sizes.push(v.size);
            if(v.color&&!colors.includes(v.color))colors.push(v.color);
        });
        if(sizes.length)reAxes.push({name:'Размер',values:sizes});
        if(colors.length)reAxes.push({name:'Цвят',values:colors});
    }
    S.wizEditId=id;S.wizType=reType;S.wizStep=3;
    S.wizData={name:p.name,code:p.code,
        retail_price:parseFloat(p.retail_price)||0,
        wholesale_price:parseFloat(p.wholesale_price)||0,
        cost_price:parseFloat(p.cost_price)||0,
        barcode:p.barcode||'',
        supplier_id:p.supplier_id,category_id:p.category_id,
        subcategory_id:p.subcategory_id||null,
        description:p.description||'',
        unit:p.unit||'бр',
        min_quantity:parseInt(p.min_quantity)||0,
        origin_country:p.origin_country||'',
        composition:p.composition||'',
        is_domestic:parseInt(p.is_domestic)||0,
        axes:reAxes,
        _editVariations:vars};
    S.wizVoiceMode=false;
    document.getElementById('wizTitle').textContent='Редактирай';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}



// S79.FIX Bug #1: Hamburger menu functions
function openMenuDrawer(){openDrawer('menu')}
function showStoreSwitcher(){
    if(typeof CFG!=='undefined' && document.getElementById('storeSelect')){
        document.getElementById('storeSelect').focus();
        document.getElementById('storeSelect').click();
    }else{
        showToast('Само 1 магазин в акаунта','');
    }
}

// S79FIX_BUG1_HAMBURGER_APPLIED

// S79.FIX Bug #2: Home search autocomplete
let _hSearchTO = null;
function onLiveSearchHome(q) {
    q = q.trim();
    const dd = document.getElementById('hSearchDD');
    if (!dd) return;
    if (q.length < 1) {
        clearTimeout(_hSearchTO);
        dd.style.display = 'none';
        return;
    }
    clearTimeout(_hSearchTO);
    _hSearchTO = setTimeout(async () => {
        const d = await api('products.php?ajax=search&mix=1&q=' + encodeURIComponent(q) + '&store_id=' + CFG.storeId);
        if (!d) { dd.style.display = 'none'; return; }
        const products = d.products || [];
        const categories = d.categories || [];
        const totalProducts = d.total_products || products.length;
        if (!products.length && !categories.length) {
            dd.innerHTML = '<div style="padding:14px;text-align:center;font-size:12px;color:var(--text-secondary)">Нищо за "' + esc(q) + '"</div>';
            dd.style.display = 'block';
            return;
        }
        let html = '';
        // Header with close
        html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-bottom:1px solid rgba(99,102,241,.12);position:sticky;top:0;background:rgba(8,8,24,0.98);z-index:1">';
        html += '<span style="font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase">' + (products.length + categories.length) + ' резултата</span>';
        html += '<div onclick="clearHSearch()" style="width:24px;height:24px;border-radius:8px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;cursor:pointer"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg></div>';
        html += '</div>';
        // Categories first (more important)
        if (categories.length) {
            html += '<div style="padding:6px 14px 2px;font-size:9px;font-weight:800;color:var(--indigo-300);text-transform:uppercase;letter-spacing:0.05em">Категории</div>';
            categories.forEach(c => {
                html += '<div style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid rgba(99,102,241,.06);cursor:pointer" onclick="pickHSearchCat(' + c.id + ')">';
                html += '<div style="width:32px;height:32px;border-radius:8px;background:rgba(20,184,166,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5eead4" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>';
                html += '<div style="flex:1;min-width:0"><div style="font-size:13px;font-weight:600">' + esc(c.name) + '</div><div style="font-size:9px;color:var(--text-secondary)">' + (c.product_count || 0) + ' артикула</div></div>';
                html += '<span style="color:var(--text-secondary);font-size:14px">›</span></div>';
            });
        }
        // Products
        if (products.length) {
            html += '<div style="padding:8px 14px 2px;font-size:9px;font-weight:800;color:var(--indigo-300);text-transform:uppercase;letter-spacing:0.05em">Артикули</div>';
            products.forEach(p => {
                const stock = parseInt(p.total_stock || 0);
                const sc = stock > 0 ? 'var(--success)' : 'var(--danger)';
                const thumb = p.image_url ? '<img src="' + p.image_url + '" style="width:100%;height:100%;object-fit:cover;border-radius:8px">' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,.3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
                html += '<div style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid rgba(99,102,241,.06);cursor:pointer" onclick="pickHSearchProd(' + p.id + ')">';
                html += '<div style="width:34px;height:34px;border-radius:8px;background:rgba(99,102,241,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">' + thumb + '</div>';
                html += '<div style="flex:1;min-width:0"><div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(p.name) + '</div><div style="font-size:9px;color:var(--text-secondary)">' + esc(p.code || '') + (p.supplier_name ? ' · ' + esc(p.supplier_name) : '') + '</div></div>';
                html += '<div style="text-align:right;flex-shrink:0"><div style="font-size:11px;font-weight:700;color:var(--indigo-300)">' + fmtPrice(p.retail_price) + '</div><div style="font-size:9px;color:' + sc + '">' + stock + ' бр</div></div></div>';
            });
        }
        // "View all" footer if more products exist
        if (totalProducts > products.length) {
            html += '<div style="padding:11px 14px;text-align:center;background:rgba(99,102,241,.06);cursor:pointer;border-top:1px solid rgba(99,102,241,.12)" onclick="viewAllHSearchResults(\'' + q.replace(/'/g, "\\'") + '\')">';
            html += '<span style="font-size:12px;font-weight:700;color:#a5b4fc">Виж всички ' + totalProducts + ' артикула →</span></div>';
        }
        dd.innerHTML = html;
        dd.style.display = 'block';
    }, 250);
}

function clearHSearch() {
    const inp = document.getElementById('hSearchInp');
    if (inp) inp.value = '';
    const dd = document.getElementById('hSearchDD');
    if (dd) dd.style.display = 'none';
}

function pickHSearchProd(id) {
    clearHSearch();
    openProductDetail(id);
}

function pickHSearchCat(id) {
    clearHSearch();
    goScreenWithHistory('products', {cat: id});
}

function viewAllHSearchResults(q) {
    clearHSearch();
    goScreenWithHistory('products');
    setTimeout(() => doSearch(q), 100);
}

// S79FIX_BUG2_AUTOCOMPLETE_APPLIED
// S79FIX_CLEANUP_HEADER_APPLIED
// ─── INIT ───
document.addEventListener('DOMContentLoaded',()=>{
    history.replaceState({scr:'home'}, '', '#home');
    goScreen('home');
});

// S43: Back button support for drawers and screens
window.addEventListener('popstate', function(e) {
    // Close topmost overlay first, don't navigate
    const mxOv = document.getElementById('mxOverlay');
    if (mxOv && mxOv.classList.contains('open')) {
        if (mxOv.classList.contains('mx-focused')) {
            // Focus mode: exit focus, keep matrix open; re-push state so next back still works
            const inp = mxOv.querySelector('input:focus');
            if (inp) inp.blur();
            try{history.pushState({modal:'matrix'},'','#matrix')}catch(_){}
            return;
        }
        mxCancel();
        return;
    }
    const wizModal = document.getElementById('wizModal');
    if (wizModal && wizModal.classList.contains('open')) { closeWizard(); return; }
    const recOv = document.getElementById('recOv');
    if (recOv && recOv.classList.contains('open')) { closeVoice(); return; }
    const drawers = ['detail','ai','filter','studio','labels','csv','qf','menu'];
    for (const n of drawers) {
        const dr = document.getElementById(n+'Dr');
        if (dr && dr.classList.contains('open')) {
            if (n === 'detail' && S.detailStack.length > 0) {
                const parentId = S.detailStack.pop();
                openProductDetail(parentId);
            } else {
                closeDrawer(n);
            }
            return;
        }
    }
    // No overlay open — navigate
    const state = e.state;
    if (!state) { goScreen('home'); return; }
    if (state.scr) { goScreen(state.scr, state); return; }
});

// Override rec-ov backdrop click for wizard mode
document.getElementById('recOv').addEventListener('click',function(e){
    if(e.target===this){
        if(S.aiWizMode)closeAIWizardOverlay();
        else closeVoice();
    }
});

// ═══ S48: Composition + Country suggest (event delegation, no inline handlers) ═══
(function(){
    function createDropdown(inputId, listId, items) {
        var inp = document.getElementById(inputId);
        if (!inp) return;
        var val = inp.value.toLowerCase();
        var existing = document.getElementById(listId);
        if (existing) existing.remove();
        if (!val || val.length < 1) return;
        var matches = items.filter(function(it){ return it.toLowerCase().indexOf(val) !== -1; });
        if (!matches.length) return;
        var dd = document.createElement('div');
        dd.id = listId;
        dd.style.cssText = 'position:absolute;left:0;right:0;top:100%;background:#1e1e2e;border:1px solid var(--border-subtle);border-radius:8px;max-height:180px;overflow-y:auto;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.5)';
        matches.slice(0, 8).forEach(function(m){
            var opt = document.createElement('div');
            opt.textContent = m;
            opt.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;color:#e2e8f0;border-bottom:1px solid rgba(255,255,255,0.05)';
            opt.onmousedown = function(e){
                e.preventDefault();
                inp.value = m;
                _justPicked=true;inp.dispatchEvent(new Event('input', {bubbles:true}));
                if (inputId === 'wOrigin') S.wizData.origin_country = m;
                if (inputId === 'wComposition') S.wizData.composition = m;
                dd.remove();
            };
            opt.onmouseenter = function(){ this.style.background = 'rgba(99,102,241,0.2)'; };
            opt.onmouseleave = function(){ this.style.background = 'transparent'; };
            dd.appendChild(opt);
        });
        inp.parentElement.style.position = 'relative';
        inp.parentElement.appendChild(dd);
    }
    function closeLists(except){
        ['wOriginList','wCompositionList'].forEach(function(id){
            if(id!==except){var e=document.getElementById(id);if(e)e.remove();}
        });
    }
    var _justPicked=false;
    document.addEventListener('input', function(e){
        if(_justPicked){_justPicked=false;return;}
        if (e.target.id === 'wOrigin') {
            closeLists('wOriginList');
            createDropdown('wOrigin', 'wOriginList', window._bizCountries || []);
        }
        if (e.target.id === 'wComposition') {
            closeLists('wCompositionList');
            createDropdown('wComposition', 'wCompositionList', window._bizCompositions || []);
        }
    });
    document.addEventListener('click', function(e){
        if (e.target.id !== 'wOrigin' && e.target.id !== 'wComposition') closeLists();
    });
})();
// ═══ S48: Focus field → scroll to center ═══
(function(){
    var body=null;
    document.addEventListener("focusin",function(e){
        var t=e.target;
        if(t.tagName!=="INPUT"&&t.tagName!=="TEXTAREA"&&t.tagName!=="SELECT")return;
        body=document.getElementById("wizBody");
        if(!body||!body.contains(t))return;
        setTimeout(function(){
            var bRect=body.getBoundingClientRect();
            var tRect=t.getBoundingClientRect();
            var middle=tRect.top-bRect.top+body.scrollTop-(bRect.height*0.4);
            var diff=Math.abs(tRect.top-bRect.top-bRect.height*0.4);
            if(diff<80)return;
            body.scrollTo({top:Math.max(0,middle),behavior:"smooth"});
        },400);
    });
    if(window.visualViewport){
        window.visualViewport.addEventListener("resize",function(){
            if(!body||!document.activeElement)return;
            var t=document.activeElement;
            if(!body.contains(t))return;
            setTimeout(function(){
                var bRect=body.getBoundingClientRect();
                var tRect=t.getBoundingClientRect();
                var middle=tRect.top-bRect.top+body.scrollTop-(bRect.height*0.4);
                var diff=Math.abs(tRect.top-bRect.top-bRect.height*0.4);
                if(diff<80)return;
                body.scrollTo({top:Math.max(0,middle),behavior:"smooth"});
            },150);
        });
    }
})();
// ═══ S48: Label print functions ═══
function wizLblAdj(idx,delta){
    var inp=document.getElementById('lblQty'+idx);
    if(!inp)return;
    var v=Math.max(0,parseInt(inp.value||0)+delta);
    inp.value=v;
    wizLblRecalc();
}
function wizLblRecalc(){
    var total=0;
    document.querySelectorAll('[id^="lblQty"]').forEach(function(inp){total+=parseInt(inp.value)||0;});
    var el=document.getElementById('lblTotal');
    if(el)el.textContent=total;
}
function wizLabelsX2(){
    document.querySelectorAll('[id^="lblQty"]').forEach(function(inp){
        inp.value=Math.max(1,(parseInt(inp.value)||1)*2);
    });
    wizLblRecalc();
}
function wizPrintLabels(comboIdx){
    // S82.CAPACITOR — BLE print on mobile APK
    if (window.CapPrinter && window.CapPrinter.isAvailable()) {
        return wizPrintLabelsMobile(comboIdx);
    }
    var combos=S.wizData._printCombos||[];
    var pm=S.wizData._printMode||'eur';
    var sup=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
    var supName=sup?sup.name:'';
    var supCity=sup?sup.city||'':'';
    var barcode=S.wizData.barcode||('200'+String(S.wizSavedId||0).padStart(9,'0'));
    if(barcode.length===12){var sum=0;for(var bi=0;bi<12;bi++)sum+=parseInt(barcode[bi])*(bi%2===0?1:3);barcode+=String((10-sum%10)%10);}
    var name=S.wizData.name||'';
    var code=S.wizData.code||'';
    var price=parseFloat(S.wizData.retail_price)||0;
    var priceBGN=Math.round(price*195583)/100000;
    priceBGN=priceBGN.toFixed(2);
    var composition=S.wizData.composition||'';
    var origin=S.wizData.origin_country||'';
    var isDomestic=S.wizData.is_domestic;
    var importLine='';
    if(!isDomestic&&supName){importLine='Внос: '+supName+(supCity?', гр. '+supCity:'');}
    var originLine='';
    if(composition)originLine+=composition;
    if(origin&&!isDomestic)originLine+=(originLine?' · ':'')+origin;

    var items=[];
    if(comboIdx===-1){
        combos.forEach(function(c,i){
            var qty=parseInt(document.getElementById('lblQty'+i)?.value)||0;
            if(qty>0)items.push({combo:c,qty:qty});
        });
    }else{
        var qty=parseInt(document.getElementById('lblQty'+comboIdx)?.value)||1;
        items.push({combo:combos[comboIdx],qty:qty});
    }
    if(!items.length){showToast('Няма етикети за печат','error');return;}

    var labels=[];
    items.forEach(function(item){
        var parts=item.combo.parts||[];
        var sizeVal='',colorVal='';
        parts.forEach(function(p){
            var n=p.axis.toLowerCase();
            if(n.includes('размер')||n.includes('size'))sizeVal=p.value;
            else if(n.includes('цвят')||n.includes('color'))colorVal=p.value;
        });
        for(var q=0;q<item.qty;q++){
            labels.push({size:sizeVal,color:colorVal,barcode:barcode});
        }
    });

    var fmtEur=function(v){return v.toFixed(2).replace('.',',')+' \u20ac';};
    var fmtBgn=function(v){return parseFloat(v).toFixed(2).replace('.',',')+' \u043b\u0432';};

    var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Етикети</title>';
    html+='<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>';
    html+='<style>';
    html+='@page{size:50mm 30mm;margin:0}';
    html+='*{box-sizing:border-box;margin:0;padding:0}';
    html+='body{font-family:Arial,sans-serif;color:#000}';
    html+='.label{width:50mm;height:30mm;padding:1.5mm 2mm;display:flex;flex-direction:column;justify-content:space-between;page-break-after:always;overflow:hidden}';
    html+='.l-top{display:flex;gap:1.5mm;align-items:flex-start}';
    html+='.l-name{font-size:7pt;font-weight:700;line-height:1.15}';
    html+='.l-code{font-size:5pt;color:#555;margin-top:0.3mm}';
    html+='.l-mid{display:flex;align-items:center;gap:1.5mm}';
    html+='.l-sz{background:#000;color:#fff;font-size:10pt;font-weight:700;padding:0.5mm 2.5mm;border-radius:1mm}';
    html+='.l-clr{font-size:7pt;color:#333}';
    html+='.l-dash{border-top:0.3mm dashed #aaa;padding-top:0.8mm}';
    html+='.l-pr{display:flex;align-items:baseline;gap:1.5mm}';
    html+='.l-eur{font-size:12pt;font-weight:700}';
    html+='.l-bgn{font-size:8pt;font-weight:600;color:#444}';
    html+='.l-eur-only{font-size:14pt;font-weight:700;text-align:center}';
    html+='.l-sz-big{font-size:16pt;padding:1mm 4mm}';
    html+='.l-clr-big{font-size:10pt;font-weight:500}';
    html+='.l-bot{border-top:0.3mm dashed #aaa;padding-top:0.5mm;display:flex;justify-content:space-between;font-size:4.5pt;color:#444}';
    html+='@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}';
    html+='</style></head><body>';

    labels.forEach(function(lb,idx){
        html+='<div class="label">';
        html+='<div class="l-top"><svg id="bc'+idx+'" style="width:28mm;height:12mm;flex-shrink:0"></svg>';
        html+='<div><div class="l-name">'+name+(supName?' '+supName:'')+'</div>';
        html+='<div class="l-code">'+(code||'')+' \u00b7 '+lb.barcode+'</div></div></div>';

        if(pm==='noprice'){
            html+='<div style="display:flex;align-items:center;gap:2mm;justify-content:center;flex:1">';
            if(lb.size)html+='<div class="l-sz l-sz-big">'+lb.size+'</div>';
            if(lb.color)html+='<span class="l-clr-big">'+lb.color+'</span>';
            html+='</div>';
        }else{
            html+='<div class="l-mid">';
            if(lb.size)html+='<div class="l-sz">'+lb.size+'</div>';
            if(lb.color)html+='<span class="l-clr">'+lb.color+'</span>';
            html+='</div>';
            html+='<div class="l-dash">';
            if(pm==='dual'){
                html+='<div class="l-pr"><span class="l-eur">'+fmtEur(price)+'</span><span style="color:#aaa;font-size:6pt">|</span><span class="l-bgn">'+fmtBgn(priceBGN)+'</span></div>';
            }else{
                html+='<div class="l-eur-only">'+fmtEur(price)+'</div>';
            }
            html+='</div>';
        }

        html+='<div class="l-bot">';
        if(originLine)html+='<span>'+originLine+'</span>';
        if(importLine)html+='<span>'+importLine+'</span>';
        html+='</div>';
        html+='</div>';
    });

    html+='<script>';
    html+='var opts={format:"EAN13",width:1,height:28,displayValue:false,margin:0};';
    html+='for(var i=0;i<'+labels.length+';i++){try{JsBarcode("#bc"+i,"'+barcode+'",opts)}catch(e){}}';
    html+='setTimeout(function(){window.print()},400);';
    html+='<\/script></body></html>';

    var w=window.open('','_blank','width=400,height=600');
    if(w){w.document.write(html);w.document.close();}
    else{showToast('Позволи pop-up прозорци','error');}
}




// ═══ S82.CAPACITOR — Printer Settings Modal ═══
async function openPrinterSettings(){
    if (typeof CapPrinter === 'undefined' || !CapPrinter.isAvailable()){
        showToast('Наличен само в мобилното приложение', 'info');
        return;
    }

    var paired = CapPrinter.hasPairedPrinter();
    var status = paired ? 'Свързан ✓' : 'Не е свързан';
    var statusColor = paired ? '#34d399' : '#f87171';

    var overlay = document.createElement('div');
    overlay.id = 'prSetOv';
    overlay.innerHTML =
        '<div style="position:fixed;inset:0;background:rgba(3,7,18,.85);backdrop-filter:blur(6px);z-index:999999;display:flex;align-items:center;justify-content:center;padding:20px"' +
        ' onclick="if(event.target===this)document.getElementById(\'prSetOv\').remove()">' +
        '<div style="background:rgba(15,15,40,.95);border:1px solid rgba(99,102,241,.4);border-radius:18px;padding:24px;max-width:360px;width:100%;box-shadow:0 0 60px rgba(99,102,241,.25)">' +
        '<div style="text-align:center;margin-bottom:16px">' +
        '<div style="font-size:42px;margin-bottom:8px">🖨️</div>' +
        '<div style="font-size:18px;font-weight:700;color:#e4e4f0;margin-bottom:4px">Принтер за етикети</div>' +
        '<div style="font-size:14px;color:'+statusColor+';font-weight:600">'+status+'</div>' +
        '</div>' +
        '<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:12px">' +
        '<button id="prSetPair" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:0;padding:14px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer">🔗 '+(paired?'Смени':'Свържи')+' принтер</button>' +
        (paired ? '<button id="prSetTest" style="background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);padding:12px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer">🧪 Тестов печат</button>' : '') +
        (paired ? '<button id="prSetForget" style="background:rgba(248,113,113,.1);color:#f87171;border:1px solid rgba(248,113,113,.3);padding:12px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer">🗑️ Забрави принтера</button>' : '') +
        '</div>' +
        '<button onclick="document.getElementById(\'prSetOv\').remove()" style="background:transparent;color:#8b92b0;border:0;padding:10px;width:100%;font-size:14px;cursor:pointer">Затвори</button>' +
        '</div></div>';
    document.body.appendChild(overlay);

    document.getElementById('prSetPair').onclick = async function(){
        try {
            showPrintOverlay('Избери принтер от списъка...');
            await CapPrinter.pair();
            hidePrintOverlay();
            showToast('Принтерът е сдвоен ✓', 'success');
            document.getElementById('prSetOv').remove();
        } catch (e) {
            hidePrintOverlay();
            showToast('Грешка: ' + (e.message || e), 'error');
        }
    };

    if (paired){
        document.getElementById('prSetTest').onclick = async function(){
            try {
                showPrintOverlay('Тестов печат...');
                await CapPrinter.test();
                hidePrintOverlay();
                showToast('Тестът успешен ✓', 'success');
            } catch (e) {
                hidePrintOverlay();
                showToast('Грешка: ' + (e.message || e), 'error');
            }
        };
        document.getElementById('prSetForget').onclick = function(){
            if (confirm('Сигурен ли си? Ще трябва пак да сдвоиш принтер.')){
                CapPrinter.forget();
                showToast('Принтерът е забравен', 'info');
                document.getElementById('prSetOv').remove();
            }
        };
    }
}

// ═══ S82.CAPACITOR — Print loading overlay ═══
function showPrintOverlay(text){
    var ov = document.getElementById('printOverlay');
    if(!ov){
        ov = document.createElement('div');
        ov.id = 'printOverlay';
        ov.innerHTML = '<style>@keyframes prSpin{to{transform:rotate(360deg)}}@keyframes prPulse{0%,100%{opacity:.4}50%{opacity:1}}</style>'+
            '<div style="position:fixed;inset:0;background:rgba(3,7,18,.85);backdrop-filter:blur(6px);z-index:999999;display:flex;align-items:center;justify-content:center">'+
            '<div style="background:rgba(15,15,40,.95);border:1px solid rgba(99,102,241,.4);border-radius:18px;padding:28px 36px;min-width:220px;text-align:center;box-shadow:0 0 60px rgba(99,102,241,.25)">'+
            '<div style="width:52px;height:52px;margin:0 auto 16px;border:4px solid rgba(99,102,241,.2);border-top-color:#818cf8;border-radius:50%;animation:prSpin 0.9s linear infinite"></div>'+
            '<div id="printOvText" style="font-size:15px;font-weight:600;color:#e4e4f0;margin-bottom:6px">Печат...</div>'+
            '<div style="font-size:11px;color:#8b92b0;animation:prPulse 1.4s ease-in-out infinite">🖨️ Не изключвай принтера</div>'+
            '</div></div>';
        document.body.appendChild(ov);
    }
    var txt = document.getElementById('printOvText');
    if(txt) txt.textContent = text || 'Печат...';
    ov.style.display = 'block';
}
function hidePrintOverlay(){
    var ov = document.getElementById('printOverlay');
    if(ov) ov.style.display = 'none';
}

// ═══ S82.CAPACITOR — Mobile BLE print ═══
async function wizPrintLabelsMobile(comboIdx){
    var combos = S.wizData._printCombos || [];
    var sup = CFG.suppliers.find(function(s){return s.id == S.wizData.supplier_id});
    var supName = sup ? sup.name : '';
    var barcode = S.wizData.barcode || ('200' + String(S.wizSavedId || 0).padStart(9, '0'));
    if (barcode.length === 12) {
        var sum = 0;
        for (var bi = 0; bi < 12; bi++) sum += parseInt(barcode[bi]) * (bi % 2 === 0 ? 1 : 3);
        barcode += String((10 - sum % 10) % 10);
    }
    var name = S.wizData.name || '';
    var code = S.wizData.code || '';
    var price = parseFloat(S.wizData.retail_price) || 0;

    var items = [];
    if (comboIdx === -1) {
        combos.forEach(function(c, i){
            var qty = parseInt(document.getElementById('lblQty' + i)?.value) || 0;
            if (qty > 0) items.push({combo: c, qty: qty});
        });
    } else {
        var qty = parseInt(document.getElementById('lblQty' + comboIdx)?.value) || 1;
        items.push({combo: combos[comboIdx], qty: qty});
    }
    if (!items.length) { showToast('Няма етикети за печат', 'error'); return; }

    // If no printer paired yet — prompt pair
    if (!CapPrinter.hasPairedPrinter()) {
        showToast('Първи път: избери принтера от списъка', 'info');
        try {
            await CapPrinter.pair();
            showToast('Принтерът е сдвоен', 'success');
        } catch (e) {
            showToast('Неуспешно сдвояване: ' + (e.message || e), 'error');
            return;
        }
    }

    var storeInfo = {
        name: (typeof CFG !== 'undefined' && CFG.storeName) ? CFG.storeName : '',
        currency: 'EUR'
    };

    var totalCopies = 0;
    items.forEach(function(it){ totalCopies += it.qty; });
    showPrintOverlay('Печат 1 от ' + items.length + '...');
    var _done = 0;

    try {
        for (var i = 0; i < items.length; i++) {
            showPrintOverlay('Печат ' + (i+1) + ' от ' + items.length + '...');
            var it = items[i];
            var parts = it.combo.parts || [];
            var sizeVal = '', colorVal = '';
            parts.forEach(function(p){
                var n = p.axis.toLowerCase();
                if (n.includes('размер') || n.includes('size')) sizeVal = p.value;
                else if (n.includes('цвят') || n.includes('color')) colorVal = p.value;
            });
            var labelName = name + (sizeVal ? ' ' + sizeVal : '') + (colorVal ? ' ' + colorVal : '');
            var product = {
                code: code,
                name: labelName,
                retail_price: price,
                barcode: barcode
            };
            await CapPrinter.print(product, storeInfo, it.qty);
            _done += it.qty;
        }
        hidePrintOverlay();
        showToast('Готово: ' + totalCopies + ' етикета', 'success');
    } catch (e) {
        hidePrintOverlay();
        showToast('Грешка: ' + (e.message || e), 'error');
    }
}

function wizLabelsReset(){
    var combos=S.wizData._printCombos||[];
    combos.forEach(function(c,i){
        var inp=document.getElementById('lblQty'+i);
        if(inp)inp.value=c.printQty||1;
    });
    wizLblRecalc();
}
function wizDownloadCSV(){
    var combos=S.wizData._printCombos||[];
    var name=S.wizData.name||'';
    var price=S.wizData.retail_price||'';
    var desc=(S.wizData.description||'').replace(/"/g,'""');
    var pcode=S.wizData.code||'';
    var barcode=S.wizData.barcode||'';
    var composition=S.wizData.composition||'';
    var origin=S.wizData.origin_country||'';
    var sup=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
    var supName=sup?sup.name:'';
    var cat=CFG.categories.find(function(c2){return c2.id==S.wizData.category_id});
    var catName=cat?cat.name:'';
    var esc2=function(v){return (v||'').toString().replace(/"/g,'""');};
    var rows=['"Наименование","Код","Баркод","Размер","Цвят","Цена","Бройка","Категория","Доставчик","Състав","Произход","Описание"'];
    if(!combos.length||(!combos[0].parts||!combos[0].parts.length)){
        rows.push('"'+esc2(name)+'","'+esc2(pcode)+'","'+esc2(barcode)+'","","","'+price+'","1","'+esc2(catName)+'","'+esc2(supName)+'","'+esc2(composition)+'","'+esc2(origin)+'","'+esc2(desc)+'"');
    }else{
        combos.forEach(function(c){
            var sz='',cl='';
            (c.parts||[]).forEach(function(p){
                var n=p.axis.toLowerCase();
                if(n.indexOf('размер')!==-1||n.indexOf('size')!==-1)sz=p.value;
                else if(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1)cl=p.value;
            });
            rows.push('"'+esc2(name)+'","'+esc2(pcode)+'","'+esc2(barcode)+'","'+esc2(sz)+'","'+esc2(cl)+'","'+price+'","'+(c.printQty||1)+'","'+esc2(catName)+'","'+esc2(supName)+'","'+esc2(composition)+'","'+esc2(origin)+'","'+esc2(desc)+'"');
        });
    }
    var csv='\uFEFF'+rows.join('\n');
    var blob=new Blob([csv],{type:'text/csv;charset=utf-8'});
    var a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download=(name||'product').replace(/[^a-zA-Z0-9\u0430-\u044f\u0410-\u042f]/g,'_')+'.csv';
    a.click();
}
// ═══ END S48 suggest ═══// ═══ END S48 suggest ═══
// ═══ S68: PER-FIELD VOICE INPUT ═══
const _fromInventory=new URLSearchParams(window.location.search).get('from')==='inventory';
const _invZoneId=parseInt(new URLSearchParams(window.location.search).get('zone_id'))||0;
const _invSessionId=parseInt(new URLSearchParams(window.location.search).get('session_id'))||0;
if(_fromInventory||new URLSearchParams(window.location.search).get('wizard')==='1'){
    document.addEventListener('DOMContentLoaded',function(){setTimeout(function(){openManualWizard()},300)});
}
function wizReturnToInventory(){
    var pid=S.wizSavedId||0;
    location.href='inventory.php#resume&product_id='+pid+'&zone_id='+_invZoneId+'&session_id='+_invSessionId;
}
var _wizMicRec=null;
function wizMic(field){
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Гласът не се поддържа','error');return}
    if(_wizMicRec){try{_wizMicRec.abort()}catch(e){}_wizMicRec=null}
    // Clear all highlights, set active on current field
    _wizClearHighlights();
    var fieldMap={name:'wName',code:'wCode',retail_price:'wPrice',wholesale_price:'wWprice',cost_price:'wCostPrice',barcode:'wBarcode',supplier:'wSupDD',category:'wCatDD',origin:'wOrigin',composition:'wComposition',subcategory:'wSubcat'};
    var targetEl=document.getElementById(fieldMap[field]);
    var targetFg=targetEl?targetEl.closest('.fg'):null;
    if(targetFg)targetFg.classList.add('wiz-active');
    // Find and mark the mic button as recording
    var micBtn=targetFg?targetFg.querySelector('.wiz-mic'):null;
    if(micBtn)micBtn.classList.add('recording');
    _wizMicRec=new SR();_wizMicRec.lang='bg-BG';_wizMicRec.continuous=false;_wizMicRec.interimResults=true;
    _wizMicRec.onresult=function(e){
        var final='',interim='';
        for(var i=0;i<e.results.length;i++){if(e.results[i].isFinal)final+=e.results[i][0].transcript;else interim+=e.results[i][0].transcript}
        if(interim)_wizMicInterim(field,interim);
        if(final){if(micBtn)micBtn.classList.remove('recording');_wizMicApply(field,final.trim())}
    };
    _wizMicRec.onend=function(){if(micBtn)micBtn.classList.remove('recording')};
    _wizMicRec.onerror=function(){if(micBtn)micBtn.classList.remove('recording');showToast('Грешка с микрофона','error')};
    _wizMicRec.start();
}
function _wizMicInterim(field,text){
    var map={name:'wName',code:'wCode',retail_price:'wPrice',wholesale_price:'wWprice',cost_price:'wCostPrice',barcode:'wBarcode',origin:'wOrigin',composition:'wComposition'};
    var el=document.getElementById(map[field]);
    if(el){el.value=text;el.style.color='#64748b'}
}
function _wizMicApply(field,text){
    if(field==='name'){var el=document.getElementById('wName');el.value=text;el.style.color='';S.wizData.name=text;showToast('Записано ✓','success');wizMarkDone('name');wizHighlightNext()}
    else if(field==='code'){var el=document.getElementById('wCode');el.value=text;el.style.color='';S.wizData.code=text;showToast('Записано ✓','success');wizMarkDone('code');wizHighlightNext()}
    else if(field==='retail_price'){var el=document.getElementById('wPrice');var n=_bgPrice(text,true);if(n!==null){el.value=n;S.wizData.retail_price=n}else{el.value=text.replace(/[^\d.,]/g,'');S.wizData.retail_price=parseFloat(el.value)||0}el.style.color='';showToast('Цена: '+el.value,'success');wizMarkDone('retail_price');wizHighlightNext()}
    else if(field==='wholesale_price'){var el=document.getElementById('wWprice');var n=_bgPrice(text,true);if(n!==null){el.value=n;S.wizData.wholesale_price=n}else{el.value=text.replace(/[^\d.,]/g,'');S.wizData.wholesale_price=parseFloat(el.value)||0}el.style.color='';showToast('Едро: '+el.value,'success');wizMarkDone('wholesale_price');wizHighlightNext()}
    else if(field==='cost_price'){var el=document.getElementById('wCostPrice');var n=_bgPrice(text,true);if(n!==null){el.value=n;S.wizData.cost_price=n}else{el.value=text.replace(/[^\d.,]/g,'');S.wizData.cost_price=parseFloat(el.value)||0}el.style.color='';showToast('Доставна: '+el.value,'success');wizMarkDone('cost_price');wizHighlightNext()}
    else if(field==='barcode'){var el=document.getElementById('wBarcode');el.value=text.replace(/\s/g,'');el.style.color='';S.wizData.barcode=el.value;showToast('Баркод: '+el.value,'success');wizMarkDone('barcode');wizHighlightNext()}
    else if(field==='supplier'){var tl=text.toLowerCase();var m=CFG.suppliers.find(function(s){return s.name.toLowerCase().includes(tl)||tl.includes(s.name.toLowerCase())});if(m){var inp=document.getElementById('wSupDD');inp.value=m.name;inp._selectedId=m.id;S.wizData.supplier_id=m.id;showToast('Доставчик: '+m.name,'success');wizMarkDone('supplier');wizHighlightNext()}else{if(confirm('Няма доставчик "'+text+'". Да го добавя?')){document.getElementById('inlSupName').value=text;S._wizMicVoiceAdd=true;wizAddInline('supplier')}}}
    else if(field==='category'){var tl=text.toLowerCase();var m=CFG.categories.find(function(c){return !c.parent_id&&(c.name.toLowerCase().includes(tl)||tl.includes(c.name.toLowerCase()))});if(m){var inp=document.getElementById('wCatDD');inp.value=m.name;inp._selectedId=m.id;S.wizData.category_id=m.id;showToast('Категория: '+m.name,'success');wizLoadSubcats(m.id);wizMarkDone('category');wizHighlightNext()}else{if(confirm('Няма категория "'+text+'". Да я добавя?')){document.getElementById('inlCatName').value=text;wizAddInline('category')}}}
    else if(field==='subcategory'){var sel=document.getElementById('wSubcat');if(!sel)return;var tl=text.toLowerCase();var found=false;for(var i=0;i<sel.options.length;i++){if(sel.options[i].text.toLowerCase().includes(tl)||tl.includes(sel.options[i].text.toLowerCase())){sel.value=sel.options[i].value;S.wizData.subcategory_id=sel.options[i].value;showToast('Подкатегория: '+sel.options[i].text,'success');wizMarkDone('subcategory');wizHighlightNext();found=true;break}}if(!found&&text.length>1){if(confirm('Няма подкатегория "'+text+'". Да я добавя?')){document.getElementById('inlSubcatName').value=text;wizAddSubcat()}}}
    else if(field==='origin'){var el=document.getElementById('wOrigin');el.value=text;el.style.color='';S.wizData.origin_country=text;showToast('Записано ✓','success')}
    else if(field==='composition'){var el=document.getElementById('wComposition');el.value=text;el.style.color='';S.wizData.composition=text;showToast('Записано ✓','success')}
}
function _bgNum(t){return _bgPrice(t)}
function _bgPrice(t,forcePrice){
    var raw=t.trim().toLowerCase();
    var hasStotinki=(/стотинки|стот\.|цент[аи]?|cents?|пени|пфениг|сантим|копейк/i).test(raw);
    raw=raw.replace(/лева|лв|евро|€|eur|euro|usd|\$|gbp|£|ron|lei|лей|крон[аи]?|злот[аи]?|динар[аи]?|форинт[аи]?|франк[аи]?|стотинки|стот\.|цент[аи]?|cents?|пени|penny|pence|пфениг[аи]?|pfennig|сантим[аи]?|копейк[аи]?/gi,' ').replace(/\s+/g,' ').trim();
    var dn=raw.replace(',','.');var pf=parseFloat(dn);if(!isNaN(pf)&&/^\d+\.?\d*$/.test(dn))return pf;
    var ones={'нула':0,'един':1,'една':1,'едно':1,'два':2,'две':2,'три':3,'четири':4,'пет':5,'шест':6,'седем':7,'осем':8,'девет':9,'десет':10,'единадесет':11,'единайсет':11,'дванадесет':12,'дванайсет':12,'тринадесет':13,'тринайсет':13,'четиринадесет':14,'четиринайсет':14,'петнадесет':15,'петнайсет':15,'шестнадесет':16,'шестнайсет':16,'седемнадесет':17,'седемнайсет':17,'осемнадесет':18,'осемнайсет':18,'деветнадесет':19,'деветнайсет':19,'двадесет':20,'двайсет':20,'тридесет':30,'трийсет':30,'четиридесет':40,'четирийсет':40,'петдесет':50,'шестдесет':60,'седемдесет':70,'осемдесет':80,'деветдесет':90,'сто':100};
    var tens=[10,20,30,40,50,60,70,80,90];
    function word(w){w=w.trim();var n=parseInt(w);if(!isNaN(n))return n;if(ones[w]!==undefined)return ones[w];return null}
    var parts=raw.split(/\s+и\s+/);
    if(parts.length===1){return word(parts[0])}
    if(parts.length===2){var a=word(parts[0]);var b=word(parts[1]);
        if(a!==null&&b!==null){
            if(forcePrice)return parseFloat(a+'.'+String(b).padStart(2,'0'));
            if(hasStotinki&&tens.indexOf(b)!==-1)return parseFloat(a+'.'+String(b).padStart(2,'0'));
            if(a>=0&&a<=9&&tens.indexOf(b)!==-1)return parseFloat(a+'.'+String(b).padStart(2,'0'));
            return a+b}}
    if(parts.length===3){var a=word(parts[0]);var b=word(parts[1]);var c=word(parts[2]);
        if(a!==null&&b!==null&&c!==null){var leva=a+b;return parseFloat(leva+'.'+String(c).padStart(2,'0'))}}
    return null}

// S68 fix: voice for variation values + new axis name
// ═══ S69: Voice Processing Pipeline (i18n) ═══
// Supports: bg, en, ro, el, sr, hr, mk, sq, tr, sl, de
// Entry: _voiceProcessAxis(text, axisName, existingValues) → {values:[], confirmNeeded:[]}

var _VOICE_LANG = {
bg: {
    sizes: {
        'ес':'S','с':'S','ем':'M','м':'M','ел':'L','л':'L',
        'хл':'XL','икс ел':'XL','иксел':'XL','екс ел':'XL',
        'екс ес':'XS','икс ес':'XS',
        'два пъти икс ел':'2XL','два пъти хл':'2XL','двойно хл':'2XL','два икс ел':'2XL','дъбъл хл':'2XL','дабъл хл':'2XL','двойно икс ел':'2XL',
        'два пъти хикс ел':'2XL','два пъти хел':'2XL','2 пъти икс ел':'2XL','2 пъти хикс ел':'2XL','2 пъти хел':'2XL','2 пъти хл':'2XL',
        'три пъти икс ел':'3XL','три пъти хл':'3XL','тройно хл':'3XL','три икс ел':'3XL','тройно икс ел':'3XL',
        'четири пъти икс ел':'4XL','четири пъти хл':'4XL',
        'уан сайз':'One Size','един размер':'One Size','универсален':'One Size',
        'хел':'XL','хикс ел':'XL','хикс':'XL','хиксел':'XL',
        'два хикс ел':'2XL','два хел':'2XL','2 хикс ел':'2XL','2 хел':'2XL','2 икс ел':'2XL','2 хл':'2XL',
        'три хикс ел':'3XL','три хел':'3XL','3 хикс ел':'3XL','3 хел':'3XL','3 икс ел':'3XL','3 хл':'3XL',
        '4 хикс ел':'4XL','4 хел':'4XL','4 икс ел':'4XL','4 хл':'4XL',
        'есемел':'S,M,L','есемелхл':'S,M,L,XL','емел':'M,L','елхл':'L,XL','есем':'S,M','емелхл':'M,L,XL'
    },
    colorPlural: {
        'черни':'Черен','бели':'Бял','сиви':'Сив','червени':'Червен','сини':'Син',
        'зелени':'Зелен','жълти':'Жълт','розови':'Розов','оранжеви':'Оранжев',
        'лилави':'Лилав','кафяви':'Кафяв','бежови':'Бежов',
        'черно':'Черен','бяло':'Бял','сиво':'Сив','червено':'Червен','синьо':'Син',
        'зелено':'Зелен','жълто':'Жълт','розово':'Розов','оранжево':'Оранжев',
        'лилаво':'Лилав','кафяво':'Кафяв','бежово':'Бежов',
        'черна':'Черен','бяла':'Бял','сива':'Сив','червена':'Червен','синя':'Син',
        'зелена':'Зелен','жълта':'Жълт','розова':'Розов','оранжева':'Оранжев',
        'лилава':'Лилав','кафява':'Кафяв','бежова':'Бежов'
    },
    colorAlias: {
        'нейви':'Navy','навй':'Navy','бордо':'Бордо','марсала':'Бордо',
        'крем':'Екрю','кремав':'Екрю','кремаво':'Екрю',
        'пудра':'Пудра','пудров':'Пудра','пудрово':'Пудра',
        'графит':'Графит','графитен':'Графит','графитено':'Графит',
        'тюркоаз':'Тюркоаз','тюркоазен':'Тюркоаз','тюркоазено':'Тюркоаз',
        'маслина':'Маслинен','маслинен':'Маслинен','маслинено':'Маслинен',
        'корал':'Корал','коралов':'Корал','коралово':'Корал',
        'екрю':'Екрю'
    },
    modifiers: ['тъмно','светло','бледо','ярко','пастелно','мръсно','прашно','неоново','кралско','бебешко','нежно','наситено','матово','перлено'],
    fillers: ['моля','сложи','запиши','добави','размер','размери','размера','размерите','цвят','цветове','цвета','благодаря']
},
en: {
    sizes: {
        'small':'S','medium':'M','large':'L','extra large':'XL','extra small':'XS',
        'double xl':'2XL','double extra large':'2XL','triple xl':'3XL',
        'one size':'One Size','free size':'One Size'
    },
    colorPlural: {
        'blacks':'Black','whites':'White','greys':'Grey','reds':'Red','blues':'Blue',
        'greens':'Green','yellows':'Yellow','pinks':'Pink','oranges':'Orange',
        'purples':'Purple','browns':'Brown'
    },
    colorAlias: {
        'navy':'Navy','maroon':'Maroon','burgundy':'Burgundy','cream':'Cream',
        'coral':'Coral','teal':'Teal','olive':'Olive','ivory':'Ivory',
        'charcoal':'Charcoal','turquoise':'Turquoise','beige':'Beige'
    },
    modifiers: ['dark','light','pale','bright','pastel','neon','royal','baby','matte','pearl','dusty'],
    fillers: ['please','put','add','save','size','sizes','color','colors','colour','thanks']
},
ro: {
    sizes: {
        'es':'S','em':'M','el':'L','ix el':'XL','ics el':'XL',
        'dublu xl':'2XL','dublu ics el':'2XL','triplu xl':'3XL',
        'extra small':'XS','extra es':'XS',
        'marime unica':'One Size','o marime':'One Size'
    },
    colorPlural: {
        'negru':'Negru','neagra':'Negru','negri':'Negru','alb':'Alb','alba':'Alb','albi':'Alb',
        'gri':'Gri','rosu':'Roșu','rosie':'Roșu','rosii':'Roșu','albastru':'Albastru','albastra':'Albastru','albastri':'Albastru',
        'verde':'Verde','verzi':'Verde','galben':'Galben','galbena':'Galben','galbeni':'Galben',
        'roz':'Roz','portocaliu':'Portocaliu','portocalie':'Portocaliu','portocalii':'Portocaliu',
        'mov':'Mov','maro':'Maro','bej':'Bej'
    },
    colorAlias: {
        'bleumarin':'Bleumarin','grena':'Grena','crem':'Crem','coral':'Coral',
        'turcoaz':'Turcoaz','masliniu':'Masliniu','bordo':'Bordo','kaki':'Kaki'
    },
    modifiers: ['inchis','deschis','pastel','neon','aprins'],
    fillers: ['te rog','pune','adauga','salveaza','marime','marimi','culoare','culori','multumesc']
},
el: {
    sizes: {
        'ες':'S','σμολ':'S','εμ':'M','μιντιουμ':'M','ελ':'L','λαρτζ':'L',
        'ξλ':'XL','εξτρα λαρτζ':'XL','εξτρα σμολ':'XS',
        'διπλο ξλ':'2XL','τριπλο ξλ':'3XL',
        'ενα μεγεθος':'One Size'
    },
    colorPlural: {
        'μαυρο':'Μαύρο','μαυρα':'Μαύρο','μαυρη':'Μαύρο',
        'ασπρο':'Λευκό','ασπρα':'Λευκό','ασπρη':'Λευκό','λευκο':'Λευκό','λευκα':'Λευκό',
        'γκρι':'Γκρι','κοκκινο':'Κόκκινο','κοκκινα':'Κόκκινο',
        'μπλε':'Μπλε','πρασινο':'Πράσινο','πρασινα':'Πράσινο',
        'κιτρινο':'Κίτρινο','κιτρινα':'Κίτρινο','ροζ':'Ροζ',
        'πορτοκαλι':'Πορτοκαλί','μωβ':'Μωβ','καφε':'Καφέ','μπεζ':'Μπεζ'
    },
    colorAlias: {
        'μπορντο':'Μπορντό','κοραλι':'Κοράλι','τιρκουαζ':'Τιρκουάζ',
        'ναυτικο μπλε':'Navy','λαδι':'Λαδί','κρεμ':'Κρεμ','εκρου':'Εκρού'
    },
    modifiers: ['σκουρο','ανοιχτο','παστελ','εντονο','νεον'],
    fillers: ['παρακαλω','βαλε','προσθεσε','μεγεθος','μεγεθη','χρωμα','χρωματα','ευχαριστω']
},
sr: {
    sizes: {
        'ес':'S','ем':'M','ел':'L','икс ел':'XL','екс ес':'XS',
        'дупло икс ел':'2XL','трипло икс ел':'3XL',
        'једна величина':'One Size','универзална':'One Size'
    },
    colorPlural: {
        'црна':'Црна','црно':'Црна','црни':'Црна','бела':'Бела','бело':'Бела','бели':'Бела',
        'сива':'Сива','сиво':'Сива','сиви':'Сива','црвена':'Црвена','црвено':'Црвена','црвени':'Црвена',
        'плава':'Плава','плаво':'Плава','плави':'Плава','зелена':'Зелена','зелено':'Зелена','зелени':'Зелена',
        'жута':'Жута','жуто':'Жута','жути':'Жута','розе':'Розе',
        'наранџаста':'Наранџаста','наранџасто':'Наранџаста',
        'љубичаста':'Љубичаста','љубичасто':'Љубичаста',
        'браон':'Браон','беж':'Беж'
    },
    colorAlias: {'тегет':'Тегет','бордо':'Бордо','крем':'Крем','корал':'Корал','тиркиз':'Тиркиз','маслинаста':'Маслинаста'},
    modifiers: ['тамно','светло','пастелно','неон','јарко'],
    fillers: ['молим','стави','додај','сачувај','величина','величине','боја','боје','хвала']
},
tr: {
    sizes: {
        'es':'S','small':'S','em':'M','medium':'M','el':'L','large':'L',
        'ekstra large':'XL','iki ekstra large':'2XL','üç ekstra large':'3XL',
        'ekstra small':'XS','tek beden':'One Size'
    },
    colorPlural: {
        'siyah':'Siyah','beyaz':'Beyaz','gri':'Gri','kırmızı':'Kırmızı','mavi':'Mavi',
        'yeşil':'Yeşil','sarı':'Sarı','pembe':'Pembe','turuncu':'Turuncu',
        'mor':'Mor','kahverengi':'Kahverengi','bej':'Bej'
    },
    colorAlias: {
        'lacivert':'Lacivert','bordo':'Bordo','krem':'Krem','mercan':'Mercan',
        'turkuaz':'Turkuaz','haki':'Haki','ekru':'Ekru'
    },
    modifiers: ['koyu','açık','pastel','neon','canlı','mat'],
    fillers: ['lütfen','ekle','kaydet','beden','bedenler','renk','renkler','teşekkürler']
},
mk: {
    sizes: {
        'ес':'S','ем':'M','ел':'L','икс ел':'XL','екс ес':'XS',
        'двојно икс ел':'2XL','тројно икс ел':'3XL','универзална':'One Size'
    },
    colorPlural: {
        'црна':'Црна','црно':'Црна','црни':'Црна','бела':'Бела','бело':'Бела','бели':'Бела',
        'сива':'Сива','сиво':'Сива','црвена':'Црвена','црвено':'Црвена',
        'сина':'Сина','сино':'Сина','зелена':'Зелена','зелено':'Зелена',
        'жолта':'Жолта','жолто':'Жолта','розева':'Розева','розево':'Розева',
        'портокалова':'Портокалова','виолетова':'Виолетова','кафеава':'Кафеава','беж':'Беж'
    },
    colorAlias: {'тегет':'Тегет','бордо':'Бордо','крем':'Крем','корал':'Корал','тиркиз':'Тиркиз'},
    modifiers: ['темно','светло','пастелно','неон'],
    fillers: ['те молам','стави','додади','зачувај','големина','големини','боја','бои','благодарам']
},
hr: {
    sizes: {
        'es':'S','em':'M','el':'L','iks el':'XL','eks es':'XS',
        'duplo iks el':'2XL','triplo iks el':'3XL','jedna veličina':'One Size','univerzalna':'One Size'
    },
    colorPlural: {
        'crna':'Crna','crno':'Crna','crni':'Crna','bijela':'Bijela','bijelo':'Bijela',
        'siva':'Siva','sivo':'Siva','crvena':'Crvena','crveno':'Crvena',
        'plava':'Plava','plavo':'Plava','zelena':'Zelena','zeleno':'Zelena',
        'žuta':'Žuta','žuto':'Žuta','roza':'Roza',
        'narančasta':'Narančasta','ljubičasta':'Ljubičasta','smeđa':'Smeđa','bež':'Bež'
    },
    colorAlias: {'tamnoplava':'Tamnoplava','bordo':'Bordo','krem':'Krem','koraljna':'Koraljna','tirkizna':'Tirkizna'},
    modifiers: ['tamno','svijetlo','pastelno','neon','jarko'],
    fillers: ['molim','stavi','dodaj','spremi','veličina','veličine','boja','boje','hvala']
},
sq: {
    sizes: {
        'es':'S','em':'M','el':'L','iks el':'XL','eks es':'XS',
        'dyfishi xl':'2XL','trefishi xl':'3XL','një masë':'One Size'
    },
    colorPlural: {
        'e zezë':'E zezë','i zi':'I zi','e bardhë':'E bardhë','i bardhë':'I bardhë',
        'gri':'Gri','e kuqe':'E kuqe','i kuq':'I kuq','blu':'Blu',
        'e gjelbër':'E gjelbër','i gjelbër':'I gjelbër',
        'e verdhë':'E verdhë','rozë':'Rozë','portokalli':'Portokalli',
        'vjollcë':'Vjollcë','kafe':'Kafe','bezhë':'Bezhë'
    },
    colorAlias: {'bordo':'Bordo','krem':'Krem','koral':'Koral'},
    modifiers: ['e errët','e çelët','pastel','neon'],
    fillers: ['ju lutem','vendos','shto','ruaj','masë','masa','ngjyrë','ngjyra','faleminderit']
},
sl: {
    sizes: {
        'es':'S','em':'M','el':'L','iks el':'XL','eks es':'XS',
        'dvojni xl':'2XL','trojni xl':'3XL','ena velikost':'One Size'
    },
    colorPlural: {
        'črna':'Črna','črno':'Črna','bela':'Bela','belo':'Bela',
        'siva':'Siva','sivo':'Siva','rdeča':'Rdeča','rdeče':'Rdeča',
        'modra':'Modra','modro':'Modra','zelena':'Zelena','zeleno':'Zelena',
        'rumena':'Rumena','roza':'Roza','oranžna':'Oranžna',
        'vijolična':'Vijolična','rjava':'Rjava','bež':'Bež'
    },
    colorAlias: {'bordo':'Bordo','krem':'Krem','turkizna':'Turkizna','olivna':'Olivna'},
    modifiers: ['temno','svetlo','pastelno','neon'],
    fillers: ['prosim','dodaj','shrani','velikost','velikosti','barva','barve','hvala']
},
de: {
    sizes: {
        'es':'S','klein':'S','em':'M','mittel':'M','el':'L','groß':'L','gross':'L',
        'extra groß':'XL','extra gross':'XL','doppel xl':'2XL','dreifach xl':'3XL',
        'extra klein':'XS','einheitsgröße':'One Size','einheitsgroesse':'One Size'
    },
    colorPlural: {
        'schwarz':'Schwarz','schwarze':'Schwarz','schwarzer':'Schwarz',
        'weiß':'Weiß','weiss':'Weiß','weiße':'Weiß',
        'grau':'Grau','graue':'Grau','rot':'Rot','rote':'Rot','roter':'Rot',
        'blau':'Blau','blaue':'Blau','grün':'Grün','gruen':'Grün','grüne':'Grün',
        'gelb':'Gelb','gelbe':'Gelb','rosa':'Rosa','pink':'Pink',
        'orange':'Orange','lila':'Lila','braun':'Braun','braune':'Braun','beige':'Beige'
    },
    colorAlias: {
        'marine':'Marine','bordeaux':'Bordeaux','creme':'Creme','koralle':'Koralle',
        'türkis':'Türkis','tuerkis':'Türkis','oliv':'Oliv','anthrazit':'Anthrazit'
    },
    modifiers: ['dunkel','hell','pastell','neon','matt'],
    fillers: ['bitte','setze','füge','speichere','größe','groesse','groessen','farbe','farben','danke']
}
};

// Standard size order for sorting
var _SIZE_ORDER=['XS','S','M','L','XL','2XL','3XL','4XL',
    '34','36','38','40','42','44','46','48','50','52','54','56',
    '35-38','39-42','43-46',
    '80','86','92','98','104','110','116','122','128','134','140','146','152','158','164',
    'W28','W29','W30','W31','W32','W33','W34','W36','W38','S/M','L/XL','One Size'];

function _vl(){return _VOICE_LANG[CFG.lang]||_VOICE_LANG.en}

function _voiceNormalize(text){
    var t=text.trim().toLowerCase();
    var fl=_vl().fillers||[];
    fl.forEach(function(f){t=t.replace(new RegExp('^'+f+'\\s+','g'),'');t=t.replace(new RegExp('\\s+'+f+'$','g'),'')});
    return t.trim();
}

function _voiceProcessAxis(text,axisName,existingValues){
    var raw=_voiceNormalize(text);
    if(!raw)return{values:[],confirmNeeded:[]};
    var nm=axisName.toLowerCase();
    var isSize=nm.includes('размер')||nm.includes('size')||nm.includes('ръст')||nm.includes('бюст')||nm.includes('mărime')||nm.includes('beden')||nm.includes('μέγεθος')||nm.includes('величина')||nm.includes('velikost')||nm.includes('größe')||nm.includes('masë');
    var isColor=nm.includes('цвят')||nm.includes('color')||nm.includes('colour')||nm.includes('десен')||nm.includes('culoare')||nm.includes('χρώμα')||nm.includes('боја')||nm.includes('boja')||nm.includes('barva')||nm.includes('ngjyrë')||nm.includes('renk')||nm.includes('farbe');
    var tokens=isSize?_splitSizes(raw):isColor?_splitColors(raw):raw.split(/\s+и\s+|\s+and\s+|\s+și\s+|\s+και\s+|\s+ve\s+|\s+und\s+|\s+dhe\s+|,\s*/).map(function(v){return v.trim()}).filter(Boolean);
    var lang=_vl();
    var mapped=[];
    tokens.forEach(function(tok){
        var result=isSize?_mapSize(tok,lang):isColor?_mapColor(tok,lang):tok;
        if(typeof result==='string'&&result.includes(','))result.split(',').forEach(function(v){if(v.trim())mapped.push(v.trim())});
        else if(result)mapped.push(result);
    });
    var presetList=_getPresetsForAxis(axisName,isSize,isColor);
    var final=[],confirmNeeded=[];
    mapped.forEach(function(val){
        var matched=_matchPreset(val,presetList,isSize,isColor);
        if(matched)final.push(matched);
        else{confirmNeeded.push(val);final.push(val)}
    });
    var seen={};
    var existingNorm=(existingValues||[]).map(function(v){return v.toLowerCase()});
    var deduped=[];
    final.forEach(function(v){var key=v.toLowerCase();if(!seen[key]&&existingNorm.indexOf(key)===-1){seen[key]=true;deduped.push(v)}});
    if(isSize)deduped.sort(function(a,b){var ai=_SIZE_ORDER.indexOf(a);if(ai===-1)ai=999;var bi=_SIZE_ORDER.indexOf(b);if(bi===-1)bi=999;return ai-bi});
    return{values:deduped,confirmNeeded:confirmNeeded};
}

function _splitSizes(raw){
    var words=raw.toLowerCase().replace(/[.,!?;:]+/g,'').split(/\s+/).filter(Boolean);
    var results=[];
    var lang=_vl();
    var aliases=lang.sizes||{};
    // Sort alias keys longest first
    var akeys=Object.keys(aliases).sort(function(a,b){return b.split(' ').length-a.split(' ').length||b.length-a.length});
    
    var i=0;
    while(i<words.length){
        // Skip noise
        if(['и','and','ve','und','dhe','și','και'].indexOf(words[i])!==-1){i++;continue}
        
        var found=false;
        // Try joining 4,3,2,1 words and match alias
        for(var len=Math.min(4,words.length-i);len>=1;len--){
            var phrase=words.slice(i,i+len).join(' ');
            if(aliases[phrase]){
                var val=aliases[phrase];
                if(val.includes(','))val.split(',').forEach(function(v){if(results.indexOf(v)===-1)results.push(v)});
                else if(results.indexOf(val)===-1)results.push(val);
                i+=len;found=true;break;
            }
        }
        if(found)continue;
        
        // Pure number
        var w=words[i];
        if(/^\d+$/.test(w)){if(results.indexOf(w)===-1)results.push(w);i++;continue}
        
        // Word-number via _bgPrice
        var n=_bgPrice(w);
        if(n!==null&&n>0&&n===Math.round(n)){var ns=String(Math.round(n));if(results.indexOf(ns)===-1)results.push(ns);i++;continue}
        
        // Skip unknown
        i++;
    }
    return results;
}

function _getAllSizePresets(){
    var all=[];
    // From _sizePresets (hardcoded in page)
    if(window._sizePresets){
        for(var k in window._sizePresets){
            window._sizePresets[k].forEach(function(v){if(all.indexOf(v)===-1)all.push(v)});
        }
    }
    // From biz variants
    if(window._bizVariants&&window._bizVariants.variant_presets){
        for(var k in window._bizVariants.variant_presets){
            var kl=k.toLowerCase();
            if(kl.includes('размер')||kl.includes('size')||kl.includes('ръст')){
                window._bizVariants.variant_presets[k].forEach(function(v){if(all.indexOf(v)===-1)all.push(v)});
            }
        }
    }
    // From global presets
    if(window._allBizPresets&&window._allBizPresets.sizes){
        window._allBizPresets.sizes.forEach(function(v){if(all.indexOf(v)===-1)all.push(v)});
    }
    // Standard sizes always included
    ['XS','S','M','L','XL','2XL','3XL','4XL','One Size'].forEach(function(v){if(all.indexOf(v)===-1)all.push(v)});
    return all;
}

function _fuzzyMatchPreset(word,presets){
    if(!word||word.length<1)return null;
    var wl=word.toLowerCase();
    // Exact case-insensitive
    for(var i=0;i<presets.length;i++){
        if(presets[i].toLowerCase()===wl)return presets[i];
    }
    // Starts with same 2+ chars (for short values like S, M, L skip this)
    if(wl.length>=2){
        for(var i=0;i<presets.length;i++){
            var pl=presets[i].toLowerCase();
            if(pl.length>=2&&(pl.indexOf(wl)===0||wl.indexOf(pl)===0))return presets[i];
        }
    }
    return null;
}

function _splitColors(raw){
    var lang=_vl();var mods=lang.modifiers||[];
    var parts=raw.split(/\s+и\s+|\s+and\s+|\s+și\s+|\s+και\s+|\s+ve\s+|\s+und\s+|\s+dhe\s+|,\s*/).map(function(v){return v.trim()}).filter(Boolean);
    var results=[];
    parts.forEach(function(part){
        var words=part.split(/\s+/);var current='';
        for(var i=0;i<words.length;i++){
            var w=words[i];
            if(mods.indexOf(w)!==-1){
                if(current&&!mods.some(function(m){return current===m})){results.push(current.trim());current=''}
                current=(current?current+' ':'')+w;
            }else{
                if(current){
                    var lastWord=current.split(/\s+/).pop();
                    if(mods.indexOf(lastWord)!==-1)current+=' '+w;
                    else{results.push(current.trim());current=w}
                }else current=w;
            }
        }
        if(current)results.push(current.trim());
    });
    return results;
}

function _mapSize(tok,lang){
    var t=tok.trim().toLowerCase();
    var sizeMap=lang.sizes||{};
    if(sizeMap[t])return sizeMap[t];
    if(/^\d+$/.test(t))return t;
    if(/^[smlSML]$/.test(t))return t.toUpperCase();
    var n=_bgPrice(t);
    if(n!==null&&n>0&&n===Math.round(n))return String(Math.round(n));
    return tok.toUpperCase();
}

function _mapColor(tok,lang){
    var t=tok.trim().toLowerCase();
    if((lang.colorAlias||{})[t])return lang.colorAlias[t];
    if((lang.colorPlural||{})[t])return lang.colorPlural[t];
    var mods=lang.modifiers||[];
    var words=t.split(/\s+/);
    if(words.length>=2&&mods.indexOf(words[0])!==-1){
        var base=words.slice(1).join(' ');
        var mapped=(lang.colorPlural||{})[base]||(lang.colorAlias||{})[base]||_stemMatchColor(base);
        if(mapped)return words[0].charAt(0).toUpperCase()+words[0].slice(1)+' '+mapped.toLowerCase();
    }
    var stemResult=_stemMatchColor(t);
    if(stemResult)return stemResult;
    return tok.charAt(0).toUpperCase()+tok.slice(1);
}

function _stemMatchColor(word){
    var w=word.toLowerCase();if(w.length<2)return null;
    var stem=w.substring(0,Math.min(w.length,4));
    for(var i=0;i<CFG.colors.length;i++){
        var cn=CFG.colors[i].name.toLowerCase();
        if(cn.indexOf(stem)===0||stem.indexOf(cn.substring(0,3))===0)return CFG.colors[i].name;
    }
    var lang=_vl();
    for(var key in(lang.colorPlural||{})){if(key.indexOf(stem)===0)return lang.colorPlural[key]}
    return null;
}

function _getPresetsForAxis(axisName,isSize,isColor){
    var presets=[];var nm=axisName.toLowerCase();
    if(window._bizVariants&&window._bizVariants.variant_presets){
        for(var k in window._bizVariants.variant_presets){
            if(k===axisName||k.toLowerCase()===nm||nm.includes(k.toLowerCase())){presets=window._bizVariants.variant_presets[k];break}
        }
    }
    if(window._allBizPresets){
        var gp=isSize?(window._allBizPresets.sizes||[]):isColor?(window._allBizPresets.colors||[]):(window._allBizPresets.other||[]);
        gp.forEach(function(v){if(presets.indexOf(v)===-1)presets.push(v)});
    }
    if(isColor&&CFG.colors)CFG.colors.forEach(function(c){if(presets.indexOf(c.name)===-1)presets.push(c.name)});
    return presets;
}

function _matchPreset(val,presets,isSize,isColor){
    if(!presets.length)return null;
    for(var i=0;i<presets.length;i++){if(presets[i]===val)return presets[i]}
    var vl=val.toLowerCase();
    for(var i=0;i<presets.length;i++){if(presets[i].toLowerCase()===vl)return presets[i]}
    if(isColor){
        var stem=vl.substring(0,Math.min(vl.length,4));
        for(var i=0;i<presets.length;i++){var ps=presets[i].toLowerCase();if(ps.indexOf(stem)===0||stem.indexOf(ps.substring(0,3))===0)return presets[i]}
    }
    return null;
}

function wizMicAxis(axIdx){
    // For size/color axes → open preset picker (more reliable than voice)
    var ax=S.wizData.axes[axIdx];
    if(ax){
        var nm=ax.name.toLowerCase();
        var isSize=nm.includes('размер')||nm.includes('size')||nm.includes('ръст');
        var isColor=nm.includes('цвят')||nm.includes('color')||nm.includes('десен');
        if(isSize||isColor){openPresetPicker(axIdx,isSize);return}
    }
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Гласът не се поддържа','error');return}
    if(_wizMicRec){try{_wizMicRec.abort()}catch(e){}_wizMicRec=null}
    var axInput=document.getElementById('axVal'+axIdx);
    var micBtn=axInput?axInput.closest('div').querySelector('.wiz-mic'):null;
    if(micBtn)micBtn.classList.add('recording');
    _wizMicRec=new SR();_wizMicRec.lang='bg-BG';_wizMicRec.continuous=false;_wizMicRec.interimResults=false;
    _wizMicRec.onresult=function(e){if(micBtn)micBtn.classList.remove('recording');
        var text=e.results[0][0].transcript.trim();
        var ax=S.wizData.axes[axIdx];if(!ax)return;
        var result=_voiceProcessAxis(text,ax.name,ax.values);
        var added=0;
        result.values.forEach(function(v){if(!ax.values.includes(v)){ax.values.push(v);added++}});
        if(added>0){
            var nm=ax.name.toLowerCase();
            if(nm.includes('размер')||nm.includes('size')||nm.includes('mărime')||nm.includes('beden')||nm.includes('größe')){
                ax.values.sort(function(a,b){var ai=_SIZE_ORDER.indexOf(a);if(ai===-1)ai=999;var bi=_SIZE_ORDER.indexOf(b);if(bi===-1)bi=999;return ai-bi});
            }
            showToast(added+' добавени ✓','success');
            if(result.confirmNeeded.length>0)showToast(result.confirmNeeded.join(', ')+' — нови','');
            renderWizard();
        } else showToast('Вече са добавени','');
    };
    _wizMicRec.onerror=function(){if(micBtn)micBtn.classList.remove('recording');showToast('Грешка с микрофона','error')};
    _wizMicRec.onend=function(){if(micBtn)micBtn.classList.remove('recording')};
    _wizMicRec.start();
}
function wizMicNewAxis(){
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Гласът не се поддържа','error');return}
    if(_wizMicRec){try{_wizMicRec.abort()}catch(e){}_wizMicRec=null}
    _wizMicRec=new SR();_wizMicRec.lang='bg-BG';_wizMicRec.continuous=false;_wizMicRec.interimResults=false;
    _wizMicRec.onresult=function(e){
        var text=e.results[0][0].transcript.trim();
        var inp=document.getElementById('newAxisName');
        if(inp){inp.value=text;showToast('Записано: '+text,'success')}
    };
    _wizMicRec.onerror=function(){showToast('Грешка с микрофона','error')};
    _wizMicRec.start();showToast('Кажи име на вариация...','success');
}

// S68: Highlight next field + mark done
function wizMarkDone(field){
    var map={name:'wName',code:'wCode',retail_price:'wPrice',wholesale_price:'wWprice',barcode:'wBarcode',supplier:'wSupDD',category:'wCatDD',origin:'wOrigin',composition:'wComposition',subcategory:'wSubcat'};
    var el=document.getElementById(map[field]);
    if(el){var fg=el.closest('.fg');if(fg){fg.classList.remove('wiz-next');fg.classList.add('wiz-done')}}
}
function _wizClearHighlights(){
    document.querySelectorAll('#wizBody .fg').forEach(function(f){f.classList.remove('wiz-next','wiz-active')});
    document.querySelectorAll('#wizBody .wiz-mic.recording').forEach(function(m){m.classList.remove('recording')});
}
function wizHighlightNext(){
    _wizClearHighlights();
    var fields=[
        {id:'wName',key:'name',check:function(){return !!S.wizData.name}},
        {id:'wCode',key:'code',check:function(){return !!S.wizData.code}},
        {id:'wPrice',key:'retail_price',check:function(){return S.wizData.retail_price>0}},
        {id:'wWprice',key:'wholesale_price',check:function(){return S.wizData.wholesale_price>0||CFG.skipWholesale}},
        {id:'wBarcode',key:'barcode',check:function(){return !!S.wizData.barcode}},
        {id:'wSupDD',key:'supplier',check:function(){return S.wizData.supplier_id>0}},
        {id:'wCatDD',key:'category',check:function(){return S.wizData.category_id>0}},
        {id:'wOrigin',key:'origin',check:function(){return !!S.wizData.origin_country||S.wizData.is_domestic}},
        {id:'wComposition',key:'composition',check:function(){return !!S.wizData.composition}},
        {id:'wSubcat',key:'subcategory',check:function(){return true}}
    ];
    for(var i=0;i<fields.length;i++){
        var el=document.getElementById(fields[i].id);
        if(!el)continue;
        if(!fields[i].check()){
            var fg=el.closest('.fg');
            if(fg){fg.classList.add('wiz-next');fg.scrollIntoView({behavior:'smooth',block:'center'})}
            return;
        }
    }
}
// ═══ END S68 ═══

const EMOJI_MAP = [
// Комплект/сет — НЕ emoji, а SVG (връща null за да мине към fallback)
  {re:/^комплект|^сет |^set | set$| set .| set,|ensemble/i, e:null},
  // Козметика разширено
  {re:/несесер|козметичн.*чант|beauty.*case|makeup.*bag/i, e:'💼'},
  {re:/крем|cream|мазнин|moisturi[sz]er|ponij/i, e:'🧴'},
  {re:/маска|mask|face.*mask/i, e:'😷'},
  {re:/дезодор|deodorant|антиперс/i, e:'🧴'},
  // Канцеларски
  {re:/тетрадк|notebook.*paper|бележник|planner/i, e:'📓'},
  {re:/химикалк|pen|молив|pencil|маркер|marker/i, e:'🖊️'},
  {re:/папка|folder|file/i, e:'📁'},
  // Ножици/гребен
  {re:/ножиц|scissors|sheers/i, e:'✂️'},
  {re:/гребен|comb|четк.*коса|hairbrush/i, e:'💇'},
  // Мед/здраве
  {re:/витамин|vitamin|хранит.*добав|supplement/i, e:'💊'},
  // Дом
  {re:/възглав|cushion|декоратив.*възглав/i, e:'🛏️'},
  {re:/килим|carpet|rug/i, e:'🏠'},

// Спортни облекла
  {re:/суичър|суитчер|суичер|sweatshirt|crewneck/i, e:'🧥'},
  {re:/анцуг|тренинг|трак|tracksuit|sports.*suit/i, e:'🧥'},
  {re:/елек|vest|жилетка.*без.*ръкав|puffer.*vest|gilet/i, e:'🦺'},
  {re:/потник|tank|tanktop|sleeveless/i, e:'👕'},
  {re:/жилетк|cardigan/i, e:'🧥'},
  // Още обувки
  {re:/балерин|ballet.*flat|flat.*shoe/i, e:'🩰'},
  {re:/мокасини|moccasin|espadril/i, e:'👞'},
  {re:/валенк|галош|guma.*boot/i, e:'👢'},
  // Още облекла
  {re:/туника|tunic|kaftan/i, e:'👚'},
  {re:/сако|blazer|костюм|suit/i, e:'🤵'},
  {re:/гащеризон|jumpsuit|overall|комбинезон/i, e:'👖'},
  {re:/боди|bodysuit|ромпер|romper/i, e:'🩱'},
  {re:/кимоно|kimono/i, e:'🥻'},
  // Бански
  {re:/бански|swimsuit|swimwear|бикин.*лято/i, e:'🩱'},
  {re:/хавлия|кърп.*плаж|beach.*towel|robe.*bath/i, e:'🛁'},
  // Домашни дрехи
  {re:/халат|robe|dressing.*gown/i, e:'🥻'},
  {re:/пеньоар/i, e:'🥻'},
  // Ръкавици детайл
  {re:/маншет|cuff|ръкав/i, e:'🧤'},
  // Аксесоари още
  {re:/клипс|hairclip|фиба|headband/i, e:'💇'},
  {re:/гумичк.*коса|hair.*tie/i, e:'💇'},

  // Обувки (отделни видове)
  {re:/маратонк|кецове|sneaker|nike|adidas|puma|reebok|vans|converse|asics|newbalance|jordan|airmax|superstar/i, e:'👟'},
  {re:/обувк|мъжки.*обувк|дамск.*обувк|oxford|loafer|dress.*shoe/i, e:'👞'},
  {re:/ток|висок.*ток|елегант.*обув|heel|stiletto|pump/i, e:'👠'},
  {re:/сандал|джапанк|flip.?flop|slipper|чехли|sandal/i, e:'👡'},
  {re:/ботуш|чизм|boot|boots/i, e:'👢'},
  // Облекло горно
  {re:/тениск|t.?shirt|потник|tee/i, e:'👕'},
  {re:/ризa|риза|shirt|blouse|блуза/i, e:'👔'},
  {re:/пуловер|суитшърт|sweater|jumper|hoodi|худи|cardigan|жилетка/i, e:'🧥'},
  {re:/яке|палто|горнищ|jacket|coat|parka|пар[ъу]тa|пухен|anorak|bomber/i, e:'🧥'},
  // Рокли/поли
  {re:/рокл|dress|robe|sukien|gown/i, e:'👗'},
  {re:/пола|skirt|юбк/i, e:'👚'},
  // Долно
  {re:/джинс|дънк|jean|levi|deni[mn]|w\d{2,}/i, e:'👖'},
  {re:/панталон|trouser|chino|pants|slacks|брич/i, e:'👖'},
  {re:/шорт|short|бермуд/i, e:'🩳'},
  {re:/клин|leggin|тайц|tight/i, e:'🩱'},
  // Бельо
  {re:/бел[ьи]о|underwear|underpants|бикини|пант[ил][еи]|boxer|boxers|slip|brief|гащ[ит]/i, e:'🩲'},
  {re:/сутие|bra|бюст|lingerie|дамско.*бел/i, e:'🩱'},
  {re:/пижам|нощн[иа]|pyjama|pajama|nightgown/i, e:'🛌'},
  // Чорапи
  {re:/чорап|sock|гьол|stocking|foot[a-z]*gear/i, e:'🧦'},
  // Шапки
  {re:/шапк|cap|hat|beanie|кепе|барет|тюрбан|bucket.hat/i, e:'🧢'},
  // Чанти
  {re:/чант[аи]|bag|handbag|tote|clutch|backpack|раница|чантичк/i, e:'👜'},
  {re:/портмон|wallet|кесия|purse/i, e:'👛'},
  {re:/куфар|luggage|suitcase|trolley/i, e:'🧳'},
  // Аксесоари
  {re:/колан|belt|ремък/i, e:'👔'},
  {re:/шал|scarf|кърп[аи].*врат|врат[ен]?.*аксесоар|bandana/i, e:'🧣'},
  {re:/ръкавиц|glove|mitten/i, e:'🧤'},
  {re:/часовник|watch|clock|гривн.*час/i, e:'⌚'},
  {re:/очил|glasses|sunglas|слънчев.*очил|спектакъл/i, e:'🕶️'},
  {re:/чадър|umbrella/i, e:'☂️'},
  // Бижу
  {re:/пръстен|ring/i, e:'💍'},
  {re:/обеци|earring/i, e:'💎'},
  {re:/колие|necklace|синджир|верижк|медалион/i, e:'📿'},
  {re:/бижу|jewel|гривн|bracelet/i, e:'💎'},
  // Козметика / парфюм
  {re:/парфюм|perfume|eau.de|toilette|кьолн/i, e:'💐'},
  {re:/червило|lipstick|балсам.*устн|lipgloss/i, e:'💄'},
  {re:/фон.*тен|foundation|крем.*лице|face.*cream/i, e:'🧴'},
  {re:/спирал|mascara|молив.*око|eyeliner|сенк|eyeshadow/i, e:'👁️'},
  {re:/лак.*нокт|nail.polish|маникюр/i, e:'💅'},
  {re:/шампоан|shampoo|балсам.*коса|conditioner/i, e:'🧴'},
  {re:/сапун|soap|душ.*гел|bodywash/i, e:'🧼'},
  // Спорт
  {re:/топк|ball|футбол|football|soccer|баскет|basket/i, e:'⚽'},
  {re:/тенис|tennis|ракет/i, e:'🎾'},
  {re:/фитнес|dumbbell|gym|тегло|тежест/i, e:'🏋️'},
  // Детски
  {re:/бебе|baby|бебешк|infant|newborn/i, e:'🍼'},
  {re:/играчк|toy|кукл|пъзел|puzzle|лего|lego/i, e:'🧸'},
  // Електроника
  {re:/телефон|phone|iphone|samsung|смарт/i, e:'📱'},
  {re:/слушалк|headphone|earphone|airpod|earbud/i, e:'🎧'},
  {re:/лаптоп|laptop|notebook/i, e:'💻'},
  {re:/кабел|зарядно|charger|cable|адаптер/i, e:'🔌'},
  // Храна / напитки
  {re:/кафе|coffee|експресо|espresso/i, e:'☕'},
  {re:/чай|tea/i, e:'🍵'},
  {re:/шоколад|chocolate|бонбон|candy|бисквит|cookie/i, e:'🍫'},
  // Домашни
  {re:/възглавниц|pillow|cushion/i, e:'🛏️'},
  {re:/одеял|кувертюр|blanket|завивка/i, e:'🛌'},
  {re:/свещ|candle/i, e:'🕯️'},
  {re:/ваз|vase|цвет.*декор/i, e:'🌸'},
  // Книги
  {re:/книг|book|роман|учебник|справочник/i, e:'📚'},
  // Инструменти
  {re:/отверк|screwdriver|инструмент|tool|чук|hammer|ключ.*гаечен/i, e:'🔧'},
];

function emojiForProduct(name, catName, subCatName) {
    const haystack = ((name || '') + ' ' + (catName || '') + ' ' + (subCatName || '')).toLowerCase();
    for (const m of EMOJI_MAP) {
        if (m.re.test(haystack)) return m.e; // може да върне null → SVG
    }
    return null; // fallback към SVG
}

function loadSections() {
    fetch('products.php?ajax=sections')
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            const countEl = document.querySelector('.title-sub');
            if (countEl && d.total_products) countEl.textContent = '· ' + d.total_products + ' шт.';

            ['q1','q2','q3','q4','q5','q6'].forEach(q => {
                const sec = d.sections[q];
                const total = d.totals[q] || '';
                const allHeads = document.querySelectorAll('.q-head.' + q);
                if (!allHeads.length) return;
                const head = allHeads[0];
                const totalEl = head.querySelector('.q-total');
                if (totalEl && total) totalEl.textContent = total;
                const scroll = head.nextElementSibling;
                if (!scroll || !scroll.classList.contains('h-scroll')) return;
                if (!sec.items.length) {
                    scroll.innerHTML = '<div style="padding:20px;color:rgba(255,255,255,.4);font-size:11px;text-align:center;width:100%">Нищо за показване</div>';
                    return;
                }
                scroll.innerHTML = sec.items.map(it => {
                    const emoji = emojiForProduct(it.name, it.category_name, it.subcategory_name);
                    const photoContent = it.image_url
                        ? `<img src="${it.image_url}" style="width:100%;height:100%;object-fit:cover;border-radius:10px">`
                        : (emoji ? `<span class="art-emoji">${emoji}</span>` : `<svg class="art-svg-fallback" viewBox="0 0 48 48" fill="none"> <defs>  <linearGradient id="ngg1" x1="0" y1="0" x2="1" y2="1">   <stop offset="0" stop-color="currentColor" stop-opacity=".85"/>   <stop offset="1" stop-color="currentColor" stop-opacity=".35"/>  </linearGradient>  <filter id="ngf1" x="-30%" y="-30%" width="160%" height="160%">   <feGaussianBlur stdDeviation="1.5" result="b"/>   <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>  </filter> </defs> <!-- Хексагонален rim с glow --> <polygon points="24,5 39,13 39,32 24,40 9,32 9,13"   stroke="url(#ngg1)" stroke-width="1.5" fill="none" filter="url(#ngf1)" opacity=".8"/> <polygon points="24,10 35,16 35,30 24,36 13,30 13,16"   stroke="currentColor" stroke-width=".8" fill="none" opacity=".5"/> <!-- Централен символ — звезда/кристал --> <path d="M24 17 L27 22 L32 22.5 L28 26 L29 31 L24 28.5 L19 31 L20 26 L16 22.5 L21 22 Z"   fill="url(#ngg1)" stroke="currentColor" stroke-width=".4" filter="url(#ngf1)"/> <circle cx="24" cy="23" r="1.5" fill="currentColor" opacity=".9"/></svg>`);
                    /* S88.PRODUCTS.AIBRAIN_WIRE: action button — handler dispatch-ва според intent.
                       Payload се пази в window.__aiAct keyed by ключ; onclick предава ключа. */
                    const act = it.action || {};
                    let actionBtn = '';
                    if (act.label) {
                        const actKey = `${q}-${it.id}-${act.topic||''}`;
                        window.__aiAct = window.__aiAct || {};
                        window.__aiAct[actKey] = act;
                        actionBtn = `<button class="art-action ${q}" onclick="event.stopPropagation();handleAiAction('${actKey.replace(/'/g,"\\'")}', ${it.id})">${escapeHtml(act.label)}</button>`;
                    }
                    return `
                    <div class="glass sm ${q} art" onclick="openProductDetail(${it.id})" data-fix="S79.FIX.B-BUG9">
                        <span class="shine"></span><span class="shine shine-bottom"></span>
                        <div class="art-photo">
                            ${photoContent}
                            <span class="tag ${it.tagClass}">${it.tag}</span>
                        </div>
                        <div class="art-nm">${escapeHtml(it.name)}</div>
                        <div class="art-bot">
                            <div class="art-prc">${it.price ? Math.round(it.price) + ' лв' : '—'}</div>
                            <div class="art-stk ${it.stkClass}">${it.stkText}</div>
                        </div>
                        <div class="art-ctx ${q}">${it.ctx}</div>
                        ${actionBtn}
                    </div>`;
                }).join('');
            });
        })
        .catch(e => console.error('loadSections:', e));
}
function escapeHtml(t){return (t||'').replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]))}

/* S88.PRODUCTS.AIBRAIN_WIRE — call-to-action dispatcher.
   intent (от ai_insights.action_data.intent) се прехвърля през ENUM-extended action_type
   към конкретен UI handler. unknown intent → product detail (safest fallback). */
function handleAiAction(actKey, productId) {
    const act = (window.__aiAct || {})[actKey] || {};
    const intent = act.intent || act.type || 'none';
    const topic = act.topic || '';
    /* TODO S89: реални handlers (order modal, transfer modal, chart navigation, dismiss API).
       За сега минимална логика: navigate_product → openProductDetail; останалите → console + product detail. */
    switch (intent) {
        case 'navigate_product':
        case 'deeplink':
            if (typeof openProductDetail === 'function') openProductDetail(productId);
            break;
        case 'navigate_chart':
            console.log('[AI Action] navigate_chart →', topic, 'product=', productId);
            if (typeof openProductDetail === 'function') openProductDetail(productId);
            break;
        case 'order_draft':
        case 'transfer_draft':
            console.log('[AI Action]', intent, '→ topic=', topic, 'product=', productId);
            if (typeof openProductDetail === 'function') openProductDetail(productId);
            break;
        case 'chat':
            console.log('[AI Action] chat →', topic, 'product=', productId);
            if (typeof openChatOverlay === 'function') openChatOverlay({topic, productId});
            else if (typeof openProductDetail === 'function') openProductDetail(productId);
            break;
        case 'dismiss':
            console.log('[AI Action] dismiss →', topic);
            /* без UI промяна за сега; S89 ще скрие card-а локално */
            break;
        case 'none':
        default:
            console.log('[AI Action] no-op intent=', intent, 'topic=', topic);
            if (typeof openProductDetail === 'function') openProductDetail(productId);
    }
}

document.addEventListener('DOMContentLoaded', () => { if (document.querySelector('#scrHome')) loadSections(); });

/* ═══ S79.FIX.B-HIDDEN-INV-UI: Store Health renderer ═══ */
function renderStoreHealth(h) {
    const dot = document.getElementById('healthDot');
    const pct = document.getElementById('healthPct');
    const fill = document.getElementById('healthFill');
    const meta = document.getElementById('healthMeta');
    if (!dot || !pct || !fill || !meta) return;
    const score = h.score || 0;
    pct.textContent = score + '%';
    fill.style.width = score + '%';
    dot.className = 'health-dot';
    fill.className = 'health-fill';
    let dotClass, fillClass;
    if (score >= 95) { dotClass='dot-green'; fillClass=''; }
    else if (score >= 80) { dotClass='dot-yellow'; fillClass='fill-yellow'; }
    else if (score >= 60) { dotClass='dot-orange'; fillClass='fill-orange'; }
    else { dotClass='dot-red'; fillClass='fill-red'; }
    dot.classList.add(dotClass);
    if (fillClass) fill.classList.add(fillClass);
    const parts = [];
    if (h.uncounted > 0) parts.push(h.uncounted + ' непреброени');
    if (h.incomplete > 0) parts.push(h.incomplete + ' недовършени');
    meta.textContent = parts.length ? parts.join(' · ') : (h.total > 0 ? 'Всичко наред' : 'Няма артикули');
    window._storeHealthData = h;
}
function openStoreHealthDetail() {
    /* S79.FIX.B-HEALTH-OV: full detail overlay (опция В) */
    const h = window._storeHealthData;
    if (!h) return;
    const score = h.score || 0;
    let statusText, statusColor, statusIcon;
    /* S79.FIX.B-HEALTH-SVG: SVG вместо emoji */
    const ICON_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    const ICON_WARN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    const ICON_ALERT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    const ICON_CRIT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    if (score >= 95) { statusText='В перфектна форма. AI знае всичко.'; statusColor='#22c55e'; statusIcon=ICON_CHECK; }
    else if (score >= 80) { statusText='Добре, но AI гадае за някои неща.'; statusColor='#eab308'; statusIcon=ICON_WARN; }
    else if (score >= 60) { statusText='AI не е сигурен. Съветите може да са неточни.'; statusColor='#f97316'; statusIcon=ICON_ALERT; }
    else { statusText='AI гадае. Основните функции са ограничени.'; statusColor='#ef4444'; statusIcon=ICON_CRIT; }
    const old = document.getElementById('healthOverlay');
    if (old) old.remove();
    const ov = document.createElement('div');
    ov.id = 'healthOverlay';
    ov.className = 'health-ov';
    ov.onclick = function(e){ if(e.target===ov) closeHealthOverlay(); };
    const fmt = (n) => n + '%';
    ov.innerHTML = `
        <div class="health-ov-box">
            <span class="mod-prod-health-line"></span>
            <button class="health-ov-close" onclick="closeHealthOverlay()" aria-label="Затвори"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            <div class="health-ov-hdr">
                <div class="health-ov-icon">${statusIcon}</div>
                <div class="health-ov-score" style="color:${statusColor}">${score}<span style="font-size:18px;opacity:.6">%</span></div>
                <div class="health-ov-title">Здраве на склада</div>
                <div class="health-ov-status">${statusText}</div>
            </div>
            <div class="health-ov-bd">
                <div class="hov-row"><span class="hov-lbl">Точност</span><div class="hov-bar"><div class="hov-fill" style="width:${h.accuracy}%"></div></div><span class="hov-val">${fmt(h.accuracy)}</span></div>
                <div class="hov-sub">Артикули преброени в последните 30 дни (тегло 40%)</div>
                <div class="hov-row"><span class="hov-lbl">Свежест</span><div class="hov-bar"><div class="hov-fill" style="width:${h.freshness}%"></div></div><span class="hov-val">${fmt(h.freshness)}</span></div>
                <div class="hov-sub">Колко скоро е било последното броене (тегло 30%)</div>
                <div class="hov-row"><span class="hov-lbl">AI увереност</span><div class="hov-bar"><div class="hov-fill" style="width:${h.confidence}%"></div></div><span class="hov-val">${fmt(h.confidence)}</span></div>
                <div class="hov-sub">Колко добре AI познава артикулите ти (тегло 30%)</div>
            </div>
            <div class="health-ov-meta">
                <div class="hov-meta-cell"><div class="hov-meta-num">${h.uncounted||0}</div><div class="hov-meta-lbl">непреброени</div></div>
                <div class="hov-meta-cell"><div class="hov-meta-num">${h.incomplete||0}</div><div class="hov-meta-lbl">недовършени</div></div>
                <div class="hov-meta-cell"><div class="hov-meta-num">${h.total||0}</div><div class="hov-meta-lbl">общо</div></div>
            </div>
            <div class="health-ov-actions">
                <button class="hov-act hov-act-primary" onclick="healthAction('quick')">
                    <div class="hov-act-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                    <div class="hov-act-txt"><div class="hov-act-ttl">Бърза проверка</div><div class="hov-act-hnt">5 артикула · 2 мин</div></div>
                </button>
                <button class="hov-act" onclick="healthAction('zone')">
                    <div class="hov-act-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                    <div class="hov-act-txt"><div class="hov-act-ttl">Зона по зона</div><div class="hov-act-hnt">Една секция от магазина</div></div>
                </button>
                <button class="hov-act" onclick="healthAction('full')">
                    <div class="hov-act-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                    <div class="hov-act-txt"><div class="hov-act-ttl">Пълно броене</div><div class="hov-act-hnt">Цялата стока</div></div>
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(ov);
    requestAnimationFrame(()=>ov.classList.add('open'));
    document.body.style.overflow='hidden';
    history.pushState({modal:'healthOv'}, '', '#healthOv');
}
function closeHealthOverlay() {
    const ov = document.getElementById('healthOverlay');
    if (!ov) return;
    ov.classList.remove('open');
    document.body.style.overflow='';
    setTimeout(()=>ov.remove(), 250);
    if (location.hash === '#healthOv') history.back();
}
function healthAction(mode) {
    /* S79.FIX.B: navigate към inventory с mode hint. inventory.php ще ги поеме в S87. */
    closeHealthOverlay();
    const url = 'inventory.php?from=health&mode=' + encodeURIComponent(mode);
    setTimeout(()=>{ window.location.href = url; }, 200);
}
(function(){
    function tryFetch(){
        if (typeof CFG === 'undefined' || !CFG.storeId) { setTimeout(tryFetch, 500); return; }
        if (window._storeHealthData) return;
        const sid = CFG.storeId;
        fetch('products.php?ajax=home_stats&store_id=' + sid)
            .then(r => r.json())
            .then(d => { if (d && d.store_health) renderStoreHealth(d.store_health); })
            .catch(()=>{});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ()=>setTimeout(tryFetch, 800));
    else setTimeout(tryFetch, 800);
})();

/* S79.FIX.B-HEALTH-OV: handle back-button to close overlay */
window.addEventListener('popstate', function(e){
    const ov = document.getElementById('healthOverlay');
    if (ov && ov.classList.contains('open')) {
        ov.classList.remove('open');
        document.body.style.overflow='';
        setTimeout(()=>ov.remove(), 250);
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
<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<?php if (file_exists(__DIR__ . "/includes/ai-chat-overlay.php")) { include __DIR__ . "/includes/ai-chat-overlay.php"; } ?>
<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
<script src="js/aibrain-modals.js"></script>

<!-- DESIGN KIT JS — theme-toggle ПРЕДИ palette (compliance v1.1 правило) -->
<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
</body>
</html>

