<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/database.php';
require_once 'config/config.php';

$pdo = DB::get();
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'] ?? 1;

// Tenant info
$tenant = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
$supato_mode = $tenant['supato_mode'] ?? 0;
$lang = $tenant['lang'] ?? 'bg';
$business_type = $tenant['business_type'] ?? '';
$currency = htmlspecialchars($tenant['currency'] ?? 'лв');

// User info
$user = DB::run("SELECT * FROM users WHERE id = ?", [$user_id])->fetch(PDO::FETCH_ASSOC);
$user_role = $user['role'] ?? 'seller';
$user_name = $user['name'] ?? '';
$max_discount = floatval($user['max_discount_pct'] ?? 100);

// Store info
$store = DB::run("SELECT * FROM stores WHERE id = ? AND tenant_id = ?", [$store_id, $tenant_id])->fetch(PDO::FETCH_ASSOC);
$store_name = $store['name'] ?? 'Магазин';

// Wholesale clients
$wholesale_clients = DB::run("SELECT id, name, phone FROM customers WHERE tenant_id = ? AND is_wholesale = 1 AND is_active = 1 ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// Page title
$page_title = $supato_mode ? 'Изходящо движение' : 'Продажба';

// ─── AJAX: Quick Search ───
if (isset($_GET['action']) && $_GET['action'] === 'quick_search') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $like = "%$q%";
    $results = DB::run("
        SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price, p.barcode,
               COALESCE(i.quantity, 0) as stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
        WHERE p.tenant_id = ? AND p.is_active = 1
          AND (p.code LIKE ? OR p.name LIKE ? OR p.barcode = ?)
        ORDER BY
          CASE WHEN p.code = ? THEN 0 WHEN p.barcode = ? THEN 1 ELSE 2 END,
          p.name
        LIMIT 10
    ", [$store_id, $tenant_id, $like, $like, $q, $q, $q])->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
    exit;
}

// ─── AJAX: Barcode Lookup ───
if (isset($_GET['action']) && $_GET['action'] === 'barcode_lookup') {
    header('Content-Type: application/json; charset=utf-8');
    $barcode = trim($_GET['barcode'] ?? '');
    $product = DB::run("
        SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price, p.barcode,
               COALESCE(i.quantity, 0) as stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.barcode = ?
        LIMIT 1
    ", [$store_id, $tenant_id, $barcode])->fetch(PDO::FETCH_ASSOC);
    echo json_encode($product ?: null);
    exit;
}

// ─── AJAX: Save Sale ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save_sale') {
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);
    $items = $data['items'] ?? [];
    $payment_method = $data['payment_method'] ?? 'cash';
    $discount_pct = floatval($data['discount_pct'] ?? 0);
    $customer_id = !empty($data['customer_id']) ? intval($data['customer_id']) : null;
    $received = floatval($data['received'] ?? 0);

    if (empty($items)) { echo json_encode(['success' => false, 'error' => 'Няма артикули']); exit; }

    try {
        $pdo->beginTransaction();
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += floatval($it['unit_price']) * intval($it['quantity']);
        }
        $discount_amount = round($subtotal * ($discount_pct / 100), 2);
        $total = round($subtotal - $discount_amount, 2);

        DB::run("INSERT INTO sales (tenant_id, store_id, user_id, customer_id, total_amount, discount_amount, discount_pct, payment_method, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())",
            [$tenant_id, $store_id, $user_id, $customer_id, $total, $discount_amount, $discount_pct, $payment_method]);
        $sale_id = $pdo->lastInsertId();

        foreach ($items as $it) {
            $pid = intval($it['product_id']);
            $qty = intval($it['quantity']);
            $price = floatval($it['unit_price']);
            $idp = floatval($it['discount_pct'] ?? 0);
            $ist = round($price * $qty * (1 - $idp / 100), 2);

            DB::run("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, discount_pct, subtotal) VALUES (?, ?, ?, ?, ?, ?)",
                [$sale_id, $pid, $qty, $price, $idp, $ist]);
            DB::run("UPDATE inventory SET quantity = GREATEST(quantity - ?, 0) WHERE product_id = ? AND store_id = ?",
                [$qty, $pid, $store_id]);
            DB::run("INSERT INTO stock_movements (tenant_id, product_id, store_id, quantity, type, reference_type, reference_id, created_at) VALUES (?, ?, ?, ?, 'out', 'sale', ?, NOW())",
                [$tenant_id, $pid, $store_id, $qty, $sale_id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $sale_id, 'total' => $total]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover"/>
<title><?= $page_title ?> — RunMyStore.ai</title>
<link href="./css/vendors/aos.css" rel="stylesheet"/>
<link rel="stylesheet" href="/css/theme.css?v=<?= @filemtime(__DIR__.'/css/theme.css') ?: 1 ?>"/>
<link rel="stylesheet" href="/css/shell.css?v=<?= @filemtime(__DIR__.'/css/shell.css') ?: 1 ?>"/>
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
<style>
/* ═══════════════════════════════════════════════════════════
   SALE MODULE — Unified Design System 2026
   Reference: warehouse.php
   ═══════════════════════════════════════════════════════════ */
:root {
    --bg-main: #030712;
    --bg-card: rgba(15, 15, 40, 0.75);
    --bg-card-hover: rgba(23, 28, 58, 0.9);
    --border-subtle: rgba(99, 102, 241, 0.15);
    --border-glow: rgba(99, 102, 241, 0.4);
    --indigo-600: #4f46e5;
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
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{
    height:100%;background:var(--bg-main);color:var(--text-primary);
    font-family:'Montserrat',Inter,system-ui,sans-serif;
    overflow:hidden;touch-action:manipulation;
    -webkit-user-select:none;user-select:none;
}
body::before{
    content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:700px;height:400px;
    background:radial-gradient(ellipse,rgba(99,102,241,0.1) 0%,transparent 70%);
    pointer-events:none;z-index:0;
}

/* ═══ LAYOUT — Full screen, no scroll ═══ */
.sale-wrap{
    position:relative;z-index:1;display:flex;flex-direction:column;
    height:100dvh;height:100vh;max-width:480px;margin:0 auto;
}

/* ═══ CAMERA-HEADER (merged) ═══ */
.cam-header{
    position:relative;height:calc(80px + env(safe-area-inset-top,0px));flex-shrink:0;overflow:hidden;
    background:#111;z-index:50;
}
.cam-header video{
    position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
}
.cam-header.wholesale{border-bottom:2px solid var(--indigo-400)}
/* Overlay controls */
.cam-overlay{position:absolute;inset:0;z-index:2;pointer-events:none}
.cam-overlay>*{pointer-events:auto}
.cam-top{
    position:absolute;top:0;left:0;right:0;
    display:flex;align-items:center;justify-content:space-between;
    padding:max(6px,calc(env(safe-area-inset-top,0px) + 6px)) 10px 6px;
}
.cam-title{
    font-size:14px;font-weight:800;
    background:linear-gradient(135deg,#f1f5f9,#a5b4fc);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    text-shadow:none;
}
.cam-btn{
    width:28px;height:28px;display:flex;align-items:center;justify-content:center;
    border-radius:8px;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);
    color:rgba(165,180,252,0.8);font-size:16px;border:none;cursor:pointer;
}
.cam-btn:active{background:rgba(99,102,241,0.3);transform:scale(0.9)}
.cam-right{display:flex;gap:4px}
.park-badge{
    position:absolute;top:-3px;right:-3px;
    min-width:14px;height:14px;border-radius:7px;
    background:var(--danger);color:#fff;font-size:8px;font-weight:800;
    display:flex;align-items:center;justify-content:center;padding:0 3px;
    box-shadow:0 0 6px rgba(239,68,68,0.5);
}
/* Scan zone corners */
.scan-corner{position:absolute;width:16px;height:16px;z-index:3}
.scan-corner svg{width:100%;height:100%}
.sc-tl{top:14px;left:15%}
.sc-tr{top:14px;right:15%}
.sc-bl{bottom:22px;left:15%}
.sc-br{bottom:22px;right:15%}
/* Laser line */
.scan-laser{
    position:absolute;left:16%;right:16%;height:2px;z-index:3;
    background:linear-gradient(90deg,transparent,#22c55e,transparent);
    box-shadow:0 0 10px #22c55e,0 0 20px rgba(34,197,94,0.3);
    animation:scanLine 2.5s ease-in-out infinite;
}
@keyframes scanLine{
    0%{top:18%}50%{top:72%}100%{top:18%}
}
/* Scanner status */
.cam-status{
    position:absolute;bottom:4px;left:0;right:0;
    display:flex;align-items:center;justify-content:center;gap:6px;
    z-index:4;animation:scanPulse 2s ease infinite;
}
@keyframes scanPulse{0%,100%{opacity:0.7}50%{opacity:1}}
.scan-dot{
    width:7px;height:7px;border-radius:50%;background:#22c55e;flex-shrink:0;
    animation:dotBlink 1.5s ease infinite;
}
@keyframes dotBlink{
    0%,100%{box-shadow:0 0 6px #22c55e;opacity:1}
    50%{box-shadow:0 0 14px #22c55e,0 0 28px rgba(34,197,94,0.4);opacity:0.6}
}
.cam-status span{
    font-size:9px;font-weight:700;color:rgba(74,222,128,0.9);
    letter-spacing:0.8px;text-transform:uppercase;
}
/* Flash on scan */
.cam-header.scanned{animation:camFlash 0.4s ease}
@keyframes camFlash{
    0%{box-shadow:inset 0 0 0 0 rgba(34,197,94,0)}
    30%{box-shadow:inset 0 0 40px 10px rgba(34,197,94,0.5)}
    100%{box-shadow:inset 0 0 0 0 rgba(34,197,94,0)}
}
.hdr-btn{display:none} /* legacy — hidden */
.green-flash{
    position:fixed;inset:0;background:rgba(34,197,94,0.25);
    z-index:999;pointer-events:none;opacity:0;transition:opacity 0.05s;
}
.green-flash.active{opacity:1}

/* ═══ SEARCH BAR ═══ */
.search-bar{
    display:flex;align-items:center;height:44px;padding:0 10px;flex-shrink:0;
    background:var(--bg-card);border-bottom:1px solid var(--border-subtle);
    gap:8px;
}
.search-display{
    flex:1;height:32px;display:flex;align-items:center;padding:0 12px;
    background:rgba(99,102,241,0.08);border:1px solid var(--border-subtle);
    border-radius:10px;color:var(--text-primary);font-size:14px;font-weight:500;
    overflow:hidden;white-space:nowrap;
}
.search-display .placeholder{color:var(--text-secondary);font-size:13px}
.search-btn{
    width:34px;height:34px;display:flex;align-items:center;justify-content:center;
    border-radius:10px;border:1px solid var(--border-subtle);background:rgba(99,102,241,0.08);
    color:var(--indigo-300);font-size:16px;cursor:pointer;flex-shrink:0;
    transition:all 0.2s;
}
.search-btn:active{background:rgba(99,102,241,0.2);transform:scale(0.92)}

/* ═══ SEARCH RESULTS ═══ */
.search-results{
    max-height:0;overflow-y:auto;flex-shrink:0;
    background:rgba(15,15,40,0.95);backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border-subtle);
    transition:max-height 0.25s ease;
}
.search-results.open{max-height:200px}
.sr-item{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 14px;border-bottom:1px solid rgba(99,102,241,0.08);
    cursor:pointer;transition:background 0.15s;
}
.sr-item:active{background:rgba(99,102,241,0.12)}
.sr-code{font-size:11px;color:var(--indigo-400);font-weight:600;margin-right:8px;min-width:50px}
.sr-name{flex:1;font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sr-price{font-size:13px;font-weight:700;color:var(--indigo-300);margin-left:8px;white-space:nowrap}
.sr-stock{font-size:10px;color:var(--text-secondary);margin-left:4px}
.sr-stock.zero{color:var(--danger)}

/* ═══ CART ═══ */
.cart-zone{
    flex:1;overflow-y:auto;padding:0;
    -webkit-overflow-scrolling:touch;
}
.cart-empty{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    height:100%;gap:12px;color:var(--text-secondary);
}
.cart-empty-icon{font-size:48px;opacity:0.3}
.cart-empty-text{font-size:14px;font-weight:500}
.cart-item{
    display:flex;align-items:center;padding:10px 14px;
    border-bottom:1px solid rgba(99,102,241,0.06);
    position:relative;overflow:hidden;
    animation:fadeUp 0.2s ease both;
    cursor:pointer;transition:background 0.15s;
}
.cart-item:active{background:rgba(99,102,241,0.08)}
.cart-item.selected{background:rgba(99,102,241,0.12);border-left:3px solid var(--indigo-500)}
.ci-info{flex:1;min-width:0}
.ci-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ci-meta{font-size:11px;color:var(--text-secondary);margin-top:2px}
.ci-right{text-align:right;flex-shrink:0;margin-left:8px}
.ci-qty{font-size:13px;font-weight:700;color:var(--indigo-300)}
.ci-price{font-size:14px;font-weight:700;margin-top:2px}
.ci-delete{
    position:absolute;right:0;top:0;bottom:0;width:70px;
    background:var(--danger);color:#fff;font-size:12px;font-weight:700;
    display:flex;align-items:center;justify-content:center;
    transform:translateX(100%);transition:transform 0.2s;
}
.cart-item.swiped .ci-delete{transform:translateX(0)}

/* ═══ SUMMARY BAR ═══ */
.summary-bar{
    display:flex;align-items:center;justify-content:space-between;
    height:44px;padding:0 14px;flex-shrink:0;
    background:var(--bg-card);border-top:1px solid var(--border-subtle);
    border-bottom:1px solid var(--border-subtle);
}
.sum-count{font-size:12px;font-weight:600;color:var(--text-secondary)}
.sum-discount{
    width:36px;height:28px;border-radius:8px;
    background:rgba(99,102,241,0.1);border:1px solid var(--border-subtle);
    color:var(--indigo-300);font-size:13px;font-weight:700;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    transition:all 0.2s;
}
.sum-discount:active{background:rgba(99,102,241,0.2);transform:scale(0.92)}
.sum-discount.active{background:rgba(234,179,8,0.15);border-color:rgba(234,179,8,0.4);color:var(--warning)}
.sum-total{font-size:15px;font-weight:800}
.sum-total .amount{
    background:linear-gradient(90deg,var(--indigo-400) 25%,#c7d2fe 50%,var(--indigo-400) 75%);
    background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.sum-total .amount.changed{animation:shimmer 1.5s linear 1}

/* ═══ ACTION BAR ═══ */
.action-bar{
    display:flex;align-items:center;gap:8px;padding:8px 12px;flex-shrink:0;
    background:rgba(3,7,18,0.93);
}
.btn-pay{
    flex:3;height:36px;border-radius:14px;border:none;cursor:pointer;
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    color:#fff;font-size:15px;font-weight:800;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:8px;
    box-shadow:0 4px 20px rgba(99,102,241,0.4);
    transition:all 0.2s;
}
.btn-pay:active{transform:scale(0.97);box-shadow:0 2px 10px rgba(99,102,241,0.3)}
.btn-pay:disabled{opacity:0.3;pointer-events:none}
.btn-park{
    flex:1;height:36px;border-radius:14px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--indigo-300);font-size:20px;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    transition:all 0.2s;backdrop-filter:blur(12px);
}
.btn-park:active{background:rgba(99,102,241,0.12);transform:scale(0.95)}

/* ═══ NUMPAD ═══ */
.numpad-zone{flex-shrink:0;background:rgba(3,7,18,0.97);padding:0 8px 132px;backdrop-filter:blur(16px)}
.numpad-ctx{
    display:flex;align-items:center;justify-content:center;
    height:0;gap:0;margin-bottom:0;overflow:hidden;
}
.ctx-label{
    font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;
    padding:3px 12px;border-radius:6px;
}
.ctx-code{background:rgba(99,102,241,0.15);color:var(--indigo-400)}
.ctx-qty{background:rgba(34,197,94,0.15);color:#4ade80}
.ctx-discount{background:rgba(234,179,8,0.15);color:#facc15}
.ctx-received{background:rgba(34,197,94,0.15);color:#4ade80}
.numpad-grid{
    display:grid;grid-template-columns:repeat(4,1fr);gap:4px;
}
.np-btn{
    height:36px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);
    font-size:18px;font-weight:600;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all 0.12s;
    backdrop-filter:blur(12px);
}
.np-btn:active{background:rgba(99,102,241,0.2);transform:scale(0.93);border-color:var(--border-glow)}
.np-btn.fn{color:var(--indigo-300);font-size:14px;font-weight:700}
.np-btn.ok{
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    border-color:transparent;color:#fff;font-weight:800;
    box-shadow:0 2px 12px rgba(99,102,241,0.3);
}
.np-btn.ok:active{box-shadow:0 1px 6px rgba(99,102,241,0.2)}
.np-btn.mic{color:#4ade80;font-size:20px}
.np-btn.clear{color:var(--danger);font-weight:700}

/* ═══ LETTER KEYBOARD ═══ */
.keyboard-zone{
    flex-shrink:0;background:rgba(3,7,18,0.97);padding:4px 4px 80px;
    backdrop-filter:blur(16px);display:none;
}
.keyboard-zone.visible{display:block}
.kb-row{display:flex;justify-content:center;gap:3px;margin-bottom:3px}
.kb-key{
    min-width:30px;height:38px;border-radius:8px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--text-primary);font-size:14px;font-weight:600;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all 0.1s;padding:0 2px;
}
.kb-key:active{background:rgba(99,102,241,0.2);transform:scale(0.9)}
.kb-key.wide{min-width:50px;font-size:11px;color:var(--indigo-300);font-weight:700}
.kb-key.space{flex:1;max-width:120px}

/* ═══ PAYMENT SHEET ═══ */
.pay-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,0.65);
    z-index:200;opacity:0;pointer-events:none;
    transition:opacity 0.25s;backdrop-filter:blur(4px);
}
.pay-overlay.open{opacity:1;pointer-events:all}
.pay-sheet{
    position:fixed;bottom:0;left:0;right:0;z-index:201;
    background:#080818;border-top:1px solid var(--border-glow);
    border-radius:22px 22px 0 0;padding:0 16px 40px;
    transform:translateY(100%);transition:transform 0.32s cubic-bezier(0.32,0,0.67,0);
    max-height:85vh;overflow-y:auto;
    box-shadow:0 -20px 60px rgba(99,102,241,0.2);
}
.pay-sheet.open{transform:translateY(0)}
.pay-handle{width:36px;height:4px;background:rgba(99,102,241,0.3);border-radius:2px;margin:14px auto 12px}
.pay-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:16px;
}
.pay-due{font-size:13px;color:var(--text-secondary)}
.pay-due-amount{
    font-size:28px;font-weight:900;
    background:linear-gradient(135deg,#f1f5f9,#a5b4fc);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.pay-close{
    width:32px;height:32px;border-radius:10px;
    background:rgba(99,102,241,0.1);border:1px solid var(--border-subtle);
    color:var(--indigo-300);font-size:16px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;
}
.pay-methods{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.pm-chip{
    flex:1;min-width:70px;height:40px;border-radius:12px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--text-secondary);font-size:12px;font-weight:600;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:4px;
    cursor:pointer;transition:all 0.2s;
}
.pm-chip.active{
    border-color:var(--indigo-400);background:rgba(99,102,241,0.15);
    color:var(--indigo-300);box-shadow:0 0 12px rgba(99,102,241,0.2);
}
.pm-chip:active{transform:scale(0.95)}

/* Payment received section */
.pay-received{margin-bottom:12px}
.pay-recv-label{font-size:12px;color:var(--text-secondary);margin-bottom:6px}
.pay-recv-amount{
    font-size:24px;font-weight:800;color:var(--text-primary);
    text-align:center;padding:8px 0;
}
.pay-banknotes{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px}
.bn-chip{
    height:36px;padding:0 14px;border-radius:10px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--text-primary);font-size:13px;font-weight:700;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all 0.15s;
}
.bn-chip:active{background:rgba(99,102,241,0.15);transform:scale(0.93)}
.bn-chip.exact{color:var(--success);border-color:rgba(34,197,94,0.3)}

/* Change display */
.pay-change{
    background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);
    border-radius:16px;padding:16px;text-align:center;margin-bottom:16px;
}
.pay-change-label{font-size:12px;color:rgba(74,222,128,0.7);margin-bottom:4px}
.pay-change-amount{font-size:32px;font-weight:900;color:#4ade80;text-shadow:0 0 20px rgba(34,197,94,0.4)}

.btn-confirm{
    width:100%;height:52px;border-radius:16px;border:none;cursor:pointer;
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    color:#fff;font-size:16px;font-weight:800;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:8px;
    box-shadow:0 4px 24px rgba(99,102,241,0.4);transition:all 0.2s;
}
.btn-confirm:active{transform:scale(0.97)}
.btn-confirm:disabled{opacity:0.3;pointer-events:none}

/* ═══ DISCOUNT CHIPS ═══ */
.discount-chips{
    display:none;align-items:center;gap:6px;padding:6px 12px;flex-shrink:0;
    background:rgba(234,179,8,0.05);border-top:1px solid rgba(234,179,8,0.15);
}
.discount-chips.visible{display:flex}
.dc-chip{
    height:30px;padding:0 12px;border-radius:8px;
    border:1px solid rgba(234,179,8,0.25);background:rgba(234,179,8,0.08);
    color:#facc15;font-size:13px;font-weight:700;font-family:inherit;
    cursor:pointer;transition:all 0.15s;
}
.dc-chip:active{transform:scale(0.93)}
.dc-chip.active{background:rgba(234,179,8,0.2);border-color:rgba(234,179,8,0.5)}
.dc-close{
    margin-left:auto;color:var(--text-secondary);font-size:18px;cursor:pointer;padding:0 4px;
}

/* ═══ WHOLESALE SHEET ═══ */
.ws-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:200;
    opacity:0;pointer-events:none;transition:opacity 0.25s;backdrop-filter:blur(4px);
}
.ws-overlay.open{opacity:1;pointer-events:all}
.ws-sheet{
    position:fixed;bottom:0;left:0;right:0;z-index:201;
    background:#080818;border-top:1px solid var(--border-glow);
    border-radius:22px 22px 0 0;padding:0 16px 40px;
    transform:translateY(100%);transition:transform 0.32s cubic-bezier(0.32,0,0.67,0);
    max-height:70vh;overflow-y:auto;
    box-shadow:0 -20px 60px rgba(99,102,241,0.2);
}
.ws-sheet.open{transform:translateY(0)}
.ws-title{font-size:15px;font-weight:800;color:var(--text-primary);text-align:center;margin-bottom:16px}
.ws-item{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 14px;border-bottom:1px solid rgba(99,102,241,0.08);
    cursor:pointer;transition:background 0.15s;border-radius:10px;margin-bottom:2px;
}
.ws-item:active{background:rgba(99,102,241,0.1)}
.ws-name{font-size:14px;font-weight:600}
.ws-phone{font-size:12px;color:var(--text-secondary)}
.ws-retail{
    padding:12px 14px;border-radius:10px;cursor:pointer;
    background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);
    color:#fca5a5;font-size:13px;font-weight:700;text-align:center;
    margin-top:8px;transition:all 0.15s;
}
.ws-retail:active{background:rgba(239,68,68,0.15)}

/* ═══ PARKED SALES OVERLAY ═══ */
.parked-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:200;
    opacity:0;pointer-events:none;transition:opacity 0.25s;backdrop-filter:blur(6px);
}
.parked-overlay.open{opacity:1;pointer-events:all}
.parked-container{
    position:fixed;top:60px;left:12px;right:12px;bottom:80px;z-index:201;
    display:flex;flex-direction:column;gap:12px;overflow-y:auto;
    opacity:0;transform:scale(0.95);transition:all 0.3s;
}
.parked-overlay.open .parked-container{opacity:1;transform:scale(1)}
.parked-card{
    background:var(--bg-card);border:1px solid var(--border-subtle);
    border-radius:16px;padding:16px;backdrop-filter:blur(12px);
    cursor:pointer;transition:all 0.2s;animation:cardIn 0.3s ease both;
}
.parked-card:active{transform:scale(0.97);border-color:var(--border-glow)}
.pc-header{display:flex;justify-content:space-between;margin-bottom:8px}
.pc-client{font-size:13px;font-weight:700;color:var(--indigo-300)}
.pc-time{font-size:11px;color:var(--text-secondary)}
.pc-info{font-size:12px;color:var(--text-secondary)}
.pc-total{font-size:16px;font-weight:800;margin-top:6px}
.pc-delete{
    float:right;padding:4px 10px;border-radius:8px;
    background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);
    color:#fca5a5;font-size:11px;font-weight:700;cursor:pointer;margin-top:8px;
}
.pc-delete:active{background:rgba(239,68,68,0.2)}
.parked-title{
    text-align:center;font-size:16px;font-weight:800;color:var(--text-primary);
    margin-bottom:8px;flex-shrink:0;
}

/* ═══ VOICE OVERLAY — products.php rec-ov/rec-box style ═══ */
.rec-ov{
    position:fixed;inset:0;z-index:300;
    background:rgba(3,7,18,0.6);backdrop-filter:blur(8px);
    display:none;align-items:flex-end;justify-content:center;
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
@keyframes recSlideUp{
    from{opacity:0;transform:translateY(30px)}
    to{opacity:1;transform:translateY(0)}
}
/* REC status row — BIG indicator */
.rec-status{
    display:flex;align-items:center;gap:10px;margin-bottom:14px;
}
.rec-dot{
    width:16px;height:16px;border-radius:50%;
    background:#ef4444;flex-shrink:0;
    box-shadow:0 0 12px #ef4444,0 0 24px rgba(239,68,68,0.4);
    animation:recPulse 1s ease infinite;
}
.rec-dot.ready{
    background:#22c55e;
    box-shadow:0 0 12px #22c55e,0 0 24px rgba(34,197,94,0.4);
    animation:none;
}
@keyframes recPulse{
    0%,100%{opacity:1;box-shadow:0 0 8px #ef4444,0 0 16px rgba(239,68,68,0.3)}
    50%{opacity:0.5;box-shadow:0 0 20px #ef4444,0 0 40px rgba(239,68,68,0.6)}
}
.rec-label{
    font-size:15px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;
}
.rec-label.recording{color:#ef4444}
.rec-label.ready{color:#22c55e}
/* Transcription */
.rec-transcript{
    min-height:44px;padding:10px 14px;margin-bottom:14px;
    background:rgba(99,102,241,0.06);border:1px solid var(--border-subtle);
    border-radius:12px;font-size:15px;font-weight:500;
    color:var(--text-primary);line-height:1.4;
    word-wrap:break-word;
}
.rec-transcript.empty{color:var(--text-secondary);font-style:italic}
/* Hint */
.rec-hint{
    font-size:11px;color:var(--text-secondary);margin-bottom:14px;
    text-align:center;line-height:1.4;
}
/* Buttons row */
.rec-actions{display:flex;gap:8px}
.rec-btn-cancel{
    flex:1;height:44px;border-radius:12px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--indigo-300);font-size:14px;font-weight:600;
    cursor:pointer;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
}
.rec-btn-cancel:active{background:rgba(99,102,241,0.12)}
.rec-btn-send{
    flex:2;height:44px;border-radius:12px;border:none;
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    color:#fff;font-size:14px;font-weight:700;
    cursor:pointer;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:6px;
    box-shadow:0 4px 16px rgba(99,102,241,0.35);
    transition:all 0.2s;
}
.rec-btn-send:active{transform:scale(0.97)}
.rec-btn-send:disabled{opacity:0.3;pointer-events:none}

/* ═══ TOAST ═══ */
.toast{
    position:fixed;top:60px;left:50%;transform:translateX(-50%) translateY(-100px);
    z-index:500;padding:12px 24px;border-radius:14px;
    background:rgba(15,15,40,0.95);border:1px solid var(--border-glow);
    backdrop-filter:blur(16px);color:var(--text-primary);
    font-size:14px;font-weight:600;white-space:nowrap;
    box-shadow:0 8px 32px rgba(99,102,241,0.3);
    transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.toast.show{transform:translateX(-50%) translateY(0)}
.toast.success{border-color:rgba(34,197,94,0.4);box-shadow:0 8px 32px rgba(34,197,94,0.2)}

/* ═══ BOTTOM NAV ═══ */
.bottom-nav{
    position:fixed;bottom:0;left:0;right:0;z-index:100;
    height:var(--bottom-nav-h);
    background:rgba(3,7,18,0.95);backdrop-filter:blur(16px);
    border-top:1px solid var(--border-subtle);
    display:flex;align-items:center;
    box-shadow:0 -5px 25px rgba(99,102,241,0.1);
}
.bnav-tab{
    flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:3px;font-size:13px;font-weight:600;
    color:rgba(165,180,252,0.5);text-decoration:none;
    transition:all 0.3s;height:100%;
}
.bnav-tab.active{color:var(--indigo-400);text-shadow:0 0 12px rgba(129,140,248,0.9)}
.bnav-tab .bnav-icon{font-size:20px;transition:all 0.3s;filter:drop-shadow(0 0 4px rgba(99,102,241,0.3))}
.bnav-tab.active .bnav-icon{transform:translateY(-2px);filter:drop-shadow(0 0 12px rgba(129,140,248,0.8))}

/* ═══ UNDO BAR ═══ */
.undo-bar{
    position:fixed;bottom:calc(var(--bottom-nav-h) + 8px);left:16px;right:16px;
    z-index:150;padding:12px 16px;border-radius:14px;
    background:rgba(15,15,40,0.95);border:1px solid rgba(239,68,68,0.3);
    backdrop-filter:blur(16px);display:none;
    justify-content:space-between;align-items:center;
    box-shadow:0 4px 20px rgba(0,0,0,0.4);
    animation:fadeUp 0.2s ease;
}
.undo-bar.show{display:flex}
.undo-text{font-size:13px;color:#fca5a5}
.undo-btn{
    padding:6px 14px;border-radius:8px;
    background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);
    color:#fca5a5;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;
}

/* ═══ ANIMATIONS ═══ */
@keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}

/* ═══ Long press popup ═══ */
.lp-popup{
    position:fixed;z-index:250;
    background:#0c0c24;border:1px solid var(--border-glow);
    border-radius:18px;padding:16px;min-width:220px;
    box-shadow:0 12px 40px rgba(0,0,0,0.6),0 0 30px rgba(99,102,241,0.15);
    display:none;
}
.lp-popup.show{display:block;animation:cardIn 0.2s ease}
.lp-title{font-size:14px;font-weight:700;margin-bottom:12px;text-align:center}
.lp-numpad{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;margin-bottom:8px}
.lp-num{
    height:42px;border-radius:10px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);font-size:16px;font-weight:600;
    display:flex;align-items:center;justify-content:center;cursor:pointer;font-family:inherit;
}
.lp-num:active{background:rgba(99,102,241,0.15)}
.lp-display{
    text-align:center;font-size:24px;font-weight:800;color:var(--indigo-300);
    margin-bottom:10px;min-height:32px;
}
.lp-actions{display:flex;gap:6px}
.lp-cancel,.lp-ok{
    flex:1;height:38px;border-radius:10px;border:none;font-size:13px;font-weight:700;
    cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;
}
.lp-cancel{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.lp-ok{background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));color:#fff}

/* ═══ NOT FOUND POPUP ═══ */
.nf-popup{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(0.9);z-index:600;background:rgba(15,15,40,0.95);border:1px solid rgba(239,68,68,0.4);border-radius:16px;padding:20px 30px;display:flex;flex-direction:column;align-items:center;gap:10px;opacity:0;pointer-events:none;box-shadow:0 10px 40px rgba(239,68,68,0.2);backdrop-filter:blur(16px);transition:all 0.2s cubic-bezier(0.34,1.56,0.64,1)}
.nf-popup.show{opacity:1;transform:translate(-50%,-50%) scale(1)}
.nf-popup .nf-icon{font-size:36px}
.nf-popup .nf-text{color:#fca5a5;font-size:16px;font-weight:700;text-align:center}
/* 15% Увеличена клавиатура */
.np-btn { height: 42px !important; font-size: 21px !important; }
.np-btn.fn { font-size: 16px !important; }
.np-btn.mic { font-size: 24px !important; }
.kb-key { height: 44px !important; min-width: 35px !important; font-size: 16px !important; }
.kb-key.wide { min-width: 58px !important; font-size: 13px !important; }

/* Повдигане на буквената клавиатура */
.keyboard-zone { padding-bottom: 140px !important; }

/* Адаптивна ширина на буквите срещу изрязване */
.kb-row { padding: 0 4px !important; width: 100% !important; box-sizing: border-box !important; }
.kb-key { flex: 1 !important; min-width: 0 !important; max-width: 42px !important; padding: 0 !important; }
.kb-key.wide { flex: 1.5 !important; max-width: 65px !important; }


/* S82.CAPACITOR safe-area */
body{padding-bottom:env(safe-area-inset-bottom);}
.bottom-nav,.btm-nav,nav.bottom,[class*="bottom-nav"]{padding-bottom:calc(8px + env(safe-area-inset-bottom)) !important;box-sizing:content-box;}
</style>
</head>
<body>

<div class="green-flash" id="greenFlash"></div>

<div class="toast" id="toast"></div>

<div class="undo-bar" id="undoBar">
    <span class="undo-text" id="undoText"></span>
    <button class="undo-btn" id="undoBtn">ОТМЕНИ</button>
</div>

<div class="rec-ov" id="recOv">
    <div class="rec-box">
        <div class="rec-status">
            <div class="rec-dot" id="recDot"></div>
            <span class="rec-label recording" id="recLabel">● ЗАПИСВА</span>
        </div>
        <div class="rec-transcript empty" id="recTranscript">Слушам...</div>
        <div class="rec-hint" id="recHint">Кажете артикул, количество или команда</div>
        <div class="rec-actions">
            <button class="rec-btn-cancel" id="recCancel">Затвори</button>
            <button class="rec-btn-send" id="recSend" disabled>🎤 Изпрати →</button>
        </div>
    </div>
</div>

<div class="lp-popup" id="lpPopup">
    <div class="lp-title" id="lpTitle">Бройки</div>
    <div class="lp-display" id="lpDisplay">0</div>
    <div class="lp-numpad">
        <button class="lp-num" onclick="lpNum('1')">1</button>
        <button class="lp-num" onclick="lpNum('2')">2</button>
        <button class="lp-num" onclick="lpNum('3')">3</button>
        <button class="lp-num" onclick="lpNum('4')">4</button>
        <button class="lp-num" onclick="lpNum('5')">5</button>
        <button class="lp-num" onclick="lpNum('6')">6</button>
        <button class="lp-num" onclick="lpNum('7')">7</button>
        <button class="lp-num" onclick="lpNum('8')">8</button>
        <button class="lp-num" onclick="lpNum('9')">9</button>
        <button class="lp-num" onclick="lpNum('C')" style="color:var(--danger)">C</button>
        <button class="lp-num" onclick="lpNum('0')">0</button>
        <button class="lp-num" onclick="lpNum('⌫')">⌫</button>
    </div>
    <div class="lp-actions">
        <button class="lp-cancel" onclick="closeLpPopup()">Откажи</button>
        <button class="lp-ok" onclick="confirmLpPopup()">OK</button>
    </div>
</div>

<div class="nf-popup" id="nfPopup">
    <div class="nf-icon">⚠️</div>
    <div class="nf-text">Няма такъв артикул</div>
</div>

<div class="sale-wrap" id="saleWrap">

    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="cam-header" id="camHeader">
        <video id="cameraVideo" autoplay playsinline muted></video>
        <div class="cam-overlay">
            <div class="cam-top">
                <button class="cam-btn" onclick="location.href='warehouse.php'">←</button>
                <span class="cam-title" id="camTitle"><?= $page_title ?></span>
                <div class="cam-right">
                    <button class="cam-btn" id="btnParkedBadge" onclick="openParked()" style="position:relative;display:none">
                        🅿️<span class="park-badge" id="parkedCount">0</span>
                    </button>
                    <button class="cam-btn" id="btnWholesale" onclick="openWholesale()">👤</button>
                    <button class="cam-btn" id="themeToggle" type="button" aria-label="Светла/тъмна тема" onclick="toggleTheme()" style="font-size:14px"><span id="themeIconSun" style="display:none">☀️</span><span id="themeIconMoon">🌙</span></button>
                </div>
            </div>
            <div class="scan-corner sc-tl"><svg viewBox="0 0 16 16"><path d="M0 5V1a1 1 0 011-1h4" fill="none" stroke="#22c55e" stroke-width="2" stroke-opacity="0.6"/></svg></div>
            <div class="scan-corner sc-tr"><svg viewBox="0 0 16 16"><path d="M16 5V1a1 1 0 00-1-1h-4" fill="none" stroke="#22c55e" stroke-width="2" stroke-opacity="0.6"/></svg></div>
            <div class="scan-corner sc-bl"><svg viewBox="0 0 16 16"><path d="M0 11v4a1 1 0 001 1h4" fill="none" stroke="#22c55e" stroke-width="2" stroke-opacity="0.6"/></svg></div>
            <div class="scan-corner sc-br"><svg viewBox="0 0 16 16"><path d="M16 11v4a1 1 0 01-1 1h-4" fill="none" stroke="#22c55e" stroke-width="2" stroke-opacity="0.6"/></svg></div>
            <div class="scan-laser"></div>
            <div class="cam-status">
                <div class="scan-dot"></div>
                <span>Скенер активен — насочи към баркод</span>
            </div>
        </div>
    </div>

    <div class="search-bar">
        <div class="search-display" id="searchDisplay">
            <span class="placeholder">🔍 Код, име или баркод</span>
        </div>
        <button class="search-btn" id="btnVoiceSearch" onclick="startVoice()">🎤</button>
        <button class="search-btn" id="btnKeyboard" onclick="toggleKeyboard()">АБВ</button>
    </div>

    <div class="search-results" id="searchResults"></div>

    <div class="discount-chips" id="discountChips">
        <button class="dc-chip" onclick="applyDiscount(5)">5%</button>
        <button class="dc-chip" onclick="applyDiscount(10)">10%</button>
        <button class="dc-chip" onclick="applyDiscount(15)">15%</button>
        <button class="dc-chip" onclick="applyDiscount(20)">20%</button>
        <span class="dc-close" onclick="closeDiscount()">✕</span>
    </div>

    <div class="cart-zone" id="cartZone">
        <div class="cart-empty" id="cartEmpty">
            <span class="cart-empty-icon">🛒</span>
            <span class="cart-empty-text">Сканирай или въведи код</span>
        </div>
    </div>

    <div class="summary-bar" id="summaryBar" style="display:none">
        <span class="sum-count" id="sumCount">0 арт.</span>
        <button class="sum-discount" id="sumDiscountBtn" onclick="toggleDiscount()">%</button>
        <span class="sum-total">Общо: <span class="amount" id="sumTotal">0,00</span> <?= $currency ?></span>
    </div>

    <div class="action-bar" id="actionBar">
        <button class="btn-pay" id="btnPay" disabled onclick="openPayment()">
            💵 ПЛАТИ <span id="payAmount">0</span> <?= $currency ?>
        </button>
        <button class="btn-park" onclick="parkSale()">🅿️</button>
    </div>

    <div class="numpad-zone" id="numpadZone">
        <div class="numpad-ctx">
            <span class="ctx-label ctx-code" id="ctxLabel">КОД</span>
        </div>
        <div class="numpad-grid">
            <button class="np-btn" onclick="numPress('1')">1</button>
            <button class="np-btn" onclick="numPress('2')">2</button>
            <button class="np-btn" onclick="numPress('3')">3</button>
            <button class="np-btn fn" onclick="numPress('⌫')">⌫</button>
            <button class="np-btn" onclick="numPress('4')">4</button>
            <button class="np-btn" onclick="numPress('5')">5</button>
            <button class="np-btn" onclick="numPress('6')">6</button>
            <button class="np-btn" onclick="numPress('00')">00</button>
            <button class="np-btn" onclick="numPress('7')">7</button>
            <button class="np-btn" onclick="numPress('8')">8</button>
            <button class="np-btn" onclick="numPress('9')">9</button>
            <button class="np-btn" onclick="numPress('.')">.</button>
            <button class="np-btn fn clear" onclick="numPress('C')">C</button>
            <button class="np-btn" onclick="numPress('0')">0</button>
            <button class="np-btn ok" onclick="numOk()">OK</button>
            <button class="np-btn mic" onclick="startVoice()">🎤</button>
        </div>
    </div>

    <div class="keyboard-zone" id="keyboardZone">
        <?php
        // ─── Country-aware keyboard layouts ───
        // Cyrillic languages get full custom keyboard
        // Latin languages get QWERTY + special chars row
        $special_chars_map = [
            'ro' => ['Ă','Î','Ș','Ț','Â'],
            'de' => ['Ü','Ö','Ä','ß'],
            'fr' => ['É','È','Ê','Ç','À','Ù'],
            'es' => ['Ñ','Á','É','Í','Ó','Ú'],
            'pt' => ['Ã','Õ','Ç','Á','É','Ó'],
            'it' => ['À','È','É','Ì','Ò','Ù'],
            'pl' => ['Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż'],
            'cs' => ['Č','Ř','Š','Ž','Ý','Á','Í','É','Ú'],
            'sk' => ['Á','Č','Ď','É','Í','Ľ','Ň','Ó','Š','Ž'],
            'hu' => ['Á','É','Í','Ó','Ö','Ő','Ú','Ü','Ű'],
            'hr' => ['Č','Ć','Đ','Š','Ž'],
            'sl' => ['Č','Š','Ž'],
            'tr' => ['Ç','Ğ','İ','Ö','Ş','Ü'],
            'sv' => ['Å','Ä','Ö'],
            'da' => ['Æ','Ø','Å'],
            'fi' => ['Ä','Ö','Å'],
            'nl' => [],
            'el' => [], // Greek gets full keyboard below
            'en' => [],
        ];
        $specials = $special_chars_map[$lang] ?? [];

        if ($lang === 'bg'): ?>
        <div class="kb-row">
            <?php foreach(['Я','В','Е','Р','Т','Ъ','У','И','О','П'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <?php foreach(['А','С','Д','Ф','Г','Х','Й','К','Л'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <?php foreach(['З','Ь','Ц','Ж','Б','Н','М'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <button class="kb-key wide" onclick="toggleKeyboard()">123→</button>
            <?php foreach(['Ш','Щ','Ч','Ю'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
            <button class="kb-key space" onclick="kbPress(' ')">SPACE</button>
            <button class="kb-key wide" onclick="numOk()" style="background:rgba(99,102,241,0.15);color:#818cf8;font-weight:800">OK</button>
            <button class="kb-key" onclick="kbPress('⌫')">⌫</button>
        </div>

        <?php elseif ($lang === 'el'): ?>
        <div class="kb-row">
            <?php foreach(['Ω','Ε','Ρ','Τ','Υ','Θ','Ι','Ο','Π'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <?php foreach(['Α','Σ','Δ','Φ','Γ','Η','Ξ','Κ','Λ'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <?php foreach(['Ζ','Χ','Ψ','Β','Ν','Μ'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <button class="kb-key wide" onclick="toggleKeyboard()">123→</button>
            <button class="kb-key" onclick="kbPress('Ά')">Ά</button>
            <button class="kb-key" onclick="kbPress('Έ')">Έ</button>
            <button class="kb-key" onclick="kbPress('Ώ')">Ώ</button>
            <button class="kb-key space" onclick="kbPress(' ')">SPACE</button>
            <button class="kb-key wide" onclick="numOk()" style="background:rgba(99,102,241,0.15);color:#818cf8;font-weight:800">OK</button>
            <button class="kb-key" onclick="kbPress('⌫')">⌫</button>
        </div>

        <?php else: ?>
        <?php if (!empty($specials)): ?>
        <div class="kb-row">
            <?php foreach($specials as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="kb-row">
            <?php foreach(['Q','W','E','R','T','Y','U','I','O','P'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <?php foreach(['A','S','D','F','G','H','J','K','L'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kb-row">
            <button class="kb-key wide" onclick="toggleKeyboard()">123→</button>
            <?php foreach(['Z','X','C','V','B','N','M'] as $k): ?>
            <button class="kb-key" onclick="kbPress('<?= $k ?>')"><?= $k ?></button>
            <?php endforeach; ?>
            <button class="kb-key" onclick="kbPress('⌫')">⌫</button>
        </div>
        <div class="kb-row">
            <button class="kb-key space" onclick="kbPress(' ')">SPACE</button>
            <button class="kb-key wide" onclick="numOk()" style="background:rgba(99,102,241,0.15);color:#818cf8;font-weight:800">OK</button>
        </div>
        <?php endif; ?>
    </div>

</div><div class="pay-overlay" id="payOverlay" onclick="closePayment()"></div>
<div class="pay-sheet" id="paySheet">
    <div class="pay-handle"></div>
    <div class="pay-header">
        <div>
            <div class="pay-due">ДЪЛЖИМО</div>
            <div class="pay-due-amount" id="payDueAmount">0,00 <?= $currency ?></div>
        </div>
        <button class="pay-close" onclick="closePayment()">✕</button>
    </div>
    <div class="pay-methods">
        <button class="pm-chip active" data-method="cash" onclick="setPayMethod('cash')">💵 Брой</button>
        <button class="pm-chip" data-method="card" onclick="setPayMethod('card')">💳 Карта</button>
        <button class="pm-chip" data-method="transfer" onclick="setPayMethod('transfer')">🏦 Превод</button>
        <button class="pm-chip" data-method="deferred" onclick="setPayMethod('deferred')">⏳ Отложено</button>
    </div>
    <div id="cashSection">
        <div class="pay-received">
            <div class="pay-recv-label">Получено:</div>
            <div class="pay-recv-amount" id="payRecvAmount">0,00 <?= $currency ?></div>
        </div>
        <div class="pay-banknotes">
            <button class="bn-chip exact" onclick="payBanknote('exact')">Точна</button>
            <button class="bn-chip" onclick="payBanknote(5)">5</button>
            <button class="bn-chip" onclick="payBanknote(10)">10</button>
            <button class="bn-chip" onclick="payBanknote(20)">20</button>
            <button class="bn-chip" onclick="payBanknote(50)">50</button>
            <button class="bn-chip" onclick="payBanknote(100)">100</button>
        </div>
        <div class="pay-change" id="payChangeBox" style="display:none">
            <div class="pay-change-label">РЕСТО</div>
            <div class="pay-change-amount" id="payChangeAmount">0,00 <?= $currency ?></div>
        </div>
    </div>
    <button class="btn-confirm" id="btnConfirm" onclick="confirmPayment()" disabled>✅ ПОТВЪРДИ ПЛАЩАНЕ</button>
</div>

<div class="ws-overlay" id="wsOverlay" onclick="closeWholesale()"></div>
<div class="ws-sheet" id="wsSheet">
    <div class="pay-handle"></div>
    <div class="ws-title">Клиент (едро)</div>
    <?php foreach ($wholesale_clients as $wc): ?>
    <div class="ws-item" onclick="selectClient(<?= $wc['id'] ?>, '<?= htmlspecialchars($wc['name'], ENT_QUOTES) ?>')">
        <div>
            <div class="ws-name"><?= htmlspecialchars($wc['name']) ?></div>
            <div class="ws-phone"><?= htmlspecialchars($wc['phone'] ?? '') ?></div>
        </div>
        <span style="color:var(--indigo-300)">›</span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($wholesale_clients)): ?>
    <div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:13px">
        Няма клиенти на едро
    </div>
    <?php endif; ?>
    <div class="ws-retail" id="wsRetail" onclick="selectClient(null, null)" style="display:none">
        — Без клиент (дребно) —
    </div>
</div>

<div class="parked-overlay" id="parkedOverlay" onclick="closeParked()">
    <div class="parked-container" id="parkedContainer" onclick="event.stopPropagation()">
        <div class="parked-title">Паркирани продажби</div>
    </div>
</div>

<?php /* sale.php — chat input intentionally OMITTED (POS scanner — no AI distraction during checkout) */ ?>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>

<script>
/* ═══════════════════════════════════════════════════════════
   SALE MODULE — JavaScript Engine
   ═══════════════════════════════════════════════════════════ */

// S82.UI — Theme toggle (default DARK, persists in localStorage['rms_theme'])
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

// ─── STATE ───
const STATE = {
    cart: [],               // [{product_id, code, name, meta, unit_price, quantity, discount_pct, image}]
    selectedIndex: -1,      // selected cart item index
    numpadCtx: 'code',      // code | qty | discount | received
    numpadInput: '',        // current numpad input string
    isWholesale: false,
    customerId: null,
    customerName: '',
    discountPct: 0,
    payMethod: 'cash',
    receivedAmount: 0,
    parked: JSON.parse(localStorage.getItem('rms_parked_<?= $store_id ?>') || '[]'),
    cameraActive: false,
    keyboardVisible: false,
    searchText: '',
    searchTimeout: null,
    undoTimeout: null,
    undoItem: null,
    undoIndex: -1,
    maxDiscount: <?= $max_discount ?>,
    currency: '<?= $currency ?>',
    lang: '<?= $lang ?>',
    storeId: <?= $store_id ?>,
    scanCooldown: false,
};

// ─── FORMAT HELPERS ───
function fmtPrice(n) {
    return parseFloat(n).toLocaleString('bg-BG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function getTotal() {
    let sub = 0;
    STATE.cart.forEach(it => {
        sub += it.unit_price * it.quantity * (1 - (it.discount_pct || 0) / 100);
    });
    const disc = sub * (STATE.discountPct / 100);
    return Math.round((sub - disc) * 100) / 100;
}

function getItemCount() {
    return STATE.cart.reduce((s, it) => s + it.quantity, 0);
}

// ─── TOAST ───
function showToast(msg, type = '', duration = 2500) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast' + (type ? ' ' + type : '');
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => t.classList.remove('show'), duration);
}

// ─── BEEP ───
let audioCtx;
function beep(freq = 1200, dur = 0.15) {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const o = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    o.connect(g); g.connect(audioCtx.destination);
    o.frequency.value = freq;
    g.gain.setValueAtTime(0.3, audioCtx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + dur);
    o.start(); o.stop(audioCtx.currentTime + dur);
}

function ching() {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    [800, 1200, 1600].forEach((f, i) => {
        const o = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        o.connect(g); g.connect(audioCtx.destination);
        o.frequency.value = f;
        o.type = 'sine';
        g.gain.setValueAtTime(0.2, audioCtx.currentTime + i * 0.08);
        g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + i * 0.08 + 0.3);
        o.start(audioCtx.currentTime + i * 0.08);
        o.stop(audioCtx.currentTime + i * 0.08 + 0.3);
    });
}

function greenFlash() {
    const el = document.getElementById('greenFlash');
    el.classList.add('active');
    setTimeout(() => el.classList.remove('active'), 120);
}

// ─── RENDER ───
function render() {
    const zone = document.getElementById('cartZone');
    const empty = document.getElementById('cartEmpty');
    const summary = document.getElementById('summaryBar');
    const btnPay = document.getElementById('btnPay');
    const payAmt = document.getElementById('payAmount');
    const sumCount = document.getElementById('sumCount');
    const sumTotal = document.getElementById('sumTotal');
    const btnParked = document.getElementById('btnParkedBadge');
    const parkedCount = document.getElementById('parkedCount');

    // Parked badge
    if (STATE.parked.length > 0) {
        btnParked.style.display = '';
        parkedCount.textContent = STATE.parked.length;
    } else {
        btnParked.style.display = 'none';
    }

    // Header wholesale
    document.getElementById('camHeader').classList.toggle('wholesale', STATE.isWholesale);
    const camTitle = document.getElementById('camTitle');
    camTitle.textContent = STATE.isWholesale
        ? (STATE.customerName || 'Едро')
        : '<?= $page_title ?>';

    // Cart
    if (STATE.cart.length === 0) {
        empty.style.display = '';
        summary.style.display = 'none';
        btnPay.disabled = true;
        zone.querySelectorAll('.cart-item').forEach(el => el.remove());
        return;
    }

    empty.style.display = 'none';
    summary.style.display = '';

    // Remove old items
    zone.querySelectorAll('.cart-item').forEach(el => el.remove());

    // Build cart items
    STATE.cart.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = 'cart-item' + (idx === STATE.selectedIndex ? ' selected' : '');
        const lineTotal = item.unit_price * item.quantity * (1 - (item.discount_pct || 0) / 100);
        div.innerHTML = `
            <div class="ci-info">
                <div class="ci-name">${esc(item.name)}</div>
                <div class="ci-meta">${esc(item.code || '')}${item.meta ? ' · ' + esc(item.meta) : ''}</div>
            </div>
            <div class="ci-right">
                <div class="ci-qty">x${item.quantity}</div>
                <div class="ci-price">${fmtPrice(lineTotal)}</div>
            </div>
            <div class="ci-delete">Изтрий</div>
        `;

        // Tap = select + qty mode
        div.addEventListener('click', (e) => {
            if (e.target.closest('.ci-delete')) return;
            selectCartItem(idx);
        });

        // Long press = popup numpad
        let lpTimer;
        div.addEventListener('touchstart', (e) => {
            lpTimer = setTimeout(() => openLpPopup(idx), 500);
        }, {passive: true});
        div.addEventListener('touchend', () => clearTimeout(lpTimer));
        div.addEventListener('touchmove', () => clearTimeout(lpTimer));

        // Swipe left to delete
        let sx = 0;
        div.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, {passive: true});
        div.addEventListener('touchmove', (e) => {
            const dx = e.touches[0].clientX - sx;
            if (dx < -40) div.classList.add('swiped');
            else div.classList.remove('swiped');
        }, {passive: true});
        div.addEventListener('touchend', () => {
            if (div.classList.contains('swiped')) {
                div.querySelector('.ci-delete').addEventListener('click', () => removeItem(idx));
            }
        });

        zone.appendChild(div);
    });

    // Summary
    const total = getTotal();
    const count = getItemCount();
    sumCount.textContent = count + ' арт.';
    sumTotal.textContent = fmtPrice(total);
    sumTotal.classList.add('changed');
    setTimeout(() => sumTotal.classList.remove('changed'), 1500);

    // Pay button
    btnPay.disabled = false;
    payAmt.textContent = fmtPrice(total);

    // Discount button state
    document.getElementById('sumDiscountBtn').classList.toggle('active', STATE.discountPct > 0);
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ─── CART OPERATIONS ───
function addToCart(product) {
    const price = STATE.isWholesale
        ? (parseFloat(product.wholesale_price) || parseFloat(product.retail_price))
        : parseFloat(product.retail_price);

    // Check if already in cart
    const existing = STATE.cart.findIndex(it => it.product_id === product.id);
    if (existing >= 0) {
        STATE.cart[existing].quantity++;
        beep(1400, 0.1);
        beep(1800, 0.1); // double beep
    } else {
        STATE.cart.push({
            product_id: product.id,
            code: product.code || '',
            name: product.name || '',
            meta: '',
            unit_price: price,
            quantity: 1,
            discount_pct: 0,
            image: product.image || '',
        });
        beep(1200, 0.15);
    }

    greenFlash();
    if (navigator.vibrate) navigator.vibrate(50);

    // Reset context to code
    setNumpadCtx('code');
    STATE.numpadInput = '';
    updateSearchDisplay();
    closeSearchResults();
    render();
}

function selectCartItem(idx) {
    if (STATE.selectedIndex === idx) {
        // Tap again = increment qty
        STATE.cart[idx].quantity++;
        beep(1000, 0.08);
    } else {
        STATE.selectedIndex = idx;
        setNumpadCtx('qty');
        STATE.numpadInput = '';
    }
    render();
}

function removeItem(idx) {
    STATE.undoItem = STATE.cart.splice(idx, 1)[0];
    STATE.undoIndex = idx;
    STATE.selectedIndex = -1;
    setNumpadCtx('code');
    render();
    showUndo();
}

function showUndo() {
    const bar = document.getElementById('undoBar');
    document.getElementById('undoText').textContent = STATE.undoItem.name + ' изтрит';
    bar.classList.add('show');
    clearTimeout(STATE.undoTimeout);
    STATE.undoTimeout = setTimeout(() => {
        bar.classList.remove('show');
        STATE.undoItem = null;
    }, 3000);
}

document.getElementById('undoBtn').addEventListener('click', () => {
    if (STATE.undoItem) {
        STATE.cart.splice(STATE.undoIndex, 0, STATE.undoItem);
        STATE.undoItem = null;
        document.getElementById('undoBar').classList.remove('show');
        clearTimeout(STATE.undoTimeout);
        render();
    }
});

// ─── NUMPAD CONTEXT ───
function setNumpadCtx(ctx) {
    STATE.numpadCtx = ctx;
    const label = document.getElementById('ctxLabel');
    label.className = 'ctx-label';
    switch (ctx) {
        case 'code':
            label.classList.add('ctx-code');
            label.textContent = 'КОД';
            break;
        case 'qty':
            label.classList.add('ctx-qty');
            label.textContent = 'БРОЙКИ';
            break;
        case 'discount':
            label.classList.add('ctx-discount');
            label.textContent = 'ОТСТЪПКА %';
            break;
        case 'received':
            label.classList.add('ctx-received');
            label.textContent = 'ПОЛУЧЕНО';
            break;
    }
}

// ─── NUMPAD INPUT ───
function numPress(key) {
    if (key === 'C') {
        STATE.numpadInput = '';
        if (STATE.numpadCtx === 'code') {
            STATE.searchText = '';
            updateSearchDisplay();
            closeSearchResults();
        } else if (STATE.numpadCtx === 'received') {
            STATE.receivedAmount = 0;
            updatePayment();
        }
        return;
    }
    if (key === '⌫') {
        STATE.numpadInput = STATE.numpadInput.slice(0, -1);
        if (STATE.numpadCtx === 'code') {
            STATE.searchText = STATE.numpadInput;
            updateSearchDisplay();
            triggerSearch();
        } else if (STATE.numpadCtx === 'received') {
            STATE.receivedAmount = parseFloat(STATE.numpadInput) || 0;
            updatePayment();
        }
        return;
    }

    if (key === '00') {
        STATE.numpadInput += '00';
    } else if (key === '.') {
        if (!STATE.numpadInput.includes('.')) STATE.numpadInput += '.';
    } else {
        STATE.numpadInput += key;
    }

    if (STATE.numpadCtx === 'code') {
        STATE.searchText = STATE.numpadInput;
        updateSearchDisplay();
        triggerSearch();
    } else if (STATE.numpadCtx === 'received') {
        STATE.receivedAmount = parseFloat(STATE.numpadInput) || 0;
        updatePayment();
    }
}

function numOk() {
    const val = STATE.numpadInput;

    switch (STATE.numpadCtx) {
        case 'code':
            if (val.length > 0) doSearch(val);
            break;

        case 'qty':
            if (STATE.selectedIndex >= 0 && val.length > 0) {
                const q = parseInt(val) || 0;
                if (q > 0) {
                    STATE.cart[STATE.selectedIndex].quantity = q;
                } else {
                    removeItem(STATE.selectedIndex);
                }
            }
            STATE.selectedIndex = -1;
            setNumpadCtx('code');
            STATE.numpadInput = '';
            render();
            break;

        case 'discount':
            if (val.length > 0) applyDiscount(parseFloat(val));
            closeDiscount();
            setNumpadCtx('code');
            STATE.numpadInput = '';
            break;

        case 'received':
            if (val.length > 0) {
                STATE.receivedAmount = parseFloat(val) || 0;
                updatePayment();
            }
            STATE.numpadInput = '';
            break;
    }
}

// ─── SEARCH ───
function updateSearchDisplay() {
    const display = document.getElementById('searchDisplay');
    if (STATE.searchText.length > 0) {
        display.innerHTML = '<span style="color:var(--indigo-400);margin-right:4px">🔍</span>' + esc(STATE.searchText) + '<span style="animation:blink 1s infinite">_</span>';
    } else {
        display.innerHTML = '<span class="placeholder">🔍 Код, име или баркод</span>';
    }
}

function triggerSearch() {
    clearTimeout(STATE.searchTimeout);
    if (STATE.searchText.length < 1) {
        closeSearchResults();
        return;
    }
    STATE.searchTimeout = setTimeout(() => doSearch(STATE.searchText), 300);
}

let nfTimeout;
function showNoResult() {
    const popup = document.getElementById('nfPopup');
    popup.classList.add('show');
    beep(400, 0.2);
    if (navigator.vibrate) navigator.vibrate([50, 50, 50]);
    clearTimeout(nfTimeout);
    nfTimeout = setTimeout(() => {
        popup.classList.remove('show');
    }, 1500);
}

function doSearch(q) {
    fetch('sale.php?action=quick_search&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(results => {
            const container = document.getElementById('searchResults');
            container.innerHTML = '';
            if (results.length === 0) {
                container.classList.remove('open');
                showNoResult();
                return;
            }
            results.forEach(p => {
                const div = document.createElement('div');
                div.className = 'sr-item';
                const price = STATE.isWholesale
                    ? (parseFloat(p.wholesale_price) || parseFloat(p.retail_price))
                    : parseFloat(p.retail_price);
                const stockCls = parseInt(p.stock) === 0 ? 'sr-stock zero' : 'sr-stock';
                div.innerHTML = `
                    <span class="sr-code">${esc(p.code || '')}</span>
                    <span class="sr-name">${esc(p.name)}</span>
                    <span class="sr-price">${fmtPrice(price)}</span>
                    <span class="${stockCls}">(${p.stock})</span>
                `;
                div.addEventListener('click', () => addToCart(p));
                container.appendChild(div);
            });
            container.classList.add('open');
        })
        .catch(err => console.error('Search error:', err));
}

function closeSearchResults() {
    document.getElementById('searchResults').classList.remove('open');
}

// ─── KEYBOARD ───
function toggleKeyboard() {
    STATE.keyboardVisible = !STATE.keyboardVisible;
    document.getElementById('keyboardZone').classList.toggle('visible', STATE.keyboardVisible);
    document.getElementById('numpadZone').style.display = STATE.keyboardVisible ? 'none' : '';
    if (STATE.keyboardVisible) {
        setNumpadCtx('code');
    }
}

function kbPress(key) {
    if (key === '⌫') {
        STATE.searchText = STATE.searchText.slice(0, -1);
    } else {
        STATE.searchText += key;
    }
    STATE.numpadInput = STATE.searchText;
    updateSearchDisplay();
    triggerSearch();
}

// ─── DISCOUNT ───
function toggleDiscount() {
    const chips = document.getElementById('discountChips');
    const visible = chips.classList.contains('visible');
    if (visible) {
        closeDiscount();
    } else {
        chips.classList.add('visible');
        setNumpadCtx('discount');
        STATE.numpadInput = '';
    }
}

function applyDiscount(pct) {
    pct = parseFloat(pct) || 0;
    if (pct > STATE.maxDiscount) {
        showToast('Максимум ' + STATE.maxDiscount + '%');
        pct = STATE.maxDiscount;
    }
    STATE.discountPct = pct;
    // Highlight active chip
    document.querySelectorAll('.dc-chip').forEach(c => {
        c.classList.toggle('active', parseFloat(c.textContent) === pct);
    });
    setNumpadCtx('code');
    STATE.numpadInput = '';
    render();
}

function closeDiscount() {
    document.getElementById('discountChips').classList.remove('visible');
    setNumpadCtx('code');
}

// ─── PAYMENT ───
function openPayment() {
    if (STATE.cart.length === 0) return;
    STATE.payMethod = 'cash';
    STATE.receivedAmount = 0;
    document.querySelectorAll('.pm-chip').forEach(c => c.classList.toggle('active', c.dataset.method === 'cash'));
    document.getElementById('cashSection').style.display = '';
    document.getElementById('payDueAmount').textContent = fmtPrice(getTotal()) + ' ' + STATE.currency;
    document.getElementById('payRecvAmount').textContent = '0,00 ' + STATE.currency;
    document.getElementById('payChangeBox').style.display = 'none';
    document.getElementById('btnConfirm').disabled = true;
    setNumpadCtx('received');
    STATE.numpadInput = '';

    document.getElementById('payOverlay').classList.add('open');
    document.getElementById('paySheet').classList.add('open');
}

function closePayment() {
    document.getElementById('payOverlay').classList.remove('open');
    document.getElementById('paySheet').classList.remove('open');
    setNumpadCtx('code');
    STATE.numpadInput = '';
}

function setPayMethod(method) {
    STATE.payMethod = method;
    document.querySelectorAll('.pm-chip').forEach(c => c.classList.toggle('active', c.dataset.method === method));

    const cashSec = document.getElementById('cashSection');
    const btnConfirm = document.getElementById('btnConfirm');

    if (method === 'cash') {
        cashSec.style.display = '';
        setNumpadCtx('received');
        btnConfirm.disabled = STATE.receivedAmount < getTotal();
    } else {
        cashSec.style.display = 'none';
        setNumpadCtx('code');
        btnConfirm.disabled = false;
    }
}

function payBanknote(val) {
    if (val === 'exact') {
        STATE.receivedAmount = getTotal();
    } else {
        STATE.receivedAmount = parseFloat(val) || 0;
    }
    STATE.numpadInput = '';
    updatePayment();
}

function updatePayment() {
    const total = getTotal();
    document.getElementById('payRecvAmount').textContent = fmtPrice(STATE.receivedAmount) + ' ' + STATE.currency;

    const changeBox = document.getElementById('payChangeBox');
    const btnConfirm = document.getElementById('btnConfirm');

    if (STATE.receivedAmount >= total) {
        const change = STATE.receivedAmount - total;
        document.getElementById('payChangeAmount').textContent = fmtPrice(change) + ' ' + STATE.currency;
        changeBox.style.display = '';
        btnConfirm.disabled = false;
    } else {
        changeBox.style.display = 'none';
        btnConfirm.disabled = true;
    }
}

function confirmPayment() {
    const data = {
        items: STATE.cart.map(it => ({
            product_id: it.product_id,
            quantity: it.quantity,
            unit_price: it.unit_price,
            discount_pct: it.discount_pct || 0,
        })),
        payment_method: STATE.payMethod,
        discount_pct: STATE.discountPct,
        customer_id: STATE.customerId,
        received: STATE.receivedAmount,
        is_wholesale: STATE.isWholesale,
    };

    document.getElementById('btnConfirm').disabled = true;

    fetch('sale.php?action=save_sale', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data),
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            ching();
            greenFlash();
            if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
            showToast('✓ Продажба #' + res.sale_id + ' записана', 'success');
            closePayment();

            // Reset
            STATE.cart = [];
            STATE.selectedIndex = -1;
            STATE.discountPct = 0;
            STATE.numpadInput = '';
            STATE.searchText = '';
            setNumpadCtx('code');
            updateSearchDisplay();
            render();
        } else {
            showToast('Грешка: ' + (res.error || 'Неизвестна'), '', 4000);
            document.getElementById('btnConfirm').disabled = false;
        }
    })
    .catch(err => {
        showToast('Мрежова грешка', '', 3000);
        document.getElementById('btnConfirm').disabled = false;
    });
}

// ─── WHOLESALE ───
function openWholesale() {
    document.getElementById('wsOverlay').classList.add('open');
    document.getElementById('wsSheet').classList.add('open');
    document.getElementById('wsRetail').style.display = STATE.isWholesale ? '' : 'none';
}

function closeWholesale() {
    document.getElementById('wsOverlay').classList.remove('open');
    document.getElementById('wsSheet').classList.remove('open');
}

function selectClient(id, name) {
    closeWholesale();
    if (id === null) {
        STATE.isWholesale = false;
        STATE.customerId = null;
        STATE.customerName = '';
    } else {
        STATE.isWholesale = true;
        STATE.customerId = id;
        STATE.customerName = name;
    }
    // Recalculate prices in cart
    STATE.cart.forEach(it => {
        // We'd need to refetch prices — for now keep current
    });
    render();
}

// ─── PARKING ───
function parkSale() {
    if (STATE.cart.length === 0) { showToast('Количката е празна'); return; }
    STATE.parked.push({
        cart: [...STATE.cart],
        customer: STATE.customerName,
        customerId: STATE.customerId,
        isWholesale: STATE.isWholesale,
        discountPct: STATE.discountPct,
        time: new Date().toLocaleTimeString('bg-BG', {hour: '2-digit', minute: '2-digit'}),
        total: getTotal(),
    });
    saveParked();
    showToast('🅿️ Продажба паркирана');

    STATE.cart = [];
    STATE.selectedIndex = -1;
    STATE.discountPct = 0;
    STATE.numpadInput = '';
    STATE.searchText = '';
    setNumpadCtx('code');
    updateSearchDisplay();
    render();
}

function saveParked() {
    localStorage.setItem('rms_parked_' + STATE.storeId, JSON.stringify(STATE.parked));
}

function openParked() {
    if (STATE.parked.length === 0) return;
    const container = document.getElementById('parkedContainer');
    container.innerHTML = '<div class="parked-title">Паркирани продажби</div>';

    STATE.parked.forEach((p, idx) => {
        const card = document.createElement('div');
        card.className = 'parked-card';
        card.style.animationDelay = (idx * 0.05) + 's';
        const count = p.cart.reduce((s, it) => s + it.quantity, 0);
        card.innerHTML = `
            <div class="pc-header">
                <span class="pc-client">${esc(p.customer || 'Дребно')}</span>
                <span class="pc-time">${p.time}</span>
            </div>
            <div class="pc-info">${count} артикула</div>
            <div class="pc-total">${fmtPrice(p.total)} ${STATE.currency}</div>
            <span class="pc-delete" data-idx="${idx}">✕ Изтрий</span>
        `;
        card.addEventListener('click', (e) => {
            if (e.target.classList.contains('pc-delete')) {
                STATE.parked.splice(idx, 1);
                saveParked();
                closeParked();
                render();
                return;
            }
            // Restore
            if (STATE.cart.length > 0) {
                parkSale(); // auto-park current
            }
            STATE.cart = p.cart;
            STATE.customerId = p.customerId;
            STATE.customerName = p.customer;
            STATE.isWholesale = p.isWholesale;
            STATE.discountPct = p.discountPct || 0;
            STATE.parked.splice(idx, 1);
            saveParked();
            closeParked();
            render();
        });
        container.appendChild(card);
    });

    document.getElementById('parkedOverlay').classList.add('open');
}

function closeParked() {
    document.getElementById('parkedOverlay').classList.remove('open');
}

// ─── LONG PRESS POPUP ───
let lpTargetIdx = -1;
let lpValue = '';

function openLpPopup(idx) {
    lpTargetIdx = idx;
    lpValue = '';
    document.getElementById('lpTitle').textContent = STATE.cart[idx].name;
    document.getElementById('lpDisplay').textContent = STATE.cart[idx].quantity;
    const popup = document.getElementById('lpPopup');
    popup.style.left = '50%';
    popup.style.top = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    popup.classList.add('show');
    if (navigator.vibrate) navigator.vibrate(30);
}

function closeLpPopup() {
    document.getElementById('lpPopup').classList.remove('show');
    lpTargetIdx = -1;
}

function lpNum(key) {
    if (key === 'C') { lpValue = ''; }
    else if (key === '⌫') { lpValue = lpValue.slice(0, -1); }
    else { lpValue += key; }
    document.getElementById('lpDisplay').textContent = lpValue || '0';
}

function confirmLpPopup() {
    if (lpTargetIdx >= 0 && lpValue.length > 0) {
        const q = parseInt(lpValue) || 0;
        if (q > 0) {
            STATE.cart[lpTargetIdx].quantity = q;
        } else {
            removeItem(lpTargetIdx);
        }
        render();
    }
    closeLpPopup();
}

// ─── CAMERA & BARCODE ───
let barcodeDetector;
let scanRAF;

async function startCamera() {
    const video = document.getElementById('cameraVideo');
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: {ideal: 1280}, height: {ideal: 720} }
        });
        video.srcObject = stream;
        STATE.cameraActive = true;
        startBarcodeScanner();
    } catch (e) {
        console.warn('Camera not available');
    }
}

function flashCamScan() {
    const el = document.getElementById('camHeader');
    el.classList.remove('scanned');
    void el.offsetWidth;
    el.classList.add('scanned');
    setTimeout(() => el.classList.remove('scanned'), 500);
}

async function toggleCamera(on) {
    // Legacy — camera is always on now
}

function startBarcodeScanner() {
    if (!('BarcodeDetector' in window)) {
        console.warn('BarcodeDetector not supported');
        return;
    }
    if (!barcodeDetector) {
        barcodeDetector = new BarcodeDetector({
            formats: ['ean_13', 'ean_8', 'code_128', 'qr_code', 'code_39']
        });
    }
    scanLoop();
}

function scanLoop() {
    if (!STATE.cameraActive) return;
    const video = document.getElementById('cameraVideo');
    if (video.readyState < 2) {
        scanRAF = requestAnimationFrame(scanLoop);
        return;
    }
    barcodeDetector.detect(video).then(codes => {
        if (codes.length > 0 && !STATE.scanCooldown) {
            const code = codes[0].rawValue;
            STATE.scanCooldown = true;
            setTimeout(() => { STATE.scanCooldown = false; }, 1500);
            handleBarcode(code);
        }
    }).catch(() => {});
    scanRAF = requestAnimationFrame(scanLoop);
}

function handleBarcode(code) {
    flashCamScan();
    fetch('sale.php?action=barcode_lookup&barcode=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(product => {
            if (product) {
                addToCart(product);
            } else {
                beep(400, 0.3);
                showToast('Баркод не е намерен: ' + code);
            }
        })
        .catch(() => {
            beep(400, 0.3);
            showToast('Грешка при търсене');
        });
}

// ─── VOICE ───
let recognition;
let lastTranscript = '';

function startVoice() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        showToast('Гласовото разпознаване не се поддържа');
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.lang = 'bg-BG';
    recognition.continuous = false;
    recognition.interimResults = false;

    const ov = document.getElementById('recOv');
    const dot = document.getElementById('recDot');
    const label = document.getElementById('recLabel');
    const transcript = document.getElementById('recTranscript');
    const hint = document.getElementById('recHint');
    const sendBtn = document.getElementById('recSend');

    // Context-aware hints
    switch (STATE.numpadCtx) {
        case 'code':
            hint.textContent = 'Напр. "Nike 42 черни" или "Продай 2 Adidas 38"';
            break;
        case 'qty':
            hint.textContent = 'Кажете число, напр. "пет" или "три"';
            break;
        case 'received':
            hint.textContent = 'Кажете сума, напр. "сто лева" или "петдесет"';
            break;
        default:
            hint.textContent = 'Кажете артикул, количество или команда';
    }

    // Reset state — RECORDING
    dot.className = 'rec-dot';
    label.className = 'rec-label recording';
    label.textContent = '● ЗАПИСВА';
    transcript.textContent = 'Слушам...';
    transcript.classList.add('empty');
    sendBtn.disabled = true;
    lastTranscript = '';

    ov.classList.add('open');

    recognition.onresult = (e) => {
        lastTranscript = e.results[0][0].transcript;
        // Switch to READY state
        dot.classList.add('ready');
        label.className = 'rec-label ready';
        label.textContent = '✓ ГОТОВО';
        transcript.textContent = lastTranscript;
        transcript.classList.remove('empty');
        sendBtn.disabled = false;
    };

    recognition.onerror = (e) => {
        dot.classList.add('ready');
        label.className = 'rec-label';
        label.textContent = 'ГРЕШКА';
        label.style.color = 'var(--warning)';
        transcript.textContent = 'Не разбрах, опитайте пак';
        transcript.classList.add('empty');
        setTimeout(() => {
            label.style.color = '';
            ov.classList.remove('open');
        }, 1500);
    };

    recognition.onend = () => {
        // If no result yet, show waiting state
        if (!lastTranscript) {
            dot.classList.add('ready');
            label.className = 'rec-label';
            label.textContent = 'ЧАКАМ';
            label.style.color = 'var(--text-secondary)';
        }
    };

    recognition.start();
}

// Send button
document.getElementById('recSend').addEventListener('click', () => {
    document.getElementById('recOv').classList.remove('open');
    if (lastTranscript) handleVoiceResult(lastTranscript);
});

// Cancel button
document.getElementById('recCancel').addEventListener('click', () => {
    if (recognition) recognition.abort();
    document.getElementById('recOv').classList.remove('open');
});

// Tap on overlay backdrop = close
document.getElementById('recOv').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
        if (recognition) recognition.abort();
        e.currentTarget.classList.remove('open');
    }
});

function handleVoiceResult(text) {
    // Send to AI for parsing
    fetch('sale-voice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            text: text,
            context: STATE.numpadCtx,
            cart: STATE.cart,
            store_id: STATE.storeId,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.action === 'search') {
            STATE.searchText = res.query || text;
            STATE.numpadInput = STATE.searchText;
            updateSearchDisplay();
            doSearch(STATE.searchText);
        } else if (res.action === 'set_qty' && STATE.selectedIndex >= 0) {
            STATE.cart[STATE.selectedIndex].quantity = parseInt(res.value) || 1;
            render();
        } else if (res.action === 'set_received') {
            STATE.receivedAmount = parseFloat(res.value) || 0;
            updatePayment();
        } else if (res.action === 'add_items' && res.items) {
            res.items.forEach(it => {
                if (it.product_id) addToCart(it);
            });
        } else {
            // Fallback: treat as search
            STATE.searchText = text;
            STATE.numpadInput = text;
            updateSearchDisplay();
            doSearch(text);
        }
    })
    .catch(() => {
        // Fallback to search
        STATE.searchText = text;
        STATE.numpadInput = text;
        updateSearchDisplay();
        doSearch(text);
    });
}

// Voice cancel handled above in recCancel listener

// ─── SWIPE NAVIGATION ───
let touchStartX = 0;
const swipePages = ['chat.php', 'warehouse.php', 'stats.php', 'sale.php'];
const currentPageIdx = 3;

document.addEventListener('touchstart', (e) => {
    if (e.target.closest('.cart-zone, .pay-sheet, .ws-sheet, .parked-container, .numpad-grid, .keyboard-zone')) return;
    touchStartX = e.touches[0].clientX;
}, {passive: true});

document.addEventListener('touchend', (e) => {
    if (!touchStartX) return;
    const dx = e.changedTouches[0].clientX - touchStartX;
    touchStartX = 0;
    if (Math.abs(dx) < 80) return;
    if (dx > 0 && currentPageIdx > 0) {
        window.location.href = swipePages[currentPageIdx - 1];
    }
    // No swipe right from last tab
});

// ─── INIT ───
document.addEventListener('DOMContentLoaded', () => {
    render();
    startCamera(); // always-on camera scanner
});

// CSS blink animation for cursor
const blinkStyle = document.createElement('style');
blinkStyle.textContent = '@keyframes blink{0%,50%{opacity:1}51%,100%{opacity:0}}';
document.head.appendChild(blinkStyle);
</script>

<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
</body>
</html>
