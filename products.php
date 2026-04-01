<?php
// products.php — Артикули с наличности
// Нов дизайн 2026 + пълна логика от стария файл

require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

$tenant_id = $_SESSION['tenant_id'];
$role      = $_SESSION['role'] ?? 'seller';
$store_id  = $_SESSION['store_id'];
$currency  = $_SESSION['currency'] ?? 'EUR';
$search    = trim($_GET['q'] ?? '');
$filter    = $_GET['filter'] ?? 'all';

$can_add      = in_array($role, ['owner', 'manager']);
$can_see_cost = ($role === 'owner');

// Артикули
$sql = "
    SELECT
        p.*,
        c.name AS category_name,
        c.variant_type,
        s.name AS supplier_name,
        (SELECT COUNT(*) FROM products ch WHERE ch.parent_id = p.id AND ch.tenant_id = p.tenant_id) AS variant_count,
        (SELECT COALESCE(SUM(i.quantity),0) FROM inventory i WHERE i.product_id = p.id AND i.tenant_id = p.tenant_id) AS total_stock,
        (SELECT COALESCE(MAX(i.min_quantity),0) FROM inventory i WHERE i.product_id = p.id AND i.tenant_id = p.tenant_id) AS min_qty
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.tenant_id = ? AND p.parent_id IS NULL AND p.is_active = 1
";
$params = [$tenant_id];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.code LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY p.name ASC";

$all_products = DB::run($sql, $params)->fetchAll();

// Броячи
$low_stock_items  = array_filter($all_products, fn($p) => $p['total_stock'] > 0 && $p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']);
$zero_stock_items = array_filter($all_products, fn($p) => $p['total_stock'] == 0);

// Приложи филтър
if ($filter === 'low') {
    $products = array_values($low_stock_items);
} elseif ($filter === 'out') {
    $products = array_values($zero_stock_items);
} else {
    $products = $all_products;
}

$categories = DB::run("SELECT id, name, variant_type FROM categories WHERE tenant_id = ? AND parent_id IS NULL ORDER BY name", [$tenant_id])->fetchAll();
$suppliers  = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? AND is_active = 1 ORDER BY name", [$tenant_id])->fetchAll();
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
:root{--bottom-nav-h:64px}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#030712;color:#e2e8f0;font-family:'Montserrat',sans-serif;margin:0;overflow-x:hidden;padding-bottom:80px}

/* AMBIENT */
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);width:700px;height:500px;background:radial-gradient(ellipse,rgba(99,102,241,.08) 0%,transparent 70%);pointer-events:none;z-index:0}

/* HEADER */
.page-header{position:sticky;top:0;z-index:50;background:rgba(3,7,18,.93);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid rgba(99,102,241,.12);padding:14px 16px 0}
.header-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.page-title{font-size:20px;font-weight:800;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gradientShift 6s linear infinite}

/* ADD BUTTON */
.btn-add{display:flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:12px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 0 20px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.16);transition:all .2s}
.btn-add:active{transform:scale(.96)}

/* SEARCH */
.search-wrap{position:relative;margin-bottom:12px}
.search-input{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#e2e8f0;font-size:14px;padding:11px 44px 11px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
.search-input::placeholder{color:#4b5563}
.search-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#4b5563;pointer-events:none}

/* FILTER TABS */
.filter-tabs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding-bottom:12px}
.filter-tabs::-webkit-scrollbar{display:none}
.ftab{flex-shrink:0;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;white-space:nowrap}
.ftab.active{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent;color:#fff;box-shadow:0 0 16px rgba(99,102,241,.35)}
.ftab .cnt{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:9px;font-size:10px;font-weight:800;margin-left:4px;padding:0 4px;background:rgba(255,255,255,.15)}
.ftab:not(.active) .cnt{background:rgba(99,102,241,.15);color:#6366f1}
.ftab:not(.active) .cnt.warn{background:rgba(245,158,11,.15);color:#f59e0b}
.ftab:not(.active) .cnt.danger{background:rgba(239,68,68,.15);color:#ef4444}

/* CONTENT */
.products-list{padding:12px 12px 20px;position:relative;z-index:1}

/* PRODUCT CARD — Cruip mouse-tracking glow */
.product-card{
  position:relative;border-radius:18px;margin-bottom:10px;overflow:hidden;
  background:rgba(15,15,35,.85);cursor:pointer;
  transition:transform .2s,box-shadow .2s;
  animation:cardSlideIn .35s ease both;
}
.product-card::before{
  content:'';position:absolute;left:var(--mx,-9999px);top:var(--my,-9999px);
  width:280px;height:280px;border-radius:50%;
  background:rgba(99,102,241,.2);transform:translate(-50%,-50%);
  filter:blur(50px);opacity:0;transition:opacity .3s;pointer-events:none;z-index:0;
}
.product-card:hover::before{opacity:1}
/* Glass border */
.product-card::after{
  content:'';position:absolute;inset:0;border-radius:inherit;
  border:1px solid transparent;
  background:linear-gradient(135deg,rgba(99,102,241,.2),rgba(99,102,241,.04),rgba(99,102,241,.12)) border-box;
  -webkit-mask:linear-gradient(#fff 0 0) padding-box,linear-gradient(#fff 0 0);
  -webkit-mask-composite:destination-out;mask-composite:exclude;
  pointer-events:none;transition:opacity .2s;
}
.product-card:active{transform:scale(.98)}
.product-card:hover::after{opacity:1.5}

/* STATUS STRIPE */
.status-stripe{position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:18px 0 0 18px;z-index:2}
.stripe-green{background:linear-gradient(to bottom,#22c55e,#16a34a);box-shadow:2px 0 14px rgba(34,197,94,.5)}
.stripe-yellow{background:linear-gradient(to bottom,#f59e0b,#d97706);box-shadow:2px 0 14px rgba(245,158,11,.5)}
.stripe-red{background:linear-gradient(to bottom,#ef4444,#dc2626);box-shadow:2px 0 14px rgba(239,68,68,.5)}

/* CARD INNER */
.card-inner{position:relative;z-index:1;padding:14px 14px 14px 18px}

/* TOP ROW */
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px}
.card-left{flex:1;min-width:0}
.card-name{font-size:14px;font-weight:700;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.card-sub{font-size:11px;color:#6b7280}
.card-right{text-align:right;flex-shrink:0}
.qty-num{font-size:24px;font-weight:900;line-height:1}
.qty-unit{font-size:10px;color:#6b7280;font-weight:600}

/* TAGS */
.card-tags{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.tag{font-size:11px;font-weight:700;border-radius:8px;padding:3px 8px;border:1px solid transparent}
.tag-price{color:#a5b4fc;background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.2)}
.tag-cost{color:#fbbf24;background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.15)}
.tag-cat{color:#6b7280;background:rgba(107,114,128,.1);border-color:rgba(107,114,128,.15)}
.tag-variant{color:#8b5cf6;background:rgba(139,92,246,.1);border-color:rgba(139,92,246,.2)}
.tag-ok{color:#22c55e;background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.2)}
.tag-low{color:#f59e0b;background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.2);animation:badgePulse 2.5s infinite}
.tag-out{color:#ef4444;background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.2);animation:badgePulse 2s infinite}
.tag-supplier{color:#6b7280;background:transparent;border-color:transparent;font-size:10px;padding:0}

/* AI BUTTON */
.ai-btn{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#a5b4fc;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:3px 8px;cursor:pointer;transition:all .2s;font-family:'Montserrat',sans-serif}
.ai-btn:active{background:rgba(99,102,241,.3)}

/* BLURRED LIST */
.products-list.blurred .product-card:not(.focused){filter:blur(1.5px);opacity:.45;transition:filter .25s,opacity .25s;pointer-events:none}
.product-card.focused{z-index:10;box-shadow:0 8px 40px rgba(99,102,241,.25)}

/* EMPTY */
.empty-state{text-align:center;padding:60px 20px;color:#4b5563}
.empty-state svg{width:48px;height:48px;margin-bottom:12px;color:#1f2937}
.empty-state h3{font-size:16px;font-weight:700;color:#374151;margin:0 0 8px}
.empty-state p{font-size:13px;margin:0}

/* DRAWER OVERLAY */
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:200;opacity:0;pointer-events:none;transition:opacity .3s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.drawer-overlay.open{opacity:1;pointer-events:all}

/* DRAWER */
.drawer{position:fixed;bottom:0;left:0;right:0;z-index:201;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;padding:0 0 48px;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:88vh;overflow-y:auto;box-shadow:0 -30px 80px rgba(99,102,241,.2)}
.drawer.open{transform:translateY(0)}
.drawer-handle{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 20px}
.drawer-body{padding:0 20px 20px}
.d-name{font-size:22px;font-weight:800;color:#f1f5f9;margin-bottom:4px}
.d-meta{font-size:12px;color:#6b7280;margin-bottom:20px}
.stock-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}
.stock-item{background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;padding:10px 12px}
.si-label{font-size:10px;color:#6b7280;font-weight:600;margin-bottom:2px}
.si-val{font-size:18px;font-weight:800;color:#f1f5f9}
.d-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.dab{padding:13px;border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px}
.dab-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.3)}
.dab-secondary{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#a5b4fc}
.dab-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#ef4444}

/* AI DRAWER */
.ai-rec{background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:14px;margin-bottom:16px}
.ai-rec-label{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.ai-rec-text{font-size:13px;color:#e2e8f0;line-height:1.65}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:300;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{position:fixed;bottom:0;left:0;right:0;z-index:301;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;padding:0 0 48px;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:92vh;overflow-y:auto}
.modal.open{transform:translateY(0)}
.modal-handle{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 0}
.modal-header{padding:16px 20px 14px;border-bottom:1px solid rgba(99,102,241,.1);display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:16px;font-weight:800;color:#f1f5f9}
.modal-close{width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.15);color:#6b7280;display:flex;align-items:center;justify-content:center;cursor:pointer}
.modal-body{padding:20px}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.form-input,.form-select{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:11px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;-webkit-appearance:none}
.form-input:focus,.form-select:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.form-input::placeholder{color:#4b5563}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.variant-section{display:none;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:12px;padding:14px;margin-top:-6px;margin-bottom:16px}
.variant-section.show{display:block}
.variant-hint{font-size:12px;color:#6366f1;margin-bottom:8px;font-weight:600}
.variant-textarea{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:10px 14px;color:#e2e8f0;font-size:13px;resize:none;height:80px;outline:none;font-family:'Montserrat',sans-serif}
.btn-submit{width:100%;padding:14px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.35),inset 0 1px 0 rgba(255,255,255,.16);transition:all .2s;margin-top:8px}
.btn-submit:active{transform:scale(.98)}
.btn-cancel{width:100%;padding:12px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.15);border-radius:14px;color:#6b7280;font-size:14px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}

/* TOAST */
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;box-shadow:0 4px 20px rgba(99,102,241,.4)}
.toast.show{opacity:1}

/* BOTTOM NAV */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:var(--bottom-nav-h);background:rgba(3,7,18,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(99,102,241,.1);display:flex;align-items:center;z-index:100}
.nav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:8px 0;text-decoration:none;border:none;background:transparent;cursor:pointer}
.nav-item svg{width:22px;height:22px;color:#3f3f5a;transition:color .2s}
.nav-item span{font-size:10px;font-weight:600;color:#3f3f5a;transition:color .2s}
.nav-item.active svg,.nav-item.active span{color:#6366f1}

/* ANIMATIONS */
@keyframes cardSlideIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes gradientShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes badgePulse{0%,100%{opacity:1}50%{opacity:.55}}
.product-card:nth-child(1){animation-delay:.04s}
.product-card:nth-child(2){animation-delay:.08s}
.product-card:nth-child(3){animation-delay:.12s}
.product-card:nth-child(4){animation-delay:.16s}
.product-card:nth-child(5){animation-delay:.20s}
.product-card:nth-child(n+6){animation-delay:.22s}
</style>
</head>
<body>

<div class="page-header">
  <div class="header-top">
    <h1 class="page-title">Артикули</h1>
    <?php if ($can_add): ?>
    <button class="btn-add" onclick="openModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Добави
    </button>
    <?php endif; ?>
  </div>

  <form method="GET" class="search-wrap">
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <input type="text" name="q" class="search-input" placeholder="Търси по име, баркод, код..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
  </form>

  <div class="filter-tabs">
    <button class="ftab <?= $filter==='all'?'active':'' ?>" onclick="setFilter('all')">
      Артикули <span class="cnt"><?= count($all_products) ?></span>
    </button>
    <button class="ftab <?= $filter==='low'?'active':'' ?>" onclick="setFilter('low')">
      Ниска нал. <span class="cnt <?= count($low_stock_items)>0?'warn':'' ?>"><?= count($low_stock_items) ?></span>
    </button>
    <button class="ftab <?= $filter==='out'?'active':'' ?>" onclick="setFilter('out')">
      Изчерпани <span class="cnt <?= count($zero_stock_items)>0?'danger':'' ?>"><?= count($zero_stock_items) ?></span>
    </button>
  </div>
</div>

<div class="products-list" id="productsList">
<?php if (empty($products)): ?>
  <div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    <h3><?= $search ? 'Няма резултати' : 'Нямаш артикули' ?></h3>
    <p><?= $search ? "Нищо не отговаря на \"".htmlspecialchars($search)."\"" : ($can_add ? 'Натисни + Добави' : 'Кажи на AI да добави артикули') ?></p>
  </div>
<?php else: foreach ($products as $p):
  $qty  = (float)$p['total_stock'];
  $min  = (float)$p['min_qty'];
  $is_zero = $qty == 0;
  $is_low  = !$is_zero && $min > 0 && $qty <= $min;

  if ($is_zero)     { $stripe='stripe-red';    $tag_class='tag-out';  $tag_text='Изчерпан';   $qty_color='#ef4444'; }
  elseif ($is_low)  { $stripe='stripe-yellow'; $tag_class='tag-low';  $tag_text='Ниска нал.'; $qty_color='#f59e0b'; }
  else              { $stripe='stripe-green';  $tag_class='tag-ok';   $tag_text='В наличност'; $qty_color='#22c55e'; }

  $pd = json_encode([
    'id'       => $p['id'],
    'name'     => $p['name'],
    'code'     => $p['code'] ?? '',
    'barcode'  => $p['barcode'] ?? '',
    'qty'      => $qty,
    'min'      => $min,
    'price'    => (float)($p['retail_price'] ?? 0),
    'cost'     => (float)($p['cost_price'] ?? 0),
    'wprice'   => (float)($p['wholesale_price'] ?? 0),
    'unit'     => $p['unit'] ?? 'бр',
    'category' => $p['category_name'] ?? '',
    'supplier' => $p['supplier_name'] ?? '',
    'variants' => (int)$p['variant_count'],
    'location' => $p['location'] ?? '',
    'tag'      => $tag_text,
    'stripe'   => $stripe,
  ], JSON_UNESCAPED_UNICODE);
?>
  <div class="product-card" onclick="openDrawer(<?= htmlspecialchars($pd, ENT_QUOTES) ?>)" data-id="<?= $p['id'] ?>">
    <div class="status-stripe <?= $stripe ?>"></div>
    <div class="card-inner">
      <div class="card-top">
        <div class="card-left">
          <div class="card-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="card-sub">
            <?php if ($p['code']): ?>Код: <?= htmlspecialchars($p['code']) ?><?php endif; ?>
            <?php if ($p['code'] && $p['category_name']): ?> · <?php endif; ?>
            <?php if ($p['category_name']): ?><?= htmlspecialchars($p['category_name']) ?><?php endif; ?>
          </div>
        </div>
        <div class="card-right">
          <div class="qty-num" style="color:<?= $qty_color ?>"><?= number_format($qty, 0) ?></div>
          <div class="qty-unit"><?= htmlspecialchars($p['unit'] ?? 'бр') ?></div>
        </div>
      </div>
      <div class="card-tags">
        <span class="tag tag-price">€<?= number_format((float)($p['retail_price']??0), 2, ',', '.') ?></span>
        <?php if ($can_see_cost && $p['cost_price'] > 0): ?>
        <span class="tag tag-cost">Дост: €<?= number_format((float)$p['cost_price'], 2, ',', '.') ?></span>
        <?php endif; ?>
        <span class="tag <?= $tag_class ?>"><?= $tag_text ?></span>
        <?php if ($p['variant_count'] > 0): ?>
        <span class="tag tag-variant"><?= $p['variant_count'] ?> варианта</span>
        <?php endif; ?>
        <?php if ($p['supplier_name']): ?>
        <span class="tag tag-supplier"><?= htmlspecialchars($p['supplier_name']) ?></span>
        <?php endif; ?>
        <button class="ai-btn" onclick="event.stopPropagation(); openAIDrawer(<?= htmlspecialchars($pd, ENT_QUOTES) ?>)">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
          AI
        </button>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>

<!-- PRODUCT DRAWER -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeAll()"></div>
<div class="drawer" id="productDrawer">
  <div class="drawer-handle"></div>
  <div class="drawer-body" id="drawerContent"></div>
</div>

<!-- AI DRAWER -->
<div class="drawer" id="aiDrawer" style="z-index:202">
  <div class="drawer-handle"></div>
  <div class="drawer-body" id="aiContent"></div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()"></div>
<?php if ($can_add): ?>
<div class="modal" id="addModal">
  <div class="modal-handle"></div>
  <div class="modal-header">
    <span class="modal-title" id="modalTitle">Нов артикул</span>
    <button class="modal-close" onclick="closeModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="modal-body">
    <form method="POST" action="product-save.php" id="productForm">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="id" id="editId">

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
        <select name="category_id" id="f_cat" class="form-select form-input" onchange="onCategoryChange(this)">
          <option value="">— Без категория —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" data-variant="<?= $cat['variant_type'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="variant-section" id="variantSection">
        <div class="variant-hint" id="variantHint">Тази категория поддържа варианти</div>
        <label class="form-label">Варианти (по желание)</label>
        <textarea name="variants_raw" id="variantsTA" class="variant-textarea" placeholder="напр: Червена S-3, Червена M-5, Синя M-2"></textarea>
        <div style="font-size:11px;color:#6b7280;margin-top:6px">AI ще създаде отделен запис за всеки вариант автоматично.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Доставчик</label>
        <select name="supplier_id" id="f_sup" class="form-select form-input">
          <option value="">— Без доставчик —</option>
          <?php foreach ($suppliers as $sup): ?>
          <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Доставна цена</label>
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
          <select name="unit" id="f_unit" class="form-select form-input">
            <option value="бр">бр</option><option value="кг">кг</option><option value="гр">гр</option>
            <option value="л">л</option><option value="мл">мл</option><option value="м">м</option>
            <option value="чифт">чифт</option><option value="кутия">кутия</option>
            <option value="пакет">пакет</option><option value="комплект">комплект</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Локация</label>
          <input type="text" name="location" id="f_loc" class="form-input" placeholder="Рафт А-3">
        </div>
        <div class="form-group">
          <label class="form-label">Синоними (AI)</label>
          <input type="text" name="description" id="f_desc" class="form-input" placeholder="найки, спортни...">
        </div>
      </div>

      <button type="submit" class="btn-submit">Запази артикул</button>
      <button type="button" class="btn-cancel" onclick="closeModal()">Отказ</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="chat.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg><span>Чат</span></a>
  <a href="warehouse.php" class="nav-item active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg><span>Склад</span></a>
  <a href="stats.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистики</span></a>
  <a href="sale.php" class="nav-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>

<div class="toast" id="toast"></div>

<script>
// Mouse-tracking glow
document.querySelectorAll('.product-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const r = card.getBoundingClientRect();
    card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    card.style.setProperty('--my', (e.clientY - r.top) + 'px');
  });
});

function setFilter(f) {
  const u = new URL(window.location);
  u.searchParams.set('filter', f);
  if (document.querySelector('.search-input')?.value) u.searchParams.set('q', document.querySelector('.search-input').value);
  window.location = u;
}

// Product Drawer
function openDrawer(p) {
  const list = document.getElementById('productsList');
  list.classList.add('blurred');
  document.querySelector(`.product-card[data-id="${p.id}"]`)?.classList.add('focused');

  const sc = p.stripe === 'stripe-green' ? '#22c55e' : p.stripe === 'stripe-yellow' ? '#f59e0b' : '#ef4444';
  const margin = p.cost > 0 ? ((p.price - p.cost) / p.price * 100).toFixed(1) : '—';
  const canSeeCost = <?= $can_see_cost ? 'true' : 'false' ?>;

  document.getElementById('drawerContent').innerHTML = `
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
      <div style="width:5px;height:50px;border-radius:3px;background:${sc};box-shadow:0 0 14px ${sc}88;flex-shrink:0"></div>
      <div style="flex:1;min-width:0">
        <div class="d-name">${p.name}</div>
        <div class="d-meta">${p.code?'Код: '+p.code+' · ':''}${p.category||''}${p.supplier?' · '+p.supplier:''}</div>
      </div>
    </div>
    <div class="stock-grid">
      <div class="stock-item"><div class="si-label">Наличност</div><div class="si-val" style="color:${sc}">${p.qty} ${p.unit}</div></div>
      <div class="stock-item"><div class="si-label">Минимум</div><div class="si-val">${p.min||'—'}</div></div>
      <div class="stock-item"><div class="si-label">Продажна цена</div><div class="si-val">€${parseFloat(p.price).toFixed(2)}</div></div>
      ${canSeeCost && p.cost > 0 ? `<div class="stock-item"><div class="si-label">Доставна / Марж</div><div class="si-val" style="color:#a5b4fc">€${parseFloat(p.cost).toFixed(2)} · ${margin}%</div></div>` : `<div class="stock-item"><div class="si-label">Марж</div><div class="si-val" style="color:#a5b4fc">${margin}%</div></div>`}
    </div>
    ${p.location ? `<div style="font-size:12px;color:#6b7280;margin-bottom:14px">📍 Локация: ${p.location}</div>` : ''}
    ${p.variants > 0 ? `<div style="font-size:12px;color:#8b5cf6;margin-bottom:14px">🎨 ${p.variants} варианта → <a href="product-detail.php?id=${p.id}" style="color:#8b5cf6">виж всички</a></div>` : ''}
    <div class="d-actions" style="margin-bottom:8px">
      ${<?= $can_add ? 'true' : 'false' ?> ? `<button class="dab dab-primary" onclick="editProduct(${p.id})">✏️ Редактирай</button>` : '<div></div>'}
      <button class="dab dab-secondary" onclick="openAIDrawer(${JSON.stringify(p).replace(/\\/g,'\\\\').replace(/'/g,"\\'")})">✦ AI съвет</button>
    </div>
    <div class="d-actions">
      <a href="sale.php?product=${p.id}" class="dab dab-secondary">🛒 Продажба</a>
      <a href="product-detail.php?id=${p.id}" class="dab dab-secondary">🔍 Детайли</a>
    </div>
  `;

  document.getElementById('drawerOverlay').classList.add('open');
  document.getElementById('productDrawer').classList.add('open');
}

// AI Drawer
function openAIDrawer(p) {
  document.getElementById('productDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.add('open');

  let rec = '';
  if (p.qty == 0) {
    rec = `<strong>${p.name}</strong> е изчерпан. Ако се е продавал добре — зареди незабавно. Всеки ден без наличност е пропуснат приход.`;
  } else if (p.min > 0 && p.qty <= p.min) {
    rec = `Наличността е под минимума (${p.qty} от ${p.min} ${p.unit}). Помисли за презареждане скоро.`;
  } else if (p.cost > 0) {
    const m = ((p.price - p.cost) / p.price * 100).toFixed(1);
    if (m < 20) rec = `Маржът на <strong>${p.name}</strong> е само ${m}%. Провери доставната цена или вдигни продажната.`;
    else rec = `<strong>${p.name}</strong> е в добро състояние. Наличността е над минимума и маржът е ${m}%. Нищо спешно.`;
  } else {
    rec = `<strong>${p.name}</strong> е в наличност. Добави доставна цена за по-точен анализ на маржа.`;
  }

  document.getElementById('aiContent').innerHTML = `
    <div style="font-size:12px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">✦ AI Анализ</div>
    <div style="font-size:20px;font-weight:800;color:#f1f5f9;margin-bottom:18px">${p.name}</div>
    <div class="ai-rec">
      <div class="ai-rec-label">Препоръка</div>
      <div class="ai-rec-text">${rec}</div>
    </div>
    <div class="stock-grid">
      <div class="stock-item"><div class="si-label">Статус</div><div style="font-size:13px;font-weight:700;margin-top:4px">${p.tag}</div></div>
      <div class="stock-item"><div class="si-label">Наличност</div><div class="si-val">${p.qty} ${p.unit}</div></div>
    </div>
    <a href="chat.php?q=${encodeURIComponent('Анализирай артикул '+p.name)}" class="dab dab-primary" style="width:100%;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:6px">Попитай AI асистента →</a>
  `;

  document.getElementById('aiDrawer').classList.add('open');
}

function closeAll() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.getElementById('productDrawer').classList.remove('open');
  document.getElementById('aiDrawer').classList.remove('open');
  document.getElementById('productsList').classList.remove('blurred');
  document.querySelectorAll('.product-card.focused').forEach(c => c.classList.remove('focused'));
}

// Swipe down to close
let ts = 0;
['productDrawer','aiDrawer'].forEach(id => {
  const el = document.getElementById(id);
  el.addEventListener('touchstart', e => ts = e.touches[0].clientY);
  el.addEventListener('touchend', e => { if (e.changedTouches[0].clientY - ts > 70) closeAll(); });
});

// Modal
function openModal() {
  document.getElementById('modalTitle').textContent = 'Нов артикул';
  document.getElementById('editId').value = '';
  document.getElementById('productForm').reset();
  document.getElementById('variantSection').classList.remove('show');
  document.getElementById('modalOverlay').classList.add('open');
  document.getElementById('addModal').classList.add('open');
}

function editProduct(id) {
  closeAll();
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
      document.getElementById('f_sup').value = p.supplier_id || '';
      document.getElementById('f_unit').value = p.unit || 'бр';
      document.getElementById('f_loc').value = p.location || '';
      document.getElementById('f_desc').value = p.description || '';
      document.getElementById('modalOverlay').classList.add('open');
      document.getElementById('addModal').classList.add('open');
    });
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.getElementById('addModal')?.classList.remove('open');
}

// Variant logic
function onCategoryChange(sel) {
  const vt = sel.options[sel.selectedIndex].dataset.variant;
  const sec = document.getElementById('variantSection');
  const hint = document.getElementById('variantHint');
  const ta = document.getElementById('variantsTA');
  const hints = {size_color:'Дрехи — въведи размери и цветове',size:'Обувки — въведи размери',volume:'Козметика — въведи обеми',capacity:'Електроника — въведи капацитет/цвят'};
  const phs = {size_color:'напр: Червена S-3, Червена M-5, Синя M-2',size:'напр: 38-2, 39-3, 40-5, 41-2',volume:'напр: 50мл-5, 100мл-8',capacity:'напр: 128GB Black-2, 256GB White-1'};
  if (vt && vt !== 'none') {
    sec.classList.add('show');
    hint.textContent = hints[vt] || 'Въведи варианти';
    ta.placeholder = phs[vt] || '';
  } else {
    sec.classList.remove('show');
  }
}

// Swipe modal
document.getElementById('addModal')?.addEventListener('touchstart', e => ts = e.touches[0].clientY);
document.getElementById('addModal')?.addEventListener('touchend', e => { if (e.changedTouches[0].clientY - ts > 100) closeModal(); });

// Toast
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
<?php if (isset($_GET['saved'])): ?>showToast('Артикулът е запазен ✓');<?php endif; ?>
<?php if (isset($_GET['error'])): ?>showToast('Грешка при запазване');<?php endif; ?>
</script>
</body>
</html>
