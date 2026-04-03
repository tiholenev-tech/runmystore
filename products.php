<?php
session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['role'] ?? 'owner';

$tenant = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch();
$currency = $tenant['currency'] ?? 'EUR';
$cs = $currency === 'EUR' ? '€' : $currency;

$filter_supplier = intval($_GET['supplier'] ?? 0);
$filter_category = intval($_GET['category'] ?? 0);
$filter_tab      = $_GET['tab'] ?? 'all';
$search          = trim($_GET['q'] ?? '');

// Suppliers
$suppliers = DB::run("
    SELECT s.id, s.name, COUNT(DISTINCT p.id) as cnt
    FROM suppliers s
    JOIN products p ON p.supplier_id = s.id AND p.tenant_id = ?
    WHERE s.tenant_id = ?
    GROUP BY s.id ORDER BY cnt DESC
", [$tenant_id, $tenant_id])->fetchAll();

// Categories
$cp = [$tenant_id];
$cq = "SELECT c.id, c.name, COUNT(p.id) as cnt FROM categories c
       JOIN products p ON p.category_id = c.id AND p.tenant_id = ?";
if ($filter_supplier) { $cq .= " AND p.supplier_id = ?"; $cp[] = $filter_supplier; }
$cq .= " WHERE c.tenant_id = ? GROUP BY c.id ORDER BY cnt DESC";
$cp[] = $tenant_id;
$categories = DB::run($cq, $cp)->fetchAll();

// Products — ALL (за броячите)
$w = ["p.tenant_id = ?", "p.parent_id IS NULL"];
$pp = [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id];
if ($filter_supplier) { $w[] = "p.supplier_id = ?"; $pp[] = $filter_supplier; }
if ($filter_category) { $w[] = "p.category_id = ?"; $pp[] = $filter_category; }
if ($search) { $w[] = "(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)"; $pp[] = "%$search%"; $pp[] = "%$search%"; $pp[] = "%$search%"; }

$wstr = implode(' AND ', $w);

$all_products = DB::run("
    SELECT p.*,
        c.name as cat_name, s.name as sup_name,
        COALESCE((SELECT SUM(i.quantity) FROM inventory i JOIN stores st ON st.id=i.store_id WHERE i.product_id=p.id AND st.tenant_id=?),0) as total_stock,
        (SELECT COUNT(*) FROM products ch WHERE ch.parent_id=p.id) as var_count,
        COALESCE((SELECT SUM(si.quantity) FROM sale_items si JOIN sales sl ON sl.id=si.sale_id WHERE si.product_id=p.id AND sl.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND sl.tenant_id=? AND sl.status='completed'),0) AS sold_30d,
        DATEDIFF(NOW(),COALESCE((SELECT MAX(sl.created_at) FROM sale_items si JOIN sales sl ON sl.id=si.sale_id WHERE si.product_id=p.id AND sl.tenant_id=? AND sl.status='completed'),p.created_at)) AS days_no_sale
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    LEFT JOIN suppliers s ON s.id=p.supplier_id
    WHERE $wstr ORDER BY p.name ASC LIMIT 200
", $pp)->fetchAll();

// Капитал
$capital = DB::run("SELECT COALESCE(SUM(i.quantity*p.cost_price),0) FROM inventory i JOIN products p ON p.id=i.product_id JOIN stores st ON st.id=i.store_id WHERE st.tenant_id=?", [$tenant_id])->fetchColumn();

// Броячи
$cnt_all    = count($all_products);
$cnt_hot    = count(array_filter($all_products, fn($p) => ($p['sold_30d'] ?? 0) > 10));
$cnt_zombie = count(array_filter($all_products, fn($p) => ($p['days_no_sale'] ?? 0) > 30 && ($p['total_stock'] ?? 0) > 0));
$cnt_low    = count(array_filter($all_products, fn($p) => ($p['total_stock'] ?? 0) > 0 && ($p['total_stock'] ?? 0) <= ($p['min_qty'] ?? 5)));
$cnt_out    = count(array_filter($all_products, fn($p) => ($p['total_stock'] ?? 0) == 0));

// Филтрация по таб
switch ($filter_tab) {
    case 'hot':    $products = array_values(array_filter($all_products, fn($p) => ($p['sold_30d'] ?? 0) > 10)); break;
    case 'zombie': $products = array_values(array_filter($all_products, fn($p) => ($p['days_no_sale'] ?? 0) > 30 && ($p['total_stock'] ?? 0) > 0)); break;
    case 'low':    $products = array_values(array_filter($all_products, fn($p) => ($p['total_stock'] ?? 0) > 0 && ($p['total_stock'] ?? 0) <= ($p['min_qty'] ?? 5))); break;
    case 'out':    $products = array_values(array_filter($all_products, fn($p) => ($p['total_stock'] ?? 0) == 0)); break;
    default:       $products = $all_products;
}

$vdata = [];
foreach ($products as $pr) {
    if ($pr['var_count'] > 0) {
        $vdata[$pr['id']] = DB::run("SELECT name FROM products WHERE parent_id=? LIMIT 6", [$pr['id']])->fetchAll(PDO::FETCH_COLUMN);
    }
}

function sc($qty, $min) {
    if ($qty == 0) return '#ef4444';
    if ($qty <= ($min ?? 5)) return '#f59e0b';
    return '#22c55e';
}
$sup_colors = ['#D4AF37','#6366f1','#22c55e','#f59e0b','#ec4899','#14b8a6','#f97316','#a855f7'];
$cat_colors = ['#D4AF37','#6366f1','#22c55e','#f59e0b','#ec4899','#14b8a6'];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Артикули — RunMyStore.ai</title>
<link href="./css/vendors/aos.css" rel="stylesheet">
<link href="./style.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
body{font-family:'Montserrat',sans-serif;background:#030712;color:#e2e8f0;min-height:100vh;overflow-x:hidden;}
/* BG */
.page-bg{pointer-events:none;position:fixed;inset:0;z-index:0;overflow:hidden;}
.page-bg img{position:absolute;}
/* HEADER */
.hdr{background:rgba(17,24,39,.9);padding:10px 14px 0;border-bottom:1px solid rgba(255,255,255,.06);position:sticky;top:0;z-index:100;backdrop-filter:blur(12px);}
.hdr-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.hdr-title{font-size:19px;font-weight:900;background:linear-gradient(to right,#e2e8f0,#a5b4fc,#f8fafc,#c7d2fe,#e2e8f0);background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;animation:gradient 6s linear infinite;}
@keyframes gradient{0%{background-position:0%}100%{background-position:200%}}
.cap-badge{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:20px;padding:4px 10px;font-size:11px;font-weight:700;color:#a5b4fc;}
.sup-lbl{font-size:10px;font-weight:700;color:#4b5563;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.sup-row{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;margin-bottom:10px;}
.sup-row::-webkit-scrollbar{display:none;}
.sup-chip{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;}
.sup-av{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:#fff;border:2px solid transparent;transition:border-color .2s;}
.sup-av.active{border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.3);}
.sup-nm{font-size:9px;font-weight:700;color:#6b7280;text-align:center;max-width:46px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sup-cnt{font-size:8px;color:#4b5563;}
.sup-all{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.04);border:1.5px dashed rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;color:#4b5563;text-align:center;line-height:1.3;cursor:pointer;}
.sup-all.active{border-color:#6366f1;color:#a5b4fc;}
/* TABS */
.tabs{display:flex;gap:6px;padding:8px 14px;overflow-x:auto;scrollbar-width:none;background:transparent;}
.tabs::-webkit-scrollbar{display:none;}
.tab{flex-shrink:0;padding:5px 12px;border-radius:20px;font-size:11px;font-weight:700;border:1px solid rgba(255,255,255,.1);color:#4b5563;background:transparent;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.tab.active{background:linear-gradient(to top,#4f46e5,#6366f1);border-color:transparent;color:#fff;}
.tab.hot{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.2);color:#f87171;}
.tab.hot.active{background:linear-gradient(135deg,#ef4444,#dc2626);border-color:transparent;color:#fff;}
.tab.zombie{background:rgba(107,114,128,.08);border-color:rgba(107,114,128,.2);color:#9ca3af;}
.tab.zombie.active{background:rgba(107,114,128,.25);border-color:rgba(107,114,128,.3);color:#d1d5db;}
.tab.low{background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.2);color:#fbbf24;}
.tab.low.active{background:linear-gradient(135deg,#f59e0b,#d97706);border-color:transparent;color:#fff;}
.tcnt{background:rgba(255,255,255,.1);border-radius:8px;padding:1px 5px;font-size:9px;}
.tab.active .tcnt{background:rgba(255,255,255,.2);}
/* CATS */
.cat-row{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding:0 14px 8px;}
.cat-row::-webkit-scrollbar{display:none;}
.cat-chip{flex-shrink:0;padding:4px 11px;border-radius:10px;font-size:11px;font-weight:700;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:#9ca3af;display:flex;align-items:center;gap:5px;cursor:pointer;text-decoration:none;}
.cat-chip.active{background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#a5b4fc;}
.cdot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
/* PRODUCT LIST */
.plist{padding:6px 12px 185px;display:flex;flex-direction:column;gap:7px;position:relative;z-index:1;}
.pcard{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;overflow:hidden;display:flex;cursor:pointer;transition:transform .1s;}
.pcard:active{transform:scale(.98);}
.pst{width:4px;flex-shrink:0;}
.pb{flex:1;padding:9px 11px;min-width:0;}
.pt{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px;}
.pn{font-size:12px;font-weight:700;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}
.psk{font-size:9px;color:#4b5563;}
.pqb{text-align:right;flex-shrink:0;margin-left:8px;}
.pq{font-size:20px;font-weight:900;line-height:1;}
.pu{font-size:9px;color:#4b5563;font-weight:600;}
.ptags{display:flex;gap:4px;flex-wrap:wrap;align-items:center;}
.tg{font-size:9px;font-weight:700;border-radius:5px;padding:2px 6px;border:1px solid transparent;}
.tp{color:#a5b4fc;background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.2);}
.thot{color:#fbbf24;background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.2);}
.tzom{color:#6b7280;background:rgba(107,114,128,.08);border-color:rgba(107,114,128,.15);}
.tlow{color:#fbbf24;background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.15);}
.tout{color:#f87171;background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.15);}
.tok{color:#4ade80;background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.15);}
.tai{color:#a5b4fc;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:5px;padding:2px 6px;font-size:9px;font-weight:700;cursor:pointer;}
.vrow{display:flex;gap:4px;margin-top:5px;flex-wrap:wrap;}
.vchip{padding:2px 7px;border-radius:6px;font-size:9px;font-weight:700;border:1px solid rgba(255,255,255,.08);color:#6b7280;background:rgba(255,255,255,.03);}
.vmore{padding:2px 7px;border-radius:6px;font-size:9px;font-weight:700;border:1px dashed rgba(255,255,255,.08);color:#4b5563;}
/* EMPTY */
.empty{text-align:center;padding:60px 20px;}
.ei{font-size:48px;margin-bottom:12px;}
.et{font-size:16px;font-weight:700;color:#6b7280;margin-bottom:6px;}
.es{font-size:13px;color:#4b5563;}
/* BOTTOM */
.bact{position:fixed;bottom:64px;left:0;right:0;background:rgba(3,7,18,.95);border-top:1px solid rgba(255,255,255,.06);z-index:90;backdrop-filter:blur(8px);}
.tools{display:flex;gap:6px;padding:8px 12px 6px;}
.tbtn{flex:1;padding:7px 4px;border-radius:10px;font-size:10px;font-weight:700;border:1px solid rgba(255,255,255,.08);color:#6b7280;background:rgba(255,255,255,.03);text-align:center;display:flex;align-items:center;justify-content:center;gap:3px;cursor:pointer;}
.tbtn.ind{background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.2);color:#a5b4fc;}
.mbtns{display:flex;padding:6px 16px 14px;align-items:center;justify-content:space-between;gap:10px;}
.bwrap{display:flex;flex-direction:column;align-items:center;gap:5px;flex:1;}
.blbl{font-size:9px;font-weight:700;color:#a5b4fc;white-space:nowrap;}
.bcir{width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;}
.bcam{background:rgba(255,255,255,.04);border:2px solid rgba(99,102,241,.3);box-shadow:0 3px 12px rgba(99,102,241,.1);}
.bscn{background:rgba(255,255,255,.04);border:2px solid rgba(99,102,241,.3);box-shadow:0 3px 12px rgba(99,102,241,.1);}
.badd{background:linear-gradient(to top,#4f46e5,#6366f1);box-shadow:0 3px 12px rgba(99,102,241,.3);}
.bdiv{width:1px;height:50px;background:rgba(255,255,255,.06);flex:0;}
/* AI btn */
.airel{position:relative;display:flex;align-items:center;justify-content:center;width:58px;height:58px;}
.airng{position:absolute;border-radius:50%;border:1.5px solid rgba(99,102,241,.3);animation:wo 2s ease-out infinite;}
.airng-1{width:58px;height:58px;animation-delay:0s;}
.airng-2{width:74px;height:74px;animation-delay:.55s;}
.airng-3{width:90px;height:90px;animation-delay:1.1s;}
.aicir{width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);box-shadow:0 5px 18px rgba(99,102,241,.4);display:flex;align-items:center;justify-content:center;position:relative;z-index:1;cursor:pointer;}
.aibars{display:flex;gap:3px;align-items:center;height:22px;}
.aibar{width:3px;border-radius:2px;background:#fff;animation:bd 1s ease-in-out infinite;}
.aibar:nth-child(1){height:7px;}.aibar:nth-child(2){height:14px;animation-delay:.15s;}.aibar:nth-child(3){height:22px;animation-delay:.3s;}.aibar:nth-child(4){height:14px;animation-delay:.45s;}.aibar:nth-child(5){height:7px;animation-delay:.6s;}
/* NAV */
.bnav{position:fixed;bottom:0;left:0;right:0;height:64px;background:rgba(3,7,18,.97);border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;z-index:100;}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;text-decoration:none;}
.nilbl{font-size:10px;font-weight:600;color:#4b5563;}
.niico{width:20px;height:20px;color:#4b5563;}
.ni.active .nilbl,.ni.active .niico{color:#818cf8;}
.nichat{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;}
.ncb{display:flex;gap:2px;align-items:center;height:10px;}
.ncb div{width:2px;border-radius:1px;background:#fff;animation:bd 1s ease-in-out infinite;}
.ncb div:nth-child(1){height:4px;}.ncb div:nth-child(2){height:7px;animation-delay:.15s;}.ncb div:nth-child(3){height:10px;animation-delay:.3s;}.ncb div:nth-child(4){height:6px;animation-delay:.45s;}
/* DRAWER */
.dovl{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:200;display:none;}
.dovl.open{display:block;}
.drw{position:fixed;bottom:0;left:0;right:0;background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:24px 24px 0 0;z-index:201;transform:translateY(100%);transition:transform .3s ease;max-height:85vh;overflow-y:auto;}
.drw.open{transform:translateY(0);}
.dhdl{width:40px;height:4px;background:rgba(255,255,255,.1);border-radius:2px;margin:14px auto 0;}
.dbody{padding:16px 20px 32px;}
.dsr{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.dstripe{width:5px;height:54px;border-radius:3px;flex-shrink:0;}
.dnm{font-size:19px;font-weight:900;color:#e2e8f0;margin-bottom:2px;}
.dmeta{font-size:11px;color:#4b5563;}
.sgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;}
.si{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px 12px;}
.slbl{font-size:10px;color:#4b5563;font-weight:600;margin-bottom:2px;}
.sval{font-size:17px;font-weight:900;color:#e2e8f0;}
.dacts{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;}
.dab{padding:12px 6px;border:none;border-radius:12px;font-size:11px;font-weight:700;display:flex;flex-direction:column;align-items:center;gap:5px;cursor:pointer;}
.dico{font-size:18px;}.dlbl{font-size:10px;font-weight:700;}
.dedit{background:linear-gradient(to top,#4f46e5,#6366f1);color:#fff;}
.dcopy{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25)!important;color:#a5b4fc;}
.ddel{background:rgba(239,68,68,.06);border:1.5px solid rgba(239,68,68,.2)!important;color:#f87171;}
.daibtn{width:100%;padding:12px;border:1px solid rgba(99,102,241,.25);border-radius:12px;background:rgba(99,102,241,.1);color:#a5b4fc;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;}
.daires{margin-top:12px;padding:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;font-size:13px;color:#e2e8f0;line-height:1.5;display:none;}
/* CONFIRM */
.covl{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:300;display:none;align-items:flex-end;justify-content:center;}
.covl.open{display:flex;}
.cbox{background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:24px 24px 0 0;width:100%;padding:24px 20px 40px;max-width:480px;}
.cico{width:52px;height:52px;border-radius:50%;background:rgba(239,68,68,.1);border:1.5px solid rgba(239,68,68,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;}
.ctit{font-size:18px;font-weight:900;color:#e2e8f0;text-align:center;margin-bottom:6px;}
.csub{font-size:13px;color:#6b7280;text-align:center;line-height:1.5;margin-bottom:22px;}
.cbld{font-weight:700;color:#e2e8f0;}
.cdelbtn{width:100%;padding:15px;border:none;border-radius:14px;background:linear-gradient(to bottom,#ef4444,#dc2626);color:#fff;font-size:15px;font-weight:800;margin-bottom:10px;cursor:pointer;}
.ccnl{width:100%;padding:13px;border:1.5px solid rgba(255,255,255,.1);border-radius:14px;background:transparent;color:#6b7280;font-size:14px;font-weight:700;cursor:pointer;}
/* AI MODAL */
.aimod{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:300;display:none;align-items:flex-end;justify-content:center;}
.aimod.open{display:flex;}
.aibox{background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:24px 24px 0 0;width:100%;padding:20px;max-width:480px;max-height:80vh;overflow-y:auto;}
.aihdl{width:40px;height:4px;background:rgba(255,255,255,.1);border-radius:2px;margin:0 auto 16px;}
.aiqcmds{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
.aiqc{padding:5px 11px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:20px;font-size:11px;font-weight:700;color:#a5b4fc;cursor:pointer;}
.aiwrap{display:flex;gap:8px;margin-bottom:12px;}
.aiinp{flex:1;padding:12px 14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:12px;font-size:14px;font-family:'Montserrat',sans-serif;outline:none;color:#e2e8f0;}
.aiinp:focus{border-color:rgba(99,102,241,.5);}
.aisndbtn{width:46px;height:46px;border-radius:12px;background:linear-gradient(to top,#4f46e5,#6366f1);border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;}
.airesp{padding:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;font-size:13px;color:#e2e8f0;line-height:1.6;min-height:60px;}
/* CAMERA */
.cammod{position:fixed;inset:0;background:#000;z-index:400;display:none;flex-direction:column;}
.cammod.open{display:flex;}
.camhdr{padding:16px;display:flex;align-items:center;justify-content:space-between;}
.camttl{color:#fff;font-size:16px;font-weight:700;}
.camcls{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
#camvid{width:100%;flex:1;object-fit:cover;}
.camctrls{padding:16px;display:flex;justify-content:center;}
.camsnap{width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.3);font-size:24px;cursor:pointer;}
.camres{padding:16px;background:rgba(255,255,255,.05);margin:0 16px;border-radius:12px;display:none;}
.camres p{color:#fff;font-size:13px;margin-bottom:8px;}
.camusebtn{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(to top,#4f46e5,#6366f1);color:#fff;font-weight:700;cursor:pointer;}
/* ADD MODAL */
.addmod{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:300;display:none;align-items:flex-end;justify-content:center;}
.addmod.open{display:flex;}
.addbox{background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:24px 24px 0 0;width:100%;padding:20px;max-width:480px;max-height:90vh;overflow-y:auto;}
.addhdl{width:40px;height:4px;background:rgba(255,255,255,.1);border-radius:2px;margin:0 auto 16px;}
.frow{margin-bottom:12px;}
.flbl{font-size:11px;font-weight:700;color:#6b7280;margin-bottom:4px;display:block;}
.finp{width:100%;padding:12px 14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:12px;font-size:14px;font-family:'Montserrat',sans-serif;outline:none;color:#e2e8f0;}
.finp:focus{border-color:rgba(99,102,241,.5);}
.frow2{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;}
.svbtn{width:100%;padding:15px;border:none;border-radius:14px;background:linear-gradient(to top,#4f46e5,#6366f1);color:#fff;font-size:15px;font-weight:800;cursor:pointer;}
/* TOAST */
.toast{position:fixed;bottom:140px;left:50%;transform:translateX(-50%);background:rgba(3,7,18,.95);border:1px solid rgba(255,255,255,.1);color:#e2e8f0;padding:10px 20px;border-radius:20px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;backdrop-filter:blur(8px);}
.toast.show{opacity:1;}
/* ANIMS */
@keyframes wo{0%{transform:scale(1);opacity:.55;}100%{transform:scale(1.65);opacity:0;}}
@keyframes bd{0%,100%{transform:scaleY(1);}50%{transform:scaleY(.25);}}
</style>
</head>
<body>

<!-- BG -->
<div class="page-bg" aria-hidden="true">
  <img src="./images/page-illustration.svg" width="846" height="594" alt="" style="left:50%;top:0;transform:translateX(-25%);opacity:.6;">
  <img src="./images/blurred-shape-gray.svg" width="760" height="668" alt="" style="left:0;top:300px;opacity:.2;">
  <img src="./images/blurred-shape.svg" width="760" height="668" alt="" style="right:0;top:350px;opacity:.15;">
</div>

<div class="toast" id="toast"></div>

<!-- HEADER -->
<div class="hdr">
  <div class="hdr-top">
    <span class="hdr-title">Артикули</span>
    <span class="cap-badge">💰 <?= $cs . number_format($capital, 0, ',', '.') ?> в склад</span>
  </div>
  <div class="sup-lbl">Доставчици</div>
  <div class="sup-row">
    <div class="sup-chip" onclick="goSupplier(0)">
      <div class="sup-all <?= !$filter_supplier ? 'active' : '' ?>">Всички</div>
      <div class="sup-nm">Всички</div>
    </div>
    <?php foreach($suppliers as $i => $s):
      $ini = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', $s['name']), 0, 2))));
      $clr = $sup_colors[$i % count($sup_colors)];
    ?>
    <div class="sup-chip" onclick="goSupplier(<?= $s['id'] ?>)">
      <div class="sup-av <?= $filter_supplier == $s['id'] ? 'active' : '' ?>" style="background:<?= $clr ?>"><?= htmlspecialchars($ini) ?></div>
      <div class="sup-nm"><?= htmlspecialchars($s['name']) ?></div>
      <div class="sup-cnt"><?= $s['cnt'] ?> бр.</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- TABS -->
<div class="tabs">
  <?php
  $tabs = [
    ['all',    'Всички',        $cnt_all,    ''],
    ['hot',    '🔥 Хит',        $cnt_hot,    'hot'],
    ['zombie', '❄️ Застояли',   $cnt_zombie, 'zombie'],
    ['low',    '⚠️ Ниска нал.', $cnt_low,    'low'],
    ['out',    '🔴 Изчерпани',  $cnt_out,    ''],
  ];
  foreach ($tabs as [$k, $lbl, $cnt, $cls]):
    $p2 = array_merge($_GET, ['tab' => $k]);
    $active = $filter_tab === $k ? 'active' : '';
  ?>
  <a href="?<?= http_build_query($p2) ?>" class="tab <?= $cls ?> <?= $active ?>"><?= $lbl ?> <span class="tcnt"><?= $cnt ?></span></a>
  <?php endforeach; ?>
</div>

<!-- CATEGORIES -->
<?php if ($categories): ?>
<div class="cat-row">
  <?php foreach ($categories as $ci => $cat):
    $p2 = array_merge($_GET, ['category' => $cat['id']]);
    $active = $filter_category == $cat['id'] ? 'active' : '';
  ?>
  <a href="?<?= http_build_query($p2) ?>" class="cat-chip <?= $active ?>">
    <div class="cdot" style="background:<?= $cat_colors[$ci % count($cat_colors)] ?>"></div>
    <?= htmlspecialchars($cat['name']) ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- PRODUCTS -->
<div class="plist">
<?php if (empty($products)): ?>
  <div class="empty"><div class="ei">📦</div><div class="et">Няма артикули</div><div class="es">Добави с бутона отдолу или чрез AI</div></div>
<?php else: foreach ($products as $p):
  $qty  = $p['total_stock'];
  $min  = $p['min_qty'] ?? 5;
  $clr  = sc($qty, $min);
  $is_hot    = ($p['sold_30d'] ?? 0) > 10;
  $is_zombie = ($p['days_no_sale'] ?? 0) > 30 && $qty > 0;
  $is_low    = $qty > 0 && $qty <= $min;
  $is_out    = $qty == 0;
  $mrg = ($p['retail_price'] > 0 && $p['cost_price'] > 0)
    ? round((($p['retail_price'] - $p['cost_price']) / $p['retail_price']) * 100)
    : null;
  $vars = $vdata[$p['id']] ?? [];
  $ddata = htmlspecialchars(json_encode([
    'id'          => $p['id'],
    'name'        => $p['name'],
    'sku'         => $p['code'] ?? '',
    'supplier'    => $p['sup_name'] ?? '',
    'category'    => $p['cat_name'] ?? '',
    'qty'         => (int)$qty,
    'min'         => (int)$min,
    'sell_price'  => $p['retail_price'],
    'cost_price'  => $p['cost_price'],
    'margin'      => $mrg,
    'color'       => $clr,
    'days'        => $p['days_no_sale'],
    'sold30'      => $p['sold_30d'],
  ]), ENT_QUOTES);
?>
  <div class="pcard" onclick="openDrw(<?= $ddata ?>)">
    <div class="pst" style="background:<?= $clr ?>"></div>
    <div class="pb">
      <div class="pt">
        <div>
          <div class="pn"><?= htmlspecialchars($p['name']) ?></div>
          <div class="psk"><?= htmlspecialchars(implode(' · ', array_filter([$p['code'] ?? '', $p['cat_name'] ?? '']))) ?></div>
        </div>
        <div class="pqb"><div class="pq" style="color:<?= $clr ?>"><?= $qty ?></div><div class="pu">бр</div></div>
      </div>
      <div class="ptags">
        <?php if ($p['retail_price'] > 0): ?>
        <span class="tg tp"><?= $cs . number_format($p['retail_price'], 2, ',', '.') ?></span>
        <?php endif; ?>
        <?php if ($is_hot): ?>
        <span class="tg thot">🔥 Хит</span>
        <?php endif; ?>
        <?php if ($is_zombie): ?>
        <span class="tg tzom">❄️ <?= $p['days_no_sale'] ?>д</span>
        <?php endif; ?>
        <?php if ($is_out): ?>
        <span class="tg tout">🔴 Изчерпан</span>
        <?php elseif ($is_low): ?>
        <span class="tg tlow">⚠️ Ниска нал.</span>
        <?php else: ?>
        <span class="tg tok">В наличност</span>
        <?php endif; ?>
        <span class="tai" onclick="event.stopPropagation();aiCardAdvice(<?= $p['id'] ?>,<?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES) ?>)">✦ AI</span>
      </div>
      <?php if ($vars): ?>
      <div class="vrow">
        <?php $sh = 0; foreach ($vars as $v): if ($sh >= 4) break; ?>
        <span class="vchip"><?= htmlspecialchars($v) ?></span>
        <?php $sh++; endforeach; ?>
        <?php if (count($vars) > 4): ?>
        <span class="vmore">+<?= count($vars) - 4 ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>

<!-- BOTTOM ACTIONS -->
<div class="bact">
  <div class="tools">
    <div class="tbtn ind" onclick="document.getElementById('csvIn').click()">📥 Импорт</div>
    <div class="tbtn ind" onclick="window.location='product-save.php?export=1'">📤 Експорт</div>
    <div class="tbtn" onclick="window.location='product-save.php?print=1'">🖨️ Принтирай</div>
  </div>
  <div class="mbtns">
    <!-- КАМЕРА -->
    <div class="bwrap">
      <div class="bcir bcam" onclick="openCam('photo')">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
          <circle cx="12" cy="13" r="3"/>
        </svg>
      </div>
      <span class="blbl">Камера</span>
    </div>
    <div class="bdiv"></div>
    <!-- AI -->
    <div class="bwrap">
      <div class="airel" onclick="document.getElementById('aimod').classList.add('open')">
        <div class="airng airng-1"></div><div class="airng airng-2"></div><div class="airng airng-3"></div>
        <div class="aicir"><div class="aibars"><div class="aibar"></div><div class="aibar"></div><div class="aibar"></div><div class="aibar"></div><div class="aibar"></div></div></div>
      </div>
      <span class="blbl">✦ AI Асистент</span>
    </div>
    <div class="bdiv"></div>
    <!-- СКЕНЕР -->
    <div class="bwrap">
      <div class="bcir bscn" onclick="openCam('scan')">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#a5b4fc" stroke-width="2">
          <rect x="2" y="2" width="5" height="5" rx="1"/>
          <rect x="17" y="2" width="5" height="5" rx="1"/>
          <rect x="2" y="17" width="5" height="5" rx="1"/>
          <path d="M17 17h5v5"/>
          <path d="M7 12H2m5 0v5M12 2v5m0 0h5"/>
        </svg>
      </div>
      <span class="blbl">Скенер</span>
    </div>
    <div class="bdiv"></div>
    <!-- ДОБАВИ -->
    <div class="bwrap">
      <div class="bcir badd" onclick="document.getElementById('addmod').classList.add('open')">
        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
      </div>
      <span class="blbl">Добави</span>
    </div>
  </div>
</div>

<!-- NAV -->
<nav class="bnav">
  <a href="chat.php" class="ni">
    <div class="nichat"><div class="ncb"><div></div><div></div><div></div><div></div></div></div>
    <span class="nilbl">✦ AI</span>
  </a>
  <a href="warehouse.php" class="ni">
    <svg class="niico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
    </svg>
    <span class="nilbl">Склад</span>
  </a>
  <a href="products.php" class="ni active">
    <svg class="niico" fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8">
      <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
    </svg>
    <span class="nilbl" style="color:#818cf8;">Артикули</span>
  </a>
  <a href="stats.php" class="ni">
    <svg class="niico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <span class="nilbl">Статистики</span>
  </a>
</nav>

<!-- DRAWER -->
<div class="dovl" id="dovl" onclick="closeDrw()"></div>
<div class="drw" id="drw">
  <div class="dhdl"></div>
  <div class="dbody">
    <div class="dsr">
      <div class="dstripe" id="dstripe"></div>
      <div><div class="dnm" id="dnm"></div><div class="dmeta" id="dmeta"></div></div>
    </div>
    <div class="sgrid">
      <div class="si"><div class="slbl">Наличност</div><div class="sval" id="dqty"></div></div>
      <div class="si"><div class="slbl">Минимум</div><div class="sval" id="dmin"></div></div>
      <div class="si"><div class="slbl">Продажна</div><div class="sval" id="dprice"></div></div>
      <div class="si"><div class="slbl">Марж</div><div class="sval" id="dmargin" style="color:#a5b4fc;"></div></div>
    </div>
    <div class="dacts">
      <button class="dab dedit" id="deditbtn"><span class="dico">✏️</span><span class="dlbl">Редактирай</span></button>
      <button class="dab dcopy" onclick="doCopy()" style="border:1px solid rgba(99,102,241,.25);"><span class="dico">📋</span><span class="dlbl">Копирай</span></button>
      <button class="dab ddel" onclick="showConfirm()" style="border:1.5px solid rgba(239,68,68,.2);"><span class="dico">🗑️</span><span class="dlbl">Изтрий</span></button>
    </div>
    <button class="daibtn" id="daibtn" onclick="loadDrwAI()">✦ AI съвет за този артикул</button>
    <div class="daires" id="daires"></div>
  </div>
</div>

<!-- CONFIRM DELETE -->
<div class="covl" id="covl">
  <div class="cbox">
    <div class="cico">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#f87171" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
    </div>
    <div class="ctit">Сигурен ли си?</div>
    <div class="csub">Ще изтриеш <span class="cbld" id="cpname"></span> и всичките му варианти.<br>Това действие е необратимо.</div>
    <button class="cdelbtn" onclick="doDelete()">🗑️ Да, изтрий</button>
    <button class="ccnl" onclick="document.getElementById('covl').classList.remove('open')">Отказ</button>
  </div>
</div>

<!-- AI MODAL -->
<div class="aimod" id="aimod">
  <div class="aibox">
    <div class="aihdl"></div>
    <div style="font-size:16px;font-weight:900;color:#e2e8f0;margin-bottom:14px;">✦ AI Асистент</div>
    <div class="aiqcmds">
      <div class="aiqc" onclick="aiSend('Застояла стока')">❄️ Застояла стока</div>
      <div class="aiqc" onclick="aiSend('Хит движения')">🔥 Хит</div>
      <div class="aiqc" onclick="aiSend('Ниска наличност')">⚠️ Ниска нал.</div>
      <div class="aiqc" onclick="aiSend('Намери артикул')">🔍 Намери</div>
      <div class="aiqc" onclick="aiSend('Добави нов артикул')">➕ Добави</div>
    </div>
    <div class="aiwrap">
      <input type="text" class="aiinp" id="aiinp" placeholder="Питай AI или търси артикул..." onkeydown="if(event.key==='Enter')aiSend()">
      <button class="aisndbtn" onclick="aiSend()">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
        </svg>
      </button>
    </div>
    <div class="airesp" id="airesp">Питай ме за артикули, наличности, движения...</div>
    <button onclick="document.getElementById('aimod').classList.remove('open')" style="width:100%;padding:12px;border:1.5px solid rgba(255,255,255,.1);border-radius:12px;background:transparent;font-size:14px;font-weight:700;color:#6b7280;margin-top:12px;cursor:pointer;">Затвори</button>
  </div>
</div>

<!-- CAMERA -->
<div class="cammod" id="cammod">
  <div class="camhdr">
    <span class="camttl" id="camttl">📷 Камера</span>
    <button class="camcls" onclick="closeCam()">✕</button>
  </div>
  <video id="camvid" autoplay playsinline></video>
  <canvas id="camcvs" style="display:none;"></canvas>
  <div class="camres" id="camres">
    <p id="camtxt"></p>
    <button class="camusebtn" onclick="useCamResult()">Използвай резултата</button>
  </div>
  <div class="camctrls">
    <button class="camsnap" id="camsnap" onclick="snapCam()">📸</button>
  </div>
</div>

<!-- ADD MODAL -->
<div class="addmod" id="addmod">
  <div class="addbox">
    <div class="addhdl"></div>
    <div style="font-size:17px;font-weight:900;color:#e2e8f0;margin-bottom:16px;">➕ Нов артикул</div>
    <div class="frow"><label class="flbl">Наименование *</label><input type="text" class="finp" id="aname" placeholder="Напр. Зимно яке HM Slim"></div>
    <div class="frow2">
      <div><label class="flbl">Продажна цена</label><input type="number" class="finp" id="aprice" placeholder="0.00" step="0.01"></div>
      <div><label class="flbl">Доставна цена</label><input type="number" class="finp" id="acost" placeholder="0.00" step="0.01"></div>
    </div>
    <div class="frow2">
      <div><label class="flbl">Баркод</label><input type="text" class="finp" id="abcode" placeholder="EAN13"></div>
      <div><label class="flbl">Начална нал.</label><input type="number" class="finp" id="aqty" placeholder="0"></div>
    </div>
    <div class="frow"><label class="flbl">Описание</label><input type="text" class="finp" id="adesc" placeholder="Кратко описание"></div>
    <button class="svbtn" onclick="saveAdd()">Запази артикула</button>
    <button onclick="document.getElementById('addmod').classList.remove('open')" style="width:100%;padding:12px;border:none;background:transparent;font-size:13px;color:#4b5563;margin-top:8px;cursor:pointer;">Отказ</button>
  </div>
</div>

<input type="file" id="csvIn" accept=".csv" style="display:none;" onchange="handleCSV(this)">

<script>
let cur = null, camStream = null, camMode = 'photo';

function goSupplier(id) {
  const p = new URLSearchParams(window.location.search);
  p.set('supplier', id); p.delete('category');
  location.href = 'products.php?' + p;
}

function openDrw(d) {
  cur = d;
  document.getElementById('dstripe').style.background = d.color;
  document.getElementById('dnm').textContent = d.name;
  document.getElementById('dmeta').textContent = [d.sku, d.category, d.supplier].filter(Boolean).join(' · ');
  const qel = document.getElementById('dqty');
  qel.textContent = d.qty + ' бр'; qel.style.color = d.color;
  document.getElementById('dmin').textContent = d.min;
  document.getElementById('dprice').textContent = d.sell_price > 0 ? '€' + parseFloat(d.sell_price).toFixed(2) : '—';
  document.getElementById('dmargin').textContent = d.margin !== null ? d.margin + '%' : '—';
  document.getElementById('deditbtn').onclick = () => { location.href = 'product-save.php?id=' + d.id; };
  document.getElementById('cpname').textContent = '"' + d.name + '"';
  document.getElementById('daires').style.display = 'none';
  document.getElementById('daibtn').textContent = '✦ AI съвет за този артикул';
  document.getElementById('daibtn').disabled = false;
  document.getElementById('dovl').classList.add('open');
  document.getElementById('drw').classList.add('open');
}

function closeDrw() {
  document.getElementById('dovl').classList.remove('open');
  document.getElementById('drw').classList.remove('open');
}

function doCopy() {
  if (!cur) return;
  fetch('product-save.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'copy', id:cur.id})})
    .then(r => r.json()).then(d => { closeDrw(); toast('📋 Артикулът е копиран'); setTimeout(() => location.reload(), 800); });
}

function showConfirm() { document.getElementById('covl').classList.add('open'); }

function doDelete() {
  if (!cur) return;
  fetch('product-save.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete', id:cur.id})})
    .then(r => r.json()).then(() => {
      document.getElementById('covl').classList.remove('open');
      closeDrw(); toast('🗑️ Изтрит');
      setTimeout(() => location.reload(), 800);
    });
}

function loadDrwAI() {
  if (!cur) return;
  const btn = document.getElementById('daibtn'), res = document.getElementById('daires');
  btn.textContent = '⏳ AI анализира...'; btn.disabled = true;
  res.style.display = 'block'; res.textContent = '';
  fetch('ai-helper.php', {method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'product_advice', product_id:cur.id, product_name:cur.name})})
    .then(r => r.json())
    .then(d => { res.textContent = d.result || d.error || 'Няма съвет'; btn.textContent = '✦ AI съвет'; btn.disabled = false; })
    .catch(() => { res.textContent = 'Грешка'; btn.textContent = '✦ AI съвет'; btn.disabled = false; });
}

function aiCardAdvice(id, name) {
  openDrw({id, name, qty:0, min:0, sell_price:0, cost_price:0, margin:null, color:'#22c55e', sku:'', supplier:'', category:'', days:0, sold30:0});
  loadDrwAI();
}

function aiSend(cmd) {
  const inp = document.getElementById('aiinp');
  const q = cmd || inp.value.trim(); if (!q) return;
  document.getElementById('airesp').textContent = '⏳ AI мисли...'; inp.value = '';
  fetch('chat-send.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({message:q, context:'products'})})
    .then(r => r.json())
    .then(d => { document.getElementById('airesp').textContent = d.reply || d.response || d.message || 'Няма отговор'; })
    .catch(() => { document.getElementById('airesp').textContent = 'Грешка'; });
}

function openCam(mode) {
  camMode = mode;
  document.getElementById('camttl').textContent = mode === 'photo' ? '📷 Камера — Снимай артикул' : '⬛ Скенер — Сканирай баркод';
  document.getElementById('cammod').classList.add('open');
  navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}})
    .then(s => { camStream = s; document.getElementById('camvid').srcObject = s; })
    .catch(() => { closeCam(); toast('Камерата не е достъпна'); });
}

function closeCam() {
  if (camStream) { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
  document.getElementById('cammod').classList.remove('open');
  document.getElementById('camres').style.display = 'none';
}

function snapCam() {
  const v = document.getElementById('camvid'), c = document.getElementById('camcvs');
  c.width = v.videoWidth; c.height = v.videoHeight;
  c.getContext('2d').drawImage(v, 0, 0);
  const img = c.toDataURL('image/jpeg', .8).split(',')[1];
  document.getElementById('camsnap').textContent = '⏳';
  fetch('ai-helper.php', {method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'scan_product', image:img, mode:camMode})})
    .then(r => r.json())
    .then(d => {
      document.getElementById('camsnap').textContent = '📸';
      document.getElementById('camtxt').textContent = d.result || 'Не е разпознато';
      document.getElementById('camres').style.display = 'block';
    })
    .catch(() => { document.getElementById('camsnap').textContent = '📸'; });
}

function useCamResult() { closeCam(); document.getElementById('addmod').classList.add('open'); }

function saveAdd() {
  const name = document.getElementById('aname').value.trim();
  if (!name) { toast('Въведи наименование'); return; }
  fetch('product-save.php', {method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'create', name,
      price: document.getElementById('aprice').value,
      cost:  document.getElementById('acost').value,
      barcode: document.getElementById('abcode').value,
      qty:   document.getElementById('aqty').value,
      description: document.getElementById('adesc').value
    })})
    .then(r => r.json())
    .then(d => {
      if (d.success) { document.getElementById('addmod').classList.remove('open'); toast('✅ Запазен'); setTimeout(() => location.reload(), 800); }
      else toast(d.error || 'Грешка');
    });
}

function handleCSV(inp) {
  const f = inp.files[0]; if (!f) return;
  const r = new FileReader();
  r.onload = e => {
    fetch('product-save.php', {method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'csv_import', csv:e.target.result})})
      .then(r => r.json())
      .then(d => {
        toast(d.imported ? '✅ Импортирани ' + d.imported + ' артикула' : (d.error || 'Грешка'));
        if (d.imported) setTimeout(() => location.reload(), 1000);
      });
  };
  r.readAsText(f, 'UTF-8');
}

function toast(msg, dur = 2500) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), dur);
}
</script>

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/vendors/aos.js"></script>
<script src="./js/main.js"></script>
</body>
</html>
