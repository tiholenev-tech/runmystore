<?php
// products.php — Артикули
// Търсачка + камера/баркод + филтри drawer
// Batch добавяне (размери × цветове)
// Progressive Disclosure форма (3 стъпки)
// Наличност по всички обекти
// Гласово въвеждане + AI описание

require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

$tenant_id    = $_SESSION['tenant_id'];
$role         = $_SESSION['role'] ?? 'seller';
$user_store   = $_SESSION['store_id'];
$currency     = $_SESSION['currency'] ?? 'EUR';
$can_add      = in_array($role, ['owner', 'manager']);
$can_see_cost = ($role === 'owner');

$search    = trim($_GET['q'] ?? '');
$filter    = $_GET['filter'] ?? 'all';
$f_cat     = (int)($_GET['cat'] ?? 0);
$f_sup     = (int)($_GET['sup'] ?? 0);
$f_size    = trim($_GET['size'] ?? '');
$f_color   = trim($_GET['color'] ?? '');
$f_min     = $_GET['pmin'] ?? '';
$f_max     = $_GET['pmax'] ?? '';
$f_store   = ($role === 'seller') ? $user_store : (int)($_GET['store'] ?? 0);

// Артикули
$where = ["p.tenant_id=?", "p.parent_id IS NULL", "p.is_active=1"];
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

$inv_join = $f_store ? "LEFT JOIN inventory i ON i.product_id=p.id AND i.tenant_id=p.tenant_id AND i.store_id=$f_store"
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

// Филтър по статус
$cnt_low = 0; $cnt_out = 0;
foreach ($all_products as $p) {
    if ($p['total_stock'] == 0) $cnt_out++;
    elseif ($p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']) $cnt_low++;
}

if ($filter === 'low') $products = array_values(array_filter($all_products, fn($p) => $p['total_stock'] > 0 && $p['min_qty'] > 0 && $p['total_stock'] <= $p['min_qty']));
elseif ($filter === 'out') $products = array_values(array_filter($all_products, fn($p) => $p['total_stock'] == 0));
else $products = $all_products;

// Данни за филтри
$categories = DB::run("SELECT id, name, variant_type FROM categories WHERE tenant_id=? AND parent_id IS NULL ORDER BY name", [$tenant_id])->fetchAll();
$suppliers  = DB::run("SELECT id, name FROM suppliers WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll();
$stores     = DB::run("SELECT id, name FROM stores WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tenant_id])->fetchAll();
$sizes      = DB::run("SELECT DISTINCT size FROM products WHERE tenant_id=? AND size IS NOT NULL AND size!='' ORDER BY size", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);
$colors     = DB::run("SELECT DISTINCT color FROM products WHERE tenant_id=? AND color IS NOT NULL AND color!='' ORDER BY color", [$tenant_id])->fetchAll(PDO::FETCH_COLUMN);

$has_filters = $f_cat || $f_sup || $f_size || $f_color || $f_min !== '' || $f_max !== '' || ($role !== 'seller' && $f_store);
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

/* HEADER */
.hdr{position:sticky;top:0;z-index:50;background:rgba(3,7,18,.93);backdrop-filter:blur(20px);border-bottom:1px solid rgba(99,102,241,.12);padding:14px 16px 0}
.hdr-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.page-title{font-size:20px;font-weight:800;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.btn-add{display:flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:12px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 0 20px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.16)}
.btn-add:active{transform:scale(.96)}

/* SEARCH ROW */
.search-row{display:flex;gap:8px;margin-bottom:12px;align-items:center}
.search-wrap{position:relative;flex:1}
.search-input{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#e2e8f0;font-size:14px;padding:11px 80px 11px 16px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
.search-input::placeholder{color:#4b5563}
.search-input:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.search-icons{position:absolute;right:10px;top:50%;transform:translateY(-50%);display:flex;gap:6px;align-items:center}
.icon-btn{width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc;transition:all .2s}
.icon-btn:active{background:rgba(99,102,241,.3)}
.filter-btn{width:40px;height:40px;border-radius:12px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc;flex-shrink:0;position:relative}
.filter-btn.active{background:rgba(99,102,241,.25);border-color:rgba(99,102,241,.5)}
.filter-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#6366f1;box-shadow:0 0 6px rgba(99,102,241,.8)}

/* FILTER TABS */
.ftabs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding-bottom:12px}
.ftabs::-webkit-scrollbar{display:none}
.ftab{flex-shrink:0;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;white-space:nowrap}
.ftab.active{background:linear-gradient(135deg,#6366f1,#8b5cf6);border-color:transparent;color:#fff;box-shadow:0 0 14px rgba(99,102,241,.35)}
.cnt{display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;border-radius:8px;font-size:10px;font-weight:800;margin-left:4px;padding:0 3px;background:rgba(255,255,255,.15)}
.ftab:not(.active) .cnt{background:rgba(99,102,241,.15);color:#6366f1}
.ftab:not(.active) .cnt.w{background:rgba(245,158,11,.15);color:#f59e0b}
.ftab:not(.active) .cnt.d{background:rgba(239,68,68,.15);color:#ef4444}

/* PRODUCTS LIST */
.plist{padding:12px 12px 20px;position:relative;z-index:1}

/* PRODUCT CARD */
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
.ai-btn:active{background:rgba(99,102,241,.3)}

/* BLURRED */
.plist.blurred .pcard:not(.focused){filter:blur(1.5px);opacity:.4;pointer-events:none;transition:filter .25s,opacity .25s}
.pcard.focused{z-index:10;box-shadow:0 8px 40px rgba(99,102,241,.25)}

/* EMPTY */
.empty{text-align:center;padding:60px 20px;color:#4b5563}
.empty svg{width:48px;height:48px;margin-bottom:12px;color:#1f2937}
.empty h3{font-size:16px;font-weight:700;color:#374151;margin:0 0 6px}
.empty p{font-size:13px;margin:0}

/* OVERLAY */
.ovl{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:200;opacity:0;pointer-events:none;transition:opacity .3s;backdrop-filter:blur(8px)}
.ovl.open{opacity:1;pointer-events:all}

/* DRAWER */
.drw{position:fixed;bottom:0;left:0;right:0;z-index:201;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:88vh;overflow-y:auto;box-shadow:0 -30px 80px rgba(99,102,241,.2)}
.drw.open{transform:translateY(0)}
.drw-h{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 20px}
.drw-b{padding:0 20px 40px}

/* PRODUCT DRAWER */
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
.dab-d{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#ef4444}

/* AI DRAWER */
.ai-rec{background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.08));border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:14px;margin-bottom:16px}
.ai-rl{font-size:10px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.ai-rt{font-size:13px;color:#e2e8f0;line-height:1.65}

/* FILTER DRAWER */
.fl-section{margin-bottom:18px}
.fl-title{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.fl-chips{display:flex;gap:6px;flex-wrap:wrap}
.fl-chip{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s}
.fl-chip.sel{background:rgba(99,102,241,.2);border-color:#6366f1;color:#a5b4fc}
.fl-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.fl-input{background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#e2e8f0;font-size:13px;padding:8px 12px;font-family:'Montserrat',sans-serif;outline:none;width:100%}
.fl-apply{width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:14px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:4px}
.fl-clear{width:100%;padding:11px;background:transparent;border:1px solid rgba(99,102,241,.2);border-radius:14px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}

/* ADD MODAL — 3 стъпки */
.modal-ovl{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:300;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(8px)}
.modal-ovl.open{opacity:1;pointer-events:all}
.modal{position:fixed;bottom:0;left:0;right:0;z-index:301;background:linear-gradient(to bottom,#0d0d25,#080818);border-top:1px solid rgba(99,102,241,.2);border-radius:24px 24px 0 0;transform:translateY(100%);transition:transform .35s cubic-bezier(.32,0,.67,0);max-height:92vh;overflow-y:auto}
.modal.open{transform:translateY(0)}
.m-handle{width:40px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:14px auto 0}
.m-head{padding:14px 20px;border-bottom:1px solid rgba(99,102,241,.1);display:flex;justify-content:space-between;align-items:center}
.m-title{font-size:16px;font-weight:800;color:#f1f5f9}
.m-close{width:32px;height:32px;border-radius:50%;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.15);color:#6b7280;display:flex;align-items:center;justify-content:center;cursor:pointer}
.m-body{padding:20px}

/* Steps */
.steps{display:flex;gap:0;margin-bottom:20px}
.step{flex:1;text-align:center;position:relative}
.step::after{content:'';position:absolute;top:10px;left:50%;width:100%;height:2px;background:rgba(99,102,241,.15)}
.step:last-child::after{display:none}
.step-dot{width:20px;height:20px;border-radius:50%;border:2px solid rgba(99,102,241,.3);background:transparent;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#6b7280;position:relative;z-index:1}
.step.done .step-dot{background:#6366f1;border-color:#6366f1;color:#fff}
.step.active .step-dot{border-color:#6366f1;color:#6366f1;box-shadow:0 0 10px rgba(99,102,241,.4)}
.step-label{font-size:10px;color:#6b7280;font-weight:600}
.step.active .step-label{color:#a5b4fc}

.step-content{display:none}
.step-content.active{display:block}

/* Form */
.fg{margin-bottom:14px}
.fl{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.fi{width:100%;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:12px;color:#e2e8f0;font-size:14px;padding:11px 14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;-webkit-appearance:none}
.fi:focus{border-color:rgba(99,102,241,.5);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.fi::placeholder{color:#4b5563}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:10px}

/* Photo upload */
.photo-upload{width:100%;height:100px;background:rgba(15,15,40,.8);border:2px dashed rgba(99,102,241,.3);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;margin-bottom:14px;transition:border-color .2s}
.photo-upload:active{border-color:#6366f1}
.photo-upload svg{color:#6366f1}
.photo-upload span{font-size:12px;color:#6b7280}

/* Batch size/color grid */
.size-grid{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.size-chip{padding:6px 12px;border-radius:10px;font-size:13px;font-weight:700;border:1px solid rgba(99,102,241,.2);color:#6b7280;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;position:relative}
.size-chip.sel{background:rgba(99,102,241,.2);border-color:#6366f1;color:#a5b4fc}
.size-chip input.price-override{position:absolute;bottom:-28px;left:0;width:80px;background:rgba(15,15,40,.9);border:1px solid rgba(99,102,241,.3);border-radius:8px;color:#e2e8f0;font-size:11px;padding:3px 6px;display:none;font-family:'Montserrat',sans-serif;outline:none;z-index:10}
.size-chip.sel input.price-override{display:block}
.color-dot{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all .2s;position:relative}
.color-dot.sel{border-color:#fff;box-shadow:0 0 10px rgba(99,102,241,.5)}
.add-size-input{display:flex;gap:6px;margin-top:6px}
.add-size-input input{flex:1;background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#e2e8f0;font-size:13px;padding:7px 10px;font-family:'Montserrat',sans-serif;outline:none}
.add-size-input button{padding:7px 12px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#a5b4fc;font-size:12px;cursor:pointer;font-family:'Montserrat',sans-serif}

/* Voice btn */
.voice-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#a5b4fc;font-size:12px;font-weight:600;cursor:pointer;font-family:'Montserrat',sans-serif;width:100%;justify-content:center;margin-bottom:14px;transition:all .2s}
.voice-btn.recording{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3);color:#ef4444;animation:pulse-rec 1s infinite}
.ai-gen-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(168,85,247,.1));border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#a5b4fc;font-size:12px;font-weight:600;cursor:pointer;font-family:'Montserrat',sans-serif;width:100%;justify-content:center;margin-bottom:14px}

.btn-next{width:100%;padding:13px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:14px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.3),inset 0 1px 0 rgba(255,255,255,.16);margin-top:4px}
.btn-back{width:100%;padding:11px;background:transparent;border:1px solid rgba(99,102,241,.15);border-radius:14px;color:#6b7280;font-size:13px;cursor:pointer;font-family:'Montserrat',sans-serif;margin-top:8px}
.btn-save{width:100%;padding:14px;background:linear-gradient(to bottom,#22c55e,#16a34a);border:none;border-radius:14px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(34,197,94,.3);margin-top:4px}

/* CAMERA */
.camera-wrap{position:fixed;inset:0;z-index:400;background:#000;display:none;flex-direction:column}
.camera-wrap.open{display:flex}
#cameraVideo{width:100%;flex:1;object-fit:cover}
.camera-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none}
.scan-frame{width:220px;height:220px;border:2px solid rgba(99,102,241,.8);border-radius:16px;box-shadow:0 0 0 9999px rgba(0,0,0,.5)}
.scan-line{position:absolute;width:200px;height:2px;background:linear-gradient(to right,transparent,#6366f1,transparent);animation:scanAnim 2s linear infinite}
.camera-close{position:absolute;top:20px;right:20px;width:40px;height:40px;border-radius:50%;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.2);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;pointer-events:all;z-index:1}
.camera-result{position:absolute;bottom:40px;left:20px;right:20px;background:rgba(3,7,18,.9);border:1px solid rgba(99,102,241,.3);border-radius:14px;padding:14px;pointer-events:all;display:none}
.camera-result.show{display:block}

/* TOAST */
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;box-shadow:0 4px 20px rgba(99,102,241,.4)}
.toast.show{opacity:1}

/* BOTTOM NAV */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(99,102,241,.1);display:flex;align-items:center;z-index:100}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:8px 0;text-decoration:none;border:none;background:transparent;cursor:pointer}
.ni svg{width:22px;height:22px;color:#3f3f5a}
.ni span{font-size:10px;font-weight:600;color:#3f3f5a}
.ni.active svg,.ni.active span{color:#6366f1}

@keyframes cIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes bp{0%,100%{opacity:1}50%{opacity:.55}}
@keyframes scanAnim{0%{top:10%}50%{top:85%}100%{top:10%}}
@keyframes pulse-rec{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 0 8px rgba(239,68,68,0)}}
.pcard:nth-child(1){animation-delay:.04s}.pcard:nth-child(2){animation-delay:.08s}.pcard:nth-child(3){animation-delay:.12s}.pcard:nth-child(4){animation-delay:.16s}.pcard:nth-child(n+5){animation-delay:.2s}
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
        <div class="icon-btn" onclick="openCamera()" title="Сканирай баркод">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="17" y="2" width="5" height="5" rx="1"/><rect x="2" y="17" width="5" height="5" rx="1"/><path d="M17 17h5v5M17 21h.01M21 17h.01M12 2v5M7 12h5M12 12v5M17 12h5M2 12h5"/></svg>
        </div>
        <div class="icon-btn" onclick="startVoiceSearch()" title="Гласово търсене" id="voiceSearchBtn">
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
  <p><?= $search?"Нищо не отговаря на \"".htmlspecialchars($search)."\"":($can_add?'Натисни + Добави':'Кажи на AI да добави') ?></p>
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
        <div class="csub">
          <?= $p['code']?'Код: '.htmlspecialchars($p['code']):'' ?>
          <?= $p['code']&&$p['category_name']?' · ':'' ?>
          <?= htmlspecialchars($p['category_name']??'') ?>
          <?= ($p['size']||$p['color'])?' · ':'' ?>
          <?= htmlspecialchars(implode(' ', array_filter([$p['size']??'',$p['color']??'']))) ?>
        </div>
      </div>
      <div>
        <div class="qnum" style="color:<?= $qc ?>"><?= number_format($qty,0) ?></div>
        <div class="qunit"><?= htmlspecialchars($p['unit']??'бр') ?></div>
      </div>
    </div>
    <div class="ctags">
      <span class="tag tp">€<?= number_format((float)($p['retail_price']??0),2,',','.') ?></span>
      <?php if($can_see_cost&&$p['cost_price']>0): ?><span class="tag tc">Дост: €<?= number_format((float)$p['cost_price'],2,',','.') ?></span><?php endif; ?>
      <span class="tag <?= $tc ?>"><?= $tt ?></span>
      <?php if($p['variant_count']>0): ?><span class="tag tv"><?= $p['variant_count'] ?> варианта</span><?php endif; ?>
      <?php if($p['supplier_name']): ?><span class="tag ts"><?= htmlspecialchars($p['supplier_name']) ?></span><?php endif; ?>
      <button class="ai-btn" onclick="event.stopPropagation();openAI(<?= htmlspecialchars($pd,ENT_QUOTES) ?>)">
        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
        AI
      </button>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
</div>

<!-- OVERLAYS -->
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

      <?php if($categories): ?>
      <div class="fl-section">
        <div class="fl-title">Категория</div>
        <div class="fl-chips">
          <?php foreach($categories as $c): ?>
          <label class="fl-chip <?= $f_cat==$c['id']?'sel':'' ?>">
            <input type="radio" name="cat" value="<?= $c['id'] ?>" style="display:none" <?= $f_cat==$c['id']?'checked':'' ?> onchange="this.closest('form').submit()">
            <?= htmlspecialchars($c['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if($suppliers): ?>
      <div class="fl-section">
        <div class="fl-title">Доставчик</div>
        <div class="fl-chips">
          <?php foreach($suppliers as $s): ?>
          <label class="fl-chip <?= $f_sup==$s['id']?'sel':'' ?>">
            <input type="radio" name="sup" value="<?= $s['id'] ?>" style="display:none" <?= $f_sup==$s['id']?'checked':'' ?> onchange="this.closest('form').submit()">
            <?= htmlspecialchars($s['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if($sizes): ?>
      <div class="fl-section">
        <div class="fl-title">Размер</div>
        <div class="fl-chips">
          <?php foreach($sizes as $sz): ?>
          <label class="fl-chip <?= $f_size===$sz?'sel':'' ?>">
            <input type="radio" name="size" value="<?= htmlspecialchars($sz) ?>" style="display:none" <?= $f_size===$sz?'checked':'' ?> onchange="this.closest('form').submit()">
            <?= htmlspecialchars($sz) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if($colors): ?>
      <div class="fl-section">
        <div class="fl-title">Цвят</div>
        <div class="fl-chips">
          <?php foreach($colors as $cl): ?>
          <label class="fl-chip <?= $f_color===$cl?'sel':'' ?>">
            <input type="radio" name="color" value="<?= htmlspecialchars($cl) ?>" style="display:none" <?= $f_color===$cl?'checked':'' ?> onchange="this.closest('form').submit()">
            <?= htmlspecialchars($cl) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="fl-section">
        <div class="fl-title">Цена (€)</div>
        <div class="fl-row">
          <input type="number" name="pmin" class="fl-input" placeholder="От" value="<?= htmlspecialchars($f_min) ?>" step="0.01">
          <input type="number" name="pmax" class="fl-input" placeholder="До" value="<?= htmlspecialchars($f_max) ?>" step="0.01">
        </div>
      </div>

      <?php if($role!=='seller'&&count($stores)>1): ?>
      <div class="fl-section">
        <div class="fl-title">Обект</div>
        <div class="fl-chips">
          <?php foreach($stores as $s): ?>
          <label class="fl-chip <?= $f_store==$s['id']?'sel':'' ?>">
            <input type="radio" name="store" value="<?= $s['id'] ?>" style="display:none" <?= $f_store==$s['id']?'checked':'' ?> onchange="this.closest('form').submit()">
            <?= htmlspecialchars($s['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

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
    <button class="m-close" onclick="closeModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="m-body">
    <!-- Steps indicator -->
    <div class="steps">
      <div class="step active" id="step-ind-1"><div class="step-dot">1</div><div class="step-label">Основно</div></div>
      <div class="step" id="step-ind-2"><div class="step-dot">2</div><div class="step-label">AI + Размери</div></div>
      <div class="step" id="step-ind-3"><div class="step-dot">3</div><div class="step-label">Детайли</div></div>
    </div>

    <form method="POST" action="product-save.php" id="productForm" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="selected_sizes" id="selectedSizes">
      <input type="hidden" name="selected_colors" id="selectedColors">
      <input type="hidden" name="size_prices" id="sizePrices">

      <!-- СТЪПКА 1 -->
      <div class="step-content active" id="step1">
        <button type="button" class="voice-btn" id="voiceBtn" onclick="toggleVoice()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
          Диктувай на AI
        </button>

        <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
          <span>Снимка на артикула</span>
          <span style="font-size:10px;color:#374151">или натисни камерата горе за баркод</span>
        </div>
        <input type="file" id="photoInput" name="image" accept="image/*" style="display:none" onchange="previewPhoto(this)">

        <div class="fg">
          <label class="fl">Наименование *</label>
          <input type="text" name="name" id="f_name" class="fi" placeholder="напр. Nike Air Max" required>
        </div>
        <div class="fg">
          <label class="fl">Продажна цена *</label>
          <input type="number" name="retail_price" id="f_price" class="fi" placeholder="0.00" step="0.01" min="0" required>
        </div>
        <div class="fg">
          <label class="fl">Баркод</label>
          <div style="display:flex;gap:8px">
            <input type="text" name="barcode" id="f_barcode" class="fi" placeholder="Сканирай или въведи">
            <div class="icon-btn" onclick="openCameraForBarcode()" style="flex-shrink:0;width:44px;height:44px;border-radius:12px">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="17" y="2" width="5" height="5" rx="1"/><rect x="2" y="17" width="5" height="5" rx="1"/><path d="M17 17h5v5"/></svg>
            </div>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-top:4px">Оставете празно → автоматично генериране</div>
        </div>
        <button type="button" class="btn-next" onclick="goStep(2)">Напред →</button>
      </div>

      <!-- СТЪПКА 2 -->
      <div class="step-content" id="step2">
        <button type="button" class="ai-gen-btn" onclick="generateAIDescription()">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
          AI генерира описание автоматично
        </button>

        <div class="fg">
          <label class="fl">Категория</label>
          <select name="category_id" id="f_cat" class="fi" style="-webkit-appearance:none">
            <option value="">— Без категория —</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>" data-variant="<?= $c['variant_type'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fg">
          <label class="fl">Размери (batch добавяне)</label>
          <div class="size-grid" id="sizeGrid">
            <?php $std_sizes=['XS','S','M','L','XL','XXL','36','37','38','39','40','41','42','43','44','45']; foreach($std_sizes as $sz): ?>
            <div class="size-chip" onclick="toggleSize(this,'<?= $sz ?>')" data-size="<?= $sz ?>"><?= $sz ?><input class="price-override" type="number" placeholder="€цена" step="0.01" onclick="event.stopPropagation()"></div>
            <?php endforeach; ?>
          </div>
          <div class="add-size-input">
            <input type="text" id="customSizeInput" placeholder="Добави размер...">
            <button type="button" onclick="addCustomSize()">+ Добави</button>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-top:6px">Натисни размер за избор. Задръж за различна цена.</div>
        </div>

        <div class="fg">
          <label class="fl">Цветове</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px" id="colorGrid">
            <?php $std_colors=['#1f1f1f','#ffffff','#ef4444','#3b82f6','#22c55e','#f59e0b','#8b5cf6','#ec4899','#64748b','#d97706']; foreach($std_colors as $cl): ?>
            <div class="color-dot" style="background:<?= $cl ?>;border-color:rgba(255,255,255,.2)" onclick="toggleColor(this,'<?= $cl ?>')" data-color="<?= $cl ?>"></div>
            <?php endforeach; ?>
          </div>
          <input type="text" name="color" id="f_color" class="fi" placeholder="или въведи цвят (напр. Тъмносин)">
        </div>

        <div class="fg">
          <label class="fl">Описание (AI)</label>
          <textarea name="description" id="f_desc" class="fi" rows="3" placeholder="AI ще генерира автоматично..."></textarea>
        </div>

        <button type="button" class="btn-next" onclick="goStep(3)">Напред →</button>
        <button type="button" class="btn-back" onclick="goStep(1)">← Назад</button>
      </div>

      <!-- СТЪПКА 3 -->
      <div class="step-content" id="step3">
        <div class="frow">
          <div class="fg">
            <label class="fl">Покупна цена</label>
            <input type="number" name="cost_price" id="f_cost" class="fi" placeholder="0.00" step="0.01" min="0">
          </div>
          <div class="fg">
            <label class="fl">Цена едро</label>
            <input type="number" name="wholesale_price" id="f_wprice" class="fi" placeholder="0.00" step="0.01" min="0">
          </div>
        </div>
        <div class="frow">
          <div class="fg">
            <label class="fl">Доставчик</label>
            <select name="supplier_id" id="f_sup" class="fi" style="-webkit-appearance:none">
              <option value="">— Без —</option>
              <?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Мерна единица</label>
            <select name="unit" id="f_unit" class="fi" style="-webkit-appearance:none">
              <option value="бр">бр</option><option value="чифт">чифт</option><option value="кг">кг</option><option value="гр">гр</option><option value="л">л</option><option value="мл">мл</option><option value="м">м</option><option value="кутия">кутия</option><option value="пакет">пакет</option><option value="комплект">комплект</option>
            </select>
          </div>
        </div>
        <div class="frow">
          <div class="fg">
            <label class="fl">Артикулен код</label>
            <input type="text" name="code" id="f_code" class="fi" placeholder="автоген. ако е празно">
          </div>
          <div class="fg">
            <label class="fl">Локация</label>
            <input type="text" name="location" id="f_loc" class="fi" placeholder="Рафт А-3">
          </div>
        </div>

        <button type="submit" class="btn-save">✓ Запази артикул</button>
        <button type="button" class="btn-back" onclick="goStep(2)">← Назад</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- CAMERA -->
<div class="camera-wrap" id="cameraWrap">
  <video id="cameraVideo" autoplay playsinline muted></video>
  <div class="camera-overlay">
    <div class="scan-frame">
      <div class="scan-line"></div>
    </div>
    <div style="color:#fff;font-size:13px;margin-top:16px;font-weight:600">Насочи камерата към баркода</div>
  </div>
  <div class="camera-close" onclick="closeCamera()">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
  </div>
  <div class="camera-result" id="cameraResult">
    <div style="font-size:11px;color:#6b7280;margin-bottom:4px">Сканиран баркод:</div>
    <div style="font-size:16px;font-weight:700;color:#f1f5f9" id="scannedCode"></div>
    <div style="display:flex;gap:8px;margin-top:10px">
      <button onclick="useScannedCode()" style="flex:1;padding:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:10px;color:#fff;font-size:13px;font-weight:700;cursor:pointer">Използвай</button>
      <button onclick="closeCamera()" style="flex:1;padding:8px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#a5b4fc;font-size:13px;cursor:pointer">Затвори</button>
    </div>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <a href="chat.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg><span>Чат</span></a>
  <a href="warehouse.php" class="ni active"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg><span>Склад</span></a>
  <a href="stats.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистики</span></a>
  <a href="sale.php" class="ni"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>

<div class="toast" id="toast"></div>

<script>
const canSeeCost = <?= $can_see_cost?'true':'false' ?>;
const canAdd = <?= $can_add?'true':'false' ?>;
let currentStep = 1;
let selectedSizes = {};
let selectedColors = [];
let cameraStream = null;
let barcodeTarget = 'search';
let ts = 0;

// Mouse glow
document.querySelectorAll('.pcard').forEach(c => {
  c.addEventListener('mousemove', e => {
    const r = c.getBoundingClientRect();
    c.style.setProperty('--mx', (e.clientX-r.left)+'px');
    c.style.setProperty('--my', (e.clientY-r.top)+'px');
  });
});

function setFilter(f) {
  const u = new URL(window.location);
  u.searchParams.set('filter', f);
  window.location = u;
}

// DRAWERS
function openDrawer(p) {
  document.getElementById('plist').classList.add('blurred');
  document.querySelector(`.pcard[data-id="${p.id}"]`)?.classList.add('focused');
  const sc = p.sc==='sg'?'#22c55e':p.sc==='sy'?'#f59e0b':'#ef4444';
  const margin = p.cost>0?((p.price-p.cost)/p.price*100).toFixed(1):'—';

  // Load stock by store
  fetch(`product-save.php?stock=${p.id}`)
    .then(r=>r.json()).then(stores=>{
      let storeHtml = stores.length>1 ? `
        <div style="font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Наличност по обекти</div>
        <div style="background:rgba(15,15,40,.8);border:1px solid rgba(99,102,241,.12);border-radius:12px;overflow:hidden;margin-bottom:14px">
          ${stores.map(s=>`<div class="store-stock-row"><span style="font-size:13px;color:#e2e8f0">${s.name}</span><span style="font-size:14px;font-weight:700;color:${s.qty==0?'#ef4444':s.qty<=s.min&&s.min>0?'#f59e0b':'#22c55e'}">${s.qty} ${p.unit}</span></div>`).join('')}
        </div>` : '';

      document.getElementById('pDrawerBody').innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
          <div style="width:5px;height:50px;border-radius:3px;background:${sc};box-shadow:0 0 14px ${sc}88;flex-shrink:0"></div>
          <div style="flex:1;min-width:0">
            <div class="d-name">${p.name}</div>
            <div class="d-meta">${p.code?'Код: '+p.code+' · ':''}${p.cat||''}${p.sup?' · '+p.sup:''}${p.size?' · '+p.size:''}${p.color?' · '+p.color:''}</div>
          </div>
        </div>
        <div class="sg2">
          <div class="si"><div class="sil">Наличност</div><div class="siv" style="color:${sc}">${p.qty} ${p.unit}</div></div>
          <div class="si"><div class="sil">Минимум</div><div class="siv">${p.min||'—'}</div></div>
          <div class="si"><div class="sil">Продажна</div><div class="siv">€${parseFloat(p.price).toFixed(2)}</div></div>
          ${canSeeCost&&p.cost>0?`<div class="si"><div class="sil">Марж</div><div class="siv" style="color:#a5b4fc">${margin}%</div></div>`:`<div class="si"><div class="sil">Марж</div><div class="siv" style="color:#a5b4fc">${margin}%</div></div>`}
        </div>
        ${storeHtml}
        ${p.loc?`<div style="font-size:12px;color:#6b7280;margin-bottom:14px">📍 ${p.loc}</div>`:''}
        ${p.variants>0?`<div style="font-size:12px;color:#8b5cf6;margin-bottom:14px">🎨 ${p.variants} варианта</div>`:''}
        <div class="dab-grid">
          ${canAdd?`<button class="dab dab-p" onclick="editProduct(${p.id})">✏️ Редактирай</button>`:'<div></div>'}
          <button class="dab dab-s" onclick="openAI(${JSON.stringify(p).replace(/'/g,"\\'")})">✦ AI съвет</button>
        </div>
        <div class="dab-grid">
          <a href="sale.php?product=${p.id}" class="dab dab-s">🛒 Продажба</a>
          <a href="product-detail.php?id=${p.id}" class="dab dab-s">🔍 Детайли</a>
        </div>
      `;
    }).catch(()=>{
      document.getElementById('pDrawerBody').innerHTML = `<div class="d-name">${p.name}</div><div class="d-meta">${p.tag}</div>`;
    });

  document.getElementById('ovl').classList.add('open');
  document.getElementById('pDrawer').classList.add('open');
}

function openAI(p) {
  document.getElementById('pDrawer').classList.remove('open');
  document.getElementById('ovl').classList.add('open');
  let rec='';
  if(p.qty==0) rec=`<strong>${p.name}</strong> е изчерпан. Зареди незабавно — всеки ден без наличност е пропуснат приход.`;
  else if(p.min>0&&p.qty<=p.min) rec=`Наличността е под минимума (${p.qty} от ${p.min} ${p.unit}). Презареди скоро.`;
  else if(p.cost>0){const m=((p.price-p.cost)/p.price*100).toFixed(1);if(m<20)rec=`Маржът е само ${m}%. Провери доставната цена или вдигни продажната.`;else rec=`Всичко е наред. Наличността е добра, маржът е ${m}%.`;}
  else rec=`${p.name} е в наличност. Добави доставна цена за по-точен анализ.`;
  document.getElementById('aiBody').innerHTML=`
    <div style="font-size:12px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">✦ AI Анализ</div>
    <div style="font-size:20px;font-weight:800;color:#f1f5f9;margin-bottom:18px">${p.name}</div>
    <div class="ai-rec"><div class="ai-rl">Препоръка</div><div class="ai-rt">${rec}</div></div>
    <div class="sg2">
      <div class="si"><div class="sil">Статус</div><div style="font-size:13px;font-weight:700;margin-top:4px">${p.tag}</div></div>
      <div class="si"><div class="sil">Наличност</div><div class="siv">${p.qty} ${p.unit}</div></div>
    </div>
    <a href="chat.php?q=${encodeURIComponent('Анализирай '+p.name)}" class="dab dab-p" style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;margin-top:4px">Попитай AI асистента →</a>
  `;
  document.getElementById('aiDrawer').classList.add('open');
}

function openFilterDrawer() {
  closeAll();
  document.getElementById('ovl').classList.add('open');
  document.getElementById('filterDrawer').classList.add('open');
}

function closeAll() {
  ['pDrawer','aiDrawer','filterDrawer'].forEach(id=>document.getElementById(id).classList.remove('open'));
  document.getElementById('ovl').classList.remove('open');
  document.getElementById('plist').classList.remove('blurred');
  document.querySelectorAll('.pcard.focused').forEach(c=>c.classList.remove('focused'));
}

['pDrawer','aiDrawer','filterDrawer'].forEach(id=>{
  const el=document.getElementById(id);
  el.addEventListener('touchstart',e=>ts=e.touches[0].clientY);
  el.addEventListener('touchend',e=>{if(e.changedTouches[0].clientY-ts>70)closeAll();});
});

// MODAL
function openModal() {
  document.getElementById('modalTitle').textContent='Нов артикул';
  document.getElementById('editId').value='';
  document.getElementById('productForm').reset();
  selectedSizes={}; selectedColors=[];
  document.querySelectorAll('.size-chip').forEach(c=>c.classList.remove('sel'));
  document.querySelectorAll('.color-dot').forEach(c=>c.classList.remove('sel'));
  goStep(1);
  document.getElementById('modalOvl').classList.add('open');
  document.getElementById('addModal').classList.add('open');
}

function closeModal() {
  document.getElementById('modalOvl').classList.remove('open');
  document.getElementById('addModal')?.classList.remove('open');
}

function goStep(n) {
  for(let i=1;i<=3;i++){
    document.getElementById('step'+i).classList.toggle('active',i===n);
    const ind=document.getElementById('step-ind-'+i);
    ind.classList.remove('active','done');
    if(i<n) ind.classList.add('done');
    else if(i===n) ind.classList.add('active');
  }
  currentStep=n;
  document.getElementById('addModal').scrollTop=0;
}

// BATCH SIZES
function toggleSize(el, size) {
  el.classList.toggle('sel');
  if(el.classList.contains('sel')) selectedSizes[size]=null;
  else delete selectedSizes[size];
  updateHiddenFields();
}

function addCustomSize() {
  const val=document.getElementById('customSizeInput').value.trim();
  if(!val) return;
  const chip=document.createElement('div');
  chip.className='size-chip';
  chip.dataset.size=val;
  chip.innerHTML=val+'<input class="price-override" type="number" placeholder="€цена" step="0.01" onclick="event.stopPropagation()">';
  chip.onclick=function(){toggleSize(this,val)};
  document.getElementById('sizeGrid').appendChild(chip);
  document.getElementById('customSizeInput').value='';
}

function toggleColor(el, color) {
  el.classList.toggle('sel');
  if(el.classList.contains('sel')) selectedColors.push(color);
  else selectedColors=selectedColors.filter(c=>c!==color);
  updateHiddenFields();
}

function updateHiddenFields() {
  document.getElementById('selectedSizes').value=Object.keys(selectedSizes).join(',');
  document.getElementById('selectedColors').value=selectedColors.join(',');
  const prices={};
  document.querySelectorAll('.size-chip.sel').forEach(chip=>{
    const p=chip.querySelector('.price-override');
    if(p&&p.value) prices[chip.dataset.size]=p.value;
  });
  document.getElementById('sizePrices').value=JSON.stringify(prices);
}

// CAMERA
function openCamera() { barcodeTarget='search'; startCamera(); }
function openCameraForBarcode() { barcodeTarget='form'; startCamera(); }

async function startCamera() {
  try {
    cameraStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
    document.getElementById('cameraVideo').srcObject=cameraStream;
    document.getElementById('cameraWrap').classList.add('open');
    document.getElementById('cameraResult').classList.remove('show');
    // Simple barcode detection via BarcodeDetector if available
    if('BarcodeDetector' in window) {
      const bd=new BarcodeDetector();
      const scan=setInterval(async()=>{
        try{
          const codes=await bd.detect(document.getElementById('cameraVideo'));
          if(codes.length){
            clearInterval(scan);
            document.getElementById('scannedCode').textContent=codes[0].rawValue;
            document.getElementById('cameraResult').classList.add('show');
          }
        }catch(e){}
      },300);
    }
  } catch(e) { showToast('Камерата не е достъпна'); }
}

function useScannedCode() {
  const code=document.getElementById('scannedCode').textContent;
  if(barcodeTarget==='search') {
    document.getElementById('searchInput').value=code;
    document.getElementById('searchForm').submit();
  } else {
    document.getElementById('f_barcode').value=code;
  }
  closeCamera();
}

function closeCamera() {
  if(cameraStream) { cameraStream.getTracks().forEach(t=>t.stop()); cameraStream=null; }
  document.getElementById('cameraWrap').classList.remove('open');
}

// VOICE SEARCH
function startVoiceSearch() {
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){showToast('Гласовото търсене не се поддържа');return;}
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  const r=new SR(); r.lang='bg-BG'; r.start();
  const btn=document.getElementById('voiceSearchBtn');
  btn.style.color='#ef4444';
  r.onresult=e=>{
    document.getElementById('searchInput').value=e.results[0][0].transcript;
    document.getElementById('searchForm').submit();
  };
  r.onend=()=>{ btn.style.color=''; };
}

// VOICE DICTATE for form
let voiceRecognition=null;
function toggleVoice() {
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){showToast('Не се поддържа на този браузър');return;}
  const btn=document.getElementById('voiceBtn');
  if(voiceRecognition){voiceRecognition.stop();voiceRecognition=null;btn.classList.remove('recording');return;}
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  voiceRecognition=new SR(); voiceRecognition.lang='bg-BG'; voiceRecognition.continuous=true; voiceRecognition.interimResults=false;
  btn.classList.add('recording');
  voiceRecognition.onresult=e=>{
    const text=e.results[e.results.length-1][0].transcript;
    fillFormFromVoice(text);
  };
  voiceRecognition.onend=()=>{ btn.classList.remove('recording'); voiceRecognition=null; };
  voiceRecognition.start();
}

function fillFormFromVoice(text) {
  showToast('Обработвам...');
  fetch('chat-send.php', {method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({message:`Извлечи данни за артикул от: "${text}". Върни JSON с полета: name, retail_price, cost_price, barcode, size, color, location. Само JSON без обяснения.`})
  }).then(r=>r.json()).then(d=>{
    try{
      const text=d.response||d.message||'';
      const match=text.match(/\{.*\}/s);
      if(match){
        const obj=JSON.parse(match[0]);
        if(obj.name) document.getElementById('f_name').value=obj.name;
        if(obj.retail_price) document.getElementById('f_price').value=obj.retail_price;
        if(obj.cost_price) document.getElementById('f_cost').value=obj.cost_price;
        if(obj.barcode) document.getElementById('f_barcode').value=obj.barcode;
        if(obj.color) document.getElementById('f_color').value=obj.color;
        if(obj.location) document.getElementById('f_loc').value=obj.location;
        showToast('AI попълни полетата ✓');
      }
    }catch(e){showToast('Не успях да разбера');}
  }).catch(()=>showToast('Грешка при AI'));
}

function generateAIDescription() {
  const name=document.getElementById('f_name').value;
  if(!name){showToast('Първо въведи наименование');return;}
  showToast('AI генерира...');
  fetch('chat-send.php', {method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({message:`Напиши кратко търговско описание (2-3 изречения) за артикул: "${name}". Само описанието, без въвеждане.`})
  }).then(r=>r.json()).then(d=>{
    const txt=d.response||d.message||'';
    if(txt) { document.getElementById('f_desc').value=txt; showToast('Описанието е генерирано ✓'); }
  }).catch(()=>showToast('Грешка при AI'));
}

function previewPhoto(input) {
  if(input.files&&input.files[0]) {
    const reader=new FileReader();
    reader.onload=e=>{
      document.querySelector('.photo-upload').style.backgroundImage=`url(${e.target.result})`;
      document.querySelector('.photo-upload').style.backgroundSize='cover';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function editProduct(id) {
  closeAll();
  fetch(`product-save.php?get=${id}`).then(r=>r.json()).then(p=>{
    document.getElementById('modalTitle').textContent='Редактирай';
    document.getElementById('editId').value=p.id;
    document.getElementById('f_name').value=p.name||'';
    document.getElementById('f_barcode').value=p.barcode||'';
    document.getElementById('f_price').value=p.retail_price||'';
    document.getElementById('f_cost').value=p.cost_price||'';
    document.getElementById('f_wprice').value=p.wholesale_price||'';
    document.getElementById('f_cat').value=p.category_id||'';
    document.getElementById('f_sup').value=p.supplier_id||'';
    document.getElementById('f_unit').value=p.unit||'бр';
    document.getElementById('f_code').value=p.code||'';
    document.getElementById('f_loc').value=p.location||'';
    document.getElementById('f_color').value=p.color||'';
    document.getElementById('f_desc').value=p.description||'';
    goStep(1);
    document.getElementById('modalOvl').classList.add('open');
    document.getElementById('addModal').classList.add('open');
  });
}

function showToast(msg) {
  const t=document.getElementById('toast');
  t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2500);
}

document.getElementById('addModal')?.addEventListener('touchstart',e=>ts=e.touches[0].clientY);
document.getElementById('addModal')?.addEventListener('touchend',e=>{if(e.changedTouches[0].clientY-ts>120)closeModal();});

<?php if(isset($_GET['saved'])): ?>showToast('Артикулът е запазен ✓');<?php endif; ?>
<?php if(isset($_GET['error'])): ?>showToast('Грешка при запазване');<?php endif; ?>
</script>
</body>
</html>
