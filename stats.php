<?php
// stats.php — Статистики
// 5 таба: Обзор / Продажби / Стоки / Финанси / Аномалии
// Drawer при натискане на карта + AI препоръка
// Role-based: owner вижда всичко, manager без печалба, seller само своя обект

session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
$pdo = DB::get();

$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$store_id   = $_SESSION['store_id'];
$currency   = $_SESSION['currency'] ?? 'EUR';

$period = $_GET['period'] ?? 'today';
$date_from = $_GET['from'] ?? null;
$date_to   = $_GET['to']   ?? null;

switch ($period) {
    case 'week':  $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); break;
    case 'month': $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'custom': $from = $date_from ?? date('Y-m-d'); $to = $date_to ?? date('Y-m-d'); break;
    default: $from = date('Y-m-d'); $to = date('Y-m-d');
}

$store_filter_sql = '';
$store_params = [$tenant_id];
if ($role === 'seller' && $store_id) {
    $store_filter_sql = ' AND s.store_id = ?';
    $store_params[] = $store_id;
} elseif ($role !== 'seller' && isset($_GET['store']) && $_GET['store']) {
    $store_filter_sql = ' AND s.store_id = ?';
    $store_params[] = (int)$_GET['store'];
}

// Продажби
$q = $pdo->prepare("SELECT COALESCE(SUM(s.total),0) AS revenue, COUNT(s.id) AS transactions, COALESCE(AVG(s.total),0) AS avg_ticket FROM sales s WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' {$store_filter_sql}");
$q->execute(array_merge([$tenant_id, $from, $to], array_slice($store_params, 1)));
$sales_summary = $q->fetch(PDO::FETCH_ASSOC);

// По обект
$q2 = $pdo->prepare("SELECT st.name AS store_name, COALESCE(SUM(s.total),0) AS revenue FROM sales s JOIN stores st ON st.id=s.store_id WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY s.store_id ORDER BY revenue DESC");
$q2->execute([$tenant_id, $from, $to]);
$sales_by_store = $q2->fetchAll(PDO::FETCH_ASSOC);

// Печалба
$profit = 0; $margin_pct = 0;
if ($role === 'owner') {
    $qp = $pdo->prepare("SELECT COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue, COALESCE(SUM(si.quantity*COALESCE(p.cost_price,0)),0) AS cost FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'");
    $qp->execute([$tenant_id, $from, $to]);
    $pr = $qp->fetch(PDO::FETCH_ASSOC);
    $profit = $pr['revenue'] - $pr['cost'];
    $margin_pct = $pr['revenue'] > 0 ? round(($profit / $pr['revenue']) * 100, 1) : 0;
}

// Топ 5
$qt = $pdo->prepare("SELECT p.name, SUM(si.quantity) AS qty_sold FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY si.product_id ORDER BY qty_sold DESC LIMIT 5");
$qt->execute([$tenant_id, $from, $to]);
$top_products = $qt->fetchAll(PDO::FETCH_ASSOC);

// Мъртъв капитал
$qd = $pdo->prepare("SELECT p.name, i.quantity, DATEDIFF(NOW(), COALESCE(MAX(sm.created_at), p.created_at)) AS days_idle, i.quantity * p.cost_price AS dead_value FROM inventory i JOIN products p ON p.id=i.product_id LEFT JOIN stock_movements sm ON sm.product_id=i.product_id AND sm.store_id=i.store_id AND sm.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.quantity>0 GROUP BY i.product_id, i.store_id HAVING days_idle >= 30 ORDER BY dead_value DESC LIMIT 20");
$qd->execute([$tenant_id]);
$dead_stock = $qd->fetchAll(PDO::FETCH_ASSOC);
$dead_capital = array_sum(array_column($dead_stock, 'dead_value'));

// Ниски наличности
$ql = $pdo->prepare("SELECT p.name, i.quantity, i.min_quantity, st.name AS store_name FROM inventory i JOIN products p ON p.id=i.product_id JOIN stores st ON st.id=i.store_id WHERE i.tenant_id=? AND i.quantity <= i.min_quantity AND i.min_quantity>0 ORDER BY i.quantity ASC LIMIT 20");
$ql->execute([$tenant_id]);
$low_stock = $ql->fetchAll(PDO::FETCH_ASSOC);

// Неплатени фактури
$unpaid_invoices = []; $unpaid_total = 0;
if ($role !== 'seller') {
    $qi = $pdo->prepare("SELECT i.number AS invoice_number, i.due_date, i.total, DATEDIFF(NOW(), i.due_date) AS overdue_days FROM invoices i WHERE i.tenant_id=? AND i.status != 'paid' ORDER BY i.due_date ASC LIMIT 15");
    $qi->execute([$tenant_id]);
    $unpaid_invoices = $qi->fetchAll(PDO::FETCH_ASSOC);
    $unpaid_total = array_sum(array_column($unpaid_invoices, 'total'));
}

// Отстъпки по продавач
$anomaly_discounts = [];
if ($role === 'owner') {
    $qa = $pdo->prepare("SELECT u.name AS seller, SUM(s.discount_amount) AS total_discount FROM sales s JOIN users u ON u.id=s.user_id WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ? AND s.discount_amount>0 GROUP BY s.user_id ORDER BY total_discount DESC LIMIT 5");
    $qa->execute([$tenant_id, $from, $to]);
    $anomaly_discounts = $qa->fetchAll(PDO::FETCH_ASSOC);
}

// Здраве
$health_score = 50;
if ($role === 'owner') {
    $score = 100;
    if ($low_stock) $score -= min(30, count($low_stock) * 3);
    if ($dead_capital > 1000) $score -= min(20, (int)($dead_capital / 500));
    if ($unpaid_total > 500)  $score -= min(20, (int)($unpaid_total / 500));
    if ($margin_pct < 20)     $score -= 15;
    $health_score = max(0, min(100, $score));
}

// Обекти
$stores_list = [];
if ($role !== 'seller') {
    $qs = $pdo->prepare("SELECT id, name FROM stores WHERE tenant_id=? AND is_active=1 ORDER BY name");
    $qs->execute([$tenant_id]);
    $stores_list = $qs->fetchAll(PDO::FETCH_ASSOC);
}

$active_tab = $_GET['tab'] ?? 'overview';
$hcolor = $health_score >= 70 ? '#22c55e' : ($health_score >= 40 ? '#f59e0b' : '#ef4444');
$hclass = $health_score >= 70 ? 'great' : ($health_score >= 40 ? 'ok' : 'bad');
$hlabel = $health_score >= 70 ? 'Бизнесът е в добро здраве' : ($health_score >= 40 ? 'Има неща за подобрение' : 'Нужно е внимание');
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Статистики — RunMyStore.ai</title>
<style>
:root{
    --bg-main: #030712;
    --bg-card: rgba(15, 15, 40, 0.75);
    --bg-card-hover: rgba(23, 28, 58, 0.9);
    --border-subtle: rgba(99, 102, 241, 0.15);
    --border-glow: rgba(99, 102, 241, 0.4);
    --indigo-500: #6366f1;
    --indigo-400: #818cf8;
    --indigo-300: #a5b4fc;
    --text-primary: #f1f5f9;
    --text-secondary: #6b7280;
    --danger: #ef4444;
    --warning: #f59e0b;
    --success: #22c55e;
    --bottom-nav-h: 64px;
}

*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}

body{
    background:var(--bg-main);
    color:var(--text-primary);
    font-family:'Montserrat',Inter,system-ui,sans-serif;
    min-height:100vh;
    overflow-x:hidden;
    padding-bottom:var(--bottom-nav-h);
}

body::before{
    content:'';
    position:fixed;
    top:-200px;
    left:50%;
    transform:translateX(-50%);
    width:700px;
    height:400px;
    background:radial-gradient(ellipse,rgba(99,102,241,.1) 0%,transparent 70%);
    pointer-events:none;
    z-index:0;
}

.page-wrap{position:relative;z-index:1;max-width:480px;margin:0 auto;padding:0 12px}

/* ═══ Header — warehouse style ═══ */
.page-header{
    position:sticky;
    top:0;
    z-index:50;
    background:rgba(3,7,18,.93);
    backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border-subtle);
    padding:12px 16px 0;
    margin:0 -12px;
}

.page-title{
    font-size:18px;
    font-weight:800;
    margin:0 0 10px;
    background:linear-gradient(135deg,#f1f5f9,#a5b4fc);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}

/* ═══ Indigo Separator ═══ */
.indigo-sep{
    height:1px;
    background:linear-gradient(to right,transparent,rgba(99,102,241,.25),transparent);
    margin:16px 0;
}

/* ═══ Period Pills — warehouse cards style ═══ */
.period-bar{
    display:flex;
    gap:8px;
    overflow-x:auto;
    scrollbar-width:none;
    padding-bottom:10px;
    align-items:center;
    margin-bottom:8px;
}
.period-bar::-webkit-scrollbar{display:none}

.period-pill{
    flex-shrink:0;
    padding:8px 16px;
    border-radius:12px;
    font-size:12px;
    font-weight:700;
    border:1px solid var(--border-subtle);
    color:var(--indigo-300);
    background:var(--bg-card);
    text-decoration:none;
    white-space:nowrap;
    transition:all .25s;
    cursor:pointer;
    backdrop-filter:blur(12px);
    position:relative;
    overflow:hidden;
}

.period-pill::before{
    content:'';
    position:absolute;
    inset:0;
    border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);
    pointer-events:none;
}

.period-pill.active{
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    border-color:transparent;
    color:#fff;
    box-shadow:0 0 20px rgba(99,102,241,.4);
}

.period-pill:active{transform:scale(.97)}
.period-pill:hover:not(.active){
    border-color:var(--border-glow);
    box-shadow:0 0 16px rgba(99,102,241,.12);
}

/* ═══ Date Picker Row ═══ */
.date-picker-row{
    display:none;
    gap:8px;
    align-items:center;
    padding-bottom:10px;
    animation:fadeIn .2s ease;
}
.date-picker-row.visible{display:flex}

.date-input{
    flex:1;
    background:var(--bg-card);
    border:1px solid var(--border-subtle);
    border-radius:12px;
    color:var(--indigo-300);
    font-size:12px;
    padding:8px 12px;
    font-family:'Montserrat',sans-serif;
    backdrop-filter:blur(12px);
}
.date-input:focus{
    outline:none;
    border-color:var(--indigo-500);
    box-shadow:0 0 12px rgba(99,102,241,.3);
}

.date-apply{
    padding:8px 16px;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    border:none;
    border-radius:12px;
    color:#fff;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    white-space:nowrap;
    box-shadow:0 4px 15px rgba(99,102,241,.3);
}

/* ═══ Tabs — warehouse cards style ═══ */
.tabs-bar{
    display:flex;
    gap:8px;
    overflow-x:auto;
    scrollbar-width:none;
    padding:4px 0 12px;
}
.tabs-bar::-webkit-scrollbar{display:none}

.tab-btn{
    flex-shrink:0;
    padding:10px 18px;
    font-size:13px;
    font-weight:700;
    color:var(--text-secondary);
    border:1px solid var(--border-subtle);
    background:var(--bg-card);
    border-radius:14px;
    cursor:pointer;
    white-space:nowrap;
    transition:all .25s;
    backdrop-filter:blur(12px);
    position:relative;
    overflow:hidden;
}

.tab-btn::before{
    content:'';
    position:absolute;
    inset:0;
    border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);
    pointer-events:none;
}

.tab-btn.active{
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    border-color:transparent;
    color:#fff;
    box-shadow:0 0 24px rgba(99,102,241,.4);
    text-shadow:0 0 12px rgba(255,255,255,.3);
}

.tab-btn:active{transform:scale(.95)}
.tab-btn:hover:not(.active){
    border-color:var(--border-glow);
    color:var(--indigo-300);
    box-shadow:0 0 16px rgba(99,102,241,.15);
}

/* ═══ Content ═══ */
.stats-content{padding:12px 0 80px;position:relative;z-index:1}

/* ═══ Grid & Cards — warehouse style ═══ */
.grid-2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    margin-bottom:10px;
}

.stat-card{
    position:relative;
    background:var(--bg-card);
    border:1px solid var(--border-subtle);
    border-radius:16px;
    padding:14px;
    cursor:pointer;
    transition:all .25s;
    backdrop-filter:blur(12px);
    overflow:hidden;
    animation:cardIn .4s ease both;
}

.stat-card::before{
    content:'';
    position:absolute;
    inset:0;
    border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);
    pointer-events:none;
}

.stat-card:active{transform:scale(.97)}
.stat-card:hover{
    border-color:var(--border-glow);
    box-shadow:0 0 28px rgba(99,102,241,.18);
}

.stat-card.glow-red{border-color:rgba(239,68,68,.25)}
.stat-card.glow-red:hover{box-shadow:0 0 28px rgba(239,68,68,.2)}
.stat-card.glow-yellow{border-color:rgba(245,158,11,.25)}
.stat-card.glow-yellow:hover{box-shadow:0 0 28px rgba(245,158,11,.2)}

.stat-card .label{
    font-size:11px;
    color:var(--text-secondary);
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.6px;
    margin-bottom:6px;
}

.stat-card .value{
    font-size:24px;
    font-weight:900;
    color:var(--text-primary);
    line-height:1;
}

.stat-card .sub{
    font-size:12px;
    color:var(--text-secondary);
    margin-top:4px;
}

.stat-card .tap-hint{
    font-size:10px;
    color:rgba(99,102,241,.5);
    margin-top:8px;
    font-weight:600;
}

/* ═══ Section Title ═══ */
.section-title{
    font-size:11px;
    font-weight:700;
    color:var(--indigo-400);
    text-transform:uppercase;
    letter-spacing:1px;
    margin:20px 0 10px;
    display:flex;
    align-items:center;
    gap:6px;
}

/* ═══ List Cards ═══ */
.list-card{
    background:var(--bg-card);
    border:1px solid var(--border-subtle);
    border-radius:16px;
    overflow:hidden;
    margin-bottom:10px;
    backdrop-filter:blur(12px);
    animation:cardIn .4s ease both;
}

.list-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 14px;
    border-bottom:1px solid rgba(99,102,241,.08);
    transition:background .15s;
    cursor:pointer;
}

.list-row:last-child{border-bottom:none}
.list-row:active{background:rgba(99,102,241,.05)}

.list-row .name{
    font-size:13px;
    color:var(--text-primary);
    font-weight:500;
    flex:1;
    margin-right:8px;
}

.list-row .val{
    font-size:13px;
    font-weight:700;
    color:var(--indigo-300);
}

/* ═══ Badge ═══ */
.badge{
    font-size:10px;
    padding:3px 10px;
    border-radius:10px;
    font-weight:800;
}
.badge-red{
    background:rgba(239,68,68,.15);
    color:#ef4444;
    box-shadow:0 0 8px rgba(239,68,68,.2);
}
.badge-yellow{
    background:rgba(245,158,11,.15);
    color:#f59e0b;
    box-shadow:0 0 8px rgba(245,158,11,.2);
}

/* ═══ Health Card ═══ */
.health-wrap{
    background:var(--bg-card);
    border:1px solid var(--border-subtle);
    border-radius:18px;
    padding:20px;
    text-align:center;
    cursor:pointer;
    margin-bottom:10px;
    position:relative;
    overflow:hidden;
    animation:cardIn .4s ease both;
    backdrop-filter:blur(12px);
}

.health-wrap::after{
    content:'';
    position:absolute;
    top:-60px;
    left:50%;
    transform:translateX(-50%);
    width:250px;
    height:150px;
    background:radial-gradient(ellipse,rgba(99,102,241,.12) 0%,transparent 70%);
    pointer-events:none;
}

.health-wrap:hover{
    border-color:var(--border-glow);
    box-shadow:0 0 40px rgba(99,102,241,.15);
}

.health-score{
    font-size:56px;
    font-weight:900;
    line-height:1;
    position:relative;
    z-index:1;
}

.health-score.great{color:#22c55e;text-shadow:0 0 40px rgba(34,197,94,.4)}
.health-score.ok{color:#f59e0b;text-shadow:0 0 40px rgba(245,158,11,.4)}
.health-score.bad{color:#ef4444;text-shadow:0 0 40px rgba(239,68,68,.4)}

.health-label{
    font-size:12px;
    color:var(--text-secondary);
    margin-top:6px;
    position:relative;
    z-index:1;
}

.health-bar-bg{
    background:rgba(99,102,241,.1);
    border-radius:6px;
    height:6px;
    margin:16px 0 4px;
    overflow:hidden;
    position:relative;
    z-index:1;
}

.health-bar-fill{
    height:6px;
    border-radius:6px;
    width:0;
    transition:width 1.4s cubic-bezier(.34,1.56,.64,1);
}

/* ═══ Anomaly Cards ═══ */
.anomaly-card{
    display:flex;
    align-items:flex-start;
    gap:10px;
    background:var(--bg-card);
    border-radius:14px;
    padding:12px;
    margin-bottom:8px;
    border:1px solid rgba(239,68,68,.2);
    cursor:pointer;
    animation:cardIn .4s ease both;
    transition:transform .15s;
    backdrop-filter:blur(12px);
}

.anomaly-card:active{transform:scale(.98)}
.anomaly-card.warning{border-color:rgba(245,158,11,.2)}
.anomaly-card.info{border-color:rgba(99,102,241,.2)}

.anomaly-dot{
    width:10px;
    height:10px;
    border-radius:50%;
    flex-shrink:0;
    margin-top:3px;
}

.dot-red{
    background:#ef4444;
    animation:pulseRed 2s infinite;
    box-shadow:0 0 10px rgba(239,68,68,.6);
}

.dot-yellow{
    background:#f59e0b;
    animation:pulseYellow 2s infinite;
    box-shadow:0 0 10px rgba(245,158,11,.6);
}

.dot-blue{
    background:#6366f1;
    box-shadow:0 0 10px rgba(99,102,241,.6);
}

.anomaly-text .title{
    font-size:13px;
    font-weight:700;
    color:var(--text-primary);
}

.anomaly-text .desc{
    font-size:11px;
    color:var(--text-secondary);
    margin-top:2px;
    line-height:1.4;
}

/* ═══ Empty State ═══ */
.empty-state{
    text-align:center;
    padding:36px 20px;
    color:var(--text-secondary);
    background:var(--bg-card);
    border-radius:16px;
    border:1px solid var(--border-subtle);
    animation:cardIn .4s ease both;
}

.empty-state svg{
    width:36px;
    height:36px;
    margin-bottom:8px;
    color:var(--indigo-400);
    opacity:.5;
}

.empty-state p{
    font-size:13px;
    margin:0;
}

/* ═══ Store Select ═══ */
.store-select{
    background:var(--bg-card);
    border:1px solid var(--border-subtle);
    border-radius:12px;
    color:var(--indigo-300);
    font-size:12px;
    padding:6px 12px;
    backdrop-filter:blur(12px);
    font-family:'Montserrat',sans-serif;
    font-weight:600;
}

/* ═══ Drawer ═══ */
.drawer-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.65);
    z-index:200;
    opacity:0;
    pointer-events:none;
    transition:opacity .25s;
    backdrop-filter:blur(4px);
}

.drawer-overlay.open{opacity:1;pointer-events:all}

.drawer{
    position:fixed;
    bottom:0;
    left:0;
    right:0;
    z-index:201;
    background:#080818;
    border-top:1px solid var(--border-glow);
    border-radius:22px 22px 0 0;
    padding:0 0 40px;
    transform:translateY(100%);
    transition:transform .32s cubic-bezier(.32,0,.67,0);
    max-height:85vh;
    overflow-y:auto;
    box-shadow:0 -20px 60px rgba(99,102,241,.2);
}

.drawer.open{transform:translateY(0)}

.drawer-handle{
    width:36px;
    height:4px;
    background:rgba(99,102,241,.3);
    border-radius:2px;
    margin:14px auto 18px;
}

.drawer-body{padding:0 16px 20px}

.drawer-title{
    font-size:11px;
    font-weight:700;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.5px;
    margin-bottom:4px;
}

.drawer-value{
    font-size:32px;
    font-weight:900;
    background:linear-gradient(135deg,#a5b4fc,#6366f1);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    line-height:1.1;
    margin-bottom:14px;
}

.drawer-explain{
    background:rgba(99,102,241,.08);
    border:1px solid var(--border-subtle);
    border-radius:12px;
    padding:12px;
    font-size:13px;
    color:var(--indigo-300);
    line-height:1.65;
    margin-bottom:12px;
}

.ai-box{
    background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));
    border:1px solid rgba(99,102,241,.2);
    border-radius:12px;
    padding:12px;
    margin-bottom:14px;
    position:relative;
    overflow:hidden;
}

.ai-box::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:1px;
    background:linear-gradient(90deg,transparent,#6366f1,transparent);
    opacity:.5;
}

.ai-box .ai-label{
    font-size:10px;
    font-weight:800;
    color:#6366f1;
    text-transform:uppercase;
    letter-spacing:1px;
    margin-bottom:6px;
}

.ai-box .ai-text{
    font-size:13px;
    color:var(--text-primary);
    line-height:1.55;
}

.drawer-btn{
    display:block;
    width:100%;
    padding:14px;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    border:none;
    border-radius:14px;
    color:#fff;
    font-size:14px;
    font-weight:700;
    text-align:center;
    cursor:pointer;
    text-decoration:none;
    box-shadow:0 4px 20px rgba(99,102,241,.35);
    transition:transform .15s;
}

.drawer-btn:active{transform:scale(.98)}

/* ═══ Shimmer ═══ */
.shimmer-text{
    background:linear-gradient(90deg,var(--indigo-400) 25%,#c7d2fe 50%,var(--indigo-400) 75%);
    background-size:200% auto;
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    animation:shimmer 3s linear infinite;
}

/* ═══ Bottom Nav — warehouse style ═══ */
.bottom-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;
    height:var(--bottom-nav-h);
    background:rgba(3,7,18,.95);
    backdrop-filter:blur(16px);
    border-top:1px solid var(--border-subtle);
    display:flex;
    align-items:center;
    z-index:100;
    box-shadow:0 -5px 25px rgba(99,102,241,.1);
}

.nav-item,.bnav-tab{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:3px;
    font-size:10px;
    font-weight:600;
    color:rgba(165,180,252,.5);
    text-decoration:none;
    transition:all .3s;
    height:100%;
    border:none;
    background:transparent;
    cursor:pointer;
}

.nav-item.active,.bnav-tab.active{
    color:var(--indigo-400);
    text-shadow:0 0 12px rgba(129,140,248,.9);
}

.nav-item svg{
    width:22px;
    height:22px;
    color:#3f3f5a;
    transition:all .3s;
}

.nav-item.active svg{
    color:var(--indigo-400);
    filter:drop-shadow(0 0 8px rgba(99,102,241,.6));
    transform:translateY(-2px);
}

.bnav-tab .bnav-icon{
    font-size:20px;
    transition:all .3s;
    filter:drop-shadow(0 0 4px rgba(99,102,241,.3));
}

.bnav-tab.active .bnav-icon{
    transform:translateY(-2px);
    filter:drop-shadow(0 0 12px rgba(129,140,248,.8));
}

/* ═══ Keyframes ═══ */
@keyframes cardIn{
    from{opacity:0;transform:translateY(12px)}
    to{opacity:1;transform:translateY(0)}
}

@keyframes fadeIn{
    from{opacity:0}
    to{opacity:1}
}

@keyframes pulseRed{
    0%,100%{box-shadow:0 0 6px rgba(239,68,68,.8)}
    50%{box-shadow:0 0 16px rgba(239,68,68,1),0 0 24px rgba(239,68,68,.4)}
}

@keyframes pulseYellow{
    0%,100%{box-shadow:0 0 6px rgba(245,158,11,.8)}
    50%{box-shadow:0 0 16px rgba(245,158,11,1),0 0 24px rgba(245,158,11,.4)}
}

@keyframes shimmer{
    0%{background-position:-200% center}
    100%{background-position:200% center}
}

/* Scrollbar */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{
    background:rgba(99,102,241,.3);
    border-radius:3px;
}
</style>
</head>
<body>

<div class="page-wrap">

    <div class="page-header">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0">
            <h1 class="page-title">Статистики</h1>
            <?php if ($role !== 'seller' && count($stores_list) > 1): ?>
            <form method="GET" style="margin:0">
                <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <select name="store" class="store-select" onchange="this.form.submit()">
                    <option value="">Всички обекти</option>
                    <?php foreach ($stores_list as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (isset($_GET['store']) && $_GET['store']==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <div class="period-bar" style="margin-top:10px">
            <a href="?period=today&tab=<?= htmlspecialchars($active_tab) ?>" class="period-pill <?= $period==='today'?'active':'' ?>">Днес</a>
            <a href="?period=week&tab=<?= htmlspecialchars($active_tab) ?>"  class="period-pill <?= $period==='week'?'active':'' ?>">Седмица</a>
            <a href="?period=month&tab=<?= htmlspecialchars($active_tab) ?>" class="period-pill <?= $period==='month'?'active':'' ?>">Месец</a>
            <button class="period-pill <?= $period==='custom'?'active':'' ?>" onclick="toggleDatePicker()" type="button">
                <?= $period==='custom' ? htmlspecialchars($from.' → '.$to) : 'От – До' ?>
            </button>
        </div>

        <div class="date-picker-row <?= $period==='custom'?'visible':'' ?>" id="datePickerRow">
            <form method="GET" style="display:flex;gap:8px;width:100%;align-items:center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="from" class="date-input" value="<?= htmlspecialchars($from) ?>" max="<?= date('Y-m-d') ?>">
                <input type="date" name="to"   class="date-input" value="<?= htmlspecialchars($to) ?>"   max="<?= date('Y-m-d') ?>">
                <button type="submit" class="date-apply">OK</button>
            </form>
        </div>

        <div class="tabs-bar">
            <?php foreach (['overview'=>'Обзор','sales'=>'Продажби','products'=>'Стоки','finance'=>'Финанси','anomalies'=>'Аномалии'] as $k=>$v): ?>
            <button class="tab-btn <?= $active_tab===$k?'active':'' ?>" onclick="switchTab('<?=$k?>',event)"><?=$v?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="indigo-sep"></div>

    <div class="stats-content">

        <!-- ═══ ОБЗОР ═══ -->
        <div id="tab-overview" class="tab-content" <?= $active_tab!=='overview'?'style="display:none"':'' ?>>

            <?php if ($role === 'owner'): ?>
            <div class="health-wrap" onclick="openDrawer('health')">
                <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Здраве на бизнеса</div>
                <div class="health-score <?= $hclass ?>" id="healthNum">0</div>
                <div class="health-label"><?= $hlabel ?></div>
                <div class="health-bar-bg"><div class="health-bar-fill" id="healthBar" style="background:<?= $hcolor ?>"></div></div>
                <div style="font-size:10px;color:rgba(99,102,241,.4);margin-top:4px">Натисни за обяснение</div>
            </div>
            <?php endif; ?>

            <div class="grid-2">
                <div class="stat-card" onclick="openDrawer('revenue')" style="animation-delay:.05s">
                    <div class="label">Оборот</div>
                    <div class="value shimmer-text" data-count="<?= round($sales_summary['revenue']) ?>">0</div>
                    <div class="sub"><?= $currency ?></div>
                    <div class="tap-hint">↗ Натисни за детайли</div>
                </div>
                <div class="stat-card" onclick="openDrawer('transactions')" style="animation-delay:.1s">
                    <div class="label">Транзакции</div>
                    <div class="value" data-count="<?= $sales_summary['transactions'] ?>">0</div>
                    <div class="sub">продажби</div>
                    <div class="tap-hint">↗ Натисни за детайли</div>
                </div>
            </div>

            <div class="grid-2">
                <div class="stat-card" onclick="openDrawer('avg_ticket')" style="animation-delay:.15s">
                    <div class="label">Средна сметка</div>
                    <div class="value" data-count="<?= round($sales_summary['avg_ticket']) ?>">0</div>
                    <div class="sub"><?= $currency ?></div>
                    <div class="tap-hint">↗ Натисни за детайли</div>
                </div>
                <?php if ($role === 'owner'): ?>
                <div class="stat-card" onclick="openDrawer('profit')" style="animation-delay:.2s">
                    <div class="label">Печалба</div>
                    <div class="value" data-count="<?= round($profit) ?>">0</div>
                    <div class="sub"><?= $margin_pct ?>% марж</div>
                    <div class="tap-hint">↗ Натисни за детайли</div>
                </div>
                <?php else: ?>
                <div class="stat-card" onclick="openDrawer('low_stock')" style="animation-delay:.2s">
                    <div class="label">Ниски наличности</div>
                    <div class="value" style="<?= count($low_stock)>0?'color:#ef4444':'' ?>" data-count="<?= count($low_stock) ?>">0</div>
                    <div class="sub">под минимум</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($role !== 'seller' && $unpaid_total > 0): ?>
            <div class="stat-card glow-red" onclick="openDrawer('unpaid')" style="animation-delay:.25s">
                <div class="label">Неплатено към доставчици</div>
                <div class="value" style="color:#ef4444;font-size:22px"><?= number_format($unpaid_total,2,',','.') ?> <?= $currency ?></div>
                <div class="sub"><?= count($unpaid_invoices) ?> фактури чакат плащане</div>
            </div>
            <?php endif; ?>

            <?php if ($dead_capital > 0): ?>
            <div class="stat-card glow-yellow" onclick="openDrawer('dead_capital')" style="animation-delay:.3s">
                <div class="label">Мъртъв капитал</div>
                <div class="value" style="color:#f59e0b;font-size:22px"><?= number_format($dead_capital,0,',','.') ?> <?= $currency ?></div>
                <div class="sub">Стока без движение ≥30 дни · <?= count($dead_stock) ?> артикула</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══ ПРОДАЖБИ ═══ -->
        <div id="tab-sales" class="tab-content" <?= $active_tab!=='sales'?'style="display:none"':'' ?>>
            <p class="section-title">По обект</p>
            <div class="list-card">
                <?php if ($sales_by_store): foreach ($sales_by_store as $row): ?>
                <div class="list-row">
                    <span class="name"><?= htmlspecialchars($row['store_name']) ?></span>
                    <span class="val"><?= number_format($row['revenue'],0,',','.') ?> <?= $currency ?></span>
                </div>
                <?php endforeach; else: ?>
                <div class="empty-state"><p>Няма продажби за периода</p></div>
                <?php endif; ?>
            </div>

            <div class="grid-2">
                <div class="stat-card" onclick="openDrawer('revenue')">
                    <div class="label">Общо</div>
                    <div class="value shimmer-text" data-count="<?= round($sales_summary['revenue']) ?>">0</div>
                    <div class="sub"><?= $currency ?></div>
                </div>
                <div class="stat-card" onclick="openDrawer('avg_ticket')">
                    <div class="label">Средна сметка</div>
                    <div class="value" data-count="<?= round($sales_summary['avg_ticket']) ?>">0</div>
                    <div class="sub"><?= $currency ?></div>
                </div>
            </div>

            <?php if ($role === 'owner' &&
