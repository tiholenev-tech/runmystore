<?php
// products.php — Артикули
// Стъпка 0: Единичен или с варианти?
// Стъпка 1: Снимка + Ime + Цена + Баркод + Диктувай
// Стъпка 2: Категория + Варианти от onboarding (grid/кръгчета/chips)
// Стъпка 3: Детайли
// Стъпка 4: Serial barcode scanner (само при варианти)

require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

$tenant_id    = $_SESSION['tenant_id'];
$role         = $_SESSION['role'] ?? 'seller';
$user_store   = $_SESSION['store_id'];
$currency     = $_SESSION['currency'] ?? 'EUR';
$can_add      = in_array($role, ['owner', 'manager']);
$can_see_cost = ($role === 'owner');

$search   = trim($_GET['q'] ?? '');
$filter   = $_GET['filter'] ?? 'all';
$f_cat    = (int)($_GET['cat'] ?? 0);
$f_sup    = (int)($_GET['sup'] ?? 0);
$f_size   = trim($_GET['size'] ?? '');
$f_color  = trim($_GET['color'] ?? '');
$f_min    = $_GET['pmin'] ?? '';
$f_max    = $_GET['pmax'] ?? '';
$f_store  = ($role === 'seller') ? $user_store : (int)($_GET['store'] ?? 0);

$where  = ["p.tenant_id=?", "p.parent_id IS NULL", "p.is_active=1"];
$params = [$tenant_id];

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.code LIKE ? OR p.alt_codes LIKE ? OR p.description LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($f_cat)   { $where[] = "p.category_id=?"; $params[] = $f_cat; }
if ($f_sup)   { $where[] = "p.supplier_id=?"; $params[] = $f_sup; }
if ($f_size)  { $where[] = "(p.size LIKE ? OR EXISTS(SELECT 1 FROM products ch WHERE ch.parent_id=p.id AND ch.size LIKE ?))"; array_push($params, "%$f_size%", "%$f_size%"); }
if ($f_color) { $where[] = "(p.color LIKE ? OR EXISTS(SELECT 1 FROM products ch WHERE ch.parent_id=p.id AND ch.color LIKE ?))"; array_push($params, "%$f_color%", "%$f_color%"); }
if ($f_min !== '') { $where[] = "p.retail_price >= ?"; $params[] = (float)$f_min; }
if ($f_max !== '') { $where[] = "p.retail_price <= ?"; $params[] = (float)$f_max; }

$inv_join = $f_store
    ? "LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id AND i.store_id=$f_store"
    : "LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id";

$sql = "
    SELECT p.*, c.name AS category_name, c.variant_type, s.name AS supplier_name,
           COALESCE(SUM(i.quantity),0) AS total_stock,
           COALESCE(MAX(i.min_quantity),0) AS min_qty,
           (SELECT COUNT(*) FROM products ch WHERE ch.parent_id=p.id AND ch.tenant_id=p.tenant_id AND ch.is_active=1) AS variant_count
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    LEFT JOIN suppliers s ON s.id=p.supplier_id
    $inv_join
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id ORDER BY p.name ASC LIMIT 200
";
$all_products = DB::run($sql, $params)->fetchAll();

$cnt_low = 0; $cnt_out = 0;
foreach ($all_products as $p) {
    if ($p['total_stock'] == 0) $cnt_out++;
    elseif ($p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']) $cnt_low++;
}

if ($filter === 'low') $products = array_values(array_filter($all_products, fn($p) => $p['total_stock'] > 0 && $p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']));
elseif ($filter === 'out') $products = array_values(array_filter($all_products, fn($p) => $p['total_stock'] == 0));
else $products = $all_products;

$categories  = DB::run("SELECT id, name, variant_type FROM categories WHERE tenant_id=? ORDER BY name", [$tenant_id])->fetchAll();
$suppliers   = DB::run("SELECT id, name FROM suppliers WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll();
$stores      = DB::run("SELECT id, name FROM stores WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll();
$sizes_used  = DB::run("SELECT DISTINCT size FROM products WHERE tenant_id=? AND size IS NOT NULL AND size!='' ORDER BY size", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
$colors_used = DB::run("SELECT DISTINCT color FROM products WHERE tenant_id=? AND color IS NOT NULL AND color!='' ORDER BY color", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);

$tenant_cfg      = DB::run("SELECT units_config FROM tenants WHERE id=?", [$tenant_id])->fetch();
$onboarding_units = json_decode($tenant_cfg['units_config'] ?? '[]', true) ?: ['бр','чифт','к-кт'];

$has_filters = $f_cat || $f_sup || $f_size || $f_color || $f_min !== '' || $f_max !== '' || ($role !== 'seller' && $f_store);

$COLOR_PALETTE = [
    ['name'=>'Черен','hex'=>'#1a1a1a'],['name'=>'Бял','hex'=>'#f5f5f5'],
    ['name'=>'Сив','hex'=>'#6b7280'],['name'=>'Тъмносив','hex'=>'#374151'],
    ['name'=>'Червен','hex'=>'#ef4444'],['name'=>'Бордо','hex'=>'#7f1d1d'],
    ['name'=>'Розов','hex'=>'#ec4899'],['name'=>'Корал','hex'=>'#f87171'],
    ['name'=>'Оранжев','hex'=>'#f97316'],['name'=>'Жълт','hex'=>'#eab308'],
    ['name'=>'Горчица','hex'=>'#ca8a04'],['name'=>'Зелен','hex'=>'#22c55e'],
    ['name'=>'Маслина','hex'=>'#65a30d'],['name'=>'Каки','hex'=>'#6b7c3d'],
    ['name'=>'Тюркоаз','hex'=>'#14b8a6'],['name'=>'Син','hex'=>'#3b82f6'],
    ['name'=>'Тъмносин','hex'=>'#1e3a8a'],['name'=>'Navy','hex'=>'#1e40af'],
    ['name'=>'Лилав','hex'=>'#8b5cf6'],['name'=>'Кафяв','hex'=>'#92400e'],
    ['name'=>'Бежов','hex'=>'#d4b896'],['name'=>'Крем','hex'=>'#fef3c7'],
    ['name'=>'Графит','hex'=>'#374151'],['name'=>'Деним','hex'=>'#1d4ed8'],
    ['name'=>'Камуфлаж','hex'=>'#4a5d23'],['name'=>'Пъстър','hex'=>'#a855f7'],
    ['name'=>'Златист','hex'=>'#d97706'],['name'=>'Сребрист','hex'=>'#9ca3af'],
];
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
:root{--nav-h:64px}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#030712;color:#e2e8f0;font-family:'Montserrat',sans-serif;margin:0;overflow-x:hidden;padding-bottom:80px}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);width:700px;height:500px;background:radial-gradient(ellipse,rgba(99,102,241,.08) 0%,transparent 70%);pointer-events:none;z-index:0}
.hdr{position:sticky;top:0;z-index:50;background:rgba(3,7,18,.93);backdrop-filter:blur(20px);border-bottom:1px solid rgba(99,102,241,.12);padding:14px 16px 0}
.hdr-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.page-title{font-size:20px;font-weight:800;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.btn-add{display:flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:12px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 0 20px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.16)}
.btn-add:active{transform:scale(.96)}
.search-row{display:flex;gap:8px;margin-bottom:12px;align-items:center}
.search-wrap{position:relative;flex:1}
.search-input{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#e2e8f0;font-size:14px;padding:11px 80px 11px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s}
.search-input::placeholder{color:#4b5563}
.search-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-icons{position:absolute;right:10px;top:50%;transform:translateY(-50%);display:flex;gap:6px;align-items:center}
.icon-btn{width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc}
.icon-btn:active{background:rgba(99,102,241,.3)}
.filter-btn{width:40px;height:40px;border-radius:12px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc;flex-shrink:0;position:relative}
.filter-btn.active{background:rgba(99,102,241,.25);border-color:rgba(99,102,241,.5)}
.filter-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#6366f1;box-shadow:0 0 6px rgba(99,102,241,.8)}
.ftabs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding-bottom:12px}
.ftabs::-webkit-scrollbar{display:none}
.ftab{flex-shrink:0;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;white-space:nowrap}
.ftab.active{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent;color:#fff;box-shadow:0 0 14px rgba(99,102,241,.35)}
.cnt{display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;border-radius:8px;font-size:10px;font-weight:800;margin-left:4px;padding:0 3px;background:rgba(255,255,255,.15)}
.ftab:not(.active) .cnt{background:rgba(99,102,241,.15);color:#6366f1}
.ftab:not(.active) .cnt.w{background:rgba(245,158,11,.15);color:#f59e0b}
.ftab:not(.active) .cnt.d{background:rgba(239,68,68,.15);color:#ef4444}
.plist{padding:12px 12px 20px;position:relative;z-index:1}
.pcard{position:relative;border-radius:18px;margin-bottom:10px;overflow:hidden;background:rgba(15,15,35,.85);cursor:pointer;transition:transform .2s;animation:cIn .35s ease both}
.pcard::before{content:'';position:absolute;left:var(--mx,-9999px);top:var(--my,-9999px);width:260px;height:260px;border-radius:50%;background:rgba(99,102,241,.18);transform:translate(-50%,-50%);filter:blur(45px);opacity:0;transition:opacity .3s;pointer-events:none;z-index:0}
.pcard:hover::before{opacity:1}
.pcard::after{content:'';position:absolute;inset:0;border-radius:inherit;border:1px solid transparent;background:linear-gradient(135deg,rgba(99,102,241,.2),rgba(99,102,241,.04),rgba(99,102,241,.12)) border-box;-webkit-mask:linear-gradient(#fff 0 0) padding-box,linear-gradient(#fff 0 0);-webkit-mask-composite:destination-out;mask-composite:exclude;pointer-events:none}
.pcard:active{transform:scale(.98)}
.stripe{position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:18px 0 0 18px;z-index:2}
.sg{background:linear-gradient(to bottom,#22c55e,#16a34a);box-shadow:2px 0 14px rgba(34,197,94,.5)}
.sy{background:linear-gradient(to bottom,#f59e0b,#d97706);box-shadow:2px 0 14px rgba(245,158,11,.5)}
.sr{background:linear-gradient(to bottom,#ef4444,#dc2626);box-shadow:2px 0 14px rgba(239,68,68,.5)}
.ci{position:relative;z-index:1;padding:14px 14px 14px 18px}
.ctop{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px}
.cname{font-size:14px;font-weight:700;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.csub{font-size:11px;color:#6b7280}
.qnum{font-size:24px;font-weight:900;line-height:1;text-align:right}
.qunit{font-size:10px;color:#6b7280;font-weight:600;text-align:right}
.ctags{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.tag{font-size:11px;font-weight:700;border-radius:8px;padding:3px 8px;border:1px solid transparent}
.tp{color:#a5b4fc;background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.2)}
.tc{color:#fbbf24;background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.15)}
.tok{color:#22c55e;background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.2)}
.tlo{color:#f59e0b;background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.2);animation:bp 2.5s infinite}
.tout{color:#ef4444;background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.2);animation:bp 2s infinite}
.tv{color:#8b5cf6;background:rgba(139,92,246,.1);border-color:rgba(139,92,246,.2)}
.ts{color:#6b7280;background:rgba(107,114,128,.08);border-color:rgba(107,114,128,.12)}
.ai-btn{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#a5b4fc;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:3px 8px;cursor:pointer;font-family:'Montserrat',sans-serif}
.plist.blurred .pcard:not(.focused){filter:blur(1.5px);opacity:.4;pointer-events:none}
.pcard.focused{z-index:10;box-shadow:0 8px 40px rgba(99,102,241,.25)}
.empty{text-align:center;padding:60px 20px;color:#4b5563}
.empty svg{width:48px;height:48px;margin-bottom:12px;color:#1f2937}
.empty h3{font-size:16px;font-weight:700;color:#374151;margin:0 0 6px}
.empty p{font-size:13px;margin:0}
.ovl{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:200;opacity:0;pointer-events:none;transition:opacity .3s;backdrop-filter:blur(8px)}
.ovl.open{opacity:1;pointer-events:all}
.drw{position:fixed;bottom:0;left:0;right:0;z-index:201;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:88vh;overflow-y:auto;box-shadow:0 -30px 80px rgba(99,102,241,.2)}
.drw.open{transform:translateY(0)}
.drw-h{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 20px}
.drw-b{padding:0 20px 40px}
.d-name{font-size:21px;font-weight:800;color:#f1f5f9;margin-bottom:3px}
.d-meta{font-size:12px;color:#6b7280;margin-bottom:18px}
.sg2{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.si{background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;padding:10px 12px}
.sil{font-size:10px;color:#6b7280;font-weight:600;margin-bottom:2px}
.siv{font-size:17px;font-weight:800;color:#f1f5f9}
.store-stock-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(99,102,241,.07)}
.store-stock-row:last-child{border-bottom:none}
.dab-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.dab{padding:13px;border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .2s}
.dab-p{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.3)}
.dab-s{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);color:#a5b4fc}
.ai-rec{background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:14px;margin-bottom:16px}
.ai-rl{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.ai-rt{font-size:13px;color:#e2e8f0;line-height:1.65}
.fl-section{margin-bottom:18px}
.fl-title{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.fl-chips{display:flex;gap:6px;flex-wrap:wrap}
.fl-chip{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s}
.fl-chip.sel{background:rgba(99,102,241,.2);border-color:#6366f1;color:#a5b4fc}
.fl-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.fl-input{background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#e2e8f0;font-size:13px;padding:8px 12px;font-family:'Montserrat',sans-serif;outline:none;width:100%}
.fl-apply{width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:14px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:4px}
.fl-clear{width:100%;padding:11px;background:transparent;border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}
.modal-ovl{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:300;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(10px)}
.modal-ovl.open{opacity:1;pointer-events:all}
.modal{position:fixed;bottom:0;left:0;right:0;z-index:301;background:linear-gradient(to bottom,#0c0c22,#070714);border-top:1px solid rgba(99,102,241,.25);border-radius:24px 24px 0 0;transform:translateY(100%);transition:transform .38s cubic-bezier(.32,0,.67,0);max-height:94vh;overflow-y:auto}
.modal.open{transform:translateY(0)}
.m-handle{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 0}
.m-head{padding:12px 20px;border-bottom:1px solid rgba(99,102,241,.1);display:flex;justify-content:space-between;align-items:center}
.m-title{font-size:16px;font-weight:800;color:#f1f5f9}
.m-close{width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.15);color:#6b7280;display:flex;align-items:center;justify-content:center;cursor:pointer}
.m-body{padding:18px 20px 24px}
.steps-bar{display:flex;gap:4px;margin-bottom:12px}
.sbar-item{flex:1;height:3px;border-radius:2px;background:rgba(99,102,241,.15);transition:background .3s}
.sbar-item.done{background:#6366f1}
.sbar-item.active{background:linear-gradient(to right,#6366f1,#8b5cf6);box-shadow:0 0 8px rgba(99,102,241,.4)}
.step-label-row{font-size:11px;color:#6b7280;margin-bottom:16px}
.step-label-row span{font-weight:700;color:#a5b4fc}
.step-content{display:none}
.step-content.active{display:block;animation:fadeIn .25s ease}
.fg{margin-bottom:14px}
.fl{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.fi{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:11px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s;-webkit-appearance:none}
.fi:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.fi::placeholder{color:#4b5563}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.type-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.type-card{background:rgba(15,15,40,.7);border:1px solid rgba(99,102,241,.15);border-radius:18px;padding:22px 16px;text-align:center;cursor:pointer;transition:all .2s}
.type-card:active{transform:scale(.97)}
.type-card.sel{border-color:#6366f1;background:rgba(99,102,241,.14);box-shadow:0 0 24px rgba(99,102,241,.25)}
.type-card-icon{font-size:38px;margin-bottom:10px}
.type-card-name{font-size:14px;font-weight:800;color:#f1f5f9;margin-bottom:4px}
.type-card-sub{font-size:11px;color:#6b7280;line-height:1.4}
.photo-upload{position:relative;width:100%;height:110px;background:rgba(15,15,40,.8);border:2px dashed rgba(99,102,241,.3);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;margin-bottom:14px;overflow:hidden}
.photo-upload:active{border-color:#6366f1}
.photo-upload span{font-size:12px;color:#6b7280}
.photo-preview{position:absolute;inset:0;background-size:cover;background-position:center}
.voice-btn{display:flex;align-items:center;gap:6px;padding:9px 14px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#a5b4fc;font-size:12px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;width:100%;justify-content:center;margin-bottom:14px}
.voice-btn.recording{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3);color:#ef4444;animation:pulse-rec 1s infinite}
.ai-gen-btn{display:flex;align-items:center;gap:6px;padding:9px 14px;background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#a5b4fc;font-size:12px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;width:100%;justify-content:center;margin-bottom:14px}
.vsec{margin-bottom:20px}
.vsec-title{font-size:12px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.vsec-title::after{content:'';flex:1;height:1px;background:linear-gradient(to right,rgba(99,102,241,.3),transparent)}
.size-grid{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:8px}
.size-chip{position:relative;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:rgba(15,15,40,.5);cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;user-select:none}
.size-chip:active{transform:scale(.95)}
.size-chip.sel{background:rgba(99,102,241,.2);border-color:#6366f1;color:#a5b4fc;box-shadow:0 0 10px rgba(99,102,241,.2)}
.size-price-badge{position:absolute;top:-6px;right:-4px;background:#6366f1;color:#fff;font-size:9px;font-weight:800;border-radius:6px;padding:1px 4px;display:none}
.size-chip.sel.has-price .size-price-badge{display:block}
.size-price-popup{display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:10;background:#0a0a1e;border:1px solid rgba(99,102,241,.3);border-radius:10px;padding:8px 10px;width:130px;box-shadow:0 8px 24px rgba(0,0,0,.5)}
.size-chip.price-open .size-price-popup{display:block}
.size-price-popup input{width:100%;background:rgba(15,15,40,.9);border:1px solid rgba(99,102,241,.3);border-radius:8px;color:#e2e8f0;font-size:12px;padding:5px 8px;font-family:'Montserrat',sans-serif;outline:none}
.size-price-popup label{font-size:10px;color:#6b7280;display:block;margin-bottom:4px}
.add-custom-row{display:flex;gap:6px;margin-top:8px}
.add-custom-input{flex:1;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.18);border-radius:10px;color:#e2e8f0;font-size:13px;padding:8px 12px;font-family:'Montserrat',sans-serif;outline:none}
.add-custom-input::placeholder{color:#4b5563}
.add-custom-btn{padding:8px 14px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#a5b4fc;font-size:12px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;white-space:nowrap}
.color-grid{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px}
.color-dot-wrap{display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer}
.color-dot{width:32px;height:32px;border-radius:50%;border:2px solid transparent;transition:all .2s}
.color-dot-wrap.sel .color-dot{border-color:#fff;box-shadow:0 0 0 2px #6366f1}
.color-dot-name{font-size:9px;color:#6b7280;text-align:center;max-width:36px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.color-dot-wrap.sel .color-dot-name{color:#a5b4fc}
.chips-grid{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.var-chip{padding:7px 14px;border-radius:10px;font-size:12px;font-weight:700;border:1px solid rgba(99,102,241,.18);color:#6b7280;background:rgba(15,15,40,.5);cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s}
.var-chip.sel{background:rgba(99,102,241,.2);border-color:#6366f1;color:#a5b4fc}
.scanner-current{background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(168,85,247,.1));border:1px solid rgba(99,102,241,.3);border-radius:16px;padding:20px;margin-bottom:16px;text-align:center}
.scanner-variant-name{font-size:18px;font-weight:800;color:#f1f5f9;margin-bottom:4px}
.scanner-instruction{font-size:12px;color:#6b7280}
.scanner-progress{display:flex;gap:6px;justify-content:center;margin-bottom:16px;flex-wrap:wrap}
.scan-dot{width:28px;height:28px;border-radius:50%;border:1px solid rgba(99,102,241,.3);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#6b7280;background:rgba(15,15,40,.5);flex-shrink:0}
.scan-dot.done{background:#22c55e;border-color:#22c55e;color:#fff}
.scan-dot.active{background:rgba(99,102,241,.2);border-color:#6366f1;color:#a5b4fc;box-shadow:0 0 10px rgba(99,102,241,.3)}
.scan-result-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(99,102,241,.07);font-size:13px}
.scan-result-row:last-child{border-bottom:none}
.btn-next{width:100%;padding:14px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:14px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.35),inset 0 1px 0 rgba(255,255,255,.16);margin-top:8px}
.btn-next:active{transform:scale(.98)}
.btn-back{width:100%;padding:12px;background:transparent;border:1px solid rgba(99,102,241,.15);border-radius:14px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}
.btn-save{width:100%;padding:14px;background:linear-gradient(to bottom,#22c55e,#16a34a);border:none;border-radius:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(34,197,94,.35);margin-top:4px}
.btn-skip{width:100%;padding:11px;background:transparent;border:1px solid rgba(99,102,241,.15);border-radius:14px;color:#6b7280;font-size:12px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:6px}
.camera-wrap{position:fixed;inset:0;z-index:400;background:#000;display:none;flex-direction:column}
.camera-wrap.open{display:flex}
#cameraVideo{width:100%;flex:1;object-fit:cover}
.camera-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none}
.scan-frame{width:220px;height:140px;border:2px solid rgba(99,102,241,.8);border-radius:16px;box-shadow:0 0 0 9999px rgba(0,0,0,.55)}
.scan-line{position:absolute;width:200px;height:2px;background:linear-gradient(to right,transparent,#6366f1,transparent);animation:scanAnim 2s linear infinite}
.camera-close{position:absolute;top:20px;right:20px;width:40px;height:40px;border-radius:50%;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.2);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;pointer-events:all;z-index:1}
.camera-label{position:absolute;top:60px;left:0;right:0;text-align:center;pointer-events:none}
.camera-label-text{background:rgba(0,0,0,.7);color:#fff;font-size:14px;font-weight:700;padding:8px 20px;border-radius:20px;display:inline-block;font-family:'Montserrat',sans-serif}
.camera-beep{position:absolute;bottom:100px;left:50%;transform:translateX(-50%);background:rgba(34,197,94,.9);color:#fff;padding:10px 24px;border-radius:12px;font-size:14px;font-weight:700;display:none;font-family:'Montserrat',sans-serif;pointer-events:none}
.camera-beep.show{display:block}
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1}
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(99,102,241,.1);display:flex;align-items:center;z-index:100}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:8px 0;text-decoration:none;border:none;background:transparent;cursor:pointer}
.ni svg{width:22px;height:22px;color:#3f3f5a}
.ni span{font-size:10px;font-weight:600;color:#3f3f5a}
.ni.active svg,.ni.active span{color:#6366f1}
@keyframes cIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes bp{0%,100%{opacity:1}50%{opacity:.55}}
@keyframes scanAnim{0%{top:10%}50%{top:80%}100%{top:10%}}
@keyframes pulse-rec{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 0 8px rgba(239,68,68,0)}}
.pcard:nth-child(1){animation-delay:.04s}.pcard:nth-child(2){animation-delay:.08s}.pcard:nth-child(3){animation-delay:.12s}.pcard:nth-child(n+4){animation-delay:.16s}
</style>
</head>
<body>

<div class="hdr">
  <div class="hdr-top">
    <h1 class="page-title">Артикули</h1>
    <?php if ($can_add): ?>
    <button class="btn-add" onclick="openModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Добави
    </button>
    <?php endif; ?>
  </div>
  <div class="search-row">
    <div class="search-wrap">
      <form method="GET" id="searchForm">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <?php if ($f_cat): ?><input type="hidden" name="cat" value="<?= $f_cat ?>"><?php endif; ?>
        <?php if ($f_sup): ?><input type="hidden" name="sup" value="<?= $f_sup ?>"><?php endif; ?>
        <?php if ($f_size): ?><input type="hidden" name="size" value="<?= htmlspecialchars($f_size) ?>"><?php endif; ?>
        <?php if ($f_color): ?><input type="hidden" name="color" value="<?= htmlspecialchars($f_color) ?>"><?php endif; ?>
        <?php if ($f_store): ?><input type="hidden" name="store" value="<?= $f_store ?>"><?php endif; ?>
        <input type="text" name="q" class="search-input" placeholder="Търси по ime, баркод, код..." value="<?= htmlspecialchars($search) ?>" autocomplete="off" id="searchInput">
      </form>
      <div class="search-icons">
        <div class="icon-btn" onclick="openCamera('search')">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="17" y="2" width="5" height="5" rx="1"/><rect x="2" y="17" width="5" height="5" rx="1"/><path d="M17 17h5v5M17 21h.01M21 17h.01M12 2v5M7 12h5M12 12v5M17 12h5M2 12h5"/></svg>
        </div>
        <div class="icon-btn" onclick="startVoiceSearch()" id="voiceSearchBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
        </div>
      </div>
    </div>
    <div class="filter-btn <?= $has_filters?'active':'' ?>" onclick="openFilterDrawer()">
      <?php if ($has_filters): ?><div class="filter-dot"></div><?php endif; ?>
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 12h10M11 20h2"/></svg>
    </div>
  </div>
  <div class="ftabs">
    <button class="ftab <?= $filter==='all'?'active':'' ?>" onclick="setFilter('all')">Артикули <span class="cnt"><?= count($all_products) ?></span></button>
    <button class="ftab <?= $filter==='low'?'active':'' ?>" onclick="setFilter('low')">Ниска нал. <span class="cnt <?= $cnt_low>0?'w':'' ?>"><?= $cnt_low ?></span></button>
    <button class="ftab <?= $filter==='out'?'active':'' ?>" onclick="setFilter('out')">Изчерпани <span class="cnt <?= $cnt_out>0?'d':'' ?>"><?= $cnt_out ?></span></button>
  </div>
</div>

<div class="plist" id="plist">
<?php if (empty($products)): ?>
<div class="empty">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
  <h3><?= $search?'Няма резултати':'Нямаш артикули' ?></h3>
  <p><?= $search?"Нищо не отговаря на \"".htmlspecialchars($search)."\"":($can_add?'Натисни + Добави':'') ?></p>
</div>
<?php else: foreach($products as $p):
  $qty=(float)$p['total_stock']; $min=(float)$p['min_qty'];
  $iz=$qty==0; $lo=!$iz&&$min>0&&$qty<=$min;
  if($iz){$sc='sr';$tc='tout';$tt='Изчерпан';$qc='#ef4444';}
  elseif($lo){$sc='sy';$tc='tlo';$tt='Ниска нал.';$qc='#f59e0b';}
  else{$sc='sg';$tc='tok';$tt='В наличност';$qc='#22c55e';}
  $pd=json_encode(['id'=>$p['id'],'name'=>$p['name'],'code'=>$p['code']??'','barcode'=>$p['barcode']??'','qty'=>$qty,'min'=>$min,'price'=>(float)($p['retail_price']??0),'cost'=>(float)($p['cost_price']??0),'wprice'=>(float)($p['wholesale_price']??0),'unit'=>$p['unit']??'бр','cat'=>$p['category_name']??'','sup'=>$p['supplier_name']??'','variants'=>(int)$p['variant_count'],'loc'=>$p['location']??'','size'=>$p['size']??'','color'=>$p['color']??'','tag'=>$tt,'sc'=>$sc],JSON_UNESCAPED_UNICODE);
?>
<div class="pcard" onclick="openDrawer(<?= htmlspecialchars($pd,ENT_QUOTES) ?>)" data-id="<?= $p['id'] ?>">
  <div class="stripe <?= $sc ?>"></div>
  <div class="ci">
    <div class="ctop">
      <div style="flex:1;min-width:0">
        <div class="cname"><?= htmlspecialchars($p['name']) ?></div>
        <div class="csub"><?= $p['code']?'Код: '.htmlspecialchars($p['code']):'' ?><?= $p['code']&&$p['category_name']?' · ':'' ?><?= htmlspecialchars($p['category_name']??'') ?><?= ($p['size']||$p['color'])?' · ':'' ?><?= htmlspecialchars(implode(' ',array_filter([$p['size']??'',$p['color']??'']))) ?></div>
      </div>
      <div><div class="qnum" style="color:<?= $qc ?>"><?= number_format($qty,0) ?></div><div class="qunit"><?= htmlspecialchars($p['unit']??'бр') ?></div></div>
    </div>
    <div class="ctags">
      <span class="tag tp">€<?= number_format((float)($p['retail_price']??0),2,',','.') ?></span>
      <?php if($can_see_cost&&$p['cost_price']>0): ?><span class="tag tc">Дост: €<?= number_format((float)$p['cost_price'],2,',','.') ?></span><?php endif; ?>
      <span class="tag <?= $tc ?>"><?= $tt ?></span>
      <?php if($p['variant_count']>0): ?><span class="tag tv"><?= $p['variant_count'] ?> варианта</span><?php endif; ?>
      <?php if($p['supplier_name']): ?><span class="tag ts"><?= htmlspecialchars($p['supplier_name']) ?></span><?php endif; ?>
      <button class="ai-btn" onclick="event.stopPropagation();openAI(<?= htmlspecialchars($pd,ENT_QUOTES) ?>)">
        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>AI
      </button>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
</div>

<div class="ovl" id="ovl" onclick="closeAll()"></div>

<!-- PRODUCT DRAWER -->
<div class="drw" id="pDrawer">
  <div class="drw-h"></div>
  <div class="drw-b" id="pDrawerBody"></div>
</div>

<!-- AI DRAWER -->
<div class="drw" id="aiDrawer" style="z-index:202">
  <div class="drw-h"></div>
  <div class="drw-b" id="aiBody"></div>
</div>

<!-- FILTER DRAWER -->
<div class="drw" id="filterDrawer" style="z-index:202">
  <div class="drw-h"></div>
  <div class="drw-b">
    <div style="font-size:16px;font-weight:800;color:#f1f5f9;margin-bottom:18px">Филтри</div>
    <form method="GET" id="filterForm">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
      <?php if($categories): ?><div class="fl-section"><div class="fl-title">Категория</div><div class="fl-chips"><?php foreach($categories as $c): ?><label class="fl-chip <?= $f_cat==$c['id']?'sel':'' ?>"><input type="radio" name="cat" value="<?= $c['id'] ?>" style="display:none" <?= $f_cat==$c['id']?'checked':'' ?> onchange="this.closest('form').submit()"><?= htmlspecialchars($c['name']) ?></label><?php endforeach; ?></div></div><?php endif; ?>
      <?php if($suppliers): ?><div class="fl-section"><div class="fl-title">Доставчик</div><div class="fl-chips"><?php foreach($suppliers as $s): ?><label class="fl-chip <?= $f_sup==$s['id']?'sel':'' ?>"><input type="radio" name="sup" value="<?= $s['id'] ?>" style="display:none" <?= $f_sup==$s['id']?'checked':'' ?> onchange="this.closest('form').submit()"><?= htmlspecialchars($s['name']) ?></label><?php endforeach; ?></div></div><?php endif; ?>
      <?php if($sizes_used): ?><div class="fl-section"><div class="fl-title">Размер</div><div class="fl-chips"><?php foreach($sizes_used as $sz): ?><label class="fl-chip <?= $f_size===$sz?'sel':'' ?>"><input type="radio" name="size" value="<?= htmlspecialchars($sz) ?>" style="display:none" <?= $f_size===$sz?'checked':'' ?> onchange="this.closest('form').submit()"><?= htmlspecialchars($sz) ?></label><?php endforeach; ?></div></div><?php endif; ?>
      <?php if($colors_used): ?><div class="fl-section"><div class="fl-title">Цвят</div><div class="fl-chips"><?php foreach($colors_used as $cl): ?><label class="fl-chip <?= $f_color===$cl?'sel':'' ?>"><input type="radio" name="color" value="<?= htmlspecialchars($cl) ?>" style="display:none" <?= $f_color===$cl?'checked':'' ?> onchange="this.closest('form').submit()"><?= htmlspecialchars($cl) ?></label><?php endforeach; ?></div></div><?php endif; ?>
      <div class="fl-section"><div class="fl-title">Цена (€)</div><div class="fl-row"><input type="number" name="pmin" class="fl-input" placeholder="От" value="<?= htmlspecialchars($f_min) ?>" step="0.01"><input type="number" name="pmax" class="fl-input" placeholder="До" value="<?= htmlspecialchars($f_max) ?>" step="0.01"></div></div>
      <?php if($role!=='seller'&&count($stores)>1): ?><div class="fl-section"><div class="fl-title">Обект</div><div class="fl-chips"><?php foreach($stores as $s): ?><label class="fl-chip <?= $f_store==$s['id']?'sel':'' ?>"><input type="radio" name="store" value="<?= $s['id'] ?>" style="display:none" <?= $f_store==$s['id']?'checked':'' ?> onchange="this.closest('form').submit()"><?= htmlspecialchars($s['name']) ?></label><?php endforeach; ?></div></div><?php endif; ?>
      <button type="submit" class="fl-apply">Приложи филтри</button>
      <a href="products.php" class="fl-clear" style="display:block;text-align:center;text-decoration:none;padding:11px">Изчисти всички</a>
    </form>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-ovl" id="modalOvl" onclick="if(event.target===this)closeModal()"></div>
<?php if($can_add): ?>
<div class="modal" id="addModal">
  <div class="m-handle"></div>
  <div class="m-head">
    <span class="m-title" id="modalTitle">Нов артикул</span>
    <button class="m-close" onclick="closeModal()"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
  </div>
  <div class="m-body">
    <div class="steps-bar" id="stepsBar">
      <div class="sbar-item active" id="sb0"></div>
      <div class="sbar-item" id="sb1"></div>
      <div class="sbar-item" id="sb2"></div>
      <div class="sbar-item" id="sb3"></div>
      <div class="sbar-item" id="sb4"></div>
    </div>
    <div class="step-label-row"><span id="stepLabelText">Стъпка 1: Вид на артикула</span></div>

    <!-- СТЪПКА 0 -->
    <div class="step-content active" id="stepC0">
      <div style="font-size:18px;font-weight:800;color:#f1f5f9;margin-bottom:6px">Какъв е артикулът?</div>
      <div style="font-size:13px;color:#6b7280;margin-bottom:20px">Изборът определя следващите стъпки</div>
      <div class="type-cards">
        <div class="type-card" id="typeCardSingle" onclick="selectType('single')">
          <div class="type-card-icon">📦</div>
          <div class="type-card-name">Единичен</div>
          <div class="type-card-sub">Един баркод, един размер, един цвят</div>
        </div>
        <div class="type-card" id="typeCardVariant" onclick="selectType('variant')">
          <div class="type-card-icon">🎨</div>
          <div class="type-card-name">С варианти</div>
          <div class="type-card-sub">Размери, цветове, разфасовки и др.</div>
        </div>
      </div>
      <button type="button" class="btn-next" id="btn0Next" onclick="goStep(1)" style="display:none">Напред →</button>
    </div>

    <!-- СТЪПКА 1 -->
    <div class="step-content" id="stepC1">
      <button type="button" class="voice-btn" id="voiceBtn" onclick="toggleVoice()">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
        Диктувай на AI
      </button>
      <div class="photo-upload" id="photoUpload" onclick="document.getElementById('photoInput').click()">
        <div class="photo-preview" id="photoPreview"></div>
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        <span>Снимка на артикула</span>
      </div>
      <input type="file" id="photoInput" accept="image/*" style="display:none" onchange="previewPhoto(this)">
      <div class="fg"><label class="fl">Наименование *</label><input type="text" id="f_name" class="fi" placeholder="напр. Nike Air Max" required></div>
      <div class="fg"><label class="fl">Продажна цена *</label><input type="number" id="f_price" class="fi" placeholder="0.00" step="0.01" min="0" required></div>
      <div class="fg">
        <label class="fl">Баркод <span style="color:#6b7280;font-weight:400;text-transform:none;letter-spacing:0">(празно = автогенериране)</span></label>
        <div style="display:flex;gap:8px">
          <input type="text" id="f_barcode" class="fi" placeholder="Сканирай или въведи">
          <div class="icon-btn" onclick="openCamera('barcode')" style="flex-shrink:0;width:44px;height:44px;border-radius:12px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="17" y="2" width="5" height="5" rx="1"/><rect x="2" y="17" width="5" height="5" rx="1"/><path d="M17 17h5v5"/></svg>
          </div>
        </div>
      </div>
      <button type="button" class="btn-next" onclick="goStep(2)">Напред →</button>
      <button type="button" class="btn-back" onclick="goStep(0)">← Назад</button>
    </div>

    <!-- СТЪПКА 2 -->
    <div class="step-content" id="stepC2">
      <button type="button" class="ai-gen-btn" onclick="generateAIDescription()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
        AI генерира описание
      </button>
      <div class="fg">
        <label class="fl">Категория</label>
        <select id="f_cat" class="fi" style="-webkit-appearance:none">
          <option value="">— Без категория —</option>
          <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" data-variant="<?= $c['variant_type'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div id="variantsContainer"></div>
      <div class="fg">
        <label class="fl">Описание</label>
        <textarea id="f_desc" class="fi" rows="2" placeholder="AI ще генерира..."></textarea>
      </div>
      <button type="button" class="btn-next" onclick="goStep(3)">Напред →</button>
      <button type="button" class="btn-back" onclick="goStep(1)">← Назад</button>
    </div>

    <!-- СТЪПКА 3 -->
    <div class="step-content" id="stepC3">
      <div class="frow">
        <div class="fg"><label class="fl">Покупна цена</label><input type="number" id="f_cost" class="fi" placeholder="0.00" step="0.01" min="0"></div>
        <div class="fg"><label class="fl">Цена едро</label><input type="number" id="f_wprice" class="fi" placeholder="0.00" step="0.01" min="0"></div>
      </div>
      <div class="frow">
        <div class="fg">
          <label class="fl">Доставчик</label>
          <select id="f_sup" class="fi" style="-webkit-appearance:none">
            <option value="">— Без —</option>
            <?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="fl">Мерна единица</label>
          <select id="f_unit" class="fi" style="-webkit-appearance:none">
            <?php foreach($onboarding_units as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
            <?php $extra=['бр','чифт','к-кт','кг','гр','л','мл','м','кутия','пакет']; foreach($extra as $u): if(!in_array($u,$onboarding_units)): ?><option value="<?= $u ?>"><?= $u ?></option><?php endif; endforeach; ?>
          </select>
        </div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Артикулен код</label><input type="text" id="f_code" class="fi" placeholder="автоген."></div>
        <div class="fg"><label class="fl">Локация / Рафт</label><input type="text" id="f_loc" class="fi" placeholder="Рафт А-3"></div>
      </div>
      <button type="button" class="btn-next" onclick="onStep3Next()">Запази и продължи →</button>
      <button type="button" class="btn-back" onclick="goStep(2)">← Назад</button>
    </div>

    <!-- СТЪПКА 4 — Serial Scanner -->
    <div class="step-content" id="stepC4">
      <div style="font-size:16px;font-weight:800;color:#f1f5f9;margin-bottom:4px">Сканирай баркодовете</div>
      <div style="font-size:12px;color:#6b7280;margin-bottom:16px">За всяка вариация поотделно — или пропусни за автобаркод</div>
      <div class="scanner-current" id="scannerCurrent">
        <div class="scanner-variant-name" id="scannerVariantName">Зарежда...</div>
        <div class="scanner-instruction">Сканирай баркода или въведи ръчно</div>
      </div>
      <div style="display:flex;gap:8px;margin-bottom:16px">
        <input type="text" class="fi" id="scannerInput" placeholder="Баркод" style="flex:1">
        <div class="icon-btn" onclick="openCamera('scanner')" style="width:44px;height:44px;border-radius:12px;flex-shrink:0">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="17" y="2" width="5" height="5" rx="1"/><rect x="2" y="17" width="5" height="5" rx="1"/><path d="M17 17h5v5"/></svg>
        </div>
      </div>
      <div class="scanner-progress" id="scannerProgress"></div>
      <div style="background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;padding:10px 14px;margin-bottom:12px;max-height:180px;overflow-y:auto" id="scannerResults"></div>
      <button type="button" class="btn-next" id="btnConfirmScan" onclick="confirmCurrentScan()">Потвърди →</button>
      <button type="button" class="btn-skip" onclick="skipCurrentScan()">Пропусни (автобаркод)</button>
      <button type="button" class="btn-save" id="btnFinalSave" onclick="finalSave()" style="display:none">✓ Запази всичко</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- CAMERA -->
<div class="camera-wrap" id="cameraWrap">
  <video id="cameraVideo" autoplay playsinline muted></video>
  <div class="camera-overlay">
    <div class="scan-frame"><div class="scan-line"></div></div>
    <div class="camera-label"><span class="camera-label-text" id="cameraLabelText">Насочи към баркода</span></div>
  </div>
  <div class="camera-close" onclick="closeCamera()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></div>
  <div class="camera-beep" id="cameraBeep">✓ Сканирано!</div>
</div>

<nav class="bnav">
  <a href="chat.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg><span>Чат</span></a>
  <a href="warehouse.php" class="ni active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg><span>Склад</span></a>
  <a href="stats.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистики</span></a>
  <a href="sale.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>
<div class="toast" id="toast"></div>

<script>
const canSeeCost = <?= $can_see_cost?'true':'false' ?>;
const canAdd     = <?= $can_add?'true':'false' ?>;
const COLOR_PALETTE = <?= json_encode($COLOR_PALETTE, JSON_UNESCAPED_UNICODE) ?>;
const stepLabels = ['Вид на артикула','Основна информация','Категория и варианти','Детайли','Сканиране на баркодове'];

let productType   = null;
let currentStep   = 0;
let onbVariants   = [];
let selectedSizes = {};
let selectedColors= [];
let customVarSel  = {};
let variantCombs  = [];
let scannerIdx    = 0;
let cameraStream  = null;
let cameraTarget  = 'search';
let voiceRec      = null;
let ts            = 0;

function goStep(n) {
  for (let i=0;i<=4;i++) {
    document.getElementById('stepC'+i)?.classList.toggle('active',i===n);
    const b=document.getElementById('sb'+i);
    if(b){b.classList.remove('active','done');if(i<n)b.classList.add('done');else if(i===n)b.classList.add('active');}
  }
  document.getElementById('stepLabelText').textContent='Стъпка '+(n+1)+': '+stepLabels[n];
  currentStep=n;
  document.getElementById('addModal').scrollTop=0;
  if(n===2) renderVariantsStep();
}

function selectType(t) {
  productType=t;
  document.getElementById('typeCardSingle').classList.toggle('sel',t==='single');
  document.getElementById('typeCardVariant').classList.toggle('sel',t==='variant');
  document.getElementById('btn0Next').style.display='block';
}

async function renderVariantsStep() {
  const cont=document.getElementById('variantsContainer');
  if(productType==='single'){cont.innerHTML='';return;}
  if(!onbVariants.length){
    try{const r=await fetch('product-save.php?variants');onbVariants=await r.json();}catch(e){onbVariants=[];}
  }
  if(!onbVariants.length){cont.innerHTML='<div style="font-size:13px;color:#6b7280;padding:10px 0">Нямаш активни варианти. Настрой ги в <a href="settings.php" style="color:#6366f1">Настройки</a>.</div>';return;}
  let html='';
  for(const v of onbVariants){
    if(v.type==='size_letter') html+=renderSizeSection(v,['XXS','XS','S','M','L','XL','XXL','3XL','4XL','5XL']);
    else if(v.type==='size_numeric') html+=renderSizeSection(v,['16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50']);
    else if(v.type==='color') html+=renderColorSection(v);
    else html+=renderChipsSection(v);
  }
  cont.innerHTML=html;
}

function renderSizeSection(v,sizes){
  const all=[...new Set([...sizes,...(v.values||[])])];
  const key=v.name.replace(/\s/g,'_');
  let chips=all.map(s=>`<div class="size-chip" data-size="${s}" data-varname="${v.name}" onclick="toggleSizeChip(this)">${s}<span class="size-price-badge" id="spb_${key}_${s}"></span><div class="size-price-popup" onclick="event.stopPropagation()"><label>Цена за ${s} (€)</label><input type="number" placeholder="0.00" step="0.01" min="0" onchange="setSizePrice('${key}','${s}',this.value)"></div></div>`).join('');
  return`<div class="vsec"><div class="vsec-title">${v.name}</div><div class="size-grid" id="sg_${key}">${chips}</div><div class="add-custom-row"><input type="text" class="add-custom-input" placeholder="Добави размер..." id="csi_${key}"><button type="button" class="add-custom-btn" onclick="addCustomSize('${v.name}','${key}')">+ Добави</button></div><div style="font-size:11px;color:#6b7280;margin-top:5px">Натисни за избор · задръж за различна цена</div></div>`;
}

function toggleSizeChip(el){
  const size=el.dataset.size;
  if(el.classList.contains('sel')){el.classList.remove('sel','price-open');delete selectedSizes[size];}
  else{el.classList.add('sel');selectedSizes[size]=null;}
}

let lpTimer=null;
document.addEventListener('touchstart',e=>{const c=e.target.closest('.size-chip.sel');if(!c)return;lpTimer=setTimeout(()=>c.classList.toggle('price-open'),500);},{passive:true});
document.addEventListener('touchend',()=>clearTimeout(lpTimer),{passive:true});

function setSizePrice(key,size,val){
  selectedSizes[size]=val?parseFloat(val):null;
  const b=document.getElementById('spb_'+key+'_'+size);
  if(b){b.textContent=val?'€'+parseFloat(val).toFixed(0):'';b.closest('.size-chip')?.classList.toggle('has-price',!!val);}
}

function addCustomSize(varName,key){
  const inp=document.getElementById('csi_'+key);const val=inp?.value.trim();if(!val)return;
  const grid=document.getElementById('sg_'+key);
  const chip=document.createElement('div');chip.className='size-chip';chip.dataset.size=val;chip.dataset.varname=varName;
  chip.innerHTML=val+`<span class="size-price-badge" id="spb_${key}_${val}"></span><div class="size-price-popup" onclick="event.stopPropagation()"><label>Цена (€)</label><input type="number" step="0.01" onchange="setSizePrice('${key}','${val}',this.value)"></div>`;
  chip.onclick=function(){toggleSizeChip(this);};grid?.appendChild(chip);if(inp)inp.value='';
}

function renderColorSection(v){
  let dots=COLOR_PALETTE.map(c=>`<div class="color-dot-wrap" data-color="${c.name}" onclick="toggleColor(this,'${c.name}')"><div class="color-dot" style="background:${c.hex}"></div><div class="color-dot-name">${c.name}</div></div>`).join('');
  return`<div class="vsec"><div class="vsec-title">Цвят</div><div class="color-grid" id="colorGrid">${dots}</div><div class="add-custom-row"><input type="text" class="add-custom-input" placeholder="Добави цвят..." id="customColorIn"><button type="button" class="add-custom-btn" onclick="addCustomColor()">+ Добави</button></div></div>`;
}

function toggleColor(el,name){
  el.classList.toggle('sel');
  if(el.classList.contains('sel')){if(!selectedColors.includes(name))selectedColors.push(name);}
  else selectedColors=selectedColors.filter(c=>c!==name);
}

function addCustomColor(){
  const inp=document.getElementById('customColorIn');const val=inp?.value.trim();if(!val)return;
  const grid=document.getElementById('colorGrid');
  const wrap=document.createElement('div');wrap.className='color-dot-wrap';wrap.dataset.color=val;
  wrap.innerHTML=`<div class="color-dot" style="background:#9ca3af"></div><div class="color-dot-name">${val}</div>`;
  wrap.onclick=function(){toggleColor(this,val);};grid?.appendChild(wrap);if(inp)inp.value='';
}

function renderChipsSection(v){
  const key=v.name.replace(/\s/g,'_');
  const chips=(v.values||[]).map(val=>`<div class="var-chip" data-varname="${v.name}" onclick="toggleVarChip(this,'${v.name}','${val.replace(/'/g,"\\'")}'">${val}</div>`).join('');
  return`<div class="vsec"><div class="vsec-title">${v.name}</div><div class="chips-grid" id="cg_${key}">${chips}</div><div class="add-custom-row"><input type="text" class="add-custom-input" placeholder="Добави ${v.name.toLowerCase()}..." id="cci_${key}"><button type="button" class="add-custom-btn" onclick="addCustomChip('${v.name}','${key}')">+ Добави</button></div></div>`;
}

function toggleVarChip(el,varName,val){
  el.classList.toggle('sel');
  if(!customVarSel[varName])customVarSel[varName]=[];
  if(el.classList.contains('sel'))customVarSel[varName].push(val);
  else customVarSel[varName]=customVarSel[varName].filter(v=>v!==val);
}

function addCustomChip(varName,key){
  const inp=document.getElementById('cci_'+key);const val=inp?.value.trim();if(!val)return;
  const grid=document.getElementById('cg_'+key);
  const chip=document.createElement('div');chip.className='var-chip';chip.dataset.varname=varName;chip.textContent=val;
  chip.onclick=function(){toggleVarChip(this,varName,val);};grid?.appendChild(chip);if(inp)inp.value='';
}

function onStep3Next(){
  if(productType==='single'){saveSingle();}
  else{
    buildCombinations();
    if(!variantCombs.length){showToast('Избери поне един вариант');return;}
    initScanner();goStep(4);
  }
}

function buildCombinations(){
  const sizes=Object.keys(selectedSizes);
  const colors=selectedColors;
  if(sizes.length&&colors.length){
    variantCombs=[];
    for(const sz of sizes)for(const cl of colors)variantCombs.push({size:sz,color:cl,price:selectedSizes[sz]||null,barcode:null});
  } else if(sizes.length){
    variantCombs=sizes.map(sz=>({size:sz,color:null,price:selectedSizes[sz]||null,barcode:null}));
  } else if(colors.length){
    variantCombs=colors.map(cl=>({size:null,color:cl,price:null,barcode:null}));
  } else {
    variantCombs=[];
    for(const[vn,vals]of Object.entries(customVarSel)){
      vals.forEach(v=>variantCombs.push({label:vn+': '+v,size:null,color:null,price:null,barcode:null}));
    }
  }
}

function initScanner(){scannerIdx=0;renderScannerProgress();showCurrentVariant();}

function renderScannerProgress(){
  document.getElementById('scannerProgress').innerHTML=variantCombs.map((v,i)=>{
    const lbl=[v.size,v.color,v.label].filter(Boolean).join('/');
    const cls=v.barcode?'done':(i===scannerIdx?'active':'');
    return`<div class="scan-dot ${cls}" title="${lbl}">${i+1}</div>`;
  }).join('');
}

function showCurrentVariant(){
  if(scannerIdx>=variantCombs.length){
    document.getElementById('scannerCurrent').style.display='none';
    document.getElementById('btnFinalSave').style.display='block';
    document.getElementById('btnConfirmScan').style.display='none';
    document.querySelector('.btn-skip').style.display='none';
    return;
  }
  const v=variantCombs[scannerIdx];
  const lbl=[v.size,v.color,v.label].filter(Boolean).join(' / ');
  document.getElementById('scannerVariantName').textContent=lbl||('Вариант '+(scannerIdx+1));
  document.getElementById('scannerInput').value='';
  document.getElementById('scannerInput').focus();
  renderScannerResults();renderScannerProgress();
}

function renderScannerResults(){
  const done=variantCombs.filter((_,i)=>i<scannerIdx);
  document.getElementById('scannerResults').innerHTML=done.map(v=>{
    const lbl=[v.size,v.color,v.label].filter(Boolean).join(' / ');
    return`<div class="scan-result-row"><span style="color:#e2e8f0">${lbl}</span><span style="color:${v.barcode?'#22c55e':'#6b7280'}">${v.barcode||'автобаркод'}</span></div>`;
  }).join('')||'<div style="font-size:12px;color:#4b5563;text-align:center;padding:8px">Сканирай първия баркод...</div>';
}

function confirmCurrentScan(){
  const bc=document.getElementById('scannerInput').value.trim();
  if(!bc){showToast('Въведи баркод или пропусни');return;}
  variantCombs[scannerIdx].barcode=bc;scannerIdx++;
  beepSound();showCurrentVariant();
}

function skipCurrentScan(){variantCombs[scannerIdx].barcode=null;scannerIdx++;showCurrentVariant();}

function beepSound(){try{const a=new AudioContext();const o=a.createOscillator();const g=a.createGain();o.connect(g);g.connect(a.destination);o.frequency.value=880;g.gain.setValueAtTime(.3,a.currentTime);g.gain.exponentialRampToValueAtTime(.01,a.currentTime+.1);o.start(a.currentTime);o.stop(a.currentTime+.1);}catch(e){}}

async function saveSingle(){
  showToast('Запазвам...');
  const fd=buildFD(false);
  await fetch('product-save.php',{method:'POST',body:fd});
  showToast('Артикулът е запазен ✓');closeModal();setTimeout(()=>location.reload(),800);
}

async function finalSave(){
  showToast('Запазвам...');
  const fd=buildFD(true);
  const r=await fetch('product-save.php',{method:'POST',body:fd});
  const j=await r.json().catch(()=>({ok:true}));
  if(j.ok||j.redirect){showToast('Запазено ✓');closeModal();setTimeout(()=>location.reload(),800);}
  else showToast('Грешка при запазване');
}

function buildFD(isVariant){
  const fd=new FormData();
  fd.append('action','create');
  fd.append('name',document.getElementById('f_name').value);
  fd.append('retail_price',document.getElementById('f_price').value);
  fd.append('barcode',document.getElementById('f_barcode').value);
  fd.append('category_id',document.getElementById('f_cat').value);
  fd.append('supplier_id',document.getElementById('f_sup').value);
  fd.append('cost_price',document.getElementById('f_cost').value);
  fd.append('wholesale_price',document.getElementById('f_wprice').value);
  fd.append('unit',document.getElementById('f_unit').value);
  fd.append('location',document.getElementById('f_loc').value);
  fd.append('description',document.getElementById('f_desc').value);
  fd.append('code',document.getElementById('f_code').value);
  if(isVariant){fd.append('has_variants','1');fd.append('variants_batch',JSON.stringify(variantCombs));}
  else{
    fd.append('has_variants','0');
    const sc=document.querySelector('.color-dot-wrap.sel');if(sc)fd.append('color',sc.dataset.color);
    const ss=document.querySelector('.size-chip.sel');if(ss)fd.append('size',ss.dataset.size);
  }
  const ph=document.getElementById('photoInput');if(ph?.files[0])fd.append('image',ph.files[0]);
  return fd;
}

function openModal(){
  productType=null;selectedSizes={};selectedColors=[];customVarSel={};variantCombs=[];scannerIdx=0;onbVariants=[];
  ['f_name','f_price','f_barcode','f_cost','f_wprice','f_code','f_loc'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('f_cat').value='';document.getElementById('f_sup').value='';document.getElementById('f_desc').value='';
  document.getElementById('photoPreview').style.backgroundImage='';
  document.getElementById('typeCardSingle').classList.remove('sel');document.getElementById('typeCardVariant').classList.remove('sel');
  document.getElementById('btn0Next').style.display='none';document.getElementById('btnFinalSave').style.display='none';
  document.getElementById('variantsContainer').innerHTML='';
  document.getElementById('btnConfirmScan').style.display='block';document.querySelector('.btn-skip').style.display='block';
  document.getElementById('scannerCurrent').style.display='block';
  goStep(0);
  document.getElementById('modalOvl').classList.add('open');document.getElementById('addModal').classList.add('open');
}

function closeModal(){document.getElementById('modalOvl').classList.remove('open');document.getElementById('addModal').classList.remove('open');}

function openDrawer(p){
  document.getElementById('plist').classList.add('blurred');
  document.querySelector(`.pcard[data-id="${p.id}"]`)?.classList.add('focused');
  const sc=p.sc==='sg'?'#22c55e':p.sc==='sy'?'#f59e0b':'#ef4444';
  const margin=p.cost>0?((p.price-p.cost)/p.price*100).toFixed(1):'—';
  fetch(`product-save.php?stock=${p.id}`).then(r=>r.json()).then(stores=>{
    const storeHtml=stores.length>1?`<div style="font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Наличност по обекти</div><div style="background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;overflow:hidden;margin-bottom:14px">${stores.map(s=>`<div class="store-stock-row"><span style="font-size:13px;color:#e2e8f0">${s.name}</span><span style="font-size:14px;font-weight:700;color:${s.qty==0?'#ef4444':s.qty<=s.min_qty&&s.min_qty>0?'#f59e0b':'#22c55e'}">${s.qty} ${p.unit}</span></div>`).join('')}</div>`:'';
    document.getElementById('pDrawerBody').innerHTML=`<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px"><div style="width:5px;height:50px;border-radius:3px;background:${sc};box-shadow:0 0 14px ${sc}88;flex-shrink:0"></div><div style="flex:1;min-width:0"><div class="d-name">${p.name}</div><div class="d-meta">${p.code?'Код: '+p.code+' · ':''}${p.cat||''}${p.sup?' · '+p.sup:''}${p.size?' · '+p.size:''}${p.color?' · '+p.color:''}</div></div></div><div class="sg2"><div class="si"><div class="sil">Наличност</div><div class="siv" style="color:${sc}">${p.qty} ${p.unit}</div></div><div class="si"><div class="sil">Минимум</div><div class="siv">${p.min||'—'}</div></div><div class="si"><div class="sil">Продажна</div><div class="siv">€${parseFloat(p.price).toFixed(2)}</div></div><div class="si"><div class="sil">Марж</div><div class="siv" style="color:#a5b4fc">${margin}%</div></div></div>${storeHtml}${p.loc?`<div style="font-size:12px;color:#6b7280;margin-bottom:14px">📍 ${p.loc}</div>`:''}${p.variants>0?`<div style="font-size:12px;color:#8b5cf6;margin-bottom:14px">🎨 ${p.variants} варианта</div>`:''}<div class="dab-grid">${canAdd?`<button class="dab dab-p" onclick="editProduct(${p.id})">✏️ Редактирай</button>`:'<div></div>'}<button class="dab dab-s" onclick="openAI(${JSON.stringify(p).replace(/'/g,"\\'")})" >✦ AI съвет</button></div><div class="dab-grid"><a href="sale.php?product=${p.id}" class="dab dab-s">🛒 Продажба</a><a href="product-detail.php?id=${p.id}" class="dab dab-s">🔍 Детайли</a></div>`;
  }).catch(()=>{document.getElementById('pDrawerBody').innerHTML=`<div class="d-name">${p.name}</div><div class="d-meta">${p.tag}</div>`;});
  document.getElementById('ovl').classList.add('open');document.getElementById('pDrawer').classList.add('open');
}

function openAI(p){
  document.getElementById('pDrawer').classList.remove('open');
  let rec='';
  if(p.qty==0)rec=`<strong>${p.name}</strong> е изчерпан. Зареди незабавно.`;
  else if(p.min>0&&p.qty<=p.min)rec=`Наличността е под минимума (${p.qty} от ${p.min}). Презареди скоро.`;
  else if(p.cost>0){const m=((p.price-p.cost)/p.price*100).toFixed(1);if(m<20)rec=`Маржът е само ${m}%. Провери доставната цена.`;else rec=`Всичко е наред. Марж ${m}%.`;}
  else rec=`${p.name} е в наличност. Добави доставна цена за анализ.`;
  document.getElementById('aiBody').innerHTML=`<div style="font-size:12px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">✦ AI Анализ</div><div style="font-size:20px;font-weight:800;color:#f1f5f9;margin-bottom:18px">${p.name}</div><div class="ai-rec"><div class="ai-rl">Препоръка</div><div class="ai-rt">${rec}</div></div><a href="chat.php?q=${encodeURIComponent('Анализирай '+p.name)}" class="dab dab-p" style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;margin-top:4px">Попитай AI →</a>`;
  document.getElementById('ovl').classList.add('open');document.getElementById('aiDrawer').classList.add('open');
}

function openFilterDrawer(){closeAll();document.getElementById('ovl').classList.add('open');document.getElementById('filterDrawer').classList.add('open');}
function closeAll(){['pDrawer','aiDrawer','filterDrawer'].forEach(id=>document.getElementById(id)?.classList.remove('open'));document.getElementById('ovl').classList.remove('open');document.getElementById('plist').classList.remove('blurred');document.querySelectorAll('.pcard.focused').forEach(c=>c.classList.remove('focused'));}
['pDrawer','aiDrawer','filterDrawer'].forEach(id=>{const el=document.getElementById(id);if(!el)return;el.addEventListener('touchstart',e=>ts=e.touches[0].clientY,{passive:true});el.addEventListener('touchend',e=>{if(e.changedTouches[0].clientY-ts>70)closeAll();},{passive:true});});
document.getElementById('addModal')?.addEventListener('touchstart',e=>ts=e.touches[0].clientY,{passive:true});
document.getElementById('addModal')?.addEventListener('touchend',e=>{if(e.changedTouches[0].clientY-ts>120)closeModal();},{passive:true});

async function openCamera(target){
  cameraTarget=target;
  const labels={search:'Търси по баркод',barcode:'Сканирай баркода на артикула',scanner:'Сканирай баркода'};
  document.getElementById('cameraLabelText').textContent=labels[target]||'Насочи към баркода';
  try{
    cameraStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
    document.getElementById('cameraVideo').srcObject=cameraStream;
    document.getElementById('cameraWrap').classList.add('open');
    if('BarcodeDetector' in window){
      const bd=new BarcodeDetector();
      const iv=setInterval(async()=>{try{const codes=await bd.detect(document.getElementById('cameraVideo'));if(codes.length){clearInterval(iv);useScannedBarcode(codes[0].rawValue);}}catch(e){}},300);
    }
  }catch(e){showToast('Камерата не е достъпна');}
}

function useScannedBarcode(code){
  const b=document.getElementById('cameraBeep');b.classList.add('show');setTimeout(()=>b.classList.remove('show'),800);
  beepSound();
  if(cameraTarget==='search'){document.getElementById('searchInput').value=code;closeCamera();document.getElementById('searchForm').submit();}
  else if(cameraTarget==='barcode'){document.getElementById('f_barcode').value=code;closeCamera();}
  else if(cameraTarget==='scanner'){document.getElementById('scannerInput').value=code;closeCamera();}
}

function closeCamera(){if(cameraStream){cameraStream.getTracks().forEach(t=>t.stop());cameraStream=null;}document.getElementById('cameraWrap').classList.remove('open');}

function startVoiceSearch(){
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){showToast('Не се поддържа');return;}
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;const r=new SR();r.lang='bg-BG';r.start();
  document.getElementById('voiceSearchBtn').style.color='#ef4444';
  r.onresult=e=>{document.getElementById('searchInput').value=e.results[0][0].transcript;document.getElementById('searchForm').submit();};
  r.onend=()=>{document.getElementById('voiceSearchBtn').style.color='';};
}

function toggleVoice(){
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){showToast('Не се поддържа');return;}
  const btn=document.getElementById('voiceBtn');
  if(voiceRec){voiceRec.stop();voiceRec=null;btn.classList.remove('recording');return;}
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  voiceRec=new SR();voiceRec.lang='bg-BG';voiceRec.continuous=true;
  btn.classList.add('recording');
  voiceRec.onresult=e=>{
    const text=e.results[e.results.length-1][0].transcript;showToast('Обработвам...');
    fetch('chat-send.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:`Извлечи данни за артикул: "${text}". JSON с name, retail_price, cost_price, barcode, size, color, location. Само JSON.`})}).then(r=>r.json()).then(d=>{try{const m=(d.response||d.message||'').match(/\{[\s\S]*\}/);if(m){const o=JSON.parse(m[0]);if(o.name)document.getElementById('f_name').value=o.name;if(o.retail_price)document.getElementById('f_price').value=o.retail_price;if(o.location)document.getElementById('f_loc').value=o.location;showToast('AI попълни ✓');}}catch(e){}});
  };
  voiceRec.onend=()=>{btn.classList.remove('recording');voiceRec=null;};
  voiceRec.start();
}

async function generateAIDescription(){
  const name=document.getElementById('f_name').value;if(!name){showToast('Първо въведи наименование');return;}
  showToast('AI генерира...');
  const r=await fetch('chat-send.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:`Напиши кратко търговско описание (2-3 изречения) за артикул "${name}". Само описанието.`})});
  const d=await r.json();const txt=d.response||d.message||'';
  if(txt){document.getElementById('f_desc').value=txt;showToast('Описанието е генерирано ✓');}
}

function previewPhoto(input){
  if(input.files?.[0]){const reader=new FileReader();reader.onload=e=>{document.getElementById('photoPreview').style.backgroundImage=`url(${e.target.result})`;};reader.readAsDataURL(input.files[0]);}
}

function editProduct(id){
  closeAll();
  fetch(`product-save.php?get=${id}`).then(r=>r.json()).then(p=>{
    document.getElementById('modalTitle').textContent='Редактирай';
    ['name','barcode','retail_price','cost_price','wholesale_price','category_id','supplier_id','unit','code','location','description'].forEach(k=>{const el=document.getElementById('f_'+(k==='retail_price'?'price':k==='cost_price'?'cost':k==='wholesale_price'?'wprice':k==='category_id'?'cat':k==='supplier_id'?'sup':k==='location'?'loc':k));if(el)el.value=p[k]||'';});
    productType='single';goStep(1);document.getElementById('modalOvl').classList.add('open');document.getElementById('addModal').classList.add('open');
  });
}

function setFilter(f){const u=new URL(window.location);u.searchParams.set('filter',f);window.location=u;}
document.querySelectorAll('.pcard').forEach(c=>{c.addEventListener('mousemove',e=>{const r=c.getBoundingClientRect();c.style.setProperty('--mx',(e.clientX-r.left)+'px');c.style.setProperty('--my',(e.clientY-r.top)+'px');});});
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500);}

<?php if(isset($_GET['saved'])): ?>showToast('Артикулът е запазен ✓');<?php endif; ?>
<?php if(isset($_GET['error'])): ?>showToast('Грешка при запазване');<?php endif; ?>
</script>
</body>
</html>
