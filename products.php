<?php
// products.php — Артикули с наличности
// Glassmorphism карти, mouse-tracking glow (Cruip pattern)
// Цветна лента вляво по статус наличност
// AI drawer за всеки артикул
// Role-based: seller вижда само своя обект

session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
$pdo = DB::get();

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];
$store_id  = $_SESSION['store_id'];
$currency  = $_SESSION['currency'] ?? 'EUR';

// Филтър
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
$sel_store = (int)($_GET['store'] ?? 0);

// Обекти
$stores = [];
if ($role !== 'seller') {
    $sq = $pdo->prepare("SELECT id, name FROM stores WHERE tenant_id=? AND is_active=1 ORDER BY name");
    $sq->execute([$tenant_id]);
    $stores = $sq->fetchAll(PDO::FETCH_ASSOC);
}

// Категории
$cq = $pdo->prepare("SELECT id, name FROM categories WHERE tenant_id=? ORDER BY name");
$cq->execute([$tenant_id]);
$categories = $cq->fetchAll(PDO::FETCH_ASSOC);

// Артикули с наличности
$where = ["p.tenant_id = ?", "p.parent_id IS NULL", "p.is_active = 1"];
$params = [$tenant_id];

if ($role === 'seller' && $store_id) {
    $store_filter = "AND i.store_id = ?";
    $params_inv = [$store_id];
} elseif ($sel_store) {
    $store_filter = "AND i.store_id = ?";
    $params_inv = [$sel_store];
} else {
    $store_filter = "";
    $params_inv = [];
}

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s);
}

$having = "";
if ($filter === 'low') {
    $having = "HAVING total_qty > 0 AND total_qty <= min_qty";
} elseif ($filter === 'out') {
    $having = "HAVING total_qty = 0";
} elseif ($filter === 'ok') {
    $having = "HAVING total_qty > min_qty";
}

$where_sql = implode(' AND ', $where);
$inv_params = array_merge($params, $params_inv);

$sql = "
    SELECT p.*,
           c.name AS category_name,
           COALESCE(SUM(i.quantity), 0) AS total_qty,
           COALESCE(MAX(i.min_quantity), 0) AS min_qty,
           COUNT(DISTINCT i.store_id) AS store_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id $store_filter
    WHERE $where_sql
    GROUP BY p.id
    $having
    ORDER BY p.name ASC
    LIMIT 100
";
$stmt = $pdo->prepare($sql);
$stmt->execute($inv_params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Броячи за табовете
$cnt_all = $cnt_low = $cnt_out = 0;
foreach ($products as $p) {
    $cnt_all++;
    if ($p['total_qty'] == 0) $cnt_out++;
    elseif ($p['total_qty'] <= $p['min_qty'] && $p['min_qty'] > 0) $cnt_low++;
}
// Реален count без HAVING
$cntq = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE p.tenant_id=? AND p.parent_id IS NULL AND p.is_active=1");
$cntq->execute([$tenant_id]);
$total_count = $cntq->fetchColumn();
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
:root{--bottom-nav-h:64px;--header-h:130px}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#030712;color:#e2e8f0;font-family:'Montserrat',sans-serif;margin:0;overflow-x:hidden}

/* ── AMBIENT GLOW BG ── */
body::before{content:'';position:fixed;top:-300px;left:50%;transform:translateX(-50%);width:800px;height:500px;background:radial-gradient(ellipse,rgba(99,102,241,.08) 0%,transparent 70%);pointer-events:none;z-index:0}
body::after{content:'';position:fixed;bottom:0;right:-100px;width:400px;height:400px;background:radial-gradient(ellipse,rgba(168,85,247,.05) 0%,transparent 70%);pointer-events:none;z-index:0}

/* ── HEADER ── */
.page-header{position:sticky;top:0;z-index:50;background:rgba(3,7,18,.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid rgba(99,102,241,.12);padding:14px 16px 0}
.header-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.page-title{font-size:20px;font-weight:800;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gradientShift 6s linear infinite}

/* ── ADD BUTTON ── */
.btn-add{display:flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:12px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 0 20px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.16);transition:all .2s;text-decoration:none;white-space:nowrap}
.btn-add:active{transform:scale(.96);box-shadow:0 0 10px rgba(99,102,241,.3)}

/* ── SEARCH ── */
.search-wrap{position:relative;margin-bottom:12px}
.search-input{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#e2e8f0;font-size:14px;padding:11px 44px 11px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
.search-input::placeholder{color:#4b5563}
.search-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#4b5563;pointer-events:none}

/* ── FILTER TABS ── */
.filter-tabs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding-bottom:12px}
.filter-tabs::-webkit-scrollbar{display:none}
.ftab{flex-shrink:0;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;white-space:nowrap}
.ftab.active{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent;color:#fff;box-shadow:0 0 16px rgba(99,102,241,.35)}
.ftab .cnt{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:9px;font-size:10px;font-weight:800;margin-left:4px;background:rgba(255,255,255,.15)}
.ftab:not(.active) .cnt{background:rgba(99,102,241,.15);color:#6366f1}

/* ── CONTENT ── */
.products-list{padding:12px 12px 80px;position:relative;z-index:1}

/* ── PRODUCT CARD ── */
.product-card{
  position:relative;border-radius:18px;margin-bottom:10px;overflow:hidden;
  background:rgba(15,15,35,.8);
  /* Cruip glassmorphism border */
  border:1px solid rgba(99,102,241,.0);
  cursor:pointer;
  transition:transform .2s;
  animation:cardSlideIn .35s ease both;
}
/* Mouse-tracking glow — Cruip pattern */
.product-card::before{
  content:'';position:absolute;left:var(--mx,-9999px);top:var(--my,-9999px);
  width:300px;height:300px;border-radius:50%;
  background:rgba(99,102,241,.25);
  transform:translate(-50%,-50%);
  filter:blur(60px);
  opacity:0;transition:opacity .4s;
  pointer-events:none;z-index:0;
}
.product-card:hover::before{opacity:1}
/* Glass border */
.product-card::after{
  content:'';position:absolute;inset:0;border-radius:inherit;
  border:1px solid transparent;
  background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(99,102,241,.05),rgba(99,102,241,.15)) border-box;
  -webkit-mask:linear-gradient(#fff 0 0) padding-box,linear-gradient(#fff 0 0);
  -webkit-mask-composite:destination-out;
  mask-composite:exclude;
  pointer-events:none;
}
.product-card:active{transform:scale(.98)}

/* ── STATUS STRIPE ── */
.status-stripe{position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:18px 0 0 18px;z-index:2}
.stripe-green{background:linear-gradient(to bottom,#22c55e,#16a34a);box-shadow:2px 0 12px rgba(34,197,94,.4)}
.stripe-yellow{background:linear-gradient(to bottom,#f59e0b,#d97706);box-shadow:2px 0 12px rgba(245,158,11,.4)}
.stripe-red{background:linear-gradient(to bottom,#ef4444,#dc2626);box-shadow:2px 0 12px rgba(239,68,68,.4)}

/* ── CARD INNER ── */
.card-inner{position:relative;z-index:1;padding:14px 14px 14px 18px;display:flex;align-items:flex-start;gap:12px}

/* ── PRODUCT ICON ── */
.product-icon{width:46px;height:46px;border-radius:12px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px}

/* ── CARD CONTENT ── */
.card-content{flex:1;min-width:0}
.card-name{font-size:14px;font-weight:700;color:#f1f5f9;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-meta{font-size:11px;color:#6b7280;margin-bottom:8px}
.card-meta span{color:#4b5563}
.card-tags{display:flex;gap:6px;flex-wrap:wrap;align-items:center}

/* ── PRICE TAG ── */
.price-tag{font-size:13px;font-weight:800;color:#a5b4fc;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:3px 8px}

/* ── STATUS BADGE ── */
.status-badge{font-size:11px;font-weight:700;border-radius:8px;padding:3px 8px}
.badge-ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);color:#22c55e}
.badge-low{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.25);color:#f59e0b}
.badge-out{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:#ef4444;animation:badgePulse 2s infinite}

/* ── QTY PILL ── */
.qty-wrap{text-align:right;flex-shrink:0}
.qty-num{font-size:22px;font-weight:900;line-height:1}
.qty-unit{font-size:10px;color:#6b7280;font-weight:600}

/* ── AI BUTTON ── */
.ai-btn{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#a5b4fc;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:3px 8px;cursor:pointer;transition:all .2s}
.ai-btn:active{background:rgba(99,102,241,.25)}

/* ── EMPTY STATE ── */
.empty-state{text-align:center;padding:60px 20px;color:#4b5563}
.empty-state svg{width:48px;height:48px;margin-bottom:12px;color:#1f2937}
.empty-state h3{font-size:16px;font-weight:700;color:#374151;margin:0 0 8px}
.empty-state p{font-size:13px;margin:0}

/* ── DRAWER OVERLAY ── */
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;opacity:0;pointer-events:none;transition:opacity .3s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.drawer-overlay.open{opacity:1;pointer-events:all}

/* ── DRAWER ── */
.drawer{position:fixed;bottom:0;left:0;right:0;z-index:201;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;padding:0 0 48px;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:88vh;overflow-y:auto;box-shadow:0 -30px 80px rgba(99,102,241,.2)}
.drawer.open{transform:translateY(0)}
.drawer-handle{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 20px}
.drawer-body{padding:0 20px 20px}
.drawer-product-name{font-size:20px;font-weight:800;color:#f1f5f9;margin-bottom:4px;background:linear-gradient(135deg,#f1f5f9,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.drawer-product-meta{font-size:12px;color:#6b7280;margin-bottom:20px}
.drawer-section{margin-bottom:16px}
.drawer-section-title{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.ai-recommendation{background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:14px;margin-bottom:16px}
.ai-rec-label{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.ai-rec-text{font-size:13px;color:#e2e8f0;line-height:1.6}
.stock-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}
.stock-item{background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;padding:10px 12px}
.stock-item-label{font-size:10px;color:#6b7280;font-weight:600;margin-bottom:2px}
.stock-item-val{font-size:18px;font-weight:800;color:#f1f5f9}
.drawer-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.daction-btn{padding:12px;border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;text-decoration:none;text-align:center}
.daction-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.3)}
.daction-secondary{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#a5b4fc}
.daction-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#ef4444}

/* ── MODAL (Add/Edit) ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:300;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{position:fixed;bottom:0;left:0;right:0;z-index:301;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;padding:0 0 48px;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:92vh;overflow-y:auto}
.modal.open{transform:translateY(0)}
.modal-handle{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 0}
.modal-header{padding:16px 20px;border-bottom:1px solid rgba(99,102,241,.1);display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:16px;font-weight:800;color:#f1f5f9}
.modal-close{width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,.1);border:none;color:#6b7280;display:flex;align-items:center;justify-content:center;cursor:pointer}
.modal-body{padding:20px}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.form-input{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:11px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;-webkit-appearance:none}
.form-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.form-input::placeholder{color:#4b5563}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.btn-submit{width:100%;padding:14px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.35),inset 0 1px 0 rgba(255,255,255,.16);transition:all .2s;margin-top:8px}
.btn-submit:active{transform:scale(.98)}

/* ── BOTTOM NAV ── */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:var(--bottom-nav-h);background:rgba(3,7,18,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(99,102,241,.1);display:flex;align-items:center;z-index:100}
.nav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:8px 0;text-decoration:none;border:none;background:transparent;cursor:pointer}
.nav-item svg{width:22px;height:22px;color:#3f3f5a;transition:color .2s}
.nav-item span{font-size:10px;font-weight:600;color:#3f3f5a;transition:color .2s}
.nav-item.active svg,.nav-item.active span{color:#6366f1}

/* ── SCROLL FADE IN ── */
@keyframes cardSlideIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes gradientShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes badgePulse{0%,100%{opacity:1}50%{opacity:.6}}

/* ── STAGGER DELAYS ── */
.product-card:nth-child(1){animation-delay:.04s}
.product-card:nth-child(2){animation-delay:.08s}
.product-card:nth-child(3){animation-delay:.12s}
.product-card:nth-child(4){animation-delay:.16s}
.product-card:nth-child(5){animation-delay:.20s}
.product-card:nth-child(n+6){animation-delay:.24s}

/* ── BLUR BACKDROP ON CARD CLICK ── */
.products-list.blurred .product-card:not(.focused){filter:blur(2px);opacity:.4;transition:filter .3s,opacity .3s}
.product-card.focused{transform:scale(1.01);z-index:10}
</style>
</head>
<body>

<div class="page-header">
  <div class="header-top">
    <h1 class="page-title">Артикули</h1>
    <button class="btn-add" onclick="openAddModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Добави
    </button>
  </div>

  <!-- Search -->
  <form method="GET" action="" class="search-wrap">
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <?php if ($sel_store): ?><input type="hidden" name="store" value="<?= $sel_store ?>"><?php endif; ?>
    <input type="text" name="q" class="search-input" placeholder="Търси по име, баркод, код..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
  </form>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <button class="ftab <?= $filter==='all'?'active':'' ?>" onclick="setFilter('all')">
      Артикули <span class="cnt"><?= $total_count ?></span>
    </button>
    <button class="ftab <?= $filter==='low'?'active':'' ?>" onclick="setFilter('low')">
      Ниска нал. <span class="cnt" style="<?= $cnt_low>0?'background:rgba(245,158,11,.25);color:#f59e0b':'' ?>"><?= $cnt_low ?></span>
    </button>
    <button class="ftab <?= $filter==='out'?'active':'' ?>" onclick="setFilter('out')">
      Изчерпани <span class="cnt" style="<?= $cnt_out>0?'background:rgba(239,68,68,.25);color:#ef4444':'' ?>"><?= $cnt_out ?></span>
    </button>
    <?php if ($role !== 'seller' && count($stores) > 1): ?>
    <select class="ftab" style="padding-right:28px;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236b7280' stroke-width='1.5'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 10px center;appearance:none" onchange="location='?filter=<?= $filter ?>&store='+this.value+'&q=<?= urlencode($search) ?>'">
      <option value="0" <?= !$sel_store?'selected':'' ?>>Всички обекти</option>
      <?php foreach ($stores as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $sel_store==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </div>
</div>

<div class="products-list" id="productsList">
  <?php if ($products): foreach ($products as $p):
    $qty = (float)$p['total_qty'];
    $min = (float)$p['min_qty'];

    if ($qty == 0) { $stripe = 'stripe-red'; $badge_class = 'badge-out'; $badge_text = 'Изчерпан'; $qty_color = '#ef4444'; }
    elseif ($min > 0 && $qty <= $min) { $stripe = 'stripe-yellow'; $badge_class = 'badge-low'; $badge_text = 'Ниска нал.'; $qty_color = '#f59e0b'; }
    else { $stripe = 'stripe-green'; $badge_class = 'badge-ok'; $badge_text = 'В наличност'; $qty_color = '#22c55e'; }

    $price = $p['retail_price'] ?? 0;
    $emoji = '📦';
    if ($p['category_name']) {
        $cat = mb_strtolower($p['category_name']);
        if (strpos($cat,'обув') !== false || strpos($cat,'маратон') !== false) $emoji = '👟';
        elseif (strpos($cat,'дрех') !== false || strpos($cat,'риз') !== false || strpos($cat,'якет') !== false) $emoji = '👕';
        elseif (strpos($cat,'аксесоар') !== false || strpos($cat,'чант') !== false) $emoji = '👜';
        elseif (strpos($cat,'електрон') !== false) $emoji = '📱';
    }

    $product_json = json_encode([
        'id' => $p['id'],
        'name' => $p['name'],
        'code' => $p['code'] ?? '',
        'qty' => $qty,
        'min' => $min,
        'price' => $price,
        'cost' => $p['cost_price'] ?? 0,
        'badge' => $badge_text,
        'badge_class' => $badge_class,
        'stripe' => $stripe,
        'category' => $p['category_name'] ?? '',
        'stores' => $p['store_count'],
    ], JSON_UNESCAPED_UNICODE);
  ?>
  <div class="product-card" onclick="openProductDrawer(<?= htmlspecialchars($product_json, ENT_QUOTES) ?>)" data-id="<?= $p['id'] ?>">
    <div class="status-stripe <?= $stripe ?>"></div>
    <div class="card-inner">
      <div class="product-icon"><?= $emoji ?></div>
      <div class="card-content">
        <div class="card-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="card-meta">
          <?php if ($p['code']): ?><span>Код: <?= htmlspecialchars($p['code']) ?></span> · <?php endif; ?>
          <?php if ($p['category_name']): ?><?= htmlspecialchars($p['category_name']) ?><?php endif; ?>
        </div>
        <div class="card-tags">
          <span class="price-tag">€<?= number_format($price, 2, ',', '.') ?></span>
          <span class="status-badge <?= $badge_class ?>"><?= $badge_text ?></span>
          <button class="ai-btn" onclick="event.stopPropagation(); openAIDrawer(<?= htmlspecialchars($product_json, ENT_QUOTES) ?>)">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
            AI
          </button>
        </div>
      </div>
      <div class="qty-wrap">
        <div class="qty-num" style="color:<?= $qty_color ?>"><?= number_format($qty, 0) ?></div>
        <div class="qty-unit">бр.</div>
      </div>
    </div>
  </div>
  <?php endforeach; else: ?>
  <div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    <h3>Няма артикули</h3>
    <p><?= $search ? "Нищо не отговаря на \"$search\"" : 'Добави първия си артикул' ?></p>
  </div>
  <?php endif; ?>
</div>

<!-- ══ PRODUCT DRAWER ══ -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeAllDrawers()"></div>
<div class="drawer" id="productDrawer">
  <div class="drawer-handle"></div>
  <div class="drawer-body" id="drawerContent"></div>
</div>

<!-- ══ AI DRAWER ══ -->
<div class="drawer" id="aiDrawer" style="z-index:202">
  <div class="drawer-handle"></div>
  <div class="drawer-body" id="aiDrawerContent"></div>
</div>

<!-- ══ ADD/EDIT MODAL ══ -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
<div class="modal" id="addModal">
  <div class="modal-handle"></div>
  <div class="modal-header">
    <span class="modal-title" id="modalTitle">Нов артикул</span>
    <button class="modal-close" onclick="closeModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="modal-body">
    <form id="productForm" action="product-save.php" method="POST">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="tenant_id" value="<?= $tenant_id ?>">

      <div class="form-group">
        <label class="form-label">Наименование *</label>
        <input type="text" name="name" id="f_name" class="form-input" placeholder="напр. Nike Air Max 42" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Код</label>
          <input type="text" name="code" id="f_code" class="form-input" placeholder="101">
        </div>
        <div class="form-group">
          <label class="form-label">Баркод</label>
          <input type="text" name="barcode" id="f_barcode" class="form-input" placeholder="EAN13">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Категория</label>
        <select name="category_id" id="f_cat" class="form-input form-select">
          <option value="">— Без категория —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Покупна цена</label>
          <input type="number" name="cost_price" id="f_cost" class="form-input" placeholder="0.00" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Продажна цена *</label>
          <input type="number" name="retail_price" id="f_price" class="form-input" placeholder="0.00" step="0.01" min="0" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Цена едро</label>
          <input type="number" name="wholesale_price" id="f_wprice" class="form-input" placeholder="0.00" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Мерна единица</label>
          <select name="unit" id="f_unit" class="form-input form-select">
            <option value="бр">бр</option>
            <option value="кг">кг</option>
            <option value="л">л</option>
            <option value="м">м</option>
            <option value="чифт">чифт</option>
            <option value="кутия">кутия</option>
            <option value="пакет">пакет</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Синоними (за AI търсене)</label>
        <input type="text" name="description" id="f_desc" class="form-input" placeholder="маратонки, найки, спортни обувки...">
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">Запази артикул</button>
    </form>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="chat.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg><span>Чат</span></a>
  <a href="warehouse.php" class="nav-item active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg><span>Склад</span></a>
  <a href="stats.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистики</span></a>
  <a href="sale.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>

<script>
// ── Mouse tracking glow (Cruip pattern) ──
document.querySelectorAll('.product-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const r = card.getBoundingClientRect();
    card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    card.style.setProperty('--my', (e.clientY - r.top) + 'px');
  });
});

// ── Filter ──
function setFilter(f) {
  const u = new URL(window.location);
  u.searchParams.set('filter', f);
  window.location = u;
}

// ── Product Drawer ──
function openProductDrawer(p) {
  const list = document.getElementById('productsList');
  list.classList.add('blurred');
  document.querySelector(`.product-card[data-id="${p.id}"]`)?.classList.add('focused');

  const stripeColor = p.stripe === 'stripe-green' ? '#22c55e' : p.stripe === 'stripe-yellow' ? '#f59e0b' : '#ef4444';
  const margin = p.cost > 0 ? ((p.price - p.cost) / p.price * 100).toFixed(1) : '—';

  document.getElementById('drawerContent').innerHTML = `
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <div style="width:6px;height:48px;border-radius:3px;background:${stripeColor};flex-shrink:0;box-shadow:0 0 12px ${stripeColor}66"></div>
      <div>
        <div class="drawer-product-name">${p.name}</div>
        <div class="drawer-product-meta">${p.code ? 'Код: ' + p.code + ' · ' : ''}${p.category || 'Без категория'}</div>
      </div>
    </div>

    <div class="stock-grid">
      <div class="stock-item">
        <div class="stock-item-label">Наличност</div>
        <div class="stock-item-val" style="color:${stripeColor}">${p.qty} бр.</div>
      </div>
      <div class="stock-item">
        <div class="stock-item-label">Минимум</div>
        <div class="stock-item-val">${p.min || '—'}</div>
      </div>
      <div class="stock-item">
        <div class="stock-item-label">Продажна цена</div>
        <div class="stock-item-val">€${parseFloat(p.price).toFixed(2)}</div>
      </div>
      <div class="stock-item">
        <div class="stock-item-label">Марж</div>
        <div class="stock-item-val" style="color:#a5b4fc">${margin}%</div>
      </div>
    </div>

    <div class="drawer-actions" style="margin-bottom:10px">
      <button class="daction-btn daction-primary" onclick="editProduct(${p.id})">✏️ Редактирай</button>
      <button class="daction-btn daction-secondary" onclick="openAIDrawer(${JSON.stringify(p).replace(/'/g,"\\'")})">✦ AI съвет</button>
    </div>
    <div class="drawer-actions">
      <a href="sale.php?product=${p.id}" class="daction-btn daction-secondary" style="display:flex;align-items:center;justify-content:center">🛒 Продажба</a>
      <button class="daction-btn daction-danger" onclick="confirmDelete(${p.id}, '${p.name.replace(/'/g,"\\'")}')">🗑 Изтрий</button>
    </div>
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('productDrawer').classList.add('open');
}

// ── AI Drawer ──
function openAIDrawer(p) {
  closeAllDrawers();

  let rec = '';
  if (p.qty == 0) {
    rec = `<strong>${p.name}</strong> е изчерпан. Ако се е продавал добре, зареди незабавно — всеки ден без наличност е пропуснат приход.`;
  } else if (p.min > 0 && p.qty <= p.min) {
    rec = `Наличността на <strong>${p.name}</strong> е под минимума. Помисли за презареждане скоро, за да избегнеш изчерпване в пиков момент.`;
  } else {
    const margin = p.cost > 0 ? ((p.price - p.cost) / p.price * 100).toFixed(1) : null;
    if (margin && margin < 20) {
      rec = `Маржът на <strong>${p.name}</strong> е само ${margin}%. Провери доставната цена или вдигни продажната.`;
    } else {
      rec = `<strong>${p.name}</strong> е в добро състояние. Наличността е над минимума и маржът е добър. Нищо спешно.`;
    }
  }

  document.getElementById('aiDrawerContent').innerHTML = `
    <div style="font-size:14px;font-weight:800;color:#a5b4fc;margin-bottom:4px">✦ AI Анализ</div>
    <div style="font-size:20px;font-weight:800;color:#f1f5f9;margin-bottom:16px">${p.name}</div>
    <div class="ai-recommendation">
      <div class="ai-rec-label">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
        Препоръка
      </div>
      <div class="ai-rec-text">${rec}</div>
    </div>
    <div class="stock-grid">
      <div class="stock-item"><div class="stock-item-label">Статус</div><div style="font-size:13px;font-weight:700;margin-top:2px">${p.badge}</div></div>
      <div class="stock-item"><div class="stock-item-label">В наличност</div><div class="stock-item-val">${p.qty} бр.</div></div>
    </div>
    <button class="daction-btn daction-primary" style="width:100%;margin-top:4px" onclick="window.location='chat.php?q=Анализирай артикул ${encodeURIComponent(p.name)}'">Попитай AI асистента →</button>
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('aiDrawer').classList.add('open');
}

function closeAllDrawers() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('productDrawer').classList.remove('open');
  document.getElementById('aiDrawer').classList.remove('open');
  document.getElementById('productsList').classList.remove('blurred');
  document.querySelectorAll('.product-card.focused').forEach(c => c.classList.remove('focused'));
}

// ── Swipe down to close ──
let ts = 0;
['productDrawer','aiDrawer'].forEach(id => {
  const el = document.getElementById(id);
  el.addEventListener('touchstart', e => ts = e.touches[0].clientY);
  el.addEventListener('touchend', e => { if (e.changedTouches[0].clientY - ts > 70) closeAllDrawers(); });
});

// ── Add Modal ──
function openAddModal() {
  document.getElementById('modalTitle').textContent = 'Нов артикул';
  document.getElementById('editId').value = '';
  document.getElementById('productForm').reset();
  document.getElementById('modalOverlay').classList.add('open');
  document.getElementById('addModal').classList.add('open');
}

function editProduct(id) {
  closeAllDrawers();
  fetch(`product-save.php?get=${id}`)
    .then(r => r.json())
    .then(p => {
      document.getElementById('modalTitle').textContent = 'Редактирай артикул';
      document.getElementById('editId').value = p.id;
      document.getElementById('f_name').value = p.name || '';
      document.getElementById('f_code').value = p.code || '';
      document.getElementById('f_barcode').value = p.barcode || '';
      document.getElementById('f_cost').value = p.cost_price || '';
      document.getElementById('f_price').value = p.retail_price || '';
      document.getElementById('f_wprice').value = p.wholesale_price || '';
      document.getElementById('f_cat').value = p.category_id || '';
      document.getElementById('f_unit').value = p.unit || 'бр';
      document.getElementById('f_desc').value = p.description || '';
      document.getElementById('modalOverlay').classList.add('open');
      document.getElementById('addModal').classList.add('open');
    });
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.getElementById('addModal').classList.remove('open');
}

function confirmDelete(id, name) {
  if (confirm(`Изтрий "${name}"?`)) {
    fetch(`product-save.php?delete=${id}`, {method:'POST'}).then(() => location.reload());
  }
}

// ── Touch swipe to close modal ──
document.getElementById('addModal').addEventListener('touchstart', e => ts = e.touches[0].clientY);
document.getElementById('addModal').addEventListener('touchend', e => { if (e.changedTouches[0].clientY - ts > 100) closeModal(); });
</script>
</body>
</html>
