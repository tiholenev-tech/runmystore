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

// Период
$period = $_GET['period'] ?? 'today';
$date_from = $_GET['from'] ?? null;
$date_to   = $_GET['to']   ?? null;

switch ($period) {
    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = date('Y-m-d');
        break;
    case 'month':
        $from = date('Y-m-01');
        $to   = date('Y-m-d');
        break;
    case 'custom':
        $from = $date_from ?? date('Y-m-d');
        $to   = $date_to   ?? date('Y-m-d');
        break;
    default: // today
        $from = date('Y-m-d');
        $to   = date('Y-m-d');
}

// Store filter
$store_filter_sql = '';
$store_params     = [$tenant_id];
if ($role === 'seller' && $store_id) {
    $store_filter_sql = ' AND s.store_id = ?';
    $store_params[]   = $store_id;
} elseif ($role !== 'seller' && isset($_GET['store']) && $_GET['store']) {
    $store_filter_sql = ' AND s.store_id = ?';
    $store_params[]   = (int)$_GET['store'];
}

// ── ПРОДАЖБИ ──────────────────────────────────────────────
// Оборот за периода
$q = $pdo->prepare("
    SELECT COALESCE(SUM(s.total_amount),0) AS revenue,
           COUNT(s.id) AS transactions,
           COALESCE(AVG(s.total_amount),0) AS avg_ticket
    FROM sales s
    WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ?
    {$store_filter_sql}
");
$params = array_merge([$tenant_id, $from, $to], array_slice($store_params, 1));
$q->execute($params);
$sales_summary = $q->fetch(PDO::FETCH_ASSOC);

// Оборот по обект
$q2 = $pdo->prepare("
    SELECT st.name AS store_name, COALESCE(SUM(s.total_amount),0) AS revenue
    FROM sales s
    JOIN stores st ON st.id = s.store_id
    WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY s.store_id
    ORDER BY revenue DESC
");
$q2->execute([$tenant_id, $from, $to]);
$sales_by_store = $q2->fetchAll(PDO::FETCH_ASSOC);

// ── ПЕЧАЛБА (само owner) ───────────────────────────────────
$profit = 0; $margin_pct = 0;
if ($role === 'owner') {
    $qp = $pdo->prepare("
        SELECT
            COALESCE(SUM(si.quantity * si.unit_price),0) AS revenue,
            COALESCE(SUM(si.quantity * COALESCE(si.cost_price,0)),0) AS cost
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ?
    ");
    $qp->execute([$tenant_id, $from, $to]);
    $pr = $qp->fetch(PDO::FETCH_ASSOC);
    $profit     = $pr['revenue'] - $pr['cost'];
    $margin_pct = $pr['revenue'] > 0 ? round(($profit / $pr['revenue']) * 100, 1) : 0;
}

// ── ТОП 5 АРТИКУЛА ───────────────────────────────────────
$qt = $pdo->prepare("
    SELECT p.name, SUM(si.quantity) AS qty_sold,
           SUM(si.quantity * si.unit_price) AS total
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY si.product_id
    ORDER BY qty_sold DESC
    LIMIT 5
");
$qt->execute([$tenant_id, $from, $to]);
$top_products = $qt->fetchAll(PDO::FETCH_ASSOC);

// ── СТОКА БЕЗ ДВИЖЕНИЕ ≥30 ДЕНИ ──────────────────────────
$qd = $pdo->prepare("
    SELECT p.name, i.quantity,
           COALESCE(MAX(sm.created_at), p.created_at) AS last_movement,
           DATEDIFF(NOW(), COALESCE(MAX(sm.created_at), p.created_at)) AS days_idle,
           i.quantity * p.cost_price AS dead_value
    FROM inventory i
    JOIN products p ON p.id = i.product_id
    LEFT JOIN stock_movements sm ON sm.product_id = i.product_id
        AND sm.store_id = i.store_id AND sm.tenant_id = i.tenant_id
    WHERE i.tenant_id=? AND i.quantity > 0
    GROUP BY i.product_id, i.store_id
    HAVING days_idle >= 30
    ORDER BY dead_value DESC
    LIMIT 20
");
$qd->execute([$tenant_id]);
$dead_stock = $qd->fetchAll(PDO::FETCH_ASSOC);
$dead_capital = array_sum(array_column($dead_stock, 'dead_value'));

// ── НИСКИ НАЛИЧНОСТИ ─────────────────────────────────────
$ql = $pdo->prepare("
    SELECT p.name, i.quantity, i.min_quantity, st.name AS store_name
    FROM inventory i
    JOIN products p ON p.id = i.product_id
    JOIN stores st ON st.id = i.store_id
    WHERE i.tenant_id=? AND i.quantity <= i.min_quantity AND i.min_quantity > 0
    ORDER BY i.quantity ASC
    LIMIT 20
");
$ql->execute([$tenant_id]);
$low_stock = $ql->fetchAll(PDO::FETCH_ASSOC);

// ── НЕПЛАТЕНИ ФАКТУРИ (owner/manager) ────────────────────
$unpaid_invoices = []; $unpaid_total = 0;
if ($role !== 'seller') {
    $qi = $pdo->prepare("
        SELECT i.invoice_number, i.due_date, i.total_amount,
               s.name AS supplier_name,
               DATEDIFF(NOW(), i.due_date) AS overdue_days
        FROM invoices i
        LEFT JOIN suppliers s ON s.id = i.supplier_id
        WHERE i.tenant_id=? AND i.status != 'paid'
        ORDER BY i.due_date ASC
        LIMIT 15
    ");
    $qi->execute([$tenant_id]);
    $unpaid_invoices = $qi->fetchAll(PDO::FETCH_ASSOC);
    $unpaid_total    = array_sum(array_column($unpaid_invoices, 'total_amount'));
}

// ── АНОМАЛИИ — ОТСТЪПКИ ──────────────────────────────────
$anomaly_discounts = [];
if ($role === 'owner') {
    $qa = $pdo->prepare("
        SELECT u.name AS seller, COUNT(*) AS cnt,
               AVG(s.discount_amount) AS avg_discount,
               SUM(s.discount_amount) AS total_discount
        FROM sales s
        JOIN users u ON u.id = s.user_id
        WHERE s.tenant_id=? AND DATE(s.created_at) BETWEEN ? AND ?
            AND s.discount_amount > 0
        GROUP BY s.user_id
        ORDER BY total_discount DESC
        LIMIT 5
    ");
    $qa->execute([$tenant_id, $from, $to]);
    $anomaly_discounts = $qa->fetchAll(PDO::FETCH_ASSOC);
}

// ── ЗДРАВЕ НА БИЗНЕСА (0-100) ────────────────────────────
$health_score = 50;
if ($role === 'owner') {
    $score = 100;
    if ($low_stock)      $score -= min(30, count($low_stock) * 3);
    if ($dead_capital > 1000) $score -= min(20, (int)($dead_capital / 500));
    if ($unpaid_total > 500)  $score -= min(20, (int)($unpaid_total / 500));
    if ($margin_pct < 20)     $score -= 15;
    $health_score = max(0, min(100, $score));
}

// Всички обекти за филтър
$stores_list = [];
if ($role !== 'seller') {
    $qs = $pdo->prepare("SELECT id, name FROM stores WHERE tenant_id=? AND is_active=1 ORDER BY name");
    $qs->execute([$tenant_id]);
    $stores_list = $qs->fetchAll(PDO::FETCH_ASSOC);
}

// Активен таб
$active_tab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Статистики — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
  :root { --bottom-nav-h: 64px; }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  body { background: #030712; color: #e2e8f0; font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; overflow-x: hidden; }

  /* ── HEADER ── */
  .stats-header {
    position: sticky; top: 0; z-index: 50;
    background: rgba(3,7,18,.92); backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(99,102,241,.15);
    padding: 12px 16px 0;
  }
  .stats-header h1 { font-size: 18px; font-weight: 700; color: #e2e8f0; margin: 0 0 10px; }

  /* ── PERIOD PILLS ── */
  .period-bar { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; padding-bottom: 10px; }
  .period-bar::-webkit-scrollbar { display: none; }
  .period-pill {
    flex-shrink: 0; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 1px solid rgba(99,102,241,.3); color: #a5b4fc; background: transparent;
    text-decoration: none; white-space: nowrap;
  }
  .period-pill.active { background: #6366f1; border-color: #6366f1; color: #fff; }

  /* ── TABS ── */
  .tabs-bar { display: flex; overflow-x: auto; scrollbar-width: none; background: rgba(15,15,35,.8); border-bottom: 1px solid rgba(99,102,241,.1); }
  .tabs-bar::-webkit-scrollbar { display: none; }
  .tab-btn {
    flex-shrink: 0; padding: 10px 16px; font-size: 12px; font-weight: 600;
    color: #6b7280; border: none; background: transparent; cursor: pointer;
    border-bottom: 2px solid transparent; white-space: nowrap;
  }
  .tab-btn.active { color: #6366f1; border-bottom-color: #6366f1; }

  /* ── CONTENT ── */
  .stats-content { padding: 12px 12px 80px; }

  /* ── STAT CARD ── */
  .stat-card {
    background: rgba(15,15,40,.7); border: 1px solid rgba(99,102,241,.15);
    border-radius: 14px; padding: 14px; cursor: pointer;
    transition: border-color .2s, transform .15s;
    -webkit-user-select: none; user-select: none;
  }
  .stat-card:active { transform: scale(.97); border-color: #6366f1; }
  .stat-card .label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
  .stat-card .value { font-size: 24px; font-weight: 800; color: #f1f5f9; line-height: 1; }
  .stat-card .sub { font-size: 12px; color: #6b7280; margin-top: 4px; }
  .stat-card .trend { font-size: 11px; font-weight: 700; margin-top: 6px; }
  .trend.up { color: #22c55e; } .trend.down { color: #ef4444; } .trend.neutral { color: #f59e0b; }
  .stat-card .tap-hint { font-size: 10px; color: rgba(99,102,241,.5); margin-top: 6px; }

  /* ── GRID ── */
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
  .grid-1 { display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 10px; }

  /* ── SECTION TITLE ── */
  .section-title { font-size: 11px; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 1px; margin: 16px 0 8px; }

  /* ── LIST CARD ── */
  .list-card { background: rgba(15,15,40,.7); border: 1px solid rgba(99,102,241,.12); border-radius: 14px; overflow: hidden; margin-bottom: 10px; }
  .list-row { display: flex; justify-content: space-between; align-items: center; padding: 11px 14px; border-bottom: 1px solid rgba(99,102,241,.07); }
  .list-row:last-child { border-bottom: none; }
  .list-row .name { font-size: 13px; color: #e2e8f0; font-weight: 500; flex: 1; margin-right: 8px; }
  .list-row .val { font-size: 13px; font-weight: 700; color: #a5b4fc; }
  .list-row .badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
  .badge-red { background: rgba(239,68,68,.15); color: #ef4444; }
  .badge-yellow { background: rgba(245,158,11,.15); color: #f59e0b; }
  .badge-green { background: rgba(34,197,94,.15); color: #22c55e; }

  /* ── HEALTH SCORE ── */
  .health-wrap { background: rgba(15,15,40,.7); border: 1px solid rgba(99,102,241,.15); border-radius: 14px; padding: 16px; text-align: center; cursor: pointer; margin-bottom: 10px; }
  .health-score { font-size: 56px; font-weight: 900; line-height: 1; }
  .health-score.great { color: #22c55e; } .health-score.ok { color: #f59e0b; } .health-score.bad { color: #ef4444; }
  .health-label { font-size: 12px; color: #6b7280; margin-top: 4px; }
  .health-bar-bg { background: rgba(99,102,241,.15); border-radius: 6px; height: 8px; margin: 10px 0; }
  .health-bar-fill { height: 8px; border-radius: 6px; transition: width .8s ease; }

  /* ── ANOMALY ── */
  .anomaly-card { display: flex; align-items: flex-start; gap: 10px; background: rgba(15,15,40,.7); border-radius: 14px; padding: 12px; margin-bottom: 8px; border: 1px solid rgba(239,68,68,.2); cursor: pointer; }
  .anomaly-card.warning { border-color: rgba(245,158,11,.2); }
  .anomaly-card.info { border-color: rgba(99,102,241,.2); }
  .anomaly-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
  .dot-red { background: #ef4444; box-shadow: 0 0 6px #ef4444; }
  .dot-yellow { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }
  .dot-blue { background: #6366f1; box-shadow: 0 0 6px #6366f1; }
  .anomaly-text .title { font-size: 13px; font-weight: 600; color: #f1f5f9; }
  .anomaly-text .desc { font-size: 11px; color: #6b7280; margin-top: 2px; line-height: 1.4; }

  /* ── STORE FILTER ── */
  .store-select { background: rgba(15,15,40,.8); border: 1px solid rgba(99,102,241,.25); border-radius: 10px; color: #a5b4fc; font-size: 12px; padding: 6px 10px; width: 100%; margin-bottom: 10px; }

  /* ── DRAWER OVERLAY ── */
  .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 200; opacity: 0; pointer-events: none; transition: opacity .25s; }
  .drawer-overlay.open { opacity: 1; pointer-events: all; }

  /* ── DRAWER ── */
  .drawer {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 201;
    background: #0f0f23; border-top: 1px solid rgba(99,102,241,.25);
    border-radius: 20px 20px 0 0; padding: 0 0 40px;
    transform: translateY(100%); transition: transform .3s cubic-bezier(.32,0,.67,0);
    max-height: 85vh; overflow-y: auto;
  }
  .drawer.open { transform: translateY(0); }
  .drawer-handle { width: 36px; height: 4px; background: rgba(99,102,241,.3); border-radius: 2px; margin: 12px auto 16px; }
  .drawer-body { padding: 0 16px 20px; }
  .drawer-title { font-size: 18px; font-weight: 800; color: #f1f5f9; margin-bottom: 4px; }
  .drawer-value { font-size: 32px; font-weight: 900; color: #6366f1; line-height: 1.1; margin-bottom: 12px; }
  .drawer-explain { background: rgba(99,102,241,.08); border-radius: 10px; padding: 12px; font-size: 13px; color: #a5b4fc; line-height: 1.6; margin-bottom: 12px; }
  .drawer-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px; }
  .drawer-table th { color: #6b7280; font-weight: 700; text-align: left; padding: 6px 8px; border-bottom: 1px solid rgba(99,102,241,.1); }
  .drawer-table td { color: #e2e8f0; padding: 7px 8px; border-bottom: 1px solid rgba(99,102,241,.06); }
  .ai-box { background: linear-gradient(135deg, rgba(99,102,241,.15), rgba(168,85,247,.1)); border: 1px solid rgba(99,102,241,.25); border-radius: 12px; padding: 12px; margin-bottom: 12px; }
  .ai-box .ai-label { font-size: 10px; font-weight: 700; color: #a5b4fc; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .ai-box .ai-text { font-size: 13px; color: #e2e8f0; line-height: 1.5; }
  .drawer-btn { display: block; width: 100%; padding: 13px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; border-radius: 12px; color: #fff; font-size: 14px; font-weight: 700; text-align: center; cursor: pointer; }

  /* ── BOTTOM NAV ── */
  .bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-h);
    background: rgba(3,7,18,.95); backdrop-filter: blur(16px);
    border-top: 1px solid rgba(99,102,241,.15);
    display: flex; align-items: center; z-index: 100;
  }
  .nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; padding: 8px 0; text-decoration: none; cursor: pointer; border: none; background: transparent; }
  .nav-item svg { width: 22px; height: 22px; }
  .nav-item span { font-size: 10px; font-weight: 600; }
  .nav-item.active svg, .nav-item.active span { color: #6366f1; }
  .nav-item svg, .nav-item span { color: #3f3f5a; }

  /* ── EMPTY STATE ── */
  .empty-state { text-align: center; padding: 40px 20px; color: #4b5563; }
  .empty-state svg { width: 40px; height: 40px; margin-bottom: 10px; color: #374151; }
  .empty-state p { font-size: 13px; }
</style>
</head>
<body>

<div class="stats-header">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Статистики</h1>
    <?php if ($role !== 'seller' && count($stores_list) > 1): ?>
    <form method="GET" style="margin:0;">
      <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
      <select name="store" class="store-select" style="width:auto;margin:0" onchange="this.form.submit()">
        <option value="">Всички обекти</option>
        <?php foreach ($stores_list as $s): ?>
          <option value="<?= $s['id'] ?>" <?= (isset($_GET['store']) && $_GET['store'] == $s['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>

  <div class="period-bar">
    <?php foreach (['today'=>'Днес','week'=>'Седмица','month'=>'Месец'] as $k=>$v): ?>
      <a href="?period=<?=$k?>&tab=<?= htmlspecialchars($active_tab) ?>" class="period-pill <?= $period===$k?'active':'' ?>"><?=$v?></a>
    <?php endforeach; ?>
    <?php if ($period === 'custom'): ?>
      <span class="period-pill active"><?= $from ?> → <?= $to ?></span>
    <?php endif; ?>
  </div>

  <div class="tabs-bar">
    <?php
    $tabs = ['overview'=>'Обзор','sales'=>'Продажби','products'=>'Стоки','finance'=>'Финанси','anomalies'=>'Аномалии'];
    foreach ($tabs as $k => $v):
    ?>
      <button class="tab-btn <?= $active_tab===$k?'active':'' ?>"
              onclick="switchTab('<?=$k?>')"><?=$v?></button>
    <?php endforeach; ?>
  </div>
</div>

<div class="stats-content">

  <!-- ══════════════════ ОБЗОР ══════════════════ -->
  <div id="tab-overview" class="tab-content" <?= $active_tab!=='overview'?'style="display:none"':'' ?>>

    <?php if ($role === 'owner'): ?>
    <div class="health-wrap" onclick="openDrawer('health')">
      <?php
        $hc = $health_score >= 70 ? 'great' : ($health_score >= 40 ? 'ok' : 'bad');
        $hcolor = $health_score >= 70 ? '#22c55e' : ($health_score >= 40 ? '#f59e0b' : '#ef4444');
        $hlabel = $health_score >= 70 ? 'Бизнесът е в добро здраве' : ($health_score >= 40 ? 'Има неща за подобрение' : 'Нужно е внимание');
      ?>
      <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Здраве на бизнеса</div>
      <div class="health-score <?= $hc ?>"><?= $health_score ?></div>
      <div class="health-label"><?= $hlabel ?></div>
      <div class="health-bar-bg">
        <div class="health-bar-fill" style="width:<?= $health_score ?>%;background:<?= $hcolor ?>"></div>
      </div>
      <div style="font-size:10px;color:rgba(99,102,241,.5);">Натисни за обяснение</div>
    </div>
    <?php endif; ?>

    <div class="grid-2">
      <div class="stat-card" onclick="openDrawer('revenue')">
        <div class="label">Оборот</div>
        <div class="value"><?= number_format($sales_summary['revenue'],0,',','.') ?></div>
        <div class="sub"><?= $currency ?></div>
        <div class="tap-hint">↗ Натисни за детайли</div>
      </div>
      <div class="stat-card" onclick="openDrawer('transactions')">
        <div class="label">Транзакции</div>
        <div class="value"><?= $sales_summary['transactions'] ?></div>
        <div class="sub">продажби</div>
        <div class="tap-hint">↗ Натисни за детайли</div>
      </div>
    </div>

    <div class="grid-2">
      <div class="stat-card" onclick="openDrawer('avg_ticket')">
        <div class="label">Средна сметка</div>
        <div class="value"><?= number_format($sales_summary['avg_ticket'],2,',','.') ?></div>
        <div class="sub"><?= $currency ?></div>
        <div class="tap-hint">↗ Натисни за детайли</div>
      </div>
      <?php if ($role === 'owner'): ?>
      <div class="stat-card" onclick="openDrawer('profit')">
        <div class="label">Печалба</div>
        <div class="value"><?= number_format($profit,0,',','.') ?></div>
        <div class="sub"><?= $margin_pct ?>% марж</div>
        <div class="tap-hint">↗ Натисни за детайли</div>
      </div>
      <?php else: ?>
      <div class="stat-card" onclick="openDrawer('low_stock')">
        <div class="label">Ниски наличности</div>
        <div class="value"><?= count($low_stock) ?></div>
        <div class="sub">артикула под минимум</div>
        <div class="tap-hint">↗ Натисни за детайли</div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($role !== 'seller' && $unpaid_total > 0): ?>
    <div class="stat-card" onclick="openDrawer('unpaid')" style="border-color:rgba(239,68,68,.25);">
      <div class="label">Неплатено към доставчици</div>
      <div class="value" style="color:#ef4444;"><?= number_format($unpaid_total,2,',','.') ?> <?= $currency ?></div>
      <div class="sub"><?= count($unpaid_invoices) ?> фактури чакат плащане</div>
    </div>
    <?php endif; ?>

    <?php if ($dead_capital > 0): ?>
    <div class="stat-card" onclick="openDrawer('dead_capital')" style="border-color:rgba(245,158,11,.25);">
      <div class="label">Мъртъв капитал</div>
      <div class="value" style="color:#f59e0b;"><?= number_format($dead_capital,0,',','.') ?> <?= $currency ?></div>
      <div class="sub">Стока без движение ≥30 дни</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════ ПРОДАЖБИ ══════════════════ -->
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

    <p class="section-title">Обобщение</p>
    <div class="grid-2">
      <div class="stat-card" onclick="openDrawer('revenue')">
        <div class="label">Общо</div>
        <div class="value"><?= number_format($sales_summary['revenue'],0,',','.') ?></div>
        <div class="sub"><?= $currency ?></div>
      </div>
      <div class="stat-card" onclick="openDrawer('avg_ticket')">
        <div class="label">Средна сметка</div>
        <div class="value"><?= number_format($sales_summary['avg_ticket'],2,',','.') ?></div>
        <div class="sub"><?= $currency ?></div>
      </div>
    </div>

    <?php if ($role === 'owner' && $anomaly_discounts): ?>
    <p class="section-title">Отстъпки по продавач</p>
    <div class="list-card">
      <?php foreach ($anomaly_discounts as $d): ?>
      <div class="list-row">
        <span class="name"><?= htmlspecialchars($d['seller']) ?></span>
        <span class="val" style="color:#f59e0b;">-<?= number_format($d['total_discount'],0,',','.') ?> <?= $currency ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════ СТОКИ ══════════════════ -->
  <div id="tab-products" class="tab-content" <?= $active_tab!=='products'?'style="display:none"':'' ?>>
    <p class="section-title">Топ 5 продавани</p>
    <div class="list-card">
      <?php if ($top_products): foreach ($top_products as $i => $p): ?>
      <div class="list-row">
        <span class="name"><span style="color:#6b7280;margin-right:6px;"><?= $i+1 ?>.</span><?= htmlspecialchars($p['name']) ?></span>
        <span class="val"><?= number_format($p['qty_sold'],0) ?> бр.</span>
      </div>
      <?php endforeach; else: ?>
      <div class="empty-state"><p>Няма данни за периода</p></div>
      <?php endif; ?>
    </div>

    <p class="section-title">Ниски наличности</p>
    <div class="list-card">
      <?php if ($low_stock): foreach ($low_stock as $l): ?>
      <div class="list-row">
        <div style="flex:1;">
          <div class="name"><?= htmlspecialchars($l['name']) ?></div>
          <div style="font-size:10px;color:#6b7280;"><?= htmlspecialchars($l['store_name']) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:13px;font-weight:700;color:#ef4444;"><?= $l['quantity'] ?> бр.</div>
          <div style="font-size:10px;color:#6b7280;">мин: <?= $l['min_quantity'] ?></div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty-state"><p>Всички артикули над минимума ✓</p></div>
      <?php endif; ?>
    </div>

    <p class="section-title">Стока без движение ≥30 дни</p>
    <div class="list-card">
      <?php if ($dead_stock): foreach (array_slice($dead_stock,0,8) as $d): ?>
      <div class="list-row">
        <div style="flex:1;">
          <div class="name"><?= htmlspecialchars($d['name']) ?></div>
          <div style="font-size:10px;color:#6b7280;"><?= $d['days_idle'] ?> дни без движение</div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:13px;font-weight:700;color:#f59e0b;"><?= number_format($d['dead_value'],0,',','.') ?> <?= $currency ?></div>
          <div style="font-size:10px;color:#6b7280;"><?= $d['quantity'] ?> бр.</div>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty-state"><p>Нямаш застояла стока ✓</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════ ФИНАНСИ ══════════════════ -->
  <div id="tab-finance" class="tab-content" <?= $active_tab!=='finance'?'style="display:none"':'' ?>>
    <?php if ($role === 'seller'): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
      <p>Финансовите данни са достъпни само за управители и собственици</p>
    </div>
    <?php else: ?>

    <?php if ($role === 'owner'): ?>
    <div class="grid-2">
      <div class="stat-card" onclick="openDrawer('profit')">
        <div class="label">Печалба</div>
        <div class="value"><?= number_format($profit,0,',','.') ?></div>
        <div class="sub"><?= $currency ?> · <?= $margin_pct ?>% марж</div>
      </div>
      <div class="stat-card" onclick="openDrawer('dead_capital')">
        <div class="label">Мъртъв капитал</div>
        <div class="value" style="color:#f59e0b;"><?= number_format($dead_capital,0,',','.') ?></div>
        <div class="sub"><?= $currency ?></div>
      </div>
    </div>
    <?php endif; ?>

    <p class="section-title">Неплатени фактури</p>
    <div class="list-card">
      <?php if ($unpaid_invoices): foreach ($unpaid_invoices as $inv): ?>
      <div class="list-row">
        <div style="flex:1;">
          <div class="name"><?= htmlspecialchars($inv['supplier_name'] ?? 'Неизвестен') ?></div>
          <div style="font-size:10px;color:#6b7280;">
            <?= $inv['invoice_number'] ?? '—' ?> · пада <?= $inv['due_date'] ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:13px;font-weight:700;color:<?= $inv['overdue_days'] > 0 ? '#ef4444' : '#a5b4fc' ?>;">
            <?= number_format($inv['total_amount'],2,',','.') ?> <?= $currency ?>
          </div>
          <?php if ($inv['overdue_days'] > 0): ?>
          <span class="badge badge-red">+<?= $inv['overdue_days'] ?> дни</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="empty-state"><p>Няма просрочени фактури ✓</p></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════ АНОМАЛИИ ══════════════════ -->
  <div id="tab-anomalies" class="tab-content" <?= $active_tab!=='anomalies'?'style="display:none"':'' ?>>

    <?php
    $anomalies = [];

    // Критични — артикули с qty=0 (топ продавани)
    $qa2 = $pdo->prepare("
        SELECT p.name
        FROM products p
        JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        WHERE p.tenant_id=? AND i.quantity=0
          AND (SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id
               WHERE si.product_id=p.id AND s.tenant_id=p.tenant_id
               AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) > 0
        LIMIT 5
    ");
    $qa2->execute([$tenant_id]);
    $zero_bestsellers = $qa2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($zero_bestsellers as $zb) {
        $anomalies[] = ['type'=>'critical','title'=>'Топ артикул изчерпан','desc'=>$zb['name'].' — продаван но с 0 наличност'];
    }

    // Ниски наличности
    foreach (array_slice($low_stock, 0, 3) as $l) {
        $anomalies[] = ['type'=>'warning','title'=>'Ниска наличност','desc'=>$l['name'].' — '.$l['quantity'].' бр. (мин: '.$l['min_quantity'].')'];
    }

    // Просрочени фактури
    foreach ($unpaid_invoices as $inv) {
        if ($inv['overdue_days'] > 0) {
            $anomalies[] = ['type'=>'critical','title'=>'Просрочена фактура','desc'=>($inv['supplier_name']??'Доставчик').' — просрочена с '.$inv['overdue_days'].' дни'];
        }
    }

    // Мъртъв капитал
    if ($dead_capital > 1000) {
        $anomalies[] = ['type'=>'warning','title'=>'Мъртъв капитал','desc'=>number_format($dead_capital,0,',','.').' '.$currency.' блокирани в стока без движение'];
    }

    // Отстъпки (owner)
    if ($role === 'owner') {
        foreach ($anomaly_discounts as $d) {
            if ($d['total_discount'] > 100) {
                $anomalies[] = ['type'=>'info','title'=>'Нетипични отстъпки','desc'=>$d['seller'].' е дал '.number_format($d['total_discount'],0,',','.').' '.$currency.' отстъпки за периода'];
            }
        }
    }
    ?>

    <?php if ($anomalies): ?>
      <p class="section-title">🔴 Критични</p>
      <?php foreach ($anomalies as $an): if ($an['type'] !== 'critical') continue; ?>
      <div class="anomaly-card">
        <div class="anomaly-dot dot-red"></div>
        <div class="anomaly-text">
          <div class="title"><?= htmlspecialchars($an['title']) ?></div>
          <div class="desc"><?= htmlspecialchars($an['desc']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>

      <p class="section-title">🟡 Предупреждения</p>
      <?php foreach ($anomalies as $an): if ($an['type'] !== 'warning') continue; ?>
      <div class="anomaly-card warning">
        <div class="anomaly-dot dot-yellow"></div>
        <div class="anomaly-text">
          <div class="title"><?= htmlspecialchars($an['title']) ?></div>
          <div class="desc"><?= htmlspecialchars($an['desc']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>

      <p class="section-title">🔵 Информация</p>
      <?php foreach ($anomalies as $an): if ($an['type'] !== 'info') continue; ?>
      <div class="anomaly-card info">
        <div class="anomaly-dot dot-blue"></div>
        <div class="anomaly-text">
          <div class="title"><?= htmlspecialchars($an['title']) ?></div>
          <div class="desc"><?= htmlspecialchars($an['desc']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>

    <?php else: ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <p>Няма аномалии. Бизнесът върви добре!</p>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /stats-content -->

<!-- ══════════════════ DRAWER OVERLAY ══════════════════ -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-handle"></div>
  <div class="drawer-body" id="drawerBody"></div>
</div>

<!-- ══════════════════ BOTTOM NAV ══════════════════ -->
<nav class="bottom-nav">
  <a href="chat.php" class="nav-item">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    <span>Чат</span>
  </a>
  <a href="warehouse.php" class="nav-item">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
    <span>Склад</span>
  </a>
  <a href="stats.php" class="nav-item active">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
    <span>Статистики</span>
  </a>
  <a href="sale.php" class="nav-item">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    <span>Въвеждане</span>
  </a>
</nav>

<script>
// Drawer data
const drawerData = {
  revenue: {
    title: 'Оборот',
    value: '<?= number_format($sales_summary['revenue'],2,',','.') ?> <?= $currency ?>',
    explain: 'Общата сума на всички продажби за избрания период. Включва и продажби на едро и дребно.',
    ai: 'За по-висок оборот фокусирай се върху топ продаваните артикули — те носят 80% от парите. Увери се че никога не са на 0.',
    action: 'Виж продажбите',
    link: 'stats.php?tab=sales&period=<?= $period ?>'
  },
  transactions: {
    title: 'Брой транзакции',
    value: '<?= $sales_summary['transactions'] ?>',
    explain: 'Броят на отделните продажби за периода. По-много транзакции с по-висока средна сметка = по-добър бизнес.',
    ai: 'Средната сметка е <?= number_format($sales_summary['avg_ticket'],2,',','.') ?> <?= $currency ?>. Опитай да предлагаш допълнителен артикул на всяка продажба — дори +1 артикул вдига оборота значително.',
    action: null
  },
  avg_ticket: {
    title: 'Средна сметка',
    value: '<?= number_format($sales_summary['avg_ticket'],2,',','.') ?> <?= $currency ?>',
    explain: 'Средната стойност на всяка продажба. Изчислява се: Оборот ÷ Брой транзакции.',
    ai: 'За да вдигнеш средната сметка: 1) Предлагай допълнителни артикули при продажба, 2) Пусни промоция "Купи 2, получи 10% отстъпка", 3) Изложи скъпи артикули до касата.',
    action: null
  },
  profit: {
    title: 'Печалба',
    value: '<?= number_format($profit,2,',','.') ?> <?= $currency ?> (<?= $margin_pct ?>% марж)',
    explain: 'Чистата печалба = Оборот минус себестойността на продадената стока (по FIFO метод). Марж под 20% е сигнал за проблем.',
    ai: '<?= $margin_pct < 20 ? "Маржът е под 20% — провери дали купуваш на правилна цена или даваш твърде много отстъпки." : ($margin_pct < 35 ? "Маржът е добър. Можеш да го вдигнеш като намалиш отстъпките или преговориш с доставчиците." : "Отличен марж! Фокусирай се върху увеличаване на оборота.") ?>',
    action: null
  },
  low_stock: {
    title: 'Ниски наличности',
    value: '<?= count($low_stock) ?> артикула',
    explain: 'Артикули под минималната наличност която си задал. Рискуваш да пропуснеш продажби ако не ги заредиш.',
    ai: 'Заредете тези артикули приоритетно. Всеки ден без наличност = пропуснати продажби.',
    action: 'Виж всички',
    link: 'stats.php?tab=products&period=<?= $period ?>'
  },
  dead_capital: {
    title: 'Мъртъв капитал',
    value: '<?= number_format($dead_capital,2,',','.') ?> <?= $currency ?>',
    explain: 'Стойността на стока която стои без движение повече от 30 дни. Тези пари не работят за теб — те просто лежат на рафта.',
    ai: '<?= $dead_capital > 3000 ? "Над €3,000 мъртъв капитал — пусни -20% промоция на застоялите артикули. По-добре да вземеш €80 от €100 отколкото да чакаш." : "Пусни намаление -15% на артикулите без движение за уикенда. Освободените пари ще работят по-добре." ?>',
    action: 'Виж артикулите',
    link: 'stats.php?tab=products&period=<?= $period ?>'
  },
  unpaid: {
    title: 'Неплатено към доставчици',
    value: '<?= number_format($unpaid_total,2,',','.') ?> <?= $currency ?>',
    explain: 'Сумата по фактури към доставчици която все още не е платена. Просрочените фактури могат да навредят на отношенията с доставчиците.',
    ai: 'Плати просрочените фактури първо. Добрите отношения с доставчиците понякога водят до по-добри цени и приоритетни доставки.',
    action: 'Виж фактурите',
    link: 'stats.php?tab=finance&period=<?= $period ?>'
  },
  health: {
    title: 'Здраве на бизнеса',
    value: '<?= $health_score ?>/100',
    explain: 'Комплексна оценка изчислена от: наличности (30%), мъртъв капитал (20%), просрочени задължения (20%), марж (15%), аномалии (15%). 70+ = добре, 40-70 = внимание, под 40 = нужни действия.',
    ai: '<?= $health_score >= 70 ? "Бизнесът е в добро здраве. Продължавай в същата посока и следи аномалиите редовно." : ($health_score >= 40 ? "Има области за подобрение. Фокусирай се върху: 1) Зареждане на ниските наличности, 2) Ликвидиране на мъртвия капитал, 3) Плащане на просрочените фактури." : "Нужни са спешни действия. Провери критичните аномалии и действай веднага.") ?>',
    action: 'Виж аномалии',
    link: 'stats.php?tab=anomalies'
  }
};

function openDrawer(key) {
  const d = drawerData[key];
  if (!d) return;

  const body = document.getElementById('drawerBody');
  body.innerHTML = `
    <div class="drawer-title">${d.title}</div>
    <div class="drawer-value">${d.value}</div>
    <div class="drawer-explain">${d.explain}</div>
    <div class="ai-box">
      <div class="ai-label">AI препоръка</div>
      <div class="ai-text">${d.ai}</div>
    </div>
    ${d.action ? `<a href="${d.link}" class="drawer-btn">${d.action} →</a>` : ''}
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('drawer').classList.add('open');
}

function closeDrawer() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('drawer').classList.remove('open');
}

// Swipe down to close drawer
let touchStart = 0;
document.getElementById('drawer').addEventListener('touchstart', e => { touchStart = e.touches[0].clientY; });
document.getElementById('drawer').addEventListener('touchend', e => {
  if (e.changedTouches[0].clientY - touchStart > 60) closeDrawer();
});

function switchTab(tab) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
  document.getElementById('tab-' + tab).style.display = '';
  event.target.classList.add('active');
  // Update URL
  const url = new URL(window.location);
  url.searchParams.set('tab', tab);
  window.history.replaceState({}, '', url);
}
</script>

</body>
</html>
