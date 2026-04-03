<?php
// products.php — Артикули — Сесия 16 FULL REWRITE
// 4 екрана: Начало | Доставчици | Категории | Артикули
// Sticky навигация, AI-first добавяне, Fal.ai AI Image Studio
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

$tenant_id    = $_SESSION['tenant_id'];
$role         = $_SESSION['role'] ?? 'seller';
$user_store   = $_SESSION['store_id'] ?? 0;
$can_add      = in_array($role, ['owner','manager']);
$can_see_cost = ($role === 'owner');
$can_ai_image = in_array($role, ['owner','manager']);

// ── GET params ──
$screen   = $_GET['screen'] ?? 'home';   // home|suppliers|categories|products
$sup_id   = (int)($_GET['sup'] ?? 0);
$cat_id   = (int)($_GET['cat'] ?? 0);
$search   = trim($_GET['q'] ?? '');
$filter   = $_GET['filter'] ?? 'all';
$f_store  = ($role === 'seller') ? $user_store : (int)($_GET['store'] ?? 0);

// ── ДАННИ: Доставчици със статистики ──
$suppliers_sql = "
    SELECT s.id, s.name, 
           COUNT(DISTINCT p.id) AS product_count,
           COALESCE(SUM(i.quantity * p.cost_price),0) AS capital,
           SUM(CASE WHEN COALESCE(inv_sum.total_qty,0) = 0 THEN 1 ELSE 0 END) AS out_count,
           SUM(CASE WHEN COALESCE(inv_sum.total_qty,0) > 0 AND COALESCE(inv_sum.total_qty,0) <= COALESCE(inv_sum.min_qty,0) AND COALESCE(inv_sum.min_qty,0) > 0 THEN 1 ELSE 0 END) AS low_count
    FROM suppliers s
    LEFT JOIN products p ON p.supplier_id=s.id AND p.tenant_id=s.tenant_id AND p.parent_id IS NULL AND p.is_active=1
    LEFT JOIN (
        SELECT product_id, SUM(quantity) AS total_qty, MAX(min_quantity) AS min_qty 
        FROM inventory WHERE tenant_id=? GROUP BY product_id
    ) inv_sum ON inv_sum.product_id=p.id
    LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
    WHERE s.tenant_id=? AND s.is_active=1
    GROUP BY s.id ORDER BY s.name
";
$suppliers = DB::run($suppliers_sql, [$tenant_id, $tenant_id])->fetchAll();

// ── ДАННИ: Категории глобални ──
$categories_sql = "
    SELECT c.id, c.name, c.variant_type, c.parent_id,
           COUNT(DISTINCT p.id) AS product_count,
           COUNT(DISTINCT p.supplier_id) AS supplier_count
    FROM categories c
    LEFT JOIN products p ON p.category_id=c.id AND p.tenant_id=c.tenant_id AND p.parent_id IS NULL AND p.is_active=1
    WHERE c.tenant_id=?
    GROUP BY c.id ORDER BY c.name
";
$categories = DB::run($categories_sql, [$tenant_id])->fetchAll();

// ── ДАННИ: Категории на конкретен доставчик ──
$sup_categories = [];
$sup_name = '';
$sup_capital = 0;
$sup_product_count = 0;
if ($sup_id) {
    $sup_info = DB::run("SELECT name FROM suppliers WHERE id=? AND tenant_id=?", [$sup_id, $tenant_id])->fetch();
    $sup_name = $sup_info['name'] ?? '';
    
    $sup_categories_sql = "
        SELECT c.id, c.name,
               COUNT(DISTINCT p.id) AS product_count,
               SUM(CASE WHEN COALESCE(inv_sum.total_qty,0) = 0 THEN 1 ELSE 0 END) AS out_count,
               SUM(CASE WHEN COALESCE(inv_sum.total_qty,0) > 0 AND COALESCE(inv_sum.total_qty,0) <= COALESCE(inv_sum.min_qty,0) AND COALESCE(inv_sum.min_qty,0) > 0 THEN 1 ELSE 0 END) AS low_count
        FROM products p
        JOIN categories c ON c.id=p.category_id
        LEFT JOIN (
            SELECT product_id, SUM(quantity) AS total_qty, MAX(min_quantity) AS min_qty 
            FROM inventory WHERE tenant_id=? GROUP BY product_id
        ) inv_sum ON inv_sum.product_id=p.id
        WHERE p.supplier_id=? AND p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
        GROUP BY c.id ORDER BY c.name
    ";
    $sup_categories = DB::run($sup_categories_sql, [$tenant_id, $sup_id, $tenant_id])->fetchAll();
    
    $sup_stats = DB::run("
        SELECT COUNT(DISTINCT p.id) AS cnt, COALESCE(SUM(i.quantity * p.cost_price),0) AS capital
        FROM products p LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
        WHERE p.supplier_id=? AND p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
    ", [$sup_id, $tenant_id])->fetch();
    $sup_product_count = $sup_stats['cnt'] ?? 0;
    $sup_capital = $sup_stats['capital'] ?? 0;
}

// ── ДАННИ: Артикули (филтрирани) ──
$where  = ["p.tenant_id=?", "p.parent_id IS NULL", "p.is_active=1"];
$params = [$tenant_id];

if ($sup_id && $cat_id) {
    $where[] = "p.supplier_id=?"; $params[] = $sup_id;
    $where[] = "p.category_id=?"; $params[] = $cat_id;
} elseif ($sup_id) {
    $where[] = "p.supplier_id=?"; $params[] = $sup_id;
} elseif ($cat_id) {
    $where[] = "p.category_id=?"; $params[] = $cat_id;
}

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.code LIKE ? OR p.alt_codes LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$inv_join = $f_store
    ? "LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id AND i.store_id=$f_store"
    : "LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id";

$products_sql = "
    SELECT p.*, c.name AS category_name, c.variant_type, s.name AS supplier_name,
           COALESCE(SUM(i.quantity),0) AS total_stock,
           COALESCE(MAX(i.min_quantity),0) AS min_qty,
           (SELECT COUNT(*) FROM products ch WHERE ch.parent_id=p.id AND ch.tenant_id=p.tenant_id AND ch.is_active=1) AS variant_count,
           (SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si JOIN sales sa ON sa.id=si.sale_id WHERE si.product_id=p.id AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS sold_30d,
           (SELECT DATEDIFF(NOW(), MAX(sa2.created_at)) FROM sale_items si2 JOIN sales sa2 ON sa2.id=si2.sale_id WHERE si2.product_id=p.id) AS days_no_sale
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    LEFT JOIN suppliers s ON s.id=p.supplier_id
    $inv_join
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id ORDER BY p.name ASC LIMIT 200
";
$all_products = DB::run($products_sql, $params)->fetchAll();

// ── Броячи за табове ──
$cnt_low = 0; $cnt_out = 0;
foreach ($all_products as $p) {
    if ($p['total_stock'] == 0) $cnt_out++;
    elseif ($p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']) $cnt_low++;
}

if ($filter === 'low') $products = array_values(array_filter($all_products, fn($p) => $p['total_stock'] > 0 && $p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']));
elseif ($filter === 'out') $products = array_values(array_filter($all_products, fn($p) => $p['total_stock'] == 0));
else $products = $all_products;

// ── HOME статистики ──
$home_stats = DB::run("
    SELECT 
        COALESCE(SUM(i.quantity * p.cost_price),0) AS total_capital,
        CASE WHEN SUM(i.quantity * p.retail_price) > 0 
             THEN ROUND((SUM(i.quantity * p.retail_price) - SUM(i.quantity * p.cost_price)) / SUM(i.quantity * p.retail_price) * 100, 1)
             ELSE 0 END AS avg_margin
    FROM products p 
    JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
    WHERE p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
", [$tenant_id])->fetch();

// ── Zombie стока (>45 дни без продажба, има наличност) ──
$zombies = DB::run("
    SELECT p.id, p.name, COALESCE(SUM(i.quantity),0) AS qty,
           COALESCE(SUM(i.quantity * p.cost_price),0) AS frozen_capital,
           DATEDIFF(NOW(), COALESCE(
               (SELECT MAX(sa.created_at) FROM sale_items si JOIN sales sa ON sa.id=si.sale_id WHERE si.product_id=p.id),
               p.created_at
           )) AS days_stuck
    FROM products p
    JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
    WHERE p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
    GROUP BY p.id
    HAVING qty > 0 AND days_stuck > 45
    ORDER BY days_stuck DESC LIMIT 10
", [$tenant_id])->fetchAll();

$zombie_total = array_sum(array_column($zombies, 'frozen_capital'));

// ── Свършващи (под минимума) ──
$running_low = DB::run("
    SELECT p.id, p.name, COALESCE(SUM(i.quantity),0) AS qty, MAX(i.min_quantity) AS min_qty
    FROM products p
    JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
    WHERE p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
    GROUP BY p.id
    HAVING qty > 0 AND min_qty > 0 AND qty <= min_qty
    ORDER BY qty ASC LIMIT 10
", [$tenant_id])->fetchAll();

// ── Топ 5 хитове ──
$top_sellers = DB::run("
    SELECT p.id, p.name, COALESCE(SUM(si.quantity),0) AS sold
    FROM sale_items si
    JOIN sales sa ON sa.id=si.sale_id
    JOIN products p ON p.id=si.product_id
    WHERE sa.tenant_id=? AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND p.parent_id IS NULL
    GROUP BY p.id ORDER BY sold DESC LIMIT 5
", [$tenant_id])->fetchAll();

// ── Бавно движещи се ──
$slow_movers = DB::run("
    SELECT p.id, p.name,
           DATEDIFF(NOW(), COALESCE(
               (SELECT MAX(sa.created_at) FROM sale_items si JOIN sales sa ON sa.id=si.sale_id WHERE si.product_id=p.id),
               p.created_at
           )) AS days_no_sale
    FROM products p
    JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id
    WHERE p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
    GROUP BY p.id
    HAVING SUM(i.quantity) > 0 AND days_no_sale BETWEEN 25 AND 45
    ORDER BY days_no_sale DESC LIMIT 5
", [$tenant_id])->fetchAll();

// ── Доставчик статистики (за екран 2) ──
$sup_top_sold = DB::run("
    SELECT s.name, COALESCE(SUM(si.quantity),0) AS sold
    FROM sale_items si JOIN sales sa ON sa.id=si.sale_id
    JOIN products p ON p.id=si.product_id
    JOIN suppliers s ON s.id=p.supplier_id
    WHERE sa.tenant_id=? AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND p.parent_id IS NULL
    GROUP BY s.id ORDER BY sold DESC LIMIT 3
", [$tenant_id])->fetchAll();

$sup_top_profit = [];
if ($can_see_cost) {
    $sup_top_profit = DB::run("
        SELECT s.name, COALESCE(SUM(si.quantity * (si.price - COALESCE(si.cost_price,0))),0) AS profit
        FROM sale_items si JOIN sales sa ON sa.id=si.sale_id
        JOIN products p ON p.id=si.product_id
        JOIN suppliers s ON s.id=p.supplier_id
        WHERE sa.tenant_id=? AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND p.parent_id IS NULL
        GROUP BY s.id ORDER BY profit DESC LIMIT 3
    ", [$tenant_id])->fetchAll();
}

// ── Stores за филтър ──
$stores = DB::run("SELECT id, name FROM stores WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll();

// ── Onboarding units ──
$tenant_cfg = DB::run("SELECT units_config, ai_credits_bg, ai_credits_tryon FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];
$ai_credits_bg = (int)($tenant_cfg['ai_credits_bg'] ?? 0);
$ai_credits_tryon = (int)($tenant_cfg['ai_credits_tryon'] ?? 0);

// ── Breadcrumb ──
$cat_name = '';
if ($cat_id) {
    $cat_info = DB::run("SELECT name FROM categories WHERE id=? AND tenant_id=?", [$cat_id, $tenant_id])->fetch();
    $cat_name = $cat_info['name'] ?? '';
}

// ── Cross-link: колко доставчика имат тази категория ──
$cross_sup_count = 0;
$cross_total = 0;
if ($cat_id && $sup_id) {
    $cross = DB::run("
        SELECT COUNT(DISTINCT p.supplier_id) AS sup_cnt, COUNT(DISTINCT p.id) AS total
        FROM products p WHERE p.category_id=? AND p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1
    ", [$cat_id, $tenant_id])->fetch();
    $cross_sup_count = $cross['sup_cnt'] ?? 0;
    $cross_total = $cross['total'] ?? 0;
}

// ── Формат валута ──
function fmtEur($v) { return number_format((float)$v, 2, ',', '.') . ' €'; }
function fmtInt($v) { return number_format((float)$v, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Артикули — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
:root{--nav-h:64px;--sticky-h:62px}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#030712;color:#e2e8f0;font-family:'Montserrat',system-ui,sans-serif;margin:0;overflow-x:hidden;padding-bottom:calc(var(--nav-h) + var(--sticky-h) + 12px)}

/* ── SVG фонове (Cruip) ── */
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);width:846px;height:594px;background:url('./images/page-illustration.svg') no-repeat center;pointer-events:none;z-index:0;opacity:.5}
body::after{content:'';position:fixed;top:400px;left:20%;width:760px;height:668px;background:url('./images/blurred-shape.svg') no-repeat center;pointer-events:none;z-index:0;opacity:.3}

/* ── Хедър ── */
.hdr{position:sticky;top:0;z-index:50;background:rgba(3,7,18,.93);backdrop-filter:blur(20px);border-bottom:1px solid rgba(99,102,241,.12);padding:12px 16px 0}
.hdr-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.page-title{font-size:20px;font-weight:800;font-family:'Nacelle',sans-serif;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}

.btn-add{display:flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:12px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 0 20px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.16)}
.btn-add:active{transform:scale(.96)}

/* ── Търсене ── */
.search-row{display:flex;gap:8px;margin-bottom:10px;align-items:center}
.search-wrap{position:relative;flex:1}
.search-input{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#e2e8f0;font-size:13px;padding:10px 74px 10px 14px;font-family:inherit;outline:none;transition:border-color .2s}
.search-input::placeholder{color:#4b5563}
.search-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-icons{position:absolute;right:8px;top:50%;transform:translateY(-50%);display:flex;gap:5px}
.icon-btn{width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc}
.icon-btn:active{background:rgba(99,102,241,.3)}
.filter-btn{width:38px;height:38px;border-radius:12px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc;flex-shrink:0}

/* ── Табове ── */
.ftabs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding-bottom:10px}
.ftabs::-webkit-scrollbar{display:none}
.ftab{flex-shrink:0;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:700;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:inherit;white-space:nowrap}
.ftab.active{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent;color:#fff;box-shadow:0 0 14px rgba(99,102,241,.35)}
.badge{display:inline-flex;min-width:16px;height:16px;border-radius:8px;font-size:10px;font-weight:800;padding:0 4px;align-items:center;justify-content:center;margin-left:4px}
.badge-w{background:rgba(245,158,11,.15);color:#f59e0b}
.badge-d{background:rgba(239,68,68,.15);color:#ef4444}
.badge-n{background:rgba(255,255,255,.15);color:#fff}

/* ── Бърз поглед карти ── */
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.stat-card{background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;padding:10px 12px}
.stat-label{font-size:10px;color:#6b7280;font-weight:600}
.stat-val{font-size:18px;font-weight:900;color:#f1f5f9}

/* ── Collapse секции ── */
.collapse-box{border-radius:14px;margin-bottom:10px;overflow:hidden}
.collapse-header{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;cursor:pointer}
.collapse-header:active{opacity:.8}
.collapse-icon{display:flex;align-items:center;gap:8px}
.collapse-emoji{font-size:15px}
.collapse-title{font-size:12px;font-weight:700}
.collapse-sub{font-size:11px;color:#6b7280}
.collapse-arrow{transition:transform .2s}
.collapse-body{display:none;padding:0 14px 12px}
.collapse-body.open{display:block}
.collapse-row{display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid rgba(255,255,255,.04);font-size:12px}
.collapse-action{padding:8px;border-radius:10px;font-size:11px;font-weight:700;text-align:center;margin-top:6px;cursor:pointer}

.cb-red{background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15)}
.cb-red .collapse-title{color:#ef4444}
.cb-red .collapse-arrow{color:#ef4444}
.cb-red .collapse-row span:last-child{color:#ef4444;font-weight:700}
.cb-red .collapse-action{background:rgba(239,68,68,.1);color:#ef4444}

.cb-yellow{background:rgba(245,158,11,.05);border:1px solid rgba(245,158,11,.15)}
.cb-yellow .collapse-title{color:#f59e0b}
.cb-yellow .collapse-arrow{color:#f59e0b}
.cb-yellow .collapse-row span:last-child{color:#f59e0b;font-weight:700}
.cb-yellow .collapse-action{background:rgba(245,158,11,.1);color:#f59e0b}

.cb-green{background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15)}
.cb-green .collapse-title{color:#22c55e}
.cb-green .collapse-arrow{color:#22c55e}
.cb-green .collapse-row span:last-child{color:#22c55e;font-weight:700}

.cb-purple{background:rgba(139,92,246,.05);border:1px solid rgba(139,92,246,.15)}
.cb-purple .collapse-title{color:#8b5cf6}
.cb-purple .collapse-arrow{color:#8b5cf6}
.cb-purple .collapse-row span:last-child{color:#8b5cf6;font-weight:700}
.cb-purple .collapse-action{background:rgba(139,92,246,.1);color:#8b5cf6}

/* ── Доставчик / Категория карти (swipe) ── */
.swipe-section{margin-bottom:18px}
.swipe-label{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.swipe-label-text{font-size:11px;font-weight:700;letter-spacing:1px}
.swipe-hint{font-size:11px;color:#4b5563}
.swipe-row{display:flex;gap:8px;overflow-x:auto;padding-bottom:6px;-webkit-overflow-scrolling:touch;scroll-snap-type:x mandatory;scrollbar-width:none}
.swipe-row::-webkit-scrollbar{display:none}
.swipe-card{min-width:150px;flex-shrink:0;scroll-snap-align:start;border-radius:14px;padding:12px;cursor:pointer;transition:transform .15s}
.swipe-card:active{transform:scale(.97)}
.sc-indigo{background:rgba(99,102,241,.07);border:1px solid rgba(99,102,241,.18)}
.sc-purple{background:rgba(139,92,246,.07);border:1px solid rgba(139,92,246,.18)}
.sc-name{font-size:14px;font-weight:700;color:#f1f5f9;margin-bottom:2px}
.sc-info{font-size:11px;color:#6b7280;margin-bottom:6px}
.sc-badges{display:flex;gap:3px;flex-wrap:wrap}
.sc-badge{font-size:10px;padding:2px 6px;border-radius:6px;font-weight:700}
.scb-ok{background:rgba(34,197,94,.12);color:#22c55e}
.scb-low{background:rgba(245,158,11,.12);color:#f59e0b}
.scb-out{background:rgba(239,68,68,.12);color:#ef4444}

/* ── Drill-down хедър ── */
.dd-header{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.dd-back{width:32px;height:32px;border-radius:10px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc;flex-shrink:0}
.dd-back:active{background:rgba(99,102,241,.25)}
.dd-breadcrumb{font-size:11px;color:#6b7280}
.dd-breadcrumb a{color:#6b7280;text-decoration:none}
.dd-breadcrumb a:hover{color:#a5b4fc}
.dd-title{font-size:16px;font-weight:800;color:#f1f5f9}
.dd-subtitle{font-size:11px;color:#6b7280}

/* ── Cross-link ── */
.cross-link{padding:8px 12px;background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.15);border-radius:10px;font-size:11px;color:#c4b5fd;display:flex;align-items:center;justify-content:space-between;cursor:pointer;margin-bottom:12px}
.cross-link:active{background:rgba(139,92,246,.15)}

/* ── Категория ред (в drill-down) ── */
.cat-row{border-radius:14px;margin-bottom:8px;background:rgba(15,15,35,.85);border:1px solid rgba(99,102,241,.12);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:transform .15s}
.cat-row:active{transform:scale(.98)}
.cat-row-name{font-size:15px;font-weight:700;color:#f1f5f9}
.cat-row-info{font-size:12px;color:#6b7280}
.cat-row-badges{display:flex;align-items:center;gap:6px}

/* ── Артикул карта ── */
.pcard{position:relative;border-radius:16px;margin-bottom:8px;overflow:hidden;background:rgba(15,15,35,.85);border:1px solid rgba(99,102,241,.12);cursor:pointer;transition:transform .15s;animation:cIn .35s ease both}
.pcard:active{transform:scale(.98)}
.pcard-inner{display:flex;align-items:center;padding:10px 14px 10px 18px;position:relative}
.stripe{position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:16px 0 0 16px}
.sg{background:linear-gradient(to bottom,#22c55e,#16a34a)}
.sy{background:linear-gradient(to bottom,#f59e0b,#d97706)}
.sr{background:linear-gradient(to bottom,#ef4444,#dc2626)}
.pcard-thumb{width:44px;height:44px;border-radius:10px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.15);display:flex;align-items:center;justify-content:center;margin-right:10px;flex-shrink:0;overflow:hidden}
.pcard-thumb img{width:100%;height:100%;object-fit:cover}
.pcard-info{flex:1;min-width:0}
.pcard-name{font-size:14px;font-weight:700;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pcard-meta{font-size:11px;color:#6b7280}
.pcard-tags{display:flex;gap:4px;margin-top:4px;flex-wrap:wrap}
.ptag{font-size:11px;font-weight:700;border-radius:6px;padding:1px 7px}
.ptag-price{color:#a5b4fc;background:rgba(99,102,241,.1)}
.ptag-ok{color:#22c55e;background:rgba(34,197,94,.1)}
.ptag-low{color:#f59e0b;background:rgba(245,158,11,.1)}
.ptag-out{color:#ef4444;background:rgba(239,68,68,.1)}
.ptag-var{color:#8b5cf6;background:rgba(139,92,246,.1)}
.pcard-qty{text-align:right;flex-shrink:0;margin-left:8px}
.pcard-num{font-size:20px;font-weight:900;line-height:1}
.pcard-unit{font-size:10px;color:#6b7280;font-weight:600}

/* ── Supplier stats (екран 2 отдолу) ── */
.stats-section{margin-top:18px}
.stats-title{font-size:11px;font-weight:700;color:#6366f1;letter-spacing:1px;margin-bottom:8px}
.stats-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(99,102,241,.06);font-size:12px}
.stats-row:last-child{border-bottom:none}
.stats-row-name{color:#e2e8f0}
.stats-row-val{font-weight:700}

/* ── Sticky навигация (4 бутона) ── */
.sticky-nav{position:fixed;bottom:var(--nav-h);left:0;right:0;z-index:90;background:linear-gradient(to top,rgba(3,7,18,.98) 80%,rgba(3,7,18,0));padding:10px 12px 8px}
.sticky-btns{display:flex;gap:6px}
.sticky-btn{flex:1;padding:8px 4px;border-radius:12px;display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;border:1px solid rgba(99,102,241,.15);background:rgba(15,15,40,.6);transition:all .15s}
.sticky-btn:active{transform:scale(.95)}
.sticky-btn.active{background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.4);box-shadow:0 0 12px rgba(99,102,241,.2)}
.sticky-btn svg{width:18px;height:18px;color:#6b7280}
.sticky-btn.active svg{color:#a5b4fc}
.sticky-btn-label{font-size:10px;font-weight:700;color:#6b7280}
.sticky-btn.active .sticky-btn-label{color:#a5b4fc}

/* ── Bottom nav ── */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.97);backdrop-filter:blur(20px);border-top:1px solid rgba(99,102,241,.1);display:flex;align-items:center;z-index:100}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:8px 0;text-decoration:none;border:none;background:transparent;cursor:pointer}
.ni svg{width:22px;height:22px;color:#3f3f5a}
.ni span{font-size:10px;font-weight:600;color:#3f3f5a}
.ni.active svg,.ni.active span{color:#6366f1}

/* ── Empty state ── */
.empty{text-align:center;padding:40px 20px;color:#4b5563}
.empty h3{font-size:16px;font-weight:700;color:#374151;margin:0 0 6px}
.empty p{font-size:13px;margin:0}

/* ── Toast ── */
.toast{position:fixed;bottom:140px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1}

/* ── AI плаващ бутон ── */
.ai-fab{position:fixed;bottom:160px;right:16px;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 20px rgba(99,102,241,.5),0 0 40px rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:80;animation:fabPulse 3s ease-in-out infinite;transition:all .3s}
.ai-fab:active{transform:scale(.9)}
.ai-fab svg{width:24px;height:24px;color:#fff}
@keyframes fabPulse{0%,100%{box-shadow:0 4px 20px rgba(99,102,241,.5),0 0 40px rgba(99,102,241,.2)}50%{box-shadow:0 4px 30px rgba(99,102,241,.7),0 0 60px rgba(99,102,241,.35)}}

/* ── Анимации ── */
@keyframes cIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.pcard:nth-child(1){animation-delay:.03s}.pcard:nth-child(2){animation-delay:.06s}.pcard:nth-child(3){animation-delay:.09s}.pcard:nth-child(n+4){animation-delay:.12s}

/* ── Content area ── */
.content{padding:12px 16px;position:relative;z-index:1}
</style>
</head>
<body>

<!-- ═══════════ ХЕДЪР ═══════════ -->
<div class="hdr">
  <div class="hdr-top">
    <?php if ($screen === 'home'): ?>
      <h1 class="page-title">Артикули</h1>
    <?php else: ?>
      <div class="dd-header" style="margin-bottom:0">
        <a href="<?php
          if ($screen === 'products' && $sup_id && $cat_id) echo "products.php?screen=categories&sup=$sup_id";
          elseif ($screen === 'products' && $cat_id) echo "products.php?screen=home";
          elseif ($screen === 'categories' && $sup_id) echo "products.php?screen=suppliers";
          else echo "products.php?screen=home";
        ?>" class="dd-back">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div>
          <?php if ($screen === 'suppliers'): ?>
            <div class="dd-title">Доставчици</div>
          <?php elseif ($screen === 'categories'): ?>
            <?php if ($sup_id): ?>
              <div class="dd-breadcrumb"><a href="products.php?screen=suppliers">Доставчици</a> ></div>
              <div class="dd-title"><?= htmlspecialchars($sup_name) ?></div>
              <div class="dd-subtitle"><?= $sup_product_count ?> артикула · <?= fmtEur($sup_capital) ?></div>
            <?php else: ?>
              <div class="dd-title">Категории</div>
            <?php endif; ?>
          <?php elseif ($screen === 'products'): ?>
            <?php if ($sup_id && $cat_id): ?>
              <div class="dd-breadcrumb">
                <a href="products.php?screen=suppliers">Дост.</a> > 
                <a href="products.php?screen=categories&sup=<?= $sup_id ?>"><?= htmlspecialchars($sup_name) ?></a> > <?= htmlspecialchars($cat_name) ?>
              </div>
            <?php elseif ($cat_id): ?>
              <div class="dd-breadcrumb"><a href="products.php?screen=home">Кат.</a> > <?= htmlspecialchars($cat_name) ?></div>
            <?php endif; ?>
            <div class="dd-title">Артикули</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($can_add): ?>
    <button class="btn-add" onclick="openAddModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Добави
    </button>
    <?php endif; ?>
  </div>

  <?php if (in_array($screen, ['home','products'])): ?>
  <div class="search-row">
    <div class="search-wrap">
      <form method="GET" id="searchForm">
        <input type="hidden" name="screen" value="<?= htmlspecialchars($screen) ?>">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <?php if ($sup_id): ?><input type="hidden" name="sup" value="<?= $sup_id ?>"><?php endif; ?>
        <?php if ($cat_id): ?><input type="hidden" name="cat" value="<?= $cat_id ?>"><?php endif; ?>
        <input type="text" name="q" class="search-input" placeholder="Търси по име, баркод, код..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      </form>
      <div class="search-icons">
        <button type="button" class="icon-btn" onclick="openCamera('search')">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17h7m-3.5-3.5v7"/></svg>
        </button>
        <button type="button" class="icon-btn" onclick="startVoiceSearch()">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
        </button>
      </div>
    </div>
    <div class="filter-btn" onclick="openFilterDrawer()">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 12h10M11 20h2"/></svg>
    </div>
  </div>
  <div class="ftabs">
    <button class="ftab <?= $filter==='all'?'active':'' ?>" onclick="setFilter('all')">Всички <span class="badge badge-n"><?= count($all_products) ?></span></button>
    <button class="ftab <?= $filter==='low'?'active':'' ?>" onclick="setFilter('low')">Ниска нал. <span class="badge <?= $cnt_low>0?'badge-w':'' ?>"><?= $cnt_low ?></span></button>
    <button class="ftab <?= $filter==='out'?'active':'' ?>" onclick="setFilter('out')">Изчерпани <span class="badge <?= $cnt_out>0?'badge-d':'' ?>"><?= $cnt_out ?></span></button>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════ СЪДЪРЖАНИЕ ═══════════ -->
<div class="content">

<?php if ($screen === 'home'): ?>
<!-- ══════ ЕКРАН 1: НАЧАЛО ══════ -->

<!-- Бърз поглед -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Капитал</div>
    <div class="stat-val"><?= fmtEur($home_stats['total_capital'] ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-val" style="color:#a5b4fc"><?= $home_stats['avg_margin'] ?? 0 ?>%</div>
    <div class="stat-label">Среден марж</div>
  </div>
</div>

<!-- Zombie -->
<?php if (!empty($zombies)): ?>
<div class="collapse-box cb-red" onclick="toggleCollapse(this)">
  <div class="collapse-header">
    <div class="collapse-icon"><span class="collapse-emoji">💀</span><div><div class="collapse-title">Zombie стока</div><div class="collapse-sub"><?= count($zombies) ?> артикула · <?= fmtEur($zombie_total) ?> замразени</div></div></div>
    <svg class="collapse-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
  </div>
  <div class="collapse-body">
    <?php foreach($zombies as $z): ?>
    <div class="collapse-row"><span style="color:#e2e8f0"><?= htmlspecialchars($z['name']) ?></span><span><?= $z['days_stuck'] ?>д · <?= fmtEur($z['frozen_capital']) ?></span></div>
    <?php endforeach; ?>
    <div class="collapse-action">💀 Намали с -30% или направи пакет</div>
  </div>
</div>
<?php endif; ?>

<!-- Свършващи -->
<?php if (!empty($running_low)): ?>
<div class="collapse-box cb-yellow" onclick="toggleCollapse(this)">
  <div class="collapse-header">
    <div class="collapse-icon"><span class="collapse-emoji">⚠</span><div><div class="collapse-title">Свършват скоро</div><div class="collapse-sub"><?= count($running_low) ?> артикула под минимума</div></div></div>
    <svg class="collapse-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
  </div>
  <div class="collapse-body">
    <?php foreach($running_low as $r): ?>
    <div class="collapse-row"><span style="color:#e2e8f0"><?= htmlspecialchars($r['name']) ?></span><span><?= fmtInt($r['qty']) ?> от <?= fmtInt($r['min_qty']) ?></span></div>
    <?php endforeach; ?>
    <div class="collapse-action">⚠ Поръчай всички наведнъж</div>
  </div>
</div>
<?php endif; ?>

<!-- Топ 5 хитове -->
<?php if (!empty($top_sellers)): ?>
<div class="collapse-box cb-green" onclick="toggleCollapse(this)">
  <div class="collapse-header">
    <div class="collapse-icon"><span class="collapse-emoji">🔥</span><div><div class="collapse-title">Топ 5 хитове</div><div class="collapse-sub">последни 30 дни</div></div></div>
    <svg class="collapse-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
  </div>
  <div class="collapse-body">
    <?php foreach($top_sellers as $i => $t): ?>
    <div class="collapse-row"><span style="color:#e2e8f0"><span style="color:#6b7280"><?= $i+1 ?>.</span> <?= htmlspecialchars($t['name']) ?></span><span><?= fmtInt($t['sold']) ?> бр</span></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Бавно движещи се -->
<?php if (!empty($slow_movers)): ?>
<div class="collapse-box cb-purple" onclick="toggleCollapse(this)">
  <div class="collapse-header">
    <div class="collapse-icon"><span class="collapse-emoji">🐌</span><div><div class="collapse-title">Бавно движещи се</div><div class="collapse-sub"><?= count($slow_movers) ?> артикула без продажба 25+ дни</div></div></div>
    <svg class="collapse-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
  </div>
  <div class="collapse-body">
    <?php foreach($slow_movers as $sm): ?>
    <div class="collapse-row"><span style="color:#e2e8f0"><?= htmlspecialchars($sm['name']) ?></span><span><?= $sm['days_no_sale'] ?>д</span></div>
    <?php endforeach; ?>
    <div class="collapse-action">🐌 Промоция -20% за 2 седмици</div>
  </div>
</div>
<?php endif; ?>

<?php elseif ($screen === 'suppliers'): ?>
<!-- ══════ ЕКРАН 2: ДОСТАВЧИЦИ ══════ -->

<div class="swipe-section">
  <div class="swipe-label">
    <div class="swipe-label-text" style="color:#6366f1">ДОСТАВЧИЦИ</div>
    <div class="swipe-hint">← swipe →</div>
  </div>
  <div class="swipe-row">
    <?php foreach($suppliers as $s): ?>
    <a href="products.php?screen=categories&sup=<?= $s['id'] ?>" class="swipe-card sc-indigo" style="text-decoration:none">
      <div class="sc-name"><?= htmlspecialchars($s['name']) ?></div>
      <div class="sc-info"><?= $s['product_count'] ?> артикула</div>
      <div class="sc-badges">
        <?php $ok = $s['product_count'] - $s['low_count'] - $s['out_count']; if($ok > 0): ?><span class="sc-badge scb-ok"><?= $ok ?></span><?php endif; ?>
        <?php if($s['low_count'] > 0): ?><span class="sc-badge scb-low"><?= $s['low_count'] ?></span><?php endif; ?>
        <?php if($s['out_count'] > 0): ?><span class="sc-badge scb-out"><?= $s['out_count'] ?></span><?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Статистики за доставчици -->
<?php if (!empty($sup_top_sold)): ?>
<div class="stats-section">
  <div class="stats-title">🔥 НАЙ-МНОГО ПРОДАЖБИ (30 ДНИ)</div>
  <?php foreach($sup_top_sold as $i => $ss): ?>
  <div class="stats-row">
    <span class="stats-row-name"><span style="color:#6b7280"><?= $i+1 ?>.</span> <?= htmlspecialchars($ss['name']) ?></span>
    <span class="stats-row-val" style="color:#22c55e"><?= fmtInt($ss['sold']) ?> бр</span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($can_see_cost && !empty($sup_top_profit)): ?>
<div class="stats-section" style="margin-top:14px">
  <div class="stats-title">💰 НАЙ-МНОГО ПЕЧАЛБА (30 ДНИ)</div>
  <?php foreach($sup_top_profit as $i => $sp): ?>
  <div class="stats-row">
    <span class="stats-row-name"><span style="color:#6b7280"><?= $i+1 ?>.</span> <?= htmlspecialchars($sp['name']) ?></span>
    <span class="stats-row-val" style="color:#a5b4fc"><?= fmtEur($sp['profit']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($screen === 'categories'): ?>
<!-- ══════ ЕКРАН 3: КАТЕГОРИИ ══════ -->

<?php if ($sup_id): ?>
  <!-- Категории на конкретен доставчик -->
  <?php if (empty($sup_categories)): ?>
    <div class="empty"><h3>Няма категории</h3><p>Този доставчик няма артикули</p></div>
  <?php else: ?>
    <?php foreach($sup_categories as $sc): ?>
    <a href="products.php?screen=products&sup=<?= $sup_id ?>&cat=<?= $sc['id'] ?>" class="cat-row" style="text-decoration:none">
      <div>
        <div class="cat-row-name"><?= htmlspecialchars($sc['name']) ?></div>
        <div class="cat-row-info"><?= $sc['product_count'] ?> артикула</div>
      </div>
      <div class="cat-row-badges">
        <?php if($sc['low_count'] > 0): ?><span class="sc-badge scb-low" style="font-size:10px"><?= $sc['low_count'] ?></span><?php endif; ?>
        <?php if($sc['out_count'] > 0): ?><span class="sc-badge scb-out" style="font-size:10px"><?= $sc['out_count'] ?></span><?php endif; ?>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4b5563" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>
    <?php endforeach; ?>
  <?php endif; ?>
<?php else: ?>
  <!-- Глобални категории -->
  <div class="swipe-section">
    <div class="swipe-label">
      <div class="swipe-label-text" style="color:#8b5cf6">КАТЕГОРИИ (ВСИЧКИ ДОСТАВЧИЦИ)</div>
      <div class="swipe-hint">← swipe →</div>
    </div>
    <div class="swipe-row">
      <?php foreach($categories as $c): ?>
      <a href="products.php?screen=products&cat=<?= $c['id'] ?>" class="swipe-card sc-purple" style="text-decoration:none">
        <div class="sc-name"><?= htmlspecialchars($c['name']) ?></div>
        <div class="sc-info"><?= $c['product_count'] ?> арт. · <?= $c['supplier_count'] ?> дост.</div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php elseif ($screen === 'products'): ?>
<!-- ══════ ЕКРАН 4: АРТИКУЛИ ══════ -->

<?php if ($sup_id && $cat_id && $cross_sup_count > 1): ?>
<a href="products.php?screen=products&cat=<?= $cat_id ?>" class="cross-link">
  <span>Виж <strong>всички <?= htmlspecialchars($cat_name) ?></strong> (<?= $cross_sup_count ?> доставчика · <?= $cross_total ?> арт.)</span>
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
</a>
<?php endif; ?>

<?php if (empty($products)): ?>
<div class="empty">
  <h3><?= $search ? 'Няма резултати' : 'Нямаш артикули' ?></h3>
  <p><?= $search ? "Нищо не отговаря на \"".htmlspecialchars($search)."\"" : ($can_add ? 'Натисни + Добави' : '') ?></p>
</div>
<?php else: foreach($products as $p):
  $qty = (float)$p['total_stock']; $min = (float)$p['min_qty'];
  $iz = $qty == 0; $lo = !$iz && $min > 0 && $qty <= $min;
  if ($iz) { $sc='sr'; $tt='Изчерпан'; $tc='ptag-out'; $qc='#ef4444'; }
  elseif ($lo) { $sc='sy'; $tt='Ниска нал.'; $tc='ptag-low'; $qc='#f59e0b'; }
  else { $sc='sg'; $tt='OK'; $tc='ptag-ok'; $qc='#22c55e'; }
?>
<div class="pcard" onclick="openProductDetail(<?= $p['id'] ?>)">
  <div class="pcard-inner">
    <div class="stripe <?= $sc ?>"></div>
    <div class="pcard-thumb">
      <?php if (!empty($p['image'])): ?>
        <img src="<?= htmlspecialchars($p['image']) ?>" alt="">
      <?php else: ?>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#4b5563" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
      <?php endif; ?>
    </div>
    <div class="pcard-info">
      <div class="pcard-name"><?= htmlspecialchars($p['name']) ?></div>
      <div class="pcard-meta"><?= htmlspecialchars($p['supplier_name'] ?? '') ?><?= $p['supplier_name'] && $p['category_name'] ? ' · ' : '' ?><?= htmlspecialchars($p['category_name'] ?? '') ?><?= $p['variant_count'] > 0 ? ' · '.$p['variant_count'].' вар.' : '' ?></div>
      <div class="pcard-tags">
        <span class="ptag ptag-price"><?= fmtEur($p['retail_price'] ?? 0) ?></span>
        <span class="ptag <?= $tc ?>"><?= $tt ?></span>
        <?php if ($p['variant_count'] > 0): ?><span class="ptag ptag-var"><?= $p['variant_count'] ?> вар.</span><?php endif; ?>
      </div>
    </div>
    <div class="pcard-qty">
      <div class="pcard-num" style="color:<?= $qc ?>"><?= fmtInt($qty) ?></div>
      <div class="pcard-unit"><?= htmlspecialchars($p['unit'] ?? 'бр') ?></div>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>

<?php endif; ?>

</div><!-- /content -->

<!-- ═══════════ AI FAB ═══════════ -->
<div class="ai-fab" onclick="openAIChat()">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
</div>

<!-- ═══════════ STICKY 4 БУТОНА ═══════════ -->
<div class="sticky-nav">
  <div class="sticky-btns">
    <a href="products.php?screen=home" class="sticky-btn <?= $screen==='home'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span class="sticky-btn-label">Начало</span>
    </a>
    <a href="products.php?screen=suppliers" class="sticky-btn <?= $screen==='suppliers'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      <span class="sticky-btn-label">Доставчици</span>
    </a>
    <a href="products.php?screen=categories" class="sticky-btn <?= $screen==='categories' && !$sup_id ?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
      <span class="sticky-btn-label">Категории</span>
    </a>
    <a href="products.php?screen=products" class="sticky-btn <?= $screen==='products'?'active':'' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      <span class="sticky-btn-label">Артикули</span>
    </a>
  </div>
</div>

<!-- ═══════════ BOTTOM NAV ═══════════ -->
<nav class="bnav">
  <a href="chat.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg><span>Чат</span></a>
  <a href="warehouse.php" class="ni active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg><span>Склад</span></a>
  <a href="stats.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистики</span></a>
  <a href="actions.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>

<div class="toast" id="toast"></div>

<script>
// ── Collapse toggle ──
function toggleCollapse(box) {
  const body = box.querySelector('.collapse-body');
  const arrow = box.querySelector('.collapse-arrow');
  const isOpen = body.classList.contains('open');
  body.classList.toggle('open');
  arrow.style.transform = isOpen ? 'rotate(0)' : 'rotate(180deg)';
}

// ── Filter tab ──
function setFilter(f) {
  const u = new URL(window.location);
  u.searchParams.set('filter', f);
  window.location = u;
}

// ── Product detail ──
function openProductDetail(id) {
  // TODO: drawer с детайли + вариации + AI снимка
  window.location = 'products.php?screen=products&detail=' + id;
}

// ── Voice search ──
function startVoiceSearch() {
  if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) { showToast('Гласът не се поддържа'); return; }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  const r = new SR(); r.lang = 'bg-BG'; r.start();
  r.onresult = e => {
    document.querySelector('.search-input').value = e.results[0][0].transcript;
    document.getElementById('searchForm').submit();
  };
}

// ── Camera (barcode scanner) ──
async function openCamera(target) {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    // TODO: fullscreen camera overlay with BarcodeDetector
    showToast('Камера отворена');
    if ('BarcodeDetector' in window) {
      const video = document.createElement('video');
      video.srcObject = stream; video.play();
      const bd = new BarcodeDetector();
      const iv = setInterval(async () => {
        try {
          const codes = await bd.detect(video);
          if (codes.length) {
            clearInterval(iv);
            stream.getTracks().forEach(t => t.stop());
            if (target === 'search') {
              document.querySelector('.search-input').value = codes[0].rawValue;
              document.getElementById('searchForm').submit();
            }
          }
        } catch(e) {}
      }, 300);
    }
  } catch(e) { showToast('Камерата не е достъпна'); }
}

// ── AI Chat popup ──
function openAIChat() {
  // TODO: малък popup с текстово поле + микрофон
  window.location = 'chat.php?context=products';
}

// ── Add modal ──
function openAddModal() {
  // TODO: AI-first add flow с голям пулсиращ бутон
  showToast('Добави артикул — скоро');
}

// ── Filter drawer ──
function openFilterDrawer() {
  // TODO: drawer с филтри
  showToast('Филтри — скоро');
}

// ── Toast ──
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
</script>
</body>
</html>
