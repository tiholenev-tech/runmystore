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

// S91.MIGRATE — body shell mode (sale.php is single-mode POS; placeholder for future dual-mode UX)
$mode = 'simple';

// ─── AJAX: Quick Search ───
if (isset($_GET['action']) && $_GET['action'] === 'quick_search') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $like = "%$q%";
    // S87D — returns parent_id, color, size, image_url for variant grouping in Search Overlay
    $results = DB::run("
        SELECT p.id, p.code, p.name, p.retail_price, p.wholesale_price, p.barcode,
               p.parent_id, p.color, p.size, p.image_url,
               COALESCE(i.quantity, 0) as stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
        WHERE p.tenant_id = ? AND p.is_active = 1
          AND (p.code LIKE ? OR p.name LIKE ? OR p.barcode = ?)
        ORDER BY
          CASE WHEN p.code = ? THEN 0 WHEN p.barcode = ? THEN 1 ELSE 2 END,
          p.name
        LIMIT 30
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

// ─── AJAX: Refetch Prices (S87 Bug #6 — wholesale toggle memory bug fix) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'refetch_prices') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = array_filter(array_map('intval', $body['product_ids'] ?? []));
    if (empty($ids)) { echo json_encode([]); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::run(
        "SELECT id, retail_price, wholesale_price FROM products WHERE tenant_id = ? AND id IN ($placeholders)",
        array_merge([$tenant_id], $ids)
    )->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int)$r['id']] = [
            'retail' => (float)$r['retail_price'],
            'wholesale' => (float)($r['wholesale_price'] ?: $r['retail_price']),
        ];
    }
    echo json_encode($out);
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

        DB::run("INSERT INTO sales (tenant_id, store_id, user_id, customer_id, total, discount_amount, discount_pct, payment_method, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())",
            [$tenant_id, $store_id, $user_id, $customer_id, $total, $discount_amount, $discount_pct, $payment_method]);
        $sale_id = $pdo->lastInsertId();

        foreach ($items as $it) {
            $pid = intval($it['product_id']);
            $qty = intval($it['quantity']);
            $price = floatval($it['unit_price']);
            $idp = floatval($it['discount_pct'] ?? 0);
            $ist = round($price * $qty * (1 - $idp / 100), 2);

            DB::run("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, discount_pct, total) VALUES (?, ?, ?, ?, ?, ?)",
                [$sale_id, $pid, $qty, $price, $idp, $ist]);
            $upd = DB::run("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND store_id = ? AND quantity >= ?",
                [$qty, $pid, $store_id, $qty]);
            if ($upd->rowCount() === 0) {
                throw new Exception("Артикулът свърши преди да го продадеш. Презареди и опитай отново.");
            }
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
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title><?= $page_title ?> — RunMyStore.ai</title>

<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">
<style>
/* ═══════════════════════════════════════════════════════════
   SALE MODULE — Unified Design System 2026
   Reference: warehouse.php
   ═══════════════════════════════════════════════════════════ */
/* S91.MIGRATE — design-kit tokens.css owns hue/theme tokens.
   Only sale-specific tokens kept here (not present in design-kit). */
:root{
    --bg-card-strong:rgba(15,15,40,0.85);
    --indigo-200:hsl(var(--hue1) 60% 85%);
    --bottom-nav-h:64px;
}
:root[data-theme="light"]{
    --bg-card-strong:rgba(255,255,255,0.95);
    --indigo-200:hsl(var(--hue1) 60% 35%);
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{
    height:100%;background:var(--bg-main);color:var(--text-primary);
    font-family:'Montserrat',Inter,system-ui,sans-serif;
    overflow:hidden;touch-action:manipulation;
    -webkit-user-select:none;user-select:none;
}
/* V5 — 3-layer radial + noise overlay */
body::before{
    content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / .22) 0%, transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / .22) 0%, transparent 60%),
        linear-gradient(180deg, #0a0b14 0%, #050609 100%);
    background-attachment: fixed;
}
:root[data-theme="light"] body::before{
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 70% / .25) 0%, transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 70% / .20) 0%, transparent 60%),
        linear-gradient(180deg, #f8faff 0%, #e7ebf5 100%);
}
body::after{
    content:'';position:fixed;inset:0;pointer-events:none;z-index:1;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity:.03;mix-blend-mode:overlay;
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
    width:30px;height:30px;display:flex;align-items:center;justify-content:center;
    border-radius:100px;background:rgba(0,0,0,0.45);backdrop-filter:blur(6px);
    color:rgba(255,255,255,0.85);font-size:14px;border:1px solid rgba(255,255,255,0.08);cursor:pointer;font-family:inherit;
    transition:all 0.15s;
}
.cam-btn:active{background:hsl(var(--hue1) 60% 45% / 0.4);transform:scale(0.9);border-color:hsl(var(--hue1) 60% 55% / 0.4)}
.cam-right{display:flex;gap:6px}
.park-badge{
    position:absolute;top:-3px;right:-3px;
    min-width:16px;height:16px;border-radius:100px;
    background:linear-gradient(135deg,hsl(38 90% 55%),hsl(38 90% 45%));
    color:#fff;font-size:9px;font-weight:900;
    display:flex;align-items:center;justify-content:center;padding:0 4px;
    box-shadow:0 0 8px hsl(38 90% 55% / 0.55),inset 0 1px 0 rgba(255,255,255,0.2);font-variant-numeric:tabular-nums;
}
/* Scan zone corners */
.scan-corner{position:absolute;width:16px;height:16px;z-index:3}
.scan-corner svg{width:100%;height:100%}
.sc-tl{top:14px;left:15%}
.sc-tr{top:14px;right:15%}
.sc-bl{bottom:22px;left:15%}
.sc-br{bottom:22px;right:15%}
/* V5 — Laser line (indigo) */
.scan-laser{
    position:absolute;left:14%;right:14%;height:2px;z-index:3;
    background:linear-gradient(90deg,transparent 5%,hsl(var(--hue1) 70% 60% / 0.85) 50%,transparent 95%);
    box-shadow:0 0 8px hsl(var(--hue1) 70% 60% / 0.6),0 0 18px hsl(var(--hue1) 70% 50% / 0.3);
    animation:scanLine 2.5s ease-in-out infinite;
}
@keyframes scanLine{
    0%{top:20%}50%{top:70%}100%{top:20%}
}
/* Scanner status */
.cam-status{
    position:absolute;bottom:6px;left:0;right:0;
    display:flex;align-items:center;justify-content:center;gap:6px;
    z-index:4;animation:scanPulse 2s ease infinite;
}
@keyframes scanPulse{0%,100%{opacity:0.7}50%{opacity:1}}
.scan-dot{
    width:6px;height:6px;border-radius:50%;background:hsl(var(--hue1) 70% 60%);flex-shrink:0;
    box-shadow:0 0 8px hsl(var(--hue1) 70% 60%);
    animation:dotBlink 1.5s ease infinite;
}
@keyframes dotBlink{
    0%,100%{box-shadow:0 0 6px hsl(var(--hue1) 70% 60%);opacity:1}
    50%{box-shadow:0 0 14px hsl(var(--hue1) 70% 60%),0 0 28px hsl(var(--hue1) 70% 50% / 0.4);opacity:0.6}
}
.cam-status span{
    font-size:9px;font-weight:800;color:rgba(255,255,255,0.85);
    letter-spacing:0.06em;text-transform:uppercase;
}
:root[data-theme="light"] .cam-status span{color:rgba(15,23,42,0.85)}
/* Flash on scan (indigo) */
.cam-header.scanned{animation:camFlash 0.4s ease}
@keyframes camFlash{
    0%{box-shadow:inset 0 0 0 0 hsl(var(--hue1) 70% 60% / 0)}
    30%{box-shadow:inset 0 0 40px 10px hsl(var(--hue1) 70% 60% / 0.5)}
    100%{box-shadow:inset 0 0 0 0 hsl(var(--hue1) 70% 60% / 0)}
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
    flex:1;height:36px;display:flex;align-items:center;padding:0 14px;
    background:var(--bg-card);border:1px solid var(--border-subtle);
    border-radius:100px;color:var(--text-primary);font-size:13px;font-weight:500;
    overflow:hidden;white-space:nowrap;backdrop-filter:blur(6px);
}
:root[data-theme="light"] .search-display{background:rgba(255,255,255,0.85)}
.search-display .placeholder{color:var(--text-muted);font-size:12px;font-weight:500}
/* V5 — Mic button: indigo gradient pill */
.search-btn{
    width:36px;height:36px;display:flex;align-items:center;justify-content:center;
    border-radius:100px;border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--indigo-300);font-size:16px;cursor:pointer;flex-shrink:0;
    transition:all 0.2s;
}
.search-btn:active{background:rgba(99,102,241,0.2);transform:scale(0.92)}
.search-btn#btnVoiceSearch{
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    border-color:hsl(var(--hue1) 60% 55%);color:white;
    box-shadow:0 0 12px hsl(var(--hue1) 60% 45% / 0.45),inset 0 1px 0 rgba(255,255,255,0.25);
    position:relative;
}
/* S87G.B6 — voice inline states */
.search-btn#btnVoiceSearch.voice-recording{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    border-color:#ef4444;color:#fff;
    animation:s87g-voice-pulse 1.1s ease-in-out infinite;
}
.search-btn#btnVoiceSearch.voice-recording::after{
    content:'';position:absolute;top:5px;right:5px;width:8px;height:8px;border-radius:50%;
    background:#fff;box-shadow:0 0 8px rgba(255,255,255,0.9);
    animation:s87g-voice-dot 0.9s ease-in-out infinite;
}
.search-btn#btnVoiceSearch.voice-processing{
    background:linear-gradient(135deg,hsl(var(--hue2) 50% 45%),hsl(var(--hue1) 55% 40%));
    opacity:0.8;
}
.search-btn#btnVoiceSearch.voice-processing::after{
    content:'';position:absolute;top:50%;left:50%;width:14px;height:14px;
    margin:-7px 0 0 -7px;border-radius:50%;
    border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;
    animation:s87g-voice-spin 0.7s linear infinite;
}
@keyframes s87g-voice-pulse{
    0%,100%{box-shadow:0 0 12px rgba(239,68,68,0.5),inset 0 1px 0 rgba(255,255,255,0.25)}
    50%{box-shadow:0 0 22px rgba(239,68,68,0.95),inset 0 1px 0 rgba(255,255,255,0.25)}
}
@keyframes s87g-voice-dot{
    0%,100%{opacity:0.55;transform:scale(0.85)}
    50%{opacity:1;transform:scale(1.15)}
}
@keyframes s87g-voice-spin{to{transform:rotate(360deg)}}
.search-btn#btnKeyboard{font-size:11px;font-weight:800;letter-spacing:0.04em}

/* S87E — real text input replaces search-display (inline search) */
.search-input{
    flex:1;height:36px;padding:0 14px;font-size:13px;font-weight:500;font-family:inherit;
    background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:100px;
    color:var(--text-primary);outline:none;backdrop-filter:blur(6px);
    -webkit-appearance:none;appearance:none;
}
:root[data-theme="light"] .search-input{background:rgba(255,255,255,0.85)}
.search-input::placeholder{color:var(--text-muted);font-size:12px;font-weight:500}
.search-input:focus{border-color:hsl(var(--hue1) 60% 50%);box-shadow:0 0 0 3px hsl(var(--hue1) 60% 50% / 0.15)}

/* S87E — inline search results (no overlay) */
.s-results-inline{
    flex-shrink:0;max-height:50vh;overflow-y:auto;
    margin:6px 8px 0;padding:4px 4px 8px;
    background:var(--bg-card-strong);backdrop-filter:blur(12px);
    border:1px solid var(--border-subtle);border-radius:14px;
    -webkit-overflow-scrolling:touch;
}
:root[data-theme="light"] .s-results-inline{background:rgba(255,255,255,0.92)}
.s-results-inline-meta{
    padding:6px 10px;font-size:10px;color:var(--text-muted);
    font-weight:700;letter-spacing:0.04em;text-transform:uppercase;
}
.s-results-inline-back{
    display:flex;align-items:center;gap:8px;padding:8px 10px;
    background:transparent;border:none;color:var(--indigo-300);
    font-size:12px;font-weight:800;letter-spacing:0.02em;cursor:pointer;font-family:inherit;
}
.s-results-inline-back:active{transform:scale(0.97)}

/* ═══ SEARCH RESULTS ═══ */
.search-results{
    max-height:0;overflow-y:auto;flex-shrink:0;
    background:var(--bg-card-strong);backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border-subtle);
    transition:max-height 0.25s ease;
    margin:0 8px;border-radius:0 0 14px 14px;
}
.search-results.open{max-height:240px;border:1px solid var(--border-subtle);border-top:none}
.sr-item{
    display:flex;align-items:center;justify-content:space-between;
    padding:11px 14px;border-bottom:1px solid var(--border-subtle);
    cursor:pointer;transition:background 0.15s;
}
.sr-item:last-child{border-bottom:none}
.sr-item:active{background:rgba(99,102,241,0.12)}
.sr-code{font-size:10px;color:var(--indigo-300);font-weight:800;margin-right:8px;min-width:54px;letter-spacing:0.04em}
.sr-name{flex:1;font-size:12px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sr-price{font-size:13px;font-weight:900;color:var(--indigo-300);margin-left:8px;white-space:nowrap;font-variant-numeric:tabular-nums}
.sr-stock{font-size:9px;color:var(--text-muted);margin-left:6px;font-weight:700;font-variant-numeric:tabular-nums}
.sr-stock.zero{color:var(--danger)}

/* ═══ CART ═══ */
.cart-zone{
    flex:1;overflow-y:auto;padding:0;
    -webkit-overflow-scrolling:touch;
}
.cart-empty{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    height:100%;gap:14px;color:var(--text-muted);padding:40px 20px;
}
.cart-empty-icon{font-size:54px;opacity:0.25;filter:drop-shadow(0 0 12px hsl(var(--hue1) 60% 50% / 0.3))}
.cart-empty-text{font-size:13px;font-weight:600;letter-spacing:0.02em}
/* V5 — set-row pattern: glass card with shine, indigo selected accent */
.cart-item{
    display:flex;align-items:center;gap:11px;padding:11px 13px;
    margin:6px 8px;border-radius:14px;
    background:var(--bg-card);border:1px solid var(--border-subtle);
    position:relative;overflow:hidden;
    animation:cardIn 0.25s ease both;
    cursor:pointer;transition:all 0.15s;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);
}
:root[data-theme="light"] .cart-item{background:rgba(255,255,255,0.65);border-color:rgba(15,23,42,0.06);box-shadow:inset 0 1px 0 rgba(255,255,255,0.5)}
.cart-item:active{background:rgba(99,102,241,0.10)}
.cart-item.selected{
    border-color:hsl(var(--hue1) 65% 55% / 0.5);
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 25% / 0.6),hsl(var(--hue2) 35% 20% / 0.5));
    box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / 0.3),inset 0 1px 0 rgba(255,255,255,0.06);
}
:root[data-theme="light"] .cart-item.selected{background:linear-gradient(135deg,hsl(var(--hue1) 50% 88%),hsl(var(--hue2) 55% 92%));border-color:hsl(var(--hue1) 40% 75%)}
.ci-info{flex:1;min-width:0}
.ci-name{font-size:12px;font-weight:700;color:var(--text-primary);letter-spacing:-0.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ci-meta{font-size:9px;color:var(--text-muted);font-weight:600;margin-top:2px;font-variant-numeric:tabular-nums}
.ci-right{text-align:right;flex-shrink:0;margin-left:8px;display:flex;flex-direction:column;align-items:flex-end;gap:2px;min-width:60px}
.ci-qty{font-size:11px;font-weight:900;color:var(--indigo-300);font-variant-numeric:tabular-nums}
.ci-price{font-size:13px;font-weight:900;color:var(--text-primary);font-variant-numeric:tabular-nums}
.ci-delete{
    position:absolute;right:0;top:0;bottom:0;width:72px;
    background:hsl(0 70% 55%);color:#fff;font-size:11px;font-weight:800;
    display:flex;align-items:center;justify-content:center;letter-spacing:0.04em;
    transform:translateX(100%);transition:transform 0.2s;border-radius:0 14px 14px 0;
}
.cart-item.swiped .ci-delete{transform:translateX(0)}

/* ═══ SUMMARY BAR ═══ */
/* V5 — Summary bar: stat-num gradient on total */
.summary-bar{
    display:flex;align-items:center;justify-content:space-between;
    height:44px;padding:0 14px;flex-shrink:0;
    background:var(--bg-card-strong);backdrop-filter:blur(8px);
    border-top:1px solid var(--border-subtle);
    border-bottom:1px solid var(--border-subtle);
}
.sum-count{font-size:11px;font-weight:700;color:var(--text-muted);letter-spacing:0.02em;font-variant-numeric:tabular-nums}
.sum-discount{
    width:36px;height:28px;border-radius:100px;
    background:var(--bg-card);border:1px solid var(--border-subtle);
    color:var(--indigo-300);font-size:12px;font-weight:800;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    transition:all 0.2s;
}
.sum-discount:active{background:rgba(99,102,241,0.2);transform:scale(0.92)}
.sum-discount.active{background:rgba(234,179,8,0.18);border-color:rgba(234,179,8,0.45);color:var(--warning);box-shadow:0 0 12px rgba(234,179,8,0.3)}
.sum-total{font-size:13px;font-weight:800;color:var(--text-secondary);letter-spacing:0.02em}
.sum-total .amount{
    font-size:18px;font-weight:900;letter-spacing:-0.02em;font-variant-numeric:tabular-nums;
    background:linear-gradient(135deg,#fff 0%,hsl(var(--hue1) 60% 85%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
}
:root[data-theme="light"] .sum-total .amount{background:linear-gradient(135deg,#1e1b4b 0%,#4338ca 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.sum-total .amount.changed{animation:shimmer 1.5s linear 1}

/* ═══ ACTION BAR ═══ */
.action-bar{
    display:flex;align-items:center;gap:8px;padding:10px 12px;flex-shrink:0;
    background:linear-gradient(180deg,hsl(220 25% 6% / 0.85),hsl(220 25% 4% / 0.95));
    backdrop-filter:blur(12px);
    border-top:1px solid var(--border-subtle);
}
:root[data-theme="light"] .action-bar{background:linear-gradient(180deg,hsl(215deg 40% 99% / 0.85),hsl(215deg 40% 97% / 0.95))}
/* V5 — act-block.indigo style: pill with gradient + inset glow */
.btn-pay{
    flex:3;height:42px;border-radius:100px;cursor:pointer;
    background:linear-gradient(135deg,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 35%, hsl(220 30% 10%)) 0%,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 20%, hsl(220 30% 8%)) 100%);
    border:1px solid hsl(var(--hue1) 65% 55% / 0.5);
    color:#fff;font-size:14px;font-weight:900;font-family:inherit;letter-spacing:0.04em;
    display:flex;align-items:center;justify-content:center;gap:8px;
    box-shadow:0 4px 14px hsl(var(--hue1) 65% 55% / 0.35),inset 0 1px 0 rgba(255,255,255,0.12),inset 0 0 20px hsl(var(--hue1) 65% 55% / 0.10);
    transition:transform 0.15s var(--ease),box-shadow 0.15s;
    text-shadow:0 0 8px rgba(0,0,0,0.3);
}
.btn-pay:active{transform:scale(0.97);box-shadow:0 2px 10px hsl(var(--hue1) 65% 55% / 0.25)}
.btn-pay:disabled{opacity:0.3;pointer-events:none}
.btn-park{
    flex:1;height:42px;border-radius:100px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--indigo-300);font-size:18px;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    transition:all 0.2s;backdrop-filter:blur(8px);
}
:root[data-theme="light"] .btn-park{background:rgba(255,255,255,0.85)}
.btn-park:active{background:rgba(99,102,241,0.12);transform:scale(0.95)}

/* ═══ NUMPAD ═══ */
.numpad-zone{flex-shrink:0;background:linear-gradient(180deg,hsl(220 25% 6% / 0.92),hsl(220 25% 4% / 0.97));padding:0 8px calc(132px + env(safe-area-inset-bottom,0px));backdrop-filter:blur(16px)}
:root[data-theme="light"] .numpad-zone{background:linear-gradient(180deg,hsl(215deg 40% 99% / 0.92),hsl(215deg 40% 97% / 0.97))}
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
/* V5 — .np pattern: rounded 14px with backdrop-filter glass */
.np-btn{
    height:36px;border-radius:14px;border:1px solid var(--border-subtle);
    background:var(--bg-card);color:var(--text-primary);
    font-size:16px;font-weight:800;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all 0.12s;
    backdrop-filter:blur(8px);font-variant-numeric:tabular-nums;
}
:root[data-theme="light"] .np-btn{background:rgba(255,255,255,0.85)}
.np-btn:active{transform:scale(0.94);background:hsl(var(--hue1) 40% 22% / 0.5);border-color:var(--border-glow)}
:root[data-theme="light"] .np-btn:active{background:hsl(var(--hue1) 60% 88%)}
.np-btn.fn{color:var(--text-muted);font-size:14px;font-weight:700}
.np-btn.ok{
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 42%));
    border-color:hsl(var(--hue1) 60% 55%);color:#fff;font-weight:900;font-size:13px;letter-spacing:0.04em;
    box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / 0.45),inset 0 1px 0 rgba(255,255,255,0.2);
}
.np-btn.ok:active{box-shadow:0 1px 6px hsl(var(--hue1) 60% 45% / 0.3)}
.np-btn.mic{color:hsl(var(--hue1) 60% 78%);font-size:18px}
.np-btn.clear{color:var(--danger);font-weight:800}

/* ═══ LETTER KEYBOARD ═══ */
.keyboard-zone{
    flex-shrink:0;background:linear-gradient(180deg,hsl(220 25% 6% / 0.92),hsl(220 25% 4% / 0.97));padding:4px 4px calc(80px + env(safe-area-inset-bottom,0px));
    backdrop-filter:blur(16px);display:none;
}
:root[data-theme="light"] .keyboard-zone{background:linear-gradient(180deg,hsl(215deg 40% 99% / 0.92),hsl(215deg 40% 97% / 0.97))}
.keyboard-zone.visible{display:block}
.kb-row{display:flex;justify-content:center;gap:3px;margin-bottom:3px}
.kb-key{
    min-width:30px;height:38px;border-radius:10px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--text-primary);font-size:14px;font-weight:700;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all 0.1s;padding:0 2px;backdrop-filter:blur(4px);
}
:root[data-theme="light"] .kb-key{background:rgba(255,255,255,0.85)}
.kb-key:active{background:hsl(var(--hue1) 60% 45% / 0.25);transform:scale(0.9);border-color:var(--border-glow)}
.kb-key.wide{min-width:50px;font-size:11px;color:var(--indigo-300);font-weight:800;letter-spacing:0.04em}
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
/* V5 — stat-label uppercase + stat-num.lg gradient */
.pay-due{font-size:9px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:hsl(var(--hue1) 50% 70%);text-shadow:0 0 8px hsl(var(--hue1) 70% 50% / 0.25)}
.pay-due-amount{
    font-size:42px;font-weight:900;letter-spacing:-0.03em;line-height:1;font-variant-numeric:tabular-nums;
    background:linear-gradient(135deg,#fff 0%,hsl(var(--hue1) 60% 85%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
}
:root[data-theme="light"] .pay-due-amount{background:linear-gradient(135deg,#1e1b4b 0%,#4338ca 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.pay-close{
    width:32px;height:32px;border-radius:100px;
    background:var(--bg-card);border:1px solid var(--border-subtle);
    color:var(--indigo-300);font-size:14px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;backdrop-filter:blur(6px);
}
.pay-methods{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
/* V5 — pkg-card pill style: pill, indigo border on active, glow */
.pm-chip{
    flex:1;min-width:70px;height:42px;border-radius:100px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--text-secondary);font-size:12px;font-weight:700;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:4px;
    cursor:pointer;transition:all 0.2s;backdrop-filter:blur(6px);
    letter-spacing:0.02em;
}
:root[data-theme="light"] .pm-chip{background:rgba(255,255,255,0.85)}
.pm-chip.active{
    border-color:hsl(var(--hue1) 65% 55% / 0.6);
    background:linear-gradient(135deg,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 25%, transparent) 0%,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 12%, transparent) 100%);
    color:var(--indigo-300);font-weight:800;
    box-shadow:0 0 14px hsl(var(--hue1) 65% 55% / 0.25),inset 0 1px 0 rgba(255,255,255,0.08);
}
.pm-chip:active{transform:scale(0.95)}

/* Payment received section */
.pay-received{margin-bottom:12px}
.pay-recv-label{font-size:9px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px}
.pay-recv-amount{
    font-size:24px;font-weight:900;color:var(--text-primary);letter-spacing:-0.02em;
    text-align:center;padding:8px 0;font-variant-numeric:tabular-nums;
}
/* V5 — Banknotes pill grid */
.pay-banknotes{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin:6px 0 14px}
.bn-chip{
    padding:11px 4px;border-radius:100px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--text-primary);font-size:13px;font-weight:800;font-family:inherit;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all 0.15s;
    text-align:center;font-variant-numeric:tabular-nums;backdrop-filter:blur(6px);
}
:root[data-theme="light"] .bn-chip{background:rgba(255,255,255,0.85)}
.bn-chip:active{background:rgba(99,102,241,0.15);transform:scale(0.93)}
.bn-chip.exact{
    background:linear-gradient(135deg,hsl(var(--hue1) 55% 32%),hsl(var(--hue2) 60% 26%));
    border-color:hsl(var(--hue1) 55% 45%);
    color:hsl(var(--hue1) 60% 92%);font-weight:900;letter-spacing:0.04em;
    box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / 0.35);
}

/* V5 — Change display: briefing-section.q3 (success green) */
.pay-change{
    position:relative;background:linear-gradient(135deg,rgba(34,197,94,0.10),rgba(0,0,0,0.15));
    border:1px solid rgba(34,197,94,0.28);
    border-radius:14px;padding:14px;text-align:center;margin-bottom:16px;overflow:hidden;
    backdrop-filter:blur(8px);
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 4px 12px rgba(0,0,0,0.2);
}
.pay-change::before{
    content:'';position:absolute;top:0;left:0;bottom:0;width:3px;border-radius:14px 0 0 14px;
    background:linear-gradient(180deg,hsl(145 70% 50%) 0%,transparent 100%);
    box-shadow:0 0 20px 1px hsl(145 70% 50%);opacity:0.9;
}
:root[data-theme="light"] .pay-change{background:linear-gradient(135deg,hsl(145 60% 96% / 0.85),rgba(255,255,255,0.92));border-color:hsl(145 50% 60% / 0.45)}
.pay-change-label{font-size:9px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:hsl(145 70% 65%);text-shadow:0 0 8px hsl(145 70% 50% / 0.3);margin-bottom:6px}
.pay-change-amount{
    font-size:32px;font-weight:900;letter-spacing:-0.03em;font-variant-numeric:tabular-nums;
    background:linear-gradient(135deg,#fff 0%,hsl(145 70% 80%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
}
:root[data-theme="light"] .pay-change-amount{background:linear-gradient(135deg,#14532d,#16a34a);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}

/* V5 — MEGA pkg-buy pill: large glow indigo */
.btn-confirm{
    width:100%;padding:14px 16px;border-radius:100px;cursor:pointer;
    background:linear-gradient(135deg,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 60%, hsl(220 30% 10%)) 0%,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 40%, hsl(220 30% 8%)) 100%);
    border:1px solid hsl(var(--hue1) 65% 55% / 0.6);
    color:#fff;font-size:13px;font-weight:900;font-family:inherit;letter-spacing:0.06em;
    display:flex;align-items:center;justify-content:center;gap:8px;
    box-shadow:0 6px 20px hsl(var(--hue1) 65% 55% / 0.5),inset 0 1px 0 rgba(255,255,255,0.18),inset 0 0 24px hsl(var(--hue1) 65% 55% / 0.18);
    transition:all 0.2s;
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
    height:30px;padding:0 14px;border-radius:100px;
    border:1px solid rgba(234,179,8,0.30);background:rgba(234,179,8,0.10);
    color:#facc15;font-size:12px;font-weight:800;font-family:inherit;letter-spacing:0.02em;
    cursor:pointer;transition:all 0.15s;backdrop-filter:blur(4px);
}
:root[data-theme="light"] .dc-chip{color:hsl(38 70% 35%)}
.dc-chip:active{transform:scale(0.93)}
.dc-chip.active{background:rgba(234,179,8,0.22);border-color:rgba(234,179,8,0.55);box-shadow:0 0 12px rgba(234,179,8,0.25)}
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
.ws-title{font-size:14px;font-weight:800;color:var(--text-primary);text-align:center;margin-bottom:16px;letter-spacing:-0.01em}
.ws-item{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 14px;background:var(--bg-card);border:1px solid var(--border-subtle);
    cursor:pointer;transition:all 0.15s;border-radius:14px;margin-bottom:6px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);
}
:root[data-theme="light"] .ws-item{background:rgba(255,255,255,0.65)}
.ws-item:active{background:rgba(99,102,241,0.10);transform:scale(0.99)}
.ws-name{font-size:13px;font-weight:700;color:var(--text-primary);letter-spacing:-0.01em}
.ws-phone{font-size:10px;color:var(--text-muted);font-weight:600;margin-top:2px}
.ws-retail{
    padding:12px 14px;border-radius:100px;cursor:pointer;
    background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.30);
    color:#fca5a5;font-size:12px;font-weight:800;text-align:center;letter-spacing:0.04em;
    margin-top:8px;transition:all 0.15s;
}
:root[data-theme="light"] .ws-retail{color:hsl(0 60% 45%)}
.ws-retail:active{background:rgba(239,68,68,0.20)}

/* ═══ S87E.BUG#6 — PARKED INLINE ACCORDION (no overlay) ═══ */
.parked-list-inline{
    flex-shrink:0;margin:0 8px 8px;padding:8px;
    background:var(--bg-card-strong);backdrop-filter:blur(12px);
    border:1px solid var(--border-subtle);border-radius:14px;
    max-height:40vh;overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    animation:srchOvIn 0.18s ease-out;
}
:root[data-theme="light"] .parked-list-inline{background:rgba(255,255,255,0.92)}
.parked-row-inline{
    display:flex;align-items:center;gap:10px;padding:10px;
    margin:4px 0;border-radius:12px;
    background:rgba(255,255,255,0.025);border:1px solid rgba(255,255,255,0.06);
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);
}
:root[data-theme="light"] .parked-row-inline{background:rgba(255,255,255,0.65);border-color:rgba(15,23,42,0.06)}
.pr-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.pr-name{font-size:12px;font-weight:800;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pr-meta{font-size:10px;color:var(--text-muted);font-weight:600;font-variant-numeric:tabular-nums;letter-spacing:0.02em}
.pr-load{
    padding:8px 14px;border-radius:100px;cursor:pointer;flex-shrink:0;
    background:linear-gradient(135deg,hsl(145 65% 42%),hsl(160 65% 36%));
    border:1px solid hsl(145 60% 50%);color:#fff;
    font-size:11px;font-weight:900;letter-spacing:0.04em;
    box-shadow:0 0 10px hsl(145 65% 45% / 0.4);font-family:inherit;
}
.pr-load:active{transform:scale(0.95)}
.pr-del{
    width:32px;height:32px;border-radius:50%;flex-shrink:0;cursor:pointer;
    background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.40);
    color:#fca5a5;
    display:flex;align-items:center;justify-content:center;
    font-family:inherit;font-size:14px;font-weight:900;line-height:1;
}
:root[data-theme="light"] .pr-del{color:hsl(0 60% 45%)}
.pr-del:active{transform:scale(0.92)}

/* V5 — pkg-card style: glass with side accent bar */
.parked-card{
    position:relative;background:linear-gradient(135deg,rgba(255,255,255,0.025),rgba(0,0,0,0.15));
    border:1px solid var(--border-subtle);
    border-radius:14px;padding:16px;backdrop-filter:blur(8px);
    cursor:pointer;transition:all 0.2s;animation:cardIn 0.3s ease both;
    overflow:hidden;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 4px 12px rgba(0,0,0,0.2);
}
:root[data-theme="light"] .parked-card{background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(241,245,249,0.92))}
.parked-card::before{
    content:'';position:absolute;top:0;left:0;bottom:0;width:3px;border-radius:14px 0 0 14px;
    background:linear-gradient(180deg,hsl(38 90% 55%) 0%,transparent 100%);
    box-shadow:0 0 20px 1px hsl(38 90% 55%);opacity:0.9;
}
.parked-card:active{transform:scale(0.97);border-color:var(--border-glow)}
.pc-header{display:flex;justify-content:space-between;margin-bottom:8px}
.pc-client{font-size:11px;font-weight:900;letter-spacing:0.06em;text-transform:uppercase;color:hsl(38 90% 65%);text-shadow:0 0 8px hsl(38 90% 55% / 0.3)}
.pc-time{font-size:10px;color:var(--text-muted);font-weight:600;font-variant-numeric:tabular-nums}
.pc-info{font-size:11px;color:var(--text-secondary);font-weight:600}
.pc-total{font-size:18px;font-weight:900;margin-top:6px;letter-spacing:-0.02em;font-variant-numeric:tabular-nums;color:var(--text-primary)}
.pc-delete{
    float:right;padding:5px 12px;border-radius:100px;
    background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.30);
    color:#fca5a5;font-size:10px;font-weight:800;cursor:pointer;margin-top:8px;letter-spacing:0.04em;
}
:root[data-theme="light"] .pc-delete{color:hsl(0 60% 45%)}
.pc-delete:active{background:rgba(239,68,68,0.20)}
.parked-title{
    text-align:center;font-size:14px;font-weight:800;color:var(--text-primary);
    margin-bottom:8px;flex-shrink:0;letter-spacing:-0.01em;
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
    flex:1;height:44px;border-radius:100px;
    border:1px solid var(--border-subtle);background:var(--bg-card);
    color:var(--indigo-300);font-size:13px;font-weight:700;
    cursor:pointer;font-family:inherit;backdrop-filter:blur(6px);letter-spacing:0.02em;
    display:flex;align-items:center;justify-content:center;
}
:root[data-theme="light"] .rec-btn-cancel{background:rgba(255,255,255,0.85)}
.rec-btn-cancel:active{background:rgba(99,102,241,0.12)}
.rec-btn-send{
    flex:2;height:44px;border-radius:100px;
    background:linear-gradient(135deg,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 35%, hsl(220 30% 10%)) 0%,
        color-mix(in oklch, hsl(var(--hue1) 65% 55%) 20%, hsl(220 30% 8%)) 100%);
    border:1px solid hsl(var(--hue1) 65% 55% / 0.5);
    color:#fff;font-size:13px;font-weight:800;letter-spacing:0.02em;
    cursor:pointer;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:6px;
    box-shadow:0 4px 14px hsl(var(--hue1) 65% 55% / 0.35),inset 0 1px 0 rgba(255,255,255,0.12);
    transition:all 0.2s;
}
.rec-btn-send:active{transform:scale(0.97)}
.rec-btn-send:disabled{opacity:0.3;pointer-events:none}

/* ═══ TOAST ═══ */
/* V5 — Toast: pill, glass, indigo glow */
.toast{
    position:fixed;top:calc(60px + env(safe-area-inset-top,0px));left:50%;transform:translateX(-50%) translateY(-100px);
    z-index:500;padding:10px 20px;border-radius:100px;
    background:var(--bg-card-strong);border:1px solid var(--border-glow);
    backdrop-filter:blur(16px);color:var(--text-primary);
    font-size:12px;font-weight:700;white-space:nowrap;letter-spacing:0.02em;
    box-shadow:0 8px 32px hsl(var(--hue1) 60% 45% / 0.30),inset 0 1px 0 rgba(255,255,255,0.06);
    transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.toast.show{transform:translateX(-50%) translateY(0)}
.toast.success{border-color:hsl(145 70% 50% / 0.5);box-shadow:0 8px 32px hsl(145 70% 50% / 0.25)}

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

/* ───────────────────────────────────────────── */
/* S87.ANIMATIONS v3 — portable CORE block       */
/* DESIGN_SYSTEM § O.3-O.10 + O.14 + O.20        */
/* sale.php: skipped numpad/keyboard tap (rapid input workflow) */
/* ───────────────────────────────────────────── */
@keyframes s87v3_pageIn{
    0%   { opacity:0; transform:translateY(40px) scale(0.92); filter:blur(8px); }
    60%  { opacity:1; filter:blur(0); }
    100% { opacity:1; transform:translateY(0) scale(1); filter:blur(0); }
}
.s87v3-pagein{animation:s87v3_pageIn 0.85s cubic-bezier(0.16,1,0.3,1) both}
@keyframes s87v3_springRelease{
    0%   { transform:scale(0.92); }
    50%  { transform:scale(1.06); }
    100% { transform:scale(1); }
}
.s87v3-tap{transition:transform 0.18s cubic-bezier(0.34,1.8,0.64,1)}
.s87v3-tap:active{transform:scale(0.92)}
.s87v3-tap.s87v3-released{animation:s87v3_springRelease 0.4s cubic-bezier(0.34,2.0,0.64,1)}
@keyframes s87v3_headerIn{
    0%   { opacity:0; transform:translateY(-30px); }
    100% { opacity:1; transform:translateY(0); }
}
@keyframes s87v3_navIn{
    0%   { opacity:0; transform:translateY(60px); }
    100% { opacity:1; transform:translateY(0); }
}
.rms-header,.header{animation:s87v3_headerIn 0.7s cubic-bezier(0.16,1,0.3,1) 0s both;transition:backdrop-filter 0.3s,background 0.3s}
.rms-bottom-nav,.bottom-nav{animation:s87v3_navIn 0.7s cubic-bezier(0.16,1,0.3,1) 1.8s both}
.rms-header.scrolled,.header.scrolled{
    backdrop-filter:blur(20px) saturate(1.2);
    -webkit-backdrop-filter:blur(20px) saturate(1.2);
    background:linear-gradient(180deg,hsl(220 25% 6% / .95),hsl(220 25% 4% / .85));
}
@media (prefers-reduced-motion: reduce){
    .s87v3-pagein,
    .rms-header,.header,
    .rms-bottom-nav,.bottom-nav,
    .s87v3-tap,.s87v3-tap.s87v3-released{
        opacity:1 !important;
        transform:none !important;
        filter:none !important;
        animation:none !important;
        transition:none !important;
    }
}

/* ─── S87B V5 PATTERNS (1:1 от docs/SALE_V5_MOCKUP.html) ─── */

/* TABS PILL — дребно/едро */
.tabs-pill{display:flex;gap:3px;padding:3px;background:rgba(0,0,0,0.3);border-radius:100px;border:1px solid rgba(255,255,255,0.05);margin:8px 12px 6px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03),inset 0 0 12px rgba(0,0,0,0.4)}
:root[data-theme="light"] .tabs-pill{background:rgba(15,23,42,0.06);border-color:rgba(15,23,42,0.10)}
.tabs-pill .tab{flex:1;padding:10px 12px;border-radius:100px;font-size:10px;font-weight:800;letter-spacing:0.04em;cursor:pointer;font-family:inherit;border:none;background:transparent;color:rgba(255,255,255,0.5);transition:all 0.2s var(--ease,cubic-bezier(0.5,1,0.89,1));display:flex;align-items:center;justify-content:center;gap:5px}
:root[data-theme="light"] .tabs-pill .tab{color:rgba(15,23,42,0.55)}
.tabs-pill .tab.active{color:white;background:linear-gradient(135deg,hsl(var(--hue1) 60% 45%),hsl(var(--hue2) 65% 40%));box-shadow:0 2px 8px hsl(var(--hue1) 60% 45% / 0.4),inset 0 1px 0 rgba(255,255,255,0.2),inset 0 0 12px hsl(var(--hue1) 70% 60% / 0.15);text-shadow:0 0 8px hsl(var(--hue1) 80% 70% / 0.5)}
.tabs-pill .tab svg{width:11px;height:11px;fill:none;stroke:currentColor;stroke-width:2}

/* BULK BANNER — паркирани */
.bulk-banner{position:relative;margin:8px 12px;padding:14px;border-radius:18px;border:1px solid;cursor:pointer;backdrop-filter:blur(8px);overflow:hidden;box-shadow:inset 0 1px 0 rgba(255,255,255,0.05),0 6px 20px rgba(0,0,0,0.3);font-family:inherit;width:calc(100% - 24px);text-align:left}
.bulk-banner.amber{border-color:hsl(38 90% 55% / 0.4);background:linear-gradient(135deg,hsl(38 70% 12% / 0.5),rgba(0,0,0,0.2)),linear-gradient(hsl(220 25% 6% / 0.8));box-shadow:inset 0 1px 0 rgba(255,255,255,0.05),0 6px 20px hsl(38 90% 55% / 0.15)}
.bulk-banner.amber::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;border-radius:18px 0 0 18px;background:hsl(38 90% 55%);box-shadow:0 0 20px 1px hsl(38 90% 55%);opacity:0.9}
.bulk-banner.amber::after{content:'';position:absolute;top:-30px;right:-30px;width:140px;height:140px;background:radial-gradient(circle,hsl(38 90% 55% / 0.3) 0%,transparent 60%);opacity:0.2;pointer-events:none}
:root[data-theme="light"] .bulk-banner.amber{background:linear-gradient(135deg,hsl(38 70% 92% / 0.85),rgba(255,255,255,0.92));border-color:hsl(38 90% 55% / 0.45)}
.bulk-row{display:flex;align-items:center;gap:12px;position:relative;z-index:5}
.bulk-icon{width:46px;height:46px;border-radius:14px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 0 rgba(255,255,255,0.08),0 0 18px hsl(38 90% 55% / 0.35)}
.bulk-icon svg{width:22px;height:22px;fill:none;stroke:hsl(38 90% 80%);stroke-width:2;filter:drop-shadow(0 0 6px hsl(38 90% 55%))}
.bulk-info{flex:1;min-width:0}
.bulk-num-row{display:flex;align-items:baseline;gap:5px;margin-bottom:1px}
.bulk-num{font-size:24px;font-weight:900;letter-spacing:-0.03em;line-height:1;font-variant-numeric:tabular-nums;background:linear-gradient(135deg,#fff 0%,hsl(38 90% 85%) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
:root[data-theme="light"] .bulk-num{background:linear-gradient(135deg,#78350f,#d97706);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.bulk-num-suffix{font-size:11px;color:var(--text-muted);font-weight:700}
.bulk-title{font-size:11px;font-weight:700;color:#e2e8f0;line-height:1.3;letter-spacing:-0.01em}
:root[data-theme="light"] .bulk-title{color:#0f172a}
.bulk-arrow{color:rgba(255,255,255,0.3);font-size:18px;flex-shrink:0;align-self:center;font-weight:600}

/* SET ROW — cart items */
.set-row{display:flex;align-items:center;gap:11px;margin:6px 0;padding:11px 13px;border-radius:14px;background:rgba(255,255,255,0.025);border:1px solid rgba(255,255,255,0.05);cursor:pointer;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);position:relative;overflow:hidden}
:root[data-theme="light"] .set-row{background:rgba(255,255,255,0.65);border-color:rgba(15,23,42,0.06);box-shadow:inset 0 1px 0 rgba(255,255,255,0.5)}
.set-row.selected{background:linear-gradient(135deg,hsl(var(--hue1) 30% 20% / 0.6),hsl(var(--hue2) 35% 16% / 0.5));border-color:hsl(var(--hue1) 50% 50% / 0.5);box-shadow:inset 0 1px 0 rgba(255,255,255,0.08),0 0 14px hsl(var(--hue1) 60% 45% / 0.25)}
:root[data-theme="light"] .set-row.selected{background:linear-gradient(135deg,hsl(var(--hue1) 50% 88%),hsl(var(--hue2) 55% 92%));border-color:hsl(var(--hue1) 40% 75%)}
.set-icon{width:38px;height:38px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,hsl(var(--hue1) 30% 25% / 0.6),hsl(var(--hue2) 35% 20% / 0.5));border:1px solid hsl(var(--hue1) 30% 30% / 0.4);display:flex;align-items:center;justify-content:center;box-shadow:inset 0 1px 0 rgba(255,255,255,0.08),0 0 8px hsl(var(--hue1) 60% 40% / 0.2);font-size:18px}
:root[data-theme="light"] .set-icon{background:linear-gradient(135deg,hsl(var(--hue1) 50% 88%),hsl(var(--hue2) 55% 92%));border-color:hsl(var(--hue1) 40% 75%)}
.set-text{flex:1;min-width:0}
.set-val{font-size:12px;font-weight:700;color:var(--text-primary);letter-spacing:-0.01em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.set-val-sub{font-size:9px;color:var(--text-muted);font-weight:600;margin-top:1px;font-variant-numeric:tabular-nums}
.set-qty{display:flex;align-items:center;gap:5px;flex-shrink:0}
.set-qty-btn{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:var(--text-primary);font-size:14px;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;padding:0}
:root[data-theme="light"] .set-qty-btn{background:rgba(15,23,42,0.06);border-color:rgba(15,23,42,0.12)}
.set-qty-val{font-size:13px;font-weight:900;min-width:18px;text-align:center;font-variant-numeric:tabular-nums;color:var(--text-primary)}
.set-total{font-size:12px;font-weight:900;color:var(--text-primary);font-variant-numeric:tabular-nums;flex-shrink:0;min-width:60px;text-align:right}
/* S87G.B4 — swipe-to-delete: ci-delete sits behind row content; .set-row-fg slides left to reveal it */
.set-row .ci-delete{position:absolute;right:0;top:0;bottom:0;width:90px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;font-size:11px;font-weight:800;letter-spacing:0.04em;border:none;cursor:pointer;font-family:inherit;border-radius:0 14px 14px 0;padding:0;box-shadow:inset 1px 0 0 rgba(255,255,255,0.12)}
.set-row .ci-delete svg{display:block}
.set-row .ci-delete:active{filter:brightness(0.92)}
.set-row .set-row-fg{display:flex;align-items:center;gap:11px;width:100%;background:inherit;border-radius:inherit;transform:translateX(0);transition:transform 0.2s ease;will-change:transform;position:relative;z-index:1}
.set-row.swiped .set-row-fg{transform:translateX(-90px)}
/* B3 — qty value as tap zone with hover hint arrows */
body.sale-page .set-qty-val{
    position:relative;
    min-width:54px;
    padding:6px 18px;
    text-align:center;
    user-select:none;
    -webkit-user-select:none;
    touch-action:manipulation;
}
body.sale-page .set-qty-val::before,
body.sale-page .set-qty-val::after{
    content:'';position:absolute;top:50%;width:6px;height:6px;
    border-style:solid;border-color:var(--text-muted);
    opacity:0;transition:opacity 0.15s;pointer-events:none;
}
body.sale-page .set-qty-val::before{left:5px;border-width:2px 0 0 2px;transform:translateY(-50%) rotate(-45deg)}
body.sale-page .set-qty-val::after{right:5px;border-width:2px 2px 0 0;transform:translateY(-50%) rotate(45deg)}
body.sale-page .set-qty-val:hover::before,
body.sale-page .set-qty-val:hover::after{opacity:0.55}
/* S87G.B4 — swipe-to-delete hint (shown once) */
.s87g-swipe-hint{
    display:flex;align-items:center;justify-content:center;gap:6px;
    padding:6px 10px;margin:4px 6px 8px;
    background:rgba(99,102,241,0.10);border:1px dashed rgba(99,102,241,0.35);
    border-radius:10px;color:var(--text-muted);
    font-size:11px;font-weight:700;letter-spacing:0.02em;
    opacity:0;animation:s87g-hint-in 0.35s ease forwards;
    transition:opacity 0.5s ease,transform 0.5s ease;
}
.s87g-swipe-hint.hide{opacity:0;transform:translateY(-4px)}
@keyframes s87g-hint-in{from{opacity:0;transform:translateY(-4px)}to{opacity:0.95;transform:none}}

/* STAT NUM — payment hero */
.stat-num{font-size:34px;font-weight:900;letter-spacing:-0.03em;background:linear-gradient(135deg,#fff 0%,hsl(var(--hue1) 60% 85%) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1;font-variant-numeric:tabular-nums;display:inline-block}
:root[data-theme="light"] .stat-num{background:linear-gradient(135deg,#1e1b4b 0%,#4338ca 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.stat-num.lg{font-size:54px}
.stat-cur{font-size:14px;color:var(--text-muted);font-weight:700;margin-left:6px}
.stat-label{font-size:9px;font-weight:900;letter-spacing:0.1em;color:hsl(var(--hue1) 50% 70%);text-transform:uppercase;text-shadow:0 0 8px hsl(var(--hue1) 70% 50% / 0.3)}

/* PKG CARD — payment methods */
.pkg-card{position:relative;cursor:pointer;margin:6px 0;padding:12px 14px;border-radius:14px;border:1px solid rgba(255,255,255,0.06);background:linear-gradient(135deg,rgba(255,255,255,0.025),rgba(0,0,0,0.15)),linear-gradient(hsl(220 25% 6% / 0.6));backdrop-filter:blur(8px);box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 4px 12px rgba(0,0,0,0.2);overflow:hidden;transition:transform 0.15s var(--ease,cubic-bezier(0.5,1,0.89,1));font-family:inherit;width:100%;text-align:left}
:root[data-theme="light"] .pkg-card{background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(241,245,249,0.92));border-color:rgba(15,23,42,0.08)}
.pkg-card::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;border-radius:14px 0 0 14px;background:linear-gradient(180deg,var(--qcol,transparent) 0%,transparent 100%);box-shadow:0 0 20px 1px var(--qcol,transparent);opacity:0.9}
.pkg-card::after{content:'';position:absolute;top:-1px;right:-1px;width:80px;height:80px;background:radial-gradient(circle at top right,var(--qcol,transparent) 0%,transparent 60%);opacity:0.15;pointer-events:none}
.pkg-card.q-blue{--qcol:hsl(220,80%,60%)}
.pkg-card.q-violet{--qcol:hsl(280,70%,62%)}
.pkg-card.q-amber{--qcol:hsl(38,90%,55%)}
.pkg-card.q3{--qcol:hsl(145,70%,50%)}
.pkg-card.q-fire{--qcol:hsl(15,95%,58%)}
.pkg-card.q-indigo{--qcol:hsl(255,70%,65%)}
.pkg-card.featured{border-color:color-mix(in oklch,var(--qcol) 40%,rgba(255,255,255,0.06));box-shadow:inset 0 1px 0 rgba(255,255,255,0.06),0 4px 14px color-mix(in oklch,var(--qcol) 25%,transparent),0 0 30px color-mix(in oklch,var(--qcol) 10%,transparent)}
.pkg-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.pkg-emoji{font-size:18px;filter:drop-shadow(0 0 8px var(--qcol,transparent))}
.pkg-name{font-size:11px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:var(--qcol);text-shadow:0 0 10px var(--qcol);flex:1}
.pkg-sub{font-size:11px;color:var(--text-secondary);font-weight:500;line-height:1.4}
.pkg-active-badge{font-size:9px;font-weight:900;padding:4px 9px;border-radius:100px;background:linear-gradient(135deg,hsl(145 70% 45%),hsl(160 70% 40%));color:white;letter-spacing:0.04em;box-shadow:0 0 8px hsl(145 70% 50% / 0.4)}
.pkg-arrow{color:rgba(255,255,255,0.3);font-size:14px;font-weight:600;margin-left:auto}
:root[data-theme="light"] .pkg-arrow{color:rgba(15,23,42,0.25)}

/* PKG BUY — mega sell button */
.pkg-buy{width:100%;margin-top:14px;padding:14px 16px;border-radius:100px;font-size:13px;font-weight:900;text-align:center;cursor:pointer;border:1px solid;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;letter-spacing:0.06em;transition:transform 0.15s,box-shadow 0.15s;background:linear-gradient(135deg,color-mix(in oklch,var(--qcol) 60%,hsl(220 30% 10%)) 0%,color-mix(in oklch,var(--qcol) 40%,hsl(220 30% 8%)) 100%);border-color:color-mix(in oklch,var(--qcol) 50%,transparent);color:white;box-shadow:0 6px 20px color-mix(in oklch,var(--qcol) 50%,transparent),inset 0 1px 0 rgba(255,255,255,0.18),inset 0 0 24px color-mix(in oklch,var(--qcol) 18%,transparent)}
.pkg-buy.mega{--qcol:hsl(255,70%,65%)}
.pkg-buy:disabled{opacity:0.4;pointer-events:none}
.pkg-buy:active{transform:scale(0.97)}
.pkg-buy svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.5}

/* BRIEFING SECTION — ресто (q3 green) */
.briefing-section{position:relative;z-index:5;margin:8px 0;padding:14px 14px 12px;border-radius:14px;border:1px solid rgba(255,255,255,0.06);background:linear-gradient(135deg,rgba(255,255,255,0.025),rgba(0,0,0,0.15)),linear-gradient(hsl(220 25% 6% / 0.6));backdrop-filter:blur(8px);box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 4px 12px rgba(0,0,0,0.2);overflow:hidden}
:root[data-theme="light"] .briefing-section{background:linear-gradient(135deg,rgba(255,255,255,0.85),rgba(241,245,249,0.92));border-color:rgba(15,23,42,0.10);box-shadow:inset 0 1px 0 rgba(255,255,255,0.6),0 4px 12px rgba(99,102,241,0.05)}
.briefing-section::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;border-radius:14px 0 0 14px;background:linear-gradient(180deg,var(--qcol,transparent) 0%,transparent 100%);box-shadow:0 0 20px 1px var(--qcol,transparent);opacity:0.9}
.briefing-section::after{content:'';position:absolute;top:-1px;right:-1px;width:80px;height:80px;background:radial-gradient(circle at top right,var(--qcol,transparent) 0%,transparent 60%);opacity:0.12;pointer-events:none}
.briefing-section.q3{--qcol:hsl(145,70%,50%)}
.briefing-head{display:flex;align-items:center;gap:7px;margin-bottom:6px}
.briefing-emoji{font-size:14px;filter:drop-shadow(0 0 6px var(--qcol,transparent))}
.briefing-name{font-size:9px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:var(--qcol);text-shadow:0 0 10px var(--qcol)}
.briefing-amount{font-size:32px;font-weight:900;letter-spacing:-0.03em;font-variant-numeric:tabular-nums;background:linear-gradient(135deg,#fff 0%,hsl(145 70% 80%) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1}
:root[data-theme="light"] .briefing-amount{background:linear-gradient(135deg,#14532d,#16a34a);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}

/* DETECT — клиент даде (purple side accent) */
.detect{position:relative;margin:10px 0;padding:11px 13px 11px 16px;border-radius:14px;border:1px solid hsl(280 70% 60% / 0.25);background:linear-gradient(135deg,hsl(280 50% 15% / 0.35),rgba(0,0,0,0.15)),linear-gradient(hsl(220 25% 6% / 0.6));backdrop-filter:blur(8px);box-shadow:inset 0 1px 0 rgba(255,255,255,0.04),0 4px 12px rgba(0,0,0,0.2);overflow:hidden}
:root[data-theme="light"] .detect{background:linear-gradient(135deg,hsl(280 60% 96% / 0.85),rgba(255,255,255,0.92));border-color:hsl(280 50% 75% / 0.45)}
.detect::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;border-radius:14px 0 0 14px;background:hsl(280 70% 62%);box-shadow:0 0 20px 1px hsl(280 70% 62%);opacity:0.9}
.detect-row{display:flex;align-items:center;gap:10px}
.detect-icon{width:32px;height:32px;border-radius:10px;flex-shrink:0;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;box-shadow:inset 0 1px 0 rgba(255,255,255,0.06),0 0 12px hsl(280 70% 62% / 0.3);font-size:15px}
.detect-text{flex:1;min-width:0}
.detect-label{font-size:9px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:hsl(280 70% 75%);text-shadow:0 0 8px hsl(280 70% 62% / 0.3)}
.detect-val{font-size:13px;font-weight:800;color:var(--text-primary);margin-top:2px;letter-spacing:-0.01em;font-variant-numeric:tabular-nums}

/* SECTION divider */
.v5-section{display:flex;align-items:center;gap:8px;margin:14px 0 8px}
.v5-section-label{font-size:9px;font-weight:900;letter-spacing:0.1em;color:hsl(var(--hue1) 50% 70%);text-transform:uppercase;text-shadow:0 0 8px hsl(var(--hue1) 70% 50% / 0.25)}
.v5-section-line{flex:1;height:1px;background:linear-gradient(90deg,hsl(var(--hue1) 60% 50% / 0.3),transparent)}

/* SUCCESS HERO */
.success-hero{text-align:center;padding:14px 0 16px}
.success-circle{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,hsl(145 70% 50%),hsl(160 70% 40%));display:flex;align-items:center;justify-content:center;margin:0 auto 8px;box-shadow:0 0 30px hsl(145 70% 50% / 0.5),0 0 0 4px hsl(145 70% 50% / 0.15),inset 0 1px 0 rgba(255,255,255,0.3);font-size:32px}

/* ═══════════════════════════════════════════════════════════
   S87D.SALE.UX_FINAL — Layout cleanup overrides
   ═══════════════════════════════════════════════════════════ */
/* 1.1 — Hide bottom nav on sale.php */
body.sale-page .rms-bottom-nav,
body.sale-page .bottom-nav { display:none !important; }
body.sale-page.has-rms-shell { padding-bottom:0 !important; }

/* 1.2 — Disable swipe / pull-to-refresh on sale wrap */
body.sale-page #saleWrap{ touch-action:pan-y; overscroll-behavior:contain }

/* 1.3 — Tabs inside cam-overlay (replaces standalone tabs-pill) */
body.sale-page #tabsPill{ display:none !important }
.cam-tabs{
    display:flex;gap:3px;padding:2px;background:rgba(0,0,0,0.5);
    border-radius:100px;backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,0.12);
}
.cam-tab{
    padding:5px 11px;border-radius:100px;font-size:9px;font-weight:800;
    letter-spacing:0.06em;background:transparent;border:none;
    color:rgba(255,255,255,0.65);font-family:inherit;cursor:pointer;
    display:flex;align-items:center;gap:4px;
}
.cam-tab svg{width:10px;height:10px;fill:none;stroke:currentColor;stroke-width:2}
.cam-tab.active{
    background:linear-gradient(135deg,hsl(255 65% 45%),hsl(222 70% 38%));
    color:#fff;box-shadow:0 2px 8px hsl(255 60% 45% / 0.5),inset 0 1px 0 rgba(255,255,255,0.2);
    text-shadow:0 0 8px rgba(255,255,255,0.3);
}

/* 1.5 — Enlarge camera scan area */
body.sale-page .cam-header{
    height:calc(130px + env(safe-area-inset-top,0px)) !important;
}
body.sale-page .scan-corner{ width:20px;height:20px }
body.sale-page .sc-tl{ top:36px;left:14% }
body.sale-page .sc-tr{ top:36px;right:14% }
body.sale-page .sc-bl{ bottom:34px;left:14% }
body.sale-page .sc-br{ bottom:34px;right:14% }
body.sale-page .scan-laser{
    box-shadow:0 0 12px hsl(var(--hue1) 70% 60% / 0.85),0 0 28px hsl(var(--hue1) 70% 50% / 0.5);
    height:2.5px;
}
body.sale-page .cam-status{ bottom:10px }
body.sale-page .cam-status span{ font-size:10px;letter-spacing:0.10em }

/* 1.7 — Smaller pay/park buttons */
body.sale-page .action-bar{ padding:8px 12px !important }
body.sale-page .btn-pay{ height:38px;font-size:13px;padding:0 14px }
body.sale-page .btn-park{ height:38px;font-size:16px }

/* 1.8 — Hide custom keyboard + numpad on sale.php (search uses overlay) */
body.sale-page #keyboardZone{ display:none !important }
body.sale-page #numpadZone{ display:none !important }

/* 1.11 — Cart gets all available space (FIX1: reserve bottom for sticky action+summary) */
body.sale-page #cartZone{ flex:1 1 auto;min-height:0;padding-bottom:108px }

/* FIX1 (S87D.UX_FIX2) — Pin summary-bar + action-bar to VIEWPORT bottom.
   Root cause discovered: .sale-wrap has animation `s87v3_pageIn` which uses transform/filter
   in keyframes; with animation-fill-mode both, the final transform is held forever, which
   makes .sale-wrap a containing block for ALL fixed-position descendants. So position:fixed
   on .action-bar becomes effectively position:absolute relative to .sale-wrap → bottom:0 lands
   below viewport on small phones. Killing the animation on sale-page restores viewport-based fixed positioning. */
body.sale-page #saleWrap{
    animation:none !important;
    transform:none !important;
    filter:none !important;
    will-change:auto !important;
}
/* S87E.BUG#1 — action-bar sticky in flow (flex column auto-pushes to bottom) */
body.sale-page .summary-bar{
    position:static !important;left:auto;right:auto;bottom:auto !important;
    max-width:none !important;margin:0;z-index:auto;
}
body.sale-page .action-bar{
    display:flex !important;
    position:sticky !important;
    bottom:0 !important;
    z-index:10;
    padding:10px 12px max(10px, env(safe-area-inset-bottom)) 12px !important;
    background:linear-gradient(180deg,transparent 0%,var(--bg-main) 30%) !important;
    margin-top:auto !important;
    left:auto;right:auto;max-width:none !important;
}
body.sale-page #cartZone{ padding-bottom:0 !important }
/* S87G.B3 — set-qty-val cosmetics (split-tap zone defined earlier in stylesheet) */
body.sale-page .set-qty-val{
    cursor:pointer;border-radius:10px;font-size:14px;font-weight:900;
    background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);
}
:root[data-theme="light"] body.sale-page .set-qty-val{background:rgba(99,102,241,0.10);border-color:rgba(99,102,241,0.30)}
body.sale-page .set-qty-val:active{background:hsl(var(--hue1) 60% 50% / 0.22)}

/* (S87E) search-display + btnKeyboard removed; see #searchInput rules above */

/* hide redundant cam-title (cam-tabs take its place) */
body.sale-page .cam-title{ display:none }
/* re-balance cam-top now that we have 3 visible children: ← + cam-tabs + cam-right */
body.sale-page .cam-top{ gap:8px }

/* ═══════════════════════════════════════════════════════════
   S87E — INLINE SEARCH RESULTS (master/variant rows reused)
   ═══════════════════════════════════════════════════════════ */
/* Master row */
.srch-master{
    display:flex;align-items:center;gap:11px;padding:14px;
    margin:5px 0;border-radius:14px;background:rgba(255,255,255,0.025);
    border:1px solid rgba(255,255,255,0.05);cursor:pointer;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);
    font-family:inherit;width:100%;text-align:left;
}
:root[data-theme="light"] .srch-master{background:rgba(255,255,255,0.65);border-color:rgba(15,23,42,0.06)}
.srch-master:active{transform:scale(0.99);background:hsl(var(--hue1) 30% 18% / 0.5)}
:root[data-theme="light"] .srch-master:active{background:hsl(var(--hue1) 50% 90%)}
.srch-master-ico{
    width:42px;height:42px;border-radius:11px;
    background:linear-gradient(135deg,hsl(var(--hue1) 30% 25% / 0.6),hsl(var(--hue2) 35% 20% / 0.5));
    border:1px solid hsl(var(--hue1) 30% 30% / 0.4);
    display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;
    overflow:hidden;
}
.srch-master-ico img{width:100%;height:100%;object-fit:cover;display:block}
.srch-master-info{flex:1;min-width:0}
.srch-master-name{
    font-size:13px;font-weight:800;color:var(--text-primary);
    letter-spacing:-0.01em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.srch-master-meta{font-size:10px;color:var(--text-muted);font-weight:600;margin-top:3px;font-variant-numeric:tabular-nums}
.srch-master-arrow{color:rgba(255,255,255,0.3);font-size:18px;flex-shrink:0;font-weight:600}
:root[data-theme="light"] .srch-master-arrow{color:rgba(15,23,42,0.3)}
/* Variant row */
.srch-variant{
    display:flex;align-items:center;gap:10px;padding:11px 12px;
    margin:5px 0;border-radius:12px;background:rgba(255,255,255,0.025);
    border:1px solid rgba(255,255,255,0.05);
}
:root[data-theme="light"] .srch-variant{background:rgba(255,255,255,0.65);border-color:rgba(15,23,42,0.06)}
.srch-variant-color{
    width:18px;height:18px;border-radius:50%;flex-shrink:0;
    border:1.5px solid rgba(255,255,255,0.2);
    background:rgba(255,255,255,0.1);
}
.srch-variant-info{flex:1;min-width:0}
.srch-variant-name{font-size:12px;font-weight:700;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.srch-variant-stock{font-size:9px;color:var(--text-muted);font-weight:600;font-variant-numeric:tabular-nums;margin-top:2px}
.srch-variant-stock.zero{color:#fca5a5}
:root[data-theme="light"] .srch-variant-stock.zero{color:#dc2626}
.srch-variant-price{font-size:12px;font-weight:900;color:var(--text-primary);font-variant-numeric:tabular-nums;margin-right:6px}
.srch-variant-add{
    width:36px;height:36px;border-radius:50%;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    border:1px solid hsl(var(--hue1) 60% 55%);color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    font-family:inherit;font-size:18px;font-weight:900;line-height:1;
    box-shadow:0 0 10px hsl(var(--hue1) 60% 45% / 0.4);
}
.srch-variant-add:active{transform:scale(0.92)}
.srch-variant-add:disabled{opacity:0.35;cursor:not-allowed}
/* S87G.HOTFIX_B5 — single-variant master "+" button (mirrors variant add) */
.srch-master-add{
    width:36px;height:36px;border-radius:50%;
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 50%),hsl(var(--hue2) 70% 45%));
    border:1px solid hsl(var(--hue1) 60% 55%);color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    font-family:inherit;font-size:18px;font-weight:900;line-height:1;padding:0;
    box-shadow:0 0 10px hsl(var(--hue1) 60% 45% / 0.4);
}
.srch-master-add:active{transform:scale(0.92)}
/* S87G.HOTFIX_B5 — selection state for tapped result rows */
.srch-master.selected, .srch-variant.selected{
    background:linear-gradient(135deg,hsl(var(--hue1) 35% 22% / 0.7),hsl(var(--hue2) 40% 18% / 0.6)) !important;
    border-color:hsl(var(--hue1) 60% 55%) !important;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.08),0 0 16px hsl(var(--hue1) 60% 45% / 0.3) !important;
}
:root[data-theme="light"] .srch-master.selected,
:root[data-theme="light"] .srch-variant.selected{
    background:linear-gradient(135deg,hsl(var(--hue1) 55% 90%),hsl(var(--hue2) 60% 92%)) !important;
    border-color:hsl(var(--hue1) 50% 65%) !important;
}
/* Toast */
.srch-toast{
    position:fixed;bottom:90px;left:50%;transform:translateX(-50%);
    padding:10px 20px;border-radius:100px;
    background:linear-gradient(135deg,hsl(145 70% 45%),hsl(160 70% 40%));
    color:#fff;font-size:12px;font-weight:800;letter-spacing:0.04em;
    box-shadow:0 6px 20px hsl(145 70% 50% / 0.4);z-index:300;
    animation:srchToastIn 0.3s ease,srchToastOut 0.3s ease 1.5s forwards;
}
@keyframes srchToastIn{from{opacity:0;transform:translateX(-50%) translateY(10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
@keyframes srchToastOut{to{opacity:0;transform:translateX(-50%) translateY(-10px)}}
/* Empty/loading state */
.srch-empty{padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;font-weight:600}

/* ═══════════════════════════════════════════════════════════
   S87D Phase 3 — PAYMENT (full-screen, simplified)
   ═══════════════════════════════════════════════════════════ */
body.sale-page .pay-overlay{
    position:fixed;inset:0;background:var(--bg-main);z-index:200;
    overflow-y:auto;padding-bottom:30px;
    display:none;flex-direction:column;
    opacity:1 !important;pointer-events:auto !important;
    backdrop-filter:none;border-radius:0;
}
body.sale-page .pay-overlay::before{
    content:'';position:fixed;inset:0;
    background:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / 0.22) 0%,transparent 60%),
               radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / 0.22) 0%,transparent 60%);
    z-index:-1;pointer-events:none;
}
body.sale-page .pay-head{
    display:flex;align-items:center;gap:10px;
    padding:max(12px,calc(env(safe-area-inset-top,0px) + 8px)) 14px 12px;
    border-bottom:1px solid rgba(255,255,255,0.06);
}
:root[data-theme="light"] body.sale-page .pay-head{border-bottom-color:rgba(15,23,42,0.06)}
body.sale-page .pay-back{
    width:34px;height:34px;border-radius:100px;
    background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.10);
    color:var(--text-secondary);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-family:inherit;
}
:root[data-theme="light"] body.sale-page .pay-back{background:rgba(15,23,42,0.06);border-color:rgba(15,23,42,0.10)}
body.sale-page .pay-page-title{
    flex:1;font-size:14px;font-weight:800;letter-spacing:-0.01em;
    color:var(--text-primary);text-align:center;
}
body.sale-page .pay-total-hero{text-align:center;padding:20px 0 16px}
body.sale-page .pay-total-hero .stat-label{display:block;margin-bottom:8px}
body.sale-page .pay-total-hero .stat-num.lg{display:inline}

body.sale-page .pay-methods-row{display:flex;gap:6px;padding:0 14px 12px}
body.sale-page .pay-method-pill{
    flex:1;padding:11px;border-radius:100px;font-size:11px;font-weight:800;
    letter-spacing:0.06em;background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);color:var(--text-secondary);
    cursor:pointer;font-family:inherit;
}
:root[data-theme="light"] body.sale-page .pay-method-pill{background:rgba(15,23,42,0.04);border-color:rgba(15,23,42,0.10)}
body.sale-page .pay-method-pill.active{
    background:linear-gradient(135deg,hsl(var(--hue1) 65% 45%),hsl(var(--hue2) 70% 38%));
    color:#fff;border-color:hsl(var(--hue1) 60% 55%);
    box-shadow:0 4px 14px hsl(var(--hue1) 60% 40% / 0.4),inset 0 1px 0 rgba(255,255,255,0.2);
    text-shadow:0 0 8px rgba(255,255,255,0.3);
}

body.sale-page .pay-input-block{
    position:relative;margin:0 14px 10px;padding:14px 18px;
    border-radius:18px;background:rgba(255,255,255,0.025);
    border:1px solid rgba(255,255,255,0.06);
}
:root[data-theme="light"] body.sale-page .pay-input-block{background:rgba(255,255,255,0.85);border-color:rgba(15,23,42,0.10)}
body.sale-page .pay-input-label{
    font-size:9px;font-weight:900;letter-spacing:0.1em;
    text-transform:uppercase;color:hsl(var(--hue1) 50% 72%);
    margin-bottom:6px;
}
:root[data-theme="light"] body.sale-page .pay-input-label{color:hsl(var(--hue1) 55% 45%)}
body.sale-page .pay-recv-input{
    width:calc(100% - 30px);background:transparent;border:none;outline:none;
    color:var(--text-primary);font-size:32px;font-weight:900;
    letter-spacing:-0.03em;font-variant-numeric:tabular-nums;
    font-family:inherit;text-align:left;padding:0;
}
body.sale-page .pay-input-cur{
    position:absolute;right:18px;top:50%;transform:translateY(-2px);
    font-size:18px;color:var(--text-muted);font-weight:700;
}

body.sale-page .pay-bn-grid{
    display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:0 14px 12px;
}
body.sale-page .pay-bn{
    padding:13px 4px;border-radius:100px;background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);color:var(--text-primary);
    font-size:14px;font-weight:800;cursor:pointer;text-align:center;
    font-variant-numeric:tabular-nums;font-family:inherit;transition:all 0.12s;
}
:root[data-theme="light"] body.sale-page .pay-bn{background:rgba(15,23,42,0.04);border-color:rgba(15,23,42,0.10)}
body.sale-page .pay-bn:active{transform:scale(0.96)}
body.sale-page .pay-bn-exact{
    background:linear-gradient(135deg,hsl(var(--hue1) 55% 32%),hsl(var(--hue2) 60% 26%));
    border-color:hsl(var(--hue1) 55% 45%);
    color:hsl(var(--hue1) 60% 92%);font-size:12px;font-weight:900;
    letter-spacing:0.04em;box-shadow:0 0 14px hsl(var(--hue1) 60% 45% / 0.35);
}

body.sale-page .pay-resto{
    margin:6px 14px 14px;padding:14px 18px;border-radius:14px;
    background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.30);
    display:flex;align-items:baseline;justify-content:space-between;gap:10px;
}
body.sale-page .pay-resto-lbl{
    font-size:9px;font-weight:900;letter-spacing:0.1em;text-transform:uppercase;color:#86efac;
}
:root[data-theme="light"] body.sale-page .pay-resto-lbl{color:#15803d}
body.sale-page .pay-resto-val{
    font-size:24px;font-weight:900;color:#4ade80;
    font-variant-numeric:tabular-nums;letter-spacing:-0.02em;
}
:root[data-theme="light"] body.sale-page .pay-resto-val{color:#15803d}

body.sale-page .pay-confirm-btn{
    display:flex;align-items:center;justify-content:center;gap:10px;
    width:calc(100% - 28px);margin:8px 14px 14px;padding:18px;
    border-radius:100px;
    background:linear-gradient(135deg,hsl(var(--hue1) 70% 50%),hsl(var(--hue2) 75% 42%));
    border:1px solid hsl(var(--hue1) 60% 55%);color:#fff;
    font-size:14px;font-weight:900;letter-spacing:0.04em;cursor:pointer;
    font-family:inherit;
    box-shadow:0 12px 32px hsl(var(--hue1) 60% 40% / 0.45),
               0 0 32px hsl(var(--hue1) 60% 50% / 0.35),
               inset 0 1px 0 rgba(255,255,255,0.28);
    text-shadow:0 0 14px rgba(255,255,255,0.35);
}
body.sale-page .pay-confirm-btn:disabled{opacity:0.4;cursor:not-allowed;box-shadow:none}

</style>
</head>
<body class="sale-page">

<div class="green-flash" id="greenFlash"></div>

<div class="toast" id="toast"></div>

<div class="undo-bar" id="undoBar">
    <span class="undo-text" id="undoText"></span>
    <button class="undo-btn s87v3-tap" id="undoBtn">ОТМЕНИ</button>
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
            <button class="rec-btn-cancel s87v3-tap" id="recCancel">Затвори</button>
            <button class="rec-btn-send s87v3-tap" id="recSend" disabled><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg> Изпрати →</button>
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
        <button class="lp-cancel s87v3-tap" onclick="closeLpPopup()">Откажи</button>
        <button class="lp-ok s87v3-tap" onclick="confirmLpPopup()">OK</button>
    </div>
</div>

<div class="nf-popup" id="nfPopup">
    <div class="nf-icon">⚠️</div>
    <div class="nf-text">Няма такъв артикул</div>
</div>

<div class="sale-wrap s87v3-pagein" id="saleWrap">

    <?php include __DIR__ . '/partials/header.php'; ?>

    <div class="cam-header" id="camHeader">
        <video id="cameraVideo" autoplay playsinline muted></video>
        <div class="cam-overlay">
            <div class="cam-top">
                <button class="cam-btn s87v3-tap" onclick="location.href='warehouse.php'">←</button>
                <span class="cam-title" id="camTitle"><?= $page_title ?></span>
                <div class="cam-tabs" id="camTabs">
                    <button class="cam-tab active" id="camTabRetail" type="button" onclick="setRetailMode()">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
                        ДРЕБНО
                    </button>
                    <button class="cam-tab" id="camTabWholesale" type="button" onclick="openWholesale()">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        ЕДРО
                    </button>
                </div>
                <div class="cam-right">
                    <button class="cam-btn s87v3-tap" id="btnParkedBadge" onclick="openParked()" style="position:relative;display:none" aria-label="Паркирани">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg><span class="park-badge" id="parkedCount">0</span>
                    </button>
                    <button class="cam-btn s87v3-tap" id="btnWholesale" onclick="openWholesale()" style="display:none">👤</button>
                    <button class="cam-btn s87v3-tap" id="themeToggle" type="button" aria-label="Светла/тъмна тема" onclick="toggleTheme()" style="font-size:14px"><span id="themeIconSun" style="display:none">☀️</span><span id="themeIconMoon">🌙</span></button>
                </div>
            </div>
            <div class="scan-corner sc-tl"><svg viewBox="0 0 16 16"><path d="M0 5V1a1 1 0 011-1h4" fill="none" stroke="hsl(255 70% 65%)" stroke-width="2" stroke-opacity="0.7"/></svg></div>
            <div class="scan-corner sc-tr"><svg viewBox="0 0 16 16"><path d="M16 5V1a1 1 0 00-1-1h-4" fill="none" stroke="hsl(255 70% 65%)" stroke-width="2" stroke-opacity="0.7"/></svg></div>
            <div class="scan-corner sc-bl"><svg viewBox="0 0 16 16"><path d="M0 11v4a1 1 0 001 1h4" fill="none" stroke="hsl(255 70% 65%)" stroke-width="2" stroke-opacity="0.7"/></svg></div>
            <div class="scan-corner sc-br"><svg viewBox="0 0 16 16"><path d="M16 11v4a1 1 0 01-1 1h-4" fill="none" stroke="hsl(255 70% 65%)" stroke-width="2" stroke-opacity="0.7"/></svg></div>
            <div class="scan-laser"></div>
            <div class="cam-status">
                <div class="scan-dot"></div>
                <span>НАСОЧИ КЪМ БАРКОДА</span>
            </div>
        </div>
    </div>

    <!-- V5 TABS PILL: ДРЕБНО / ЕДРО -->
    <div class="tabs-pill" id="tabsPill">
        <button class="tab active" id="tabRetail" type="button" onclick="setRetailMode()">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
            ДРЕБНО
        </button>
        <button class="tab" id="tabWholesale" type="button" onclick="openWholesale()">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            ЕДРО
        </button>
    </div>

    <!-- V5 BULK BANNER: Паркирани (показва се само ако има parked) -->
    <button type="button" class="bulk-banner amber" id="bulkParked" style="display:none" onclick="openParked()">
        <div class="bulk-row">
            <div class="bulk-icon">
                <svg viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
            </div>
            <div class="bulk-info">
                <div class="bulk-num-row">
                    <span class="bulk-num" id="bulkParkedNum">0</span>
                    <span class="bulk-num-suffix">паркирани</span>
                </div>
                <div class="bulk-title" id="bulkParkedTitle">—</div>
            </div>
            <span class="bulk-arrow">›</span>
        </div>
    </button>

    <!-- S87E.BUG#6 — Parked inline accordion (toggled by openParked/closeParked) -->
    <div id="parkedListInline" class="parked-list-inline" style="display:none"></div>

    <div class="search-bar">
        <input type="text" id="searchInput" class="search-input" inputmode="search" placeholder="Код, име или баркод" autocomplete="off" autocapitalize="off">
        <button class="search-btn" id="btnVoiceSearch" onclick="startVoice()" aria-label="Гласово търсене"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></button>
    </div>

    <div id="searchResultsInline" class="s-results-inline" style="display:none"></div>
    <!-- legacy hidden numpad-driven results container (kept for code paths that may still reference) -->
    <div class="search-results" id="searchResults" style="display:none"></div>

    <div class="discount-chips" id="discountChips">
        <button class="dc-chip" onclick="applyDiscount(5)">5%</button>
        <button class="dc-chip" onclick="applyDiscount(10)">10%</button>
        <button class="dc-chip" onclick="applyDiscount(15)">15%</button>
        <button class="dc-chip" onclick="applyDiscount(20)">20%</button>
        <button class="dc-chip" onclick="customDiscount()">Друго…</button>
        <span class="dc-close" onclick="closeDiscount()">✕</span>
    </div>

    <div class="cart-zone" id="cartZone">
        <div class="cart-empty" id="cartEmpty">
            <span class="cart-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span>
            <span class="cart-empty-text">Сканирай или въведи код</span>
        </div>
    </div>

    <div class="summary-bar" id="summaryBar" style="display:none">
        <span class="sum-count" id="sumCount">0 арт.</span>
        <button class="sum-discount" id="sumDiscountBtn" onclick="toggleDiscount()">%</button>
        <span class="sum-total">Общо: <span class="amount" id="sumTotal">0,00</span> <?= $currency ?></span>
    </div>

    <div class="action-bar" id="actionBar">
        <button class="btn-pay s87v3-tap" id="btnPay" disabled onclick="openPayment()">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            ПЛАТИ <span id="payAmount">0</span> <?= $currency ?>
        </button>
        <button class="btn-park s87v3-tap" onclick="parkSale()" aria-label="Паркирай">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
        </button>
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
            <button class="np-btn mic" onclick="startVoice()" aria-label="Глас"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></button>
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

</div>
<!-- ═══ S87D — PAYMENT (full-screen, simplified, 3 methods, editable input) ═══ -->
<div class="pay-overlay" id="payOverlay" style="display:none">
    <div class="pay-head">
        <button class="pay-back" type="button" onclick="closePayment()" aria-label="Назад">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="pay-page-title">ПЛАЩАНЕ</span>
        <span style="width:34px"></span>
    </div>

    <div class="pay-total-hero">
        <div class="stat-label">Сума за плащане</div>
        <span class="stat-num lg" id="payDueAmount">0,00</span><span class="stat-cur" id="payDueCur"><?= $currency ?></span>
    </div>

    <div class="pay-methods-row">
        <button class="pay-method-pill active pm-chip" type="button" data-method="cash" onclick="setPayMethod('cash')">КЕШ</button>
        <button class="pay-method-pill pm-chip" type="button" data-method="card" onclick="setPayMethod('card')">КАРТА</button>
        <button class="pay-method-pill pm-chip" type="button" data-method="bank_transfer" onclick="setPayMethod('bank_transfer')">ПРЕВОД</button>
    </div>

    <div id="cashSection">
        <div class="pay-input-block">
            <div class="pay-input-label">Точна сума получена</div>
            <input type="text" inputmode="decimal" id="payRecvAmount" class="pay-recv-input" value="0,00" onkeyup="payCalcChange()" onchange="payCalcChange()" oninput="payCalcChange()" autocomplete="off">
            <span class="pay-input-cur"><?= $currency ?></span>
        </div>

        <div class="pay-bn-grid">
            <button class="pay-bn" type="button" onclick="payBanknote(5)">5</button>
            <button class="pay-bn" type="button" onclick="payBanknote(10)">10</button>
            <button class="pay-bn" type="button" onclick="payBanknote(20)">20</button>
            <button class="pay-bn" type="button" onclick="payBanknote(50)">50</button>
            <button class="pay-bn" type="button" onclick="payBanknote(100)">100</button>
            <button class="pay-bn" type="button" onclick="payBanknote(200)">200</button>
            <button class="pay-bn" type="button" onclick="payBanknote(500)">500</button>
            <button class="pay-bn pay-bn-exact" type="button" onclick="payExact()">ТОЧНО</button>
        </div>

        <div class="pay-resto" id="payChangeBox">
            <span class="pay-resto-lbl">РЕСТО:</span>
            <span class="pay-resto-val" id="payChangeAmount">0,00 <?= $currency ?></span>
        </div>
    </div>

    <button type="button" class="pay-confirm-btn s87v3-tap" id="btnConfirm" onclick="confirmPayment()" disabled>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        ПОТВЪРДИ ПЛАЩАНЕ <span id="payConfirmAmount">0,00 <?= $currency ?></span>
    </button>
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

<!-- S87E — parked overlay removed; rendered inline as accordion under bulk-banner -->

<?php /* sale.php — chat input intentionally OMITTED (POS scanner — no AI distraction during checkout) */ ?>
<?php include __DIR__ . '/partials/bottom-nav.php'; ?>

<!-- S87E — search overlay removed; search is now inline (#searchInput + #searchResultsInline) -->

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

// ─── S87.DEBUG OVERLAY (Bug #1: търсачката не реагира — за phone debug без Chrome inspect) ───
// Toggle: long-press на 'sale-wrap' wrapper за 3s — показва/скрива overlay
window.__rms_debug = false;
function debugLog(msg){
    try {
        if (!window.__rms_debug) return;
        var dbg = document.getElementById('dbgOverlay');
        if (!dbg) {
            dbg = document.createElement('div');
            dbg.id='dbgOverlay';
            dbg.style.cssText='position:fixed;top:calc(50px + env(safe-area-inset-top,0px));left:8px;right:8px;max-height:180px;overflow-y:auto;background:rgba(0,0,0,.85);color:#0f0;font:10px/1.3 monospace;padding:8px;z-index:9999;border-radius:8px;border:1px solid rgba(0,255,0,.3);pointer-events:none;white-space:pre-wrap';
            document.body.appendChild(dbg);
        }
        var t = new Date().toTimeString().slice(0,8);
        dbg.innerHTML += '<div>[' + t + '] ' + (msg || '').toString().slice(0, 200) + '</div>';
        dbg.scrollTop = dbg.scrollHeight;
    } catch(_) {}
}
// Long-press 'СКЕНЕР АКТИВЕН' status text → toggle debug
document.addEventListener('DOMContentLoaded', function(){
    var camStatus = document.querySelector('.cam-status');
    if (!camStatus) return;
    var lpTimer;
    camStatus.addEventListener('touchstart', function(){
        lpTimer = setTimeout(function(){
            window.__rms_debug = !window.__rms_debug;
            var existing = document.getElementById('dbgOverlay');
            if (existing) existing.remove();
            if (window.__rms_debug) {
                debugLog('🟢 Debug overlay ON — long-press status за изключване');
                debugLog('UA: ' + navigator.userAgent.slice(0,80));
                debugLog('viewport: ' + window.innerWidth + 'x' + window.innerHeight);
            }
        }, 1800);
    }, {passive:true});
    camStatus.addEventListener('touchend', function(){ clearTimeout(lpTimer); });
    camStatus.addEventListener('touchmove', function(){ clearTimeout(lpTimer); });
});

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

/* S87 Bug #10 — EUR/BGN dual display per Закон №4 (BIBLE) until 2026-08-08.
 * Returns "1.95 € (3.82 лв)" if BGN tenant before deadline, else single currency.
 * Rate: 1.95583 (fixed per BNB).
 */
const BGN_EUR_RATE = 1.95583;
const DUAL_DEADLINE = new Date('2026-08-08T00:00:00');
function priceFormat(n, opts) {
    opts = opts || {};
    var primary = parseFloat(n) || 0;
    var cur = STATE.currency || 'лв';
    var dual = opts.dual !== false && new Date() < DUAL_DEADLINE;
    var primaryStr = fmtPrice(primary) + ' ' + cur;
    if (!dual) return primaryStr;
    // Compute secondary
    var secondary, secCur;
    if (cur === 'лв' || cur === 'BGN') {
        secondary = primary / BGN_EUR_RATE;
        secCur = '€';
    } else if (cur === '€' || cur === 'EUR') {
        secondary = primary * BGN_EUR_RATE;
        secCur = 'лв';
    } else {
        return primaryStr;
    }
    return primaryStr + ' (' + fmtPrice(secondary) + ' ' + secCur + ')';
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

// S87G.B4 — first-time swipe hint (shown once per device via localStorage)
const S87G_SWIPE_HINT_KEY = 'rms_s87g_swipe_hint_shown';
function s87gShowSwipeHintOnce() {
    try {
        if (localStorage.getItem(S87G_SWIPE_HINT_KEY) === '1') return;
        const zone = document.getElementById('cartZone');
        if (!zone) return;
        if (zone.querySelector('.s87g-swipe-hint')) return;
        const hint = document.createElement('div');
        hint.className = 's87g-swipe-hint';
        hint.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg> плъзни наляво за изтриване';
        zone.insertBefore(hint, zone.firstChild);
        setTimeout(() => { if (hint.parentNode) hint.classList.add('hide'); }, 4500);
        setTimeout(() => { if (hint.parentNode) hint.remove(); }, 5200);
        localStorage.setItem(S87G_SWIPE_HINT_KEY, '1');
    } catch (_) { /* private mode */ }
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

    // Parked badge (legacy + V5 bulk-banner)
    if (STATE.parked.length > 0) {
        if (btnParked) btnParked.style.display = '';
        if (parkedCount) parkedCount.textContent = STATE.parked.length;
    } else {
        if (btnParked) btnParked.style.display = 'none';
    }
    // V5 bulk-banner.amber for parked
    const bulkParked = document.getElementById('bulkParked');
    const bulkParkedNum = document.getElementById('bulkParkedNum');
    const bulkParkedTitle = document.getElementById('bulkParkedTitle');
    if (bulkParked) {
        if (STATE.parked.length > 0) {
            bulkParked.style.display = '';
            bulkParkedNum.textContent = STATE.parked.length;
            bulkParkedTitle.textContent = STATE.parked.map((p, i) =>
                'Парк ' + (i + 1) + ' · ' + fmtPrice(p.total) + ' ' + STATE.currency
            ).join(' + ');
        } else {
            bulkParked.style.display = 'none';
        }
    }

    // Header wholesale
    document.getElementById('camHeader').classList.toggle('wholesale', STATE.isWholesale);
    const camTitle = document.getElementById('camTitle');
    camTitle.textContent = STATE.isWholesale
        ? (STATE.customerName || 'Едро')
        : '<?= $page_title ?>';

    // V5 tabs-pill active state (legacy, hidden via CSS on sale-page) + S87D cam-tabs
    const tabRetail = document.getElementById('tabRetail');
    const tabWholesale = document.getElementById('tabWholesale');
    if (tabRetail && tabWholesale) {
        tabRetail.classList.toggle('active', !STATE.isWholesale);
        tabWholesale.classList.toggle('active', STATE.isWholesale);
        if (STATE.isWholesale && STATE.customerName) {
            tabWholesale.innerHTML = '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' + esc(STATE.customerName).toUpperCase();
        } else {
            tabWholesale.innerHTML = '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>ЕДРО';
        }
    }
    const camTabRetail = document.getElementById('camTabRetail');
    const camTabWholesale = document.getElementById('camTabWholesale');
    if (camTabRetail && camTabWholesale) {
        camTabRetail.classList.toggle('active', !STATE.isWholesale);
        camTabWholesale.classList.toggle('active', STATE.isWholesale);
        const wsSvg = '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
        if (STATE.isWholesale && STATE.customerName) {
            camTabWholesale.innerHTML = wsSvg + esc(STATE.customerName).toUpperCase();
        } else {
            camTabWholesale.innerHTML = wsSvg + 'ЕДРО';
        }
    }

    // Cart
    if (STATE.cart.length === 0) {
        empty.style.display = '';
        summary.style.display = 'none';
        btnPay.disabled = true;
        zone.querySelectorAll('.cart-item, .set-row').forEach(el => el.remove());
        if (typeof s87dDraftSave === 'function') s87dDraftSave(); // FIX5: clear draft if cart empty
        return;
    }

    empty.style.display = 'none';
    summary.style.display = '';

    // Remove old items
    zone.querySelectorAll('.cart-item, .set-row').forEach(el => el.remove());

    // Build cart items (V5 set-row pattern; S87G — split tap zones + swipe-to-delete)
    STATE.cart.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = 'set-row' + (idx === STATE.selectedIndex ? ' selected' : '');
        const lineTotal = item.unit_price * item.quantity * (1 - (item.discount_pct || 0) / 100);
        const unitSub = fmtPrice(item.unit_price) + ' ' + STATE.currency + '/бр' + (item.code ? ' · ' + esc(item.code) : '');
        div.innerHTML = `
            <button type="button" class="ci-delete" data-act="del" aria-label="Изтрий">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                <span>Изтрий</span>
            </button>
            <div class="set-row-fg">
                <div class="set-icon">📦</div>
                <div class="set-text">
                    <div class="set-val">${esc(item.name)}</div>
                    <div class="set-val-sub">${unitSub}</div>
                </div>
                <div class="set-qty">
                    <span class="set-qty-val" data-qty-edit="${idx}" role="button" aria-label="Промени брой">${item.quantity}</span>
                </div>
                <div class="set-total">${fmtPrice(lineTotal)}</div>
            </div>
        `;
        const fg = div.querySelector('.set-row-fg');
        const qtyVal = div.querySelector('.set-qty-val');
        const delBtn = div.querySelector('.ci-delete');

        // S87G.B3 — split tap zone on qty value: left half = -1, right half = +1; long-press = numpad
        let qtyLpTimer = null;
        let qtyLpFired = false;
        if (qtyVal) {
            qtyVal.addEventListener('click', (e) => {
                e.stopPropagation();
                if (qtyLpFired) { qtyLpFired = false; return; }
                const rect = qtyVal.getBoundingClientRect();
                const x = (e.clientX !== undefined ? e.clientX : (e.changedTouches && e.changedTouches[0] ? e.changedTouches[0].clientX : rect.left + rect.width / 2));
                if (x < rect.left + rect.width / 2) {
                    if (STATE.cart[idx].quantity > 1) {
                        STATE.cart[idx].quantity--;
                        render();
                    } else {
                        removeItem(idx);
                    }
                } else {
                    STATE.cart[idx].quantity++;
                    render();
                }
            });
            qtyVal.addEventListener('touchstart', () => {
                qtyLpFired = false;
                clearTimeout(qtyLpTimer);
                qtyLpTimer = setTimeout(() => { qtyLpFired = true; openQtyEditor(idx); }, 500);
            }, {passive: true});
            qtyVal.addEventListener('touchend', () => clearTimeout(qtyLpTimer));
            qtyVal.addEventListener('touchmove', () => clearTimeout(qtyLpTimer));
            qtyVal.addEventListener('touchcancel', () => clearTimeout(qtyLpTimer));
        }

        // Tap row body (not qty, not delete) = select
        div.addEventListener('click', (e) => {
            if (e.target.closest('.ci-delete')) return;
            if (e.target.closest('.set-qty-val')) return;
            if (div.classList.contains('swiped')) {
                // Tap outside delete while swiped → close swipe
                div.classList.remove('swiped');
                if (fg) fg.style.transform = '';
                return;
            }
            selectCartItem(idx);
        });

        // S87G.B4 — swipe left to reveal delete
        let sx = 0, sy = 0, sxStart = 0, dragging = false, locked = false;
        const SWIPE_REVEAL = 90; // matches .ci-delete width
        const SWIPE_THRESHOLD_PX = 60;
        const SWIPE_THRESHOLD_PCT = 0.30;

        div.addEventListener('touchstart', (e) => {
            if (e.target.closest('.set-qty-val')) return; // qty has its own gestures
            const t = e.touches[0];
            const rect = div.getBoundingClientRect();
            sxStart = t.clientX - rect.left; // x within row
            // Spec: ignore swipe unless touchstart is in the rightmost 30px of the row
            if (sxStart < rect.width - 30) { dragging = false; locked = false; sx = 0; return; }
            sx = t.clientX;
            sy = t.clientY;
            dragging = true;
            locked = false;
            if (fg) fg.style.transition = 'none';
        }, {passive: true});

        div.addEventListener('touchmove', (e) => {
            if (!dragging) return;
            const t = e.touches[0];
            const dx = t.clientX - sx;
            const dy = t.clientY - sy;
            if (!locked) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                // Lock direction on first significant move
                if (Math.abs(dy) > Math.abs(dx)) { dragging = false; if (fg) fg.style.transform = ''; return; }
                locked = true;
            }
            if (dx > 0) {
                if (fg) fg.style.transform = '';
                return;
            }
            const clamped = Math.max(dx, -SWIPE_REVEAL);
            if (fg) fg.style.transform = 'translateX(' + clamped + 'px)';
            div.classList.add('swiping');
        }, {passive: true});

        const endSwipe = (e) => {
            if (!dragging) return;
            const t = (e.changedTouches && e.changedTouches[0]) || null;
            const dx = t ? (t.clientX - sx) : 0;
            const rect = div.getBoundingClientRect();
            dragging = false;
            div.classList.remove('swiping');
            if (fg) fg.style.transition = '';
            const reveal = (dx <= -SWIPE_THRESHOLD_PX) || (Math.abs(dx) / Math.max(rect.width, 1) >= SWIPE_THRESHOLD_PCT);
            if (reveal && locked) {
                div.classList.add('swiped');
                if (fg) fg.style.transform = '';
            } else {
                div.classList.remove('swiped');
                if (fg) fg.style.transform = '';
            }
            locked = false;
        };
        div.addEventListener('touchend', endSwipe);
        div.addEventListener('touchcancel', endSwipe);

        if (delBtn) {
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeItem(idx);
            });
        }

        zone.appendChild(div);
    });

    // S87G.B4 — first-time hint: "← плъзни наляво за изтриване"
    if (STATE.cart.length > 0 && typeof s87gShowSwipeHintOnce === 'function') s87gShowSwipeHintOnce();

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

    // FIX5: persist cart as draft on every render so accidental nav-away is recoverable
    if (typeof s87dDraftSave === 'function') s87dDraftSave();
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ─── CART OPERATIONS ───
function addToCart(product, qtyOverride) {
    const qty = (typeof qtyOverride === 'number' && qtyOverride > 0) ? qtyOverride : 1;
    const price = STATE.isWholesale
        ? (parseFloat(product.wholesale_price) || parseFloat(product.retail_price))
        : parseFloat(product.retail_price);

    // Check if already in cart
    const existing = STATE.cart.findIndex(it => it.product_id === product.id);
    if (existing >= 0) {
        STATE.cart[existing].quantity += qty;
        beep(1400, 0.1);
        beep(1800, 0.1); // double beep
    } else {
        STATE.cart.push({
            product_id: product.id,
            code: product.code || '',
            name: product.name || '',
            meta: '',
            unit_price: price,
            quantity: qty,
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
    STATE.searchText = '';
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
    debugLog('numPress("' + key + '") ctx=' + STATE.numpadCtx);
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
// S87E — search input is the source of truth; updateSearchDisplay syncs into #searchInput
function updateSearchDisplay() {
    const input = document.getElementById('searchInput');
    if (input && input.value !== (STATE.searchText || '')) {
        input.value = STATE.searchText || '';
    }
}

function triggerSearch() {
    clearTimeout(STATE.searchTimeout);
    if (!STATE.searchText || STATE.searchText.length < 1) {
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

// S87E — legacy doSearch alias: route through inline search (master/variant rendering)
function doSearch(q) {
    debugLog('doSearch("' + q + '") → doInlineSearch');
    if (typeof doInlineSearch === 'function') {
        doInlineSearch(q);
    }
}

function closeSearchResults() {
    if (typeof inlineSearchClose === 'function') inlineSearchClose();
    const oldEl = document.getElementById('searchResults');
    if (oldEl) oldEl.classList.remove('open');
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
    debugLog('kbPress("' + key + '")');
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

// S87G.B7 — custom discount via inline numpad modal (no native prompt)
function customDiscount() {
    const cur = STATE.discountPct || 0;
    openDiscountModal(cur);
}

// ─── PAYMENT (S87D simplified, S87E.BUG#7 — empty input + smart placeholder) ───
function openPayment() {
    if (STATE.cart.length === 0) return;
    const total = getTotal();
    STATE.payMethod = 'cash';
    STATE.receivedAmount = 0; // S87E — input starts empty; cashier types/taps banknote

    // Hero
    document.getElementById('payDueAmount').textContent = fmtPrice(total).replace(/\s.*$/, '');
    document.getElementById('payDueCur').textContent = ' ' + STATE.currency;

    // Methods → reset to cash active
    setPayMethod('cash', /*skipShow*/true);

    // S87E.BUG#7 — empty input; placeholder is suggested round amount (≥ 20)
    const recv = document.getElementById('payRecvAmount');
    const sugg = Math.max(20, Math.ceil((total + 20) / 10) * 10);
    recv.value = '';
    recv.placeholder = 'напр. ' + sugg.toFixed(2).replace('.', ',');

    // Confirm label
    document.getElementById('payConfirmAmount').textContent = fmtPrice(total) + ' ' + STATE.currency;

    payCalcChange();

    document.getElementById('payOverlay').style.display = 'flex';
    document.body.classList.add('overlay-open');
}

function closePayment() {
    document.getElementById('payOverlay').style.display = 'none';
    document.body.classList.remove('overlay-open');
}

function setPayMethod(method, skipShow) {
    STATE.payMethod = method;
    document.querySelectorAll('.pay-method-pill').forEach(b => {
        b.classList.toggle('active', b.dataset.method === method);
    });

    const cashSec = document.getElementById('cashSection');
    const btnConfirm = document.getElementById('btnConfirm');
    if (method === 'cash') {
        cashSec.style.display = '';
        payCalcChange();
    } else {
        // Card / bank_transfer: no resto needed — confirm always enabled
        cashSec.style.display = 'none';
        btnConfirm.disabled = false;
        document.getElementById('payConfirmAmount').textContent = fmtPrice(getTotal()) + ' ' + STATE.currency;
    }
}

function payBanknote(amt) {
    const recvInput = document.getElementById('payRecvAmount');
    const cur = parseFloat((recvInput.value || '0').replace(',', '.')) || 0;
    const next = cur + parseFloat(amt);
    recvInput.value = next.toFixed(2).replace('.', ',');
    payCalcChange();
}

function payExact() {
    const total = getTotal();
    document.getElementById('payRecvAmount').value = total.toFixed(2).replace('.', ',');
    payCalcChange();
}

function payCalcChange() {
    const total = getTotal();
    const inputVal = (document.getElementById('payRecvAmount').value || '0').replace(',', '.');
    const recv = parseFloat(inputVal) || 0;
    STATE.receivedAmount = recv;

    const change = recv - total;
    document.getElementById('payChangeAmount').textContent = fmtPrice(Math.max(0, change)) + ' ' + STATE.currency;
    document.getElementById('payConfirmAmount').textContent = fmtPrice(total) + ' ' + STATE.currency;

    const btnConfirm = document.getElementById('btnConfirm');
    if (STATE.payMethod === 'cash') {
        btnConfirm.disabled = recv < total;
    } else {
        btnConfirm.disabled = false;
    }
}

// Legacy alias — older callers (voice/numpad) set STATE.receivedAmount; sync input then recalc
function updatePayment() {
    const inp = document.getElementById('payRecvAmount');
    if (inp && typeof STATE.receivedAmount === 'number') {
        const cur = parseFloat((inp.value || '0').replace(',', '.')) || 0;
        if (Math.abs(cur - STATE.receivedAmount) > 0.0001) {
            inp.value = STATE.receivedAmount.toFixed(2).replace('.', ',');
        }
    }
    payCalcChange();
}
function updatePmCardActive() { /* no-op: legacy V5 pkg-card replaced by simple pills */ }

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
function setRetailMode() {
    // V5 tabs-pill: ДРЕБНО клавиш → switch to retail (no client)
    if (!STATE.isWholesale) return; // already retail
    selectClient(null, null);
}
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
    // S87 Bug #6: refetch prices when wholesale toggles (was TODO/noop before)
    if (STATE.cart.length === 0) { render(); return; }
    const ids = STATE.cart.map(it => it.product_id);
    debugLog('refetch prices for ' + ids.length + ' items, wholesale=' + STATE.isWholesale);
    fetch('sale.php?action=refetch_prices', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_ids: ids}),
    })
    .then(r => r.json())
    .then(prices => {
        STATE.cart.forEach(it => {
            const p = prices[it.product_id];
            if (p) {
                it.unit_price = STATE.isWholesale ? p.wholesale : p.retail;
            }
        });
        render();
    })
    .catch(err => {
        debugLog('❌ refetch failed: ' + err);
        showToast('Не успях да обновя цените', '', 3000);
        render();
    });
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
    showToast('Продажба паркирана');

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

// S87E.BUG#6 — Parked inline accordion (toggle, not overlay)
function openParked() {
    const list = document.getElementById('parkedListInline');
    if (!list) return;
    if (STATE.parked.length === 0) {
        list.style.display = 'none';
        return;
    }
    // Toggle: tapping bulk-banner with already-open list closes it
    if (list.style.display !== 'none' && list.innerHTML !== '') {
        closeParked();
        return;
    }
    renderParkedInline();
    list.style.display = '';
}

function renderParkedInline() {
    const list = document.getElementById('parkedListInline');
    if (!list) return;
    list.innerHTML = '';
    STATE.parked.forEach((p, idx) => {
        const row = document.createElement('div');
        row.className = 'parked-row-inline';
        const count = p.cart.reduce((s, it) => s + it.quantity, 0);
        row.innerHTML =
            '<div class="pr-info">' +
                '<div class="pr-name">' + esc(p.customer || 'Дребно') + ' · ' + p.time + '</div>' +
                '<div class="pr-meta">' + count + ' арт. · ' + fmtPrice(p.total) + ' ' + STATE.currency + '</div>' +
            '</div>' +
            '<button type="button" class="pr-load" data-idx="' + idx + '">Зареди</button>' +
            '<button type="button" class="pr-del" data-idx="' + idx + '" aria-label="Изтрий">✕</button>';
        row.querySelector('.pr-load').addEventListener('click', (e) => {
            e.stopPropagation();
            loadParked(idx);
        });
        row.querySelector('.pr-del').addEventListener('click', (e) => {
            e.stopPropagation();
            deleteParked(idx);
        });
        list.appendChild(row);
    });
}

function loadParked(idx) {
    const p = STATE.parked[idx];
    if (!p) return;
    if (STATE.cart.length > 0) {
        parkSale(); // auto-park current
        // After parkSale, STATE.parked grew by 1 — recompute idx
        idx = STATE.parked.findIndex(x => x === p);
        if (idx < 0) return;
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
}

function deleteParked(idx) {
    STATE.parked.splice(idx, 1);
    saveParked();
    if (STATE.parked.length === 0) {
        closeParked();
    } else {
        renderParkedInline();
    }
    render();
}

function closeParked() {
    const list = document.getElementById('parkedListInline');
    if (!list) return;
    list.style.display = 'none';
    list.innerHTML = '';
}

// ─── S87G — Generic numpad modal (qty / discount / search-add) ───
// Reuses #lpPopup HTML; modes: 'qty' (cart row qty edit), 'discount' (discount %), 'search' (add product with qty)
let qmMode = 'qty';
let qmTargetIdx = -1;       // for qty mode
let qmTargetProduct = null; // for search mode
let qmValue = '';
// Legacy aliases (kept for any older callers)
let lpTargetIdx = -1;
let lpValue = '';

function _qmShow(title, initial) {
    qmValue = initial != null ? String(initial) : '';
    document.getElementById('lpTitle').textContent = title || '';
    document.getElementById('lpDisplay').textContent = qmValue || '0';
    const popup = document.getElementById('lpPopup');
    popup.style.left = '50%';
    popup.style.top = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    popup.classList.add('show');
    if (navigator.vibrate) navigator.vibrate(30);
}

// S87G.B2 — Qty edit modal (replaces openQtyEditor / openLpPopup native prompt)
function openQtyEditor(idx) {
    if (idx < 0 || idx >= STATE.cart.length) return;
    qmMode = 'qty';
    qmTargetIdx = idx;
    qmTargetProduct = null;
    lpTargetIdx = idx;
    _qmShow(STATE.cart[idx].name || 'Бройки', STATE.cart[idx].quantity);
}
function openQtyModal(idx) { return openQtyEditor(idx); }

// S87G.B7 — Discount modal
function openDiscountModal(initial) {
    qmMode = 'discount';
    qmTargetIdx = -1;
    qmTargetProduct = null;
    _qmShow('Отстъпка %', initial || '');
}

// S87G.B5 — Search-add modal: tap on result → numpad → addItem(product, qty)
function openSearchAddModal(product) {
    if (!product) return;
    qmMode = 'search';
    qmTargetIdx = -1;
    qmTargetProduct = product;
    _qmShow(product.name || 'Бройки', '1');
}

function closeLpPopup() {
    document.getElementById('lpPopup').classList.remove('show');
    qmTargetIdx = -1;
    qmTargetProduct = null;
    qmValue = '';
    lpTargetIdx = -1;
    lpValue = '';
}

function lpNum(key) {
    if (key === 'C') { qmValue = ''; }
    else if (key === '⌫') { qmValue = qmValue.slice(0, -1); }
    else if (key === '.' || key === ',') {
        // Allow decimal only in discount mode (e.g. 12.5%)
        if (qmMode === 'discount' && !qmValue.includes('.')) {
            qmValue += qmValue.length === 0 ? '0.' : '.';
        }
    }
    else { qmValue += key; }
    lpValue = qmValue; // legacy mirror
    document.getElementById('lpDisplay').textContent = qmValue || '0';
}

function confirmLpPopup() {
    const trimmed = qmValue.trim();
    if (qmMode === 'qty') {
        if (qmTargetIdx < 0 || qmTargetIdx >= STATE.cart.length) { closeLpPopup(); return; }
        const q = parseInt(trimmed, 10);
        if (trimmed === '' || isNaN(q) || q < 0) { showToast('Невалиден брой'); return; }
        const idx = qmTargetIdx;
        if (q === 0) {
            closeLpPopup();
            removeItem(idx);
            return;
        }
        STATE.cart[idx].quantity = q;
        closeLpPopup();
        render();
        return;
    }
    if (qmMode === 'discount') {
        const n = parseFloat(trimmed.replace(',', '.'));
        if (trimmed === '' || isNaN(n) || n < 0 || n > 100) { showToast('Невалиден процент (0–100)'); return; }
        closeLpPopup();
        applyDiscount(n);
        return;
    }
    if (qmMode === 'search') {
        if (!qmTargetProduct) { closeLpPopup(); return; }
        const q = parseInt(trimmed, 10);
        if (trimmed === '' || isNaN(q) || q <= 0) { showToast('Невалиден брой'); return; }
        const product = qmTargetProduct;
        closeLpPopup();
        addToCart(product, q);
        const inp = document.getElementById('searchInput');
        if (inp) inp.value = '';
        STATE.searchText = '';
        inlineSearchClose();
        return;
    }
    closeLpPopup();
}

// Legacy alias retained (no longer triggered by long-press; some legacy modules may call it)
function openLpPopup(idx) { return openQtyEditor(idx); }

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
        debugLog('camera started');
    } catch (e) {
        console.warn('Camera not available', e);
        debugLog('❌ camera: ' + (e && e.name ? e.name : String(e)).slice(0, 60));
        // S87 Bug #11: fallback UX toast for browsers without BarcodeDetector
        if (!('BarcodeDetector' in window)) {
            showToast('Камерата не сканира — въведи код ръчно', '', 4000);
        }
    }
}

// S87 Bug #3: Camera lifecycle — release stream on hide, restart on show
function stopCamera() {
    const video = document.getElementById('cameraVideo');
    if (video && video.srcObject) {
        video.srcObject.getTracks().forEach(t => t.stop());
        video.srcObject = null;
    }
    if (scanRAF) { cancelAnimationFrame(scanRAF); scanRAF = null; }
    STATE.cameraActive = false;
    debugLog('camera stopped');
}
document.addEventListener('visibilitychange', function(){
    if (document.hidden) {
        stopCamera();
    } else if (!STATE.cameraActive) {
        startCamera();
    }
});
window.addEventListener('pagehide', stopCamera);

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

// S87G.B6 — Voice inline (no overlay). Mic button stays in place; red pulse + interim transcript fills #searchInput.
let s87gVoiceActive = false;

function startVoice() {
    if (s87gVoiceActive) {
        // Tap again while recording → stop early
        try { if (recognition) recognition.stop(); } catch(_) {}
        return;
    }
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        showToast('Гласовото разпознаване не се поддържа');
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.lang = 'bg-BG';
    recognition.continuous = false;
    recognition.interimResults = true; // S87G — real-time interim transcript in #searchInput

    const btn = document.getElementById('btnVoiceSearch');
    const inp = document.getElementById('searchInput');
    if (btn) { btn.classList.remove('voice-processing'); btn.classList.add('voice-recording'); }
    s87gVoiceActive = true;
    lastTranscript = '';

    recognition.onresult = (e) => {
        let finalText = '';
        let interimText = '';
        for (let i = 0; i < e.results.length; i++) {
            const r = e.results[i];
            if (r.isFinal) finalText += r[0].transcript;
            else interimText += r[0].transcript;
        }
        const live = (finalText + ' ' + interimText).trim();
        if (inp) inp.value = live;
        STATE.searchText = live;
        if (finalText) lastTranscript = finalText.trim();
        if (live.length >= 2) {
            clearTimeout(srchOvDebounce);
            srchOvDebounce = setTimeout(() => doInlineSearch(live), 250);
        }
    };

    recognition.onerror = () => {
        if (btn) btn.classList.remove('voice-recording');
        s87gVoiceActive = false;
        showToast('Не разбрах, опитайте пак');
    };

    recognition.onend = () => {
        if (btn) {
            btn.classList.remove('voice-recording');
            if (lastTranscript) {
                btn.classList.add('voice-processing');
                setTimeout(() => btn.classList.remove('voice-processing'), 800);
            }
        }
        s87gVoiceActive = false;
        if (lastTranscript) {
            handleVoiceResult(lastTranscript);
        }
    };

    try { recognition.start(); }
    catch (err) {
        if (btn) btn.classList.remove('voice-recording');
        s87gVoiceActive = false;
        showToast('Грешка при стартиране на микрофона');
    }
}

// S87G — rec-ov handlers preserved as no-ops for legacy callers; rec-ov is no longer opened by sale-page voice button (kept in DOM for future modules).
(function wireRecOvLegacy(){
    const recSendEl = document.getElementById('recSend');
    if (recSendEl) recSendEl.addEventListener('click', () => {
        const ov = document.getElementById('recOv');
        if (ov) ov.classList.remove('open');
        if (lastTranscript) handleVoiceResult(lastTranscript);
    });
    const recCancelEl = document.getElementById('recCancel');
    if (recCancelEl) recCancelEl.addEventListener('click', () => {
        if (recognition) { try { recognition.abort(); } catch(_){} }
        const ov = document.getElementById('recOv');
        if (ov) ov.classList.remove('open');
    });
    const recOvEl = document.getElementById('recOv');
    if (recOvEl) recOvEl.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            if (recognition) { try { recognition.abort(); } catch(_){} }
            e.currentTarget.classList.remove('open');
        }
    });
})();

function handleVoiceResult(text) {
    // S87E — voice fills #searchInput inline; preserves AI parsing flow.
    function fillAndSearch(q) {
        STATE.searchText = q || '';
        STATE.numpadInput = STATE.searchText;
        const inp = document.getElementById('searchInput');
        if (inp) inp.value = STATE.searchText;
        updateSearchDisplay();
        if (STATE.searchText.length >= 1 && typeof doInlineSearch === 'function') {
            doInlineSearch(STATE.searchText);
        }
    }
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
            fillAndSearch(res.query || text);
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
            fillAndSearch(text);
        }
    })
    .catch(() => {
        fillAndSearch(text);
    });
}

// Voice cancel handled above in recCancel listener

// ─── SWIPE NAVIGATION ───
let touchStartX = 0;
const swipePages = ['chat.php', 'warehouse.php', 'stats.php', 'sale.php'];
const currentPageIdx = 3;

document.addEventListener('touchstart', (e) => {
    if (e.target.closest('.cart-zone, .pay-sheet, .ws-sheet, .parked-list-inline, .s-results-inline, .numpad-grid, .keyboard-zone')) return;
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
    // S87E — search is now inline (#searchInput); no overlay-open wiring needed
});

// CSS blink animation for cursor
const blinkStyle = document.createElement('style');
blinkStyle.textContent = '@keyframes blink{0%,50%{opacity:1}51%,100%{opacity:0}}';
document.head.appendChild(blinkStyle);

/* ═══════════════════════════════════════════════════════════
   S87D Phase 4 — DRAFT save/restore + browser/hardware back guard
   FIX3,4,5: protect against accidental navigate-away while cart has items.
   ═══════════════════════════════════════════════════════════ */
const RMS_DRAFT_KEY = 'rms_sale_draft_v1';

function s87dDraftSave() {
    try {
        if (!STATE || !STATE.cart || STATE.cart.length === 0) {
            localStorage.removeItem(RMS_DRAFT_KEY);
            return;
        }
        const draft = {
            cart: STATE.cart,
            discountPct: STATE.discountPct || 0,
            customerId: STATE.customerId || null,
            customerName: STATE.customerName || null,
            isWholesale: !!STATE.isWholesale,
            ts: Date.now(),
        };
        localStorage.setItem(RMS_DRAFT_KEY, JSON.stringify(draft));
    } catch (_) { /* quota / private mode — ignore */ }
}
function s87dDraftClear() {
    try { localStorage.removeItem(RMS_DRAFT_KEY); } catch (_) {}
}
function s87dDraftLoad() {
    try {
        const raw = localStorage.getItem(RMS_DRAFT_KEY);
        if (!raw) return null;
        const d = JSON.parse(raw);
        // expire after 24h
        if (!d || !d.ts || (Date.now() - d.ts) > 86400000) {
            s87dDraftClear();
            return null;
        }
        return d;
    } catch (_) { return null; }
}

// Save on tab close/refresh — render() also calls s87dDraftSave directly (see below)
window.addEventListener('beforeunload', () => { s87dDraftSave(); });
window.addEventListener('pagehide', () => { s87dDraftSave(); });

// Restore prompt on load (only if cart still empty)
document.addEventListener('DOMContentLoaded', () => {
    const d = s87dDraftLoad();
    if (!d || !d.cart || d.cart.length === 0) return;
    if (STATE.cart && STATE.cart.length > 0) return; // already populated (e.g. server-side)

    const ageMin = Math.round((Date.now() - d.ts) / 60000);
    const ageStr = ageMin < 1 ? 'преди малко' : (ageMin < 60 ? 'преди ' + ageMin + ' мин' : 'преди ' + Math.round(ageMin/60) + ' ч');
    const cnt = d.cart.reduce((s, it) => s + (parseInt(it.quantity) || 0), 0);

    setTimeout(() => {
        if (confirm('Намерена незавършена продажба (' + cnt + ' арт., ' + ageStr + '). Възстанови?')) {
            STATE.cart = d.cart;
            STATE.discountPct = d.discountPct || 0;
            STATE.customerId = d.customerId || null;
            STATE.customerName = d.customerName || null;
            STATE.isWholesale = !!d.isWholesale;
            render();
        } else {
            s87dDraftClear();
        }
    }, 400);
});

/* Browser/Hardware BACK protection
   - Open overlay (search/payment) pushes a history entry; popstate pops it & closes overlay (no navigate)
   - On main sale screen with non-empty cart, popstate triggers confirm before navigating away
*/
let s87dHistoryGuardArmed = false;
function s87dArmHistoryGuard(tag) {
    history.pushState({ rmsOverlay: tag }, '', location.href);
    s87dHistoryGuardArmed = true;
}
function s87dDisarmHistoryGuard() {
    s87dHistoryGuardArmed = false;
}

window.addEventListener('popstate', (e) => {
    // 1) overlays handle first (S87E — search is inline; only payment is overlay)
    const pov = document.getElementById('payOverlay');
    if (pov && pov.style.display === 'flex') {
        closePayment();
        s87dDisarmHistoryGuard();
        return;
    }
    // 2) main screen: cart non-empty → save draft + confirm
    if (STATE && STATE.cart && STATE.cart.length > 0) {
        s87dDraftSave();
        // Re-arm one history step so the user can still back out — but warn first
        history.pushState({ rmsGuard: true }, '', location.href);
        if (confirm('Имате ' + STATE.cart.length + ' арт. в кошницата. Запазени са като чернова. Излез ли?')) {
            // pop the guard we just pushed, then go back for real
            history.go(-2);
        }
    }
});

// Wrap openPayment to push history state for back-button overlay close.
// (S87E — search is inline; only payment retains its overlay/history guard.)
document.addEventListener('DOMContentLoaded', () => {
    const _oOpenPay = window.openPayment;
    if (typeof _oOpenPay === 'function') {
        window.openPayment = function() {
            _oOpenPay.apply(this, arguments);
            s87dArmHistoryGuard('pay');
        };
    }
});

// On successful confirmPayment we should clear draft (cart auto-clears + render saves empty)
// — already covered: after confirmPayment STATE.cart=[] → render() → s87dDraftSave removes key.

/* ═══════════════════════════════════════════════════════════
   S87E — INLINE SEARCH (master/variant grouping, debounce + AbortController)
   ═══════════════════════════════════════════════════════════ */
let srchOvCurrentMaster = null;
let srchOvDebounce = null;
let srchOvAbortCtl = null;

function inlineSearchClose() {
    const c = document.getElementById('searchResultsInline');
    if (!c) return;
    c.style.display = 'none';
    c.innerHTML = '';
    srchOvCurrentMaster = null;
    srchSelectedKey = null;
}

function inlineSearchBackToMaster() {
    // Re-run the search to repopulate masters
    const input = document.getElementById('searchInput');
    const q = input ? input.value.trim() : '';
    srchOvCurrentMaster = null;
    if (q.length >= 1) {
        doInlineSearch(q);
    } else {
        inlineSearchClose();
    }
}

function doInlineSearch(q) {
    const c = document.getElementById('searchResultsInline');
    if (!c) return;
    c.style.display = '';
    c.innerHTML = '<div class="s-results-inline-meta">Търся...</div>';

    // Abort prior request to avoid stale-results race
    if (srchOvAbortCtl) { try { srchOvAbortCtl.abort(); } catch(_){} }
    srchOvAbortCtl = new AbortController();

    fetch('sale.php?action=quick_search&q=' + encodeURIComponent(q), { signal: srchOvAbortCtl.signal })
        .then(r => r.json())
        .then(results => {
            const masters = srchOvGroupByMaster(results || []);
            srchOvRenderMasters(masters);
            const total = (results || []).length;
            const mCount = masters.length;
            const metaEl = c.querySelector('.s-results-inline-meta');
            if (metaEl) metaEl.textContent = 'Намерени: ' + mCount + (mCount === 1 ? ' модел' : ' модела') + ' · ' + total + (total === 1 ? ' вариант' : ' варианта');
        })
        .catch(err => {
            if (err && err.name === 'AbortError') return;
            const meta = c.querySelector('.s-results-inline-meta');
            if (meta) meta.textContent = 'Грешка при търсене';
            console.error('doInlineSearch error', err);
        });
}

// Group by parent_id; if NULL → group by name; fallback group key = id (single-row)
function srchOvGroupByMaster(results) {
    const masters = {};
    results.forEach(p => {
        const key = p.parent_id ? ('p' + p.parent_id) : ('n:' + (p.name || '').toLowerCase());
        if (!masters[key]) {
            masters[key] = {
                key: key,
                name: p.name || '—',
                image: p.image_url || null,
                variants: [],
                minPrice: parseFloat(p.retail_price) || 0,
                maxPrice: parseFloat(p.retail_price) || 0,
            };
        }
        masters[key].variants.push(p);
        const price = parseFloat(p.retail_price) || 0;
        if (price < masters[key].minPrice) masters[key].minPrice = price;
        if (price > masters[key].maxPrice) masters[key].maxPrice = price;
        if (!masters[key].image && p.image_url) masters[key].image = p.image_url;
    });
    return Object.values(masters);
}

// S87G.HOTFIX_B5 — selection key for currently-tapped result row (master or variant).
// Row body tap = select (visual state); explicit "+" / "→" button = open numpad.
let srchSelectedKey = null;

function srchClearSelectedUI() {
    const c = document.getElementById('searchResultsInline');
    if (!c) return;
    c.querySelectorAll('.srch-master.selected, .srch-variant.selected').forEach(el => el.classList.remove('selected'));
}

function srchSelectRow(rowEl, key) {
    srchClearSelectedUI();
    if (rowEl) rowEl.classList.add('selected');
    srchSelectedKey = key;
}

function srchOvRenderMasters(masters) {
    const c = document.getElementById('searchResultsInline');
    if (!c) return;
    // Preserve meta text from caller
    const metaTxt = (c.querySelector('.s-results-inline-meta') || {}).textContent || '';
    c.innerHTML = '<div class="s-results-inline-meta">' + esc(metaTxt) + '</div>';
    if (masters.length === 0) {
        c.innerHTML += '<div class="srch-empty">Няма намерени артикули</div>';
        return;
    }
    masters.forEach((m) => {
        const row = document.createElement('div');
        row.className = 'srch-master s87v3-tap';
        row.setAttribute('role', 'button');
        const variantText = m.variants.length === 1 ? '1 вариант' : (m.variants.length + ' варианта');
        const priceText = (m.minPrice === m.maxPrice)
            ? fmtPrice(m.minPrice)
            : ('от ' + fmtPrice(m.minPrice));
        const ico = m.image
            ? '<img src="' + esc(m.image) + '" alt="" loading="lazy">'
            : '📦';
        const single = m.variants.length === 1;
        const masterKey = single ? ('p:' + m.variants[0].id) : ('m:' + m.key);
        if (srchSelectedKey === masterKey) row.classList.add('selected');
        const trailingHtml = single
            ? '<button type="button" class="srch-master-add" aria-label="Добави">+</button>'
            : '<span class="srch-master-arrow" aria-hidden="true">›</span>';
        row.innerHTML =
            '<div class="srch-master-ico">' + ico + '</div>' +
            '<div class="srch-master-info">' +
                '<div class="srch-master-name">' + esc(m.name) + '</div>' +
                '<div class="srch-master-meta">' + variantText + ' · ' + priceText + '</div>' +
            '</div>' +
            trailingHtml;
        if (single) {
            // Single-variant master: row body tap = select; explicit "+" tap = open numpad.
            row.addEventListener('click', (e) => {
                if (e.target.closest('.srch-master-add')) return; // add button handler below
                srchSelectRow(row, masterKey);
            });
            const addBtn = row.querySelector('.srch-master-add');
            if (addBtn) addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                srchSelectRow(row, masterKey);
                srchOvAddProduct(m.variants[0]);
            });
        } else {
            // Multi-variant master: row tap = drill-down to variants (no selection persists).
            row.addEventListener('click', () => {
                srchOvOpenVariants(m);
            });
        }
        c.appendChild(row);
    });
}

function srchOvOpenVariants(master) {
    srchOvCurrentMaster = master;
    const c = document.getElementById('searchResultsInline');
    if (!c) return;
    c.style.display = '';
    c.innerHTML = '';
    const back = document.createElement('button');
    back.type = 'button';
    back.className = 's-results-inline-back';
    back.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg> Назад · ' + esc(master.name);
    back.addEventListener('click', inlineSearchBackToMaster);
    c.appendChild(back);
    master.variants.forEach(v => {
        const div = document.createElement('div');
        div.className = 'srch-variant s87v3-tap';
        div.setAttribute('role', 'button');
        const variantKey = 'p:' + v.id;
        if (srchSelectedKey === variantKey) div.classList.add('selected');
        const stock = parseInt(v.stock || 0);
        const stockCls = stock === 0 ? 'srch-variant-stock zero' : 'srch-variant-stock';
        const colorBg = srchOvColorToCss(v.color);
        const variantTitle = [v.color, v.size].filter(x => x && String(x).trim()).join(' · ') || (v.code || '—');
        div.innerHTML =
            '<span class="srch-variant-color" style="background:' + colorBg + '"></span>' +
            '<div class="srch-variant-info">' +
                '<div class="srch-variant-name">' + esc(variantTitle) + '</div>' +
                '<div class="' + stockCls + '">' + stock + ' бр</div>' +
            '</div>' +
            '<span class="srch-variant-price">' + fmtPrice(parseFloat(v.retail_price) || 0) + '</span>' +
            '<button type="button" class="srch-variant-add" aria-label="Добави">+</button>';
        // Row body tap = select (visual state); does NOT open numpad.
        div.addEventListener('click', (e) => {
            if (e.target.closest('.srch-variant-add')) return; // add button handler below
            srchSelectRow(div, variantKey);
        });
        const addBtn = div.querySelector('.srch-variant-add');
        if (addBtn) addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            srchSelectRow(div, variantKey);
            srchOvAddProduct(v);
        });
        c.appendChild(div);
    });
}

// Map a Bulgarian/English color name to a CSS color (rough fallback)
function srchOvColorToCss(name) {
    if (!name) return 'rgba(255,255,255,0.12)';
    const n = String(name).trim().toLowerCase();
    const map = {
        'червен':'#e11d48','red':'#e11d48',
        'син':'#2563eb','blue':'#2563eb',
        'тъмносин':'#1e3a8a','navy':'#1e3a8a',
        'зелен':'#16a34a','green':'#16a34a',
        'жълт':'#eab308','yellow':'#eab308',
        'черен':'#1a1a1a','black':'#1a1a1a',
        'бял':'#f8fafc','white':'#f8fafc',
        'сив':'#94a3b8','grey':'#94a3b8','gray':'#94a3b8',
        'кафяв':'#92400e','brown':'#92400e',
        'розов':'#ec4899','pink':'#ec4899',
        'оранжев':'#f97316','orange':'#f97316',
        'лилав':'#9333ea','purple':'#9333ea','виолетов':'#9333ea',
        'бежов':'#d6c2a4','beige':'#d6c2a4',
    };
    if (map[n]) return map[n];
    // Try first word
    const first = n.split(/\s+/)[0];
    if (map[first]) return map[first];
    return 'rgba(255,255,255,0.12)';
}

// S87G.B5 — Search result tap → numpad modal for qty; OK adds with chosen qty.
function srchOvAddProduct(product) {
    if (typeof openSearchAddModal === 'function') {
        openSearchAddModal(product);
        return;
    }
    if (typeof addToCart === 'function') addToCart(product);
    showSrchToast('✓ ' + (product.name || 'Артикул') + ' добавен');
}

function showSrchToast(msg) {
    const old = document.querySelector('.srch-toast');
    if (old) old.remove();
    const t = document.createElement('div');
    t.className = 'srch-toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { if (t.parentNode) t.remove(); }, 2000);
}

function srchOvVoice() {
    if (typeof startVoice === 'function') {
        // existing handler: opens recording overlay; user dictates → injected via voice flow
        startVoice();
    }
}

// S87G.B1 — Wire #searchInput live-search (input + keyup, debounced 250ms, min 2 chars, AbortController)
(function wireLiveSearch(){
    function doWire(){
        const input = document.getElementById('searchInput');
        if (!input || input.__s87gWired) return;
        input.__s87gWired = true;
        const onChange = () => {
            const q = (input.value || '').trim();
            STATE.searchText = q;
            clearTimeout(srchOvDebounce);
            if (srchOvAbortCtl) { try { srchOvAbortCtl.abort(); } catch(_){} }
            if (q.length < 2) {
                inlineSearchClose();
                return;
            }
            srchOvDebounce = setTimeout(() => doInlineSearch(q), 250);
        };
        input.addEventListener('input', onChange);
        input.addEventListener('keyup', onChange);
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const q = (input.value || '').trim();
                if (q.length >= 2) {
                    clearTimeout(srchOvDebounce);
                    doInlineSearch(q);
                }
            }
        });
    }
    if (document.readyState !== 'loading') doWire();
    else document.addEventListener('DOMContentLoaded', doWire);
})();

// Hardware back (Capacitor): inline-variants → masters; otherwise close inline
document.addEventListener('backbutton', (e) => {
    if (srchOvCurrentMaster) {
        e.preventDefault();
        inlineSearchBackToMaster();
        return;
    }
    const c = document.getElementById('searchResultsInline');
    if (c && c.style.display !== 'none' && c.innerHTML.length > 0) {
        e.preventDefault();
        const input = document.getElementById('searchInput');
        if (input) input.value = '';
        inlineSearchClose();
    }
});

/* ───────────────────────────────────────────── */
/* S87.ANIMATIONS v3 — portable JS (idempotent)  */
/* sale.php: numpad/keyboard untouched (rapid input) */
/* ───────────────────────────────────────────── */
(function s87v3_init(){
    if (window.__s87v3_loaded) return;
    window.__s87v3_loaded = true;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduced) {
        var headerEl = document.querySelector('.rms-header') || document.querySelector('.header');
        var lastScroll = 0;
        window.addEventListener('scroll', function(){
            var y = window.scrollY;
            if (headerEl) {
                if (y > 30 && lastScroll <= 30) headerEl.classList.add('scrolled');
                else if (y <= 30 && lastScroll > 30) headerEl.classList.remove('scrolled');
            }
            lastScroll = y;
        }, { passive: true });
    }
    if (!reduced) {
        var attachTap = function(){
            document.querySelectorAll('.s87v3-tap').forEach(function(el){
                if (el.__s87v3_tap) return;
                el.__s87v3_tap = true;
                var handler = function(){
                    el.classList.remove('s87v3-released');
                    void el.offsetWidth;
                    el.classList.add('s87v3-released');
                    setTimeout(function(){ el.classList.remove('s87v3-released'); }, 400);
                };
                el.addEventListener('touchend', handler, { passive: true });
                el.addEventListener('mouseup', handler);
            });
        };
        if (document.readyState !== 'loading') attachTap();
        else document.addEventListener('DOMContentLoaded', attachTap);
    }
})();
</script>

<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
</body>
</html>
