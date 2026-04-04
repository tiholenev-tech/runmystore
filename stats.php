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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Статистики — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
:root { --nav-h: 64px; }
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
body { 
    background: radial-gradient(circle at top right, #13172c, #0b0f1a); 
    color: #e2e8f0; font-family: 'Inter', sans-serif; 
    min-height: 100dvh; overflow-x: hidden; padding-bottom: var(--nav-h); 
}

/* ── BACKGROUND ── */
.bg-deco { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; opacity: 0.6; }
.bg-deco img { position: absolute; max-width: none; }

/* ── HEADER ── */
.stats-header { position: sticky; top: 0; z-index: 100; background: rgba(11,15,26,0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid rgba(99,102,241,0.15); padding: 16px 16px 0; }
.stats-header h1 { 
    font-size: 24px; font-weight: 800; margin: 0 0 15px; 
    background: linear-gradient(135deg, #ffffff, #a5b4fc); -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; background-clip: text; animation: gShift 6s linear infinite; 
}
@keyframes gShift { 0% { background-position: 0% center } 100% { background-position: 200% center } }

.period-bar { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; padding-bottom: 12px; }
.period-bar::-webkit-scrollbar { display: none; }
.period-pill { flex-shrink: 0; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid rgba(99,102,241,0.25); color: #a5b4fc; background: rgba(99,102,241,0.05); text-decoration: none; white-space: nowrap; transition: 0.2s; }
.period-pill.active { background: linear-gradient(135deg, #6366f1, #8b5cf6); border-color: transparent; color: #fff; box-shadow: 0 4px 15px rgba(99,102,241,0.3); }

.tabs-bar { display: flex; overflow-x: auto; scrollbar-width: none; border-top: 1px solid rgba(255,255,255,0.05); }
.tabs-bar::-webkit-scrollbar { display: none; }
.tab-btn { flex-shrink: 0; padding: 14px 18px; font-size: 13px; font-weight: 700; color: #64748b; border: none; background: transparent; cursor: pointer; border-bottom: 3px solid transparent; transition: 0.2s; white-space: nowrap; }
.tab-btn.active { color: #818cf8; border-bottom-color: #6366f1; }

/* ── CONTENT ── */
.stats-content { padding: 20px 16px; position: relative; z-index: 10; }

/* ── HEALTH WRAP ── */
.health-wrap { 
    background: rgba(30, 35, 60, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(99,102,241,0.2); 
    border-radius: 28px; padding: 24px; text-align: center; cursor: pointer; margin-bottom: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
}
.health-score { font-size: 64px; font-weight: 900; line-height: 1; letter-spacing: -2px; }
.health-score.great { color: #22c55e; text-shadow: 0 0 25px rgba(34,197,94,0.4); }
.health-score.ok { color: #f59e0b; text-shadow: 0 0 25px rgba(245,158,11,0.4); }
.health-score.bad { color: #ef4444; text-shadow: 0 0 25px rgba(239,68,68,0.4); }
.health-bar-bg { background: rgba(255,255,255,0.05); border-radius: 10px; height: 8px; margin: 18px 0 6px; overflow: hidden; }
.health-bar-fill { height: 100%; border-radius: 10px; width: 0; transition: width 1.5s cubic-bezier(0.34, 1.56, 0.64, 1); }

/* ── CARDS ── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.stat-card { 
    background: rgba(30, 35, 60, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); 
    border-radius: 20px; padding: 18px; cursor: pointer; transition: 0.2s; position: relative; overflow: hidden;
}
.stat-card:active { transform: scale(0.96); background: rgba(99, 102, 241, 0.1); }
.stat-card.glow-red { border-color: rgba(239, 68, 68, 0.3); }
.stat-card.glow-yellow { border-color: rgba(245, 158, 11, 0.3); }
.stat-card .label { font-size: 11px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.stat-card .value { font-size: 24px; font-weight: 800; color: #f8fafc; line-height: 1.2; }
.stat-card .sub { font-size: 12px; color: #64748b; margin-top: 4px; }

/* ── LISTS ── */
.section-title { font-size: 12px; font-weight: 800; color: #818cf8; text-transform: uppercase; letter-spacing: 1px; margin: 24px 0 12px; }
.list-card { background: rgba(15, 20, 35, 0.5); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 20px; overflow: hidden; }
.list-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); }
.list-row:active { background: rgba(99,102,241,0.08); }

/* ── DRAWER ── */
.drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); z-index: 2000; opacity: 0; pointer-events: none; transition: 0.3s; }
.drawer-overlay.open { opacity: 1; pointer-events: all; }
.drawer { 
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 2001; 
    background: #0b0f1a; border-top: 1px solid rgba(99, 102, 241, 0.3); 
    border-radius: 24px 24px 0 0; padding: 0 0 40px; transform: translateY(100%); 
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); max-height: 85vh; overflow-y: auto;
}
.drawer.open { transform: translateY(0); }
.drawer-handle { width: 40px; height: 5px; background: rgba(99, 102, 241, 0.3); border-radius: 10px; margin: 12px auto 20px; }
.drawer-body { padding: 0 24px; }
.drawer-value { font-size: 32px; font-weight: 900; background: linear-gradient(135deg, #fff, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 15px; }

/* ── BOTTOM NAV (Strong Glow) ── */
.bottom-nav { 
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; background: rgba(11, 15, 26, 0.95); 
    backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border-top: 1px solid rgba(99, 102, 241, 0.25); 
    display: flex; height: var(--nav-h); box-shadow: 0 -5px 25px rgba(99, 102, 241, 0.2);
}
.nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; text-decoration: none; color: rgba(165, 180, 252, 0.5); transition: 0.3s; font-weight: 600; }
.nav-item.active { color: #c7d2fe; text-shadow: 0 0 12px rgba(129, 140, 248, 0.9); }
.nav-item svg { width: 22px; height: 22px; }
.nav-item.active svg { transform: translateY(-2px); filter: drop-shadow(0 0 8px rgba(129, 140, 248, 0.8)); }
.nav-item span { font-size: 10px; }

/* ── SHIMMER ── */
@keyframes shimmer { 0% { background-position: -200% center } 100% { background-position: 200% center } }
.shimmer-text { background: linear-gradient(90deg, #fff 25%, #a5b4fc 50%, #fff 75%); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: shimmer 3s linear infinite; }
</style>
</head>
<body>

<div class="bg-deco">
    <img style="left: 50%; top: 0; transform: translateX(-25%); width: 846px;" src="./images/page-illustration.svg" alt="">
</div>

<div class="stats-header">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Статистики</h1>
    <?php if ($role !== 'seller' && count($stores_list) > 1): ?>
    <form method="GET">
      <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
      <select name="store" style="background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.2); color:#a5b4fc; border-radius:10px; padding:4px 8px; font-size:12px;" onchange="this.form.submit()">
        <option value="">Всички обекти</option>
        <?php foreach ($stores_list as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (isset($_GET['store']) && $_GET['store']==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>

  <div class="period-bar">
    <a href="?period=today&tab=<?= htmlspecialchars($active_tab) ?>" class="period-pill <?= $period==='today'?'active':'' ?>">Днес</a>
    <a href="?period=week&tab=<?= htmlspecialchars($active_tab) ?>"  class="period-pill <?= $period==='week'?'active':'' ?>">Седмица</a>
    <a href="?period=month&tab=<?= htmlspecialchars($active_tab) ?>" class="period-pill <?= $period==='month'?'active':'' ?>">Месец</a>
    <button class="period-pill <?= $period==='custom'?'active':'' ?>" onclick="toggleDatePicker()">Календар</button>
  </div>

  <div class="tabs-bar">
    <?php foreach (['overview'=>'Обзор','sales'=>'Продажби','products'=>'Стоки','finance'=>'Финанси','anomalies'=>'Аномалии'] as $k=>$v): ?>
    <button class="tab-btn <?= $active_tab===$k?'active':'' ?>" onclick="switchTab('<?=$k?>',event)"><?=$v?></button>
    <?php endforeach; ?>
  </div>
</div>

<div class="stats-content">

<div id="tab-overview" class="tab-content" <?= $active_tab!=='overview'?'style="display:none"':'' ?>>
  <?php if ($role === 'owner'): ?>
  <div class="health-wrap" onclick="openDrawer('health')">
    <div style="font-size:11px;font-weight:800;color:#94a3b8;text-transform:uppercase;margin-bottom:12px">Здраве на бизнеса</div>
    <div class="health-score <?= $hclass ?>" id="healthNum">0</div>
    <div class="health-label" style="font-weight:700; margin-top:8px; color:#e2e8f0;"><?= $hlabel ?></div>
    <div class="health-bar-bg"><div class="health-bar-fill" id="healthBar" style="background:<?= $hcolor ?>"></div></div>
  </div>
  <?php endif; ?>

  <div class="grid-2">
    <div class="stat-card" onclick="openDrawer('revenue')">
      <div class="label">Оборот</div>
      <div class="value shimmer-text" data-count="<?= round($sales_summary['revenue']) ?>">0</div>
      <div class="sub"><?= $currency ?></div>
    </div>
    <div class="stat-card" onclick="openDrawer('transactions')">
      <div class="label">Транзакции</div>
      <div class="value" data-count="<?= $sales_summary['transactions'] ?>">0</div>
      <div class="sub">продажби</div>
    </div>
  </div>

  <div class="grid-2">
    <div class="stat-card" onclick="openDrawer('avg_ticket')">
      <div class="label">Среден бон</div>
      <div class="value" data-count="<?= round($sales_summary['avg_ticket']) ?>">0</div>
      <div class="sub"><?= $currency ?></div>
    </div>
    <?php if ($role === 'owner'): ?>
    <div class="stat-card" onclick="openDrawer('profit')">
      <div class="label">Печалба</div>
      <div class="value" data-count="<?= round($profit) ?>">0</div>
      <div class="sub"><?= $margin_pct ?>% марж</div>
    </div>
    <?php else: ?>
    <div class="stat-card" onclick="openDrawer('low_stock')">
      <div class="label">За зареждане</div>
      <div class="value" data-count="<?= count($low_stock) ?>">0</div>
      <div class="sub">артикула</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div id="tab-sales" class="tab-content" <?= $active_tab!=='sales'?'style="display:none"':'' ?>>
    <p class="section-title">Продажби по обекти</p>
    <div class="list-card">
        <?php foreach ($sales_by_store as $row): ?>
        <div class="list-row">
            <span class="name"><?= htmlspecialchars($row['store_name']) ?></span>
            <span class="val"><?= number_format($row['revenue'],0,',','.') ?> <?= $currency ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="tab-products" class="tab-content" <?= $active_tab!=='products'?'style="display:none"':'' ?>>
    <p class="section-title">Бестселъри</p>
    <div class="list-card">
        <?php foreach ($top_products as $i=>$p): ?>
        <div class="list-row">
            <span class="name"><span style="color:#6366f1; font-weight:800; margin-right:8px;"><?= $i+1 ?>.</span><?= htmlspecialchars($p['name']) ?></span>
            <span class="val"><?= number_format($p['qty_sold'],0) ?> бр.</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</div>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-handle"></div>
  <div class="drawer-body" id="drawerBody"></div>
</div>

<nav class="bottom-nav">
  <a href="chat.php" class="nav-item"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg><span>AI</span></a>
  <a href="warehouse.php" class="nav-item"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg><span>Склад</span></a>
  <a href="stats.php" class="nav-item active"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Справки</span></a>
  <a href="actions.php" class="nav-item"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>

<script>
const D={
  revenue:{title:'Оборот',value:'<?= number_format($sales_summary['revenue'],2,',','.') ?> <?= $currency ?>',explain:'Общата сума на всички завършени продажби.',ai:'Фокусирай се върху топ артикулите.',action:'Продажби',link:'stats.php?tab=sales'},
  transactions:{title:'Транзакции',value:'<?= $sales_summary['transactions'] ?>',explain:'Брой продажби.',ai:'Средният бон е <?= number_format($sales_summary['avg_ticket'],2,',','.') ?>.',action:null},
  profit:{title:'Печалба',value:'<?= number_format($profit,2,',','.') ?> <?= $currency ?>',explain:'Оборот минус себестойност.',ai:'<?= $margin_pct < 20 ? "Маржът е нисък." : "Отличен марж!" ?>',action:null},
  health:{title:'Бизнес здраве',value:'<?= $health_score ?>/100',explain:'Комплексен индекс.',ai:'Бизнесът е стабилен.',action:null}
};

function openDrawer(k){
  const d=D[k]; if(!d)return;
  document.getElementById('drawerBody').innerHTML=`<div class="drawer-value">${d.value}</div><p style="color:#94a3b8; line-height:1.6;">${d.explain}</p><div style="background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.2); border-radius:15px; padding:15px; margin-top:15px;"><div style="font-size:11px; font-weight:800; color:#a5b4fc; text-transform:uppercase; margin-bottom:5px;">✦ AI Препоръка</div><div style="color:#e2e8f0; font-size:14px;">${d.ai}</div></div>`;
  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('drawer').classList.add('open');
}
function closeDrawer(){
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('drawer').classList.remove('open');
}
function switchTab(tab,e){
  document.querySelectorAll('.tab-content').forEach(el=>el.style.display='none');
  document.querySelectorAll('.tab-btn').forEach(el=>el.classList.remove('active'));
  document.getElementById('tab-'+tab).style.display='block';
  e.target.classList.add('active');
}
function toggleDatePicker(){ document.getElementById('datePickerRow')?.classList.toggle('visible'); }

function animCount(el,target,dur=1000){
  let s=null;
  const step=(t)=>{
    if(!s)s=t;
    const prog=Math.min((t-s)/dur,1);
    el.textContent=Math.floor(prog*target).toLocaleString();
    if(prog<1)requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}
window.onload=()=>{
  document.querySelectorAll('[data-count]').forEach(el=>animCount(el,parseFloat(el.dataset.count)));
  setTimeout(()=>{
      document.getElementById('healthBar').style.width='<?= $health_score ?>%';
      animCount(document.getElementById('healthNum'), <?= $health_score ?>);
  }, 200);
};
</script>
</body>
</html>
