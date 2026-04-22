<?php
/**
 * print.php — RunMyStore.ai
 * S81.PRINT Stage 2 — Bluetooth Label Printing Page
 * 
 * Страница за печат на етикети за продукт + всички негови варианти.
 * 
 * Usage:
 *   print.php?product_id=123              — един продукт + варианти
 *   print.php?product_ids=123,456,789     — bulk режим
 * 
 * Изисква: js/printer.js (DTM-5811 TSPL)
 */

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
$store_id  = (int)($_SESSION['store_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'seller';

if (!$tenant_id) { header('Location: login.php'); exit; }

// ────────────────────────────────────────────────────────────
// LOCAL HELPERS (не разчитаме на глобални)
// ────────────────────────────────────────────────────────────

const BGN_TO_EUR_RATE = 1.95583;
const BG_DUAL_END_DATE = '2026-08-08';

/**
 * Форматира цена с двойно обозначение (€ + лв) ако е BG до 8.8.2026
 * Връща ['eur' => '€ 45.50', 'bgn' => '89.00 лв', 'show_bgn' => bool]
 */
function pricePair($amount, $tenant) {
    $amount = (float)$amount;
    $currency = strtolower($tenant['currency'] ?? 'eur');
    $country  = strtoupper($tenant['country_code'] ?? 'BG');
    $today    = date('Y-m-d');
    $show_bgn = ($country === 'BG' && $today <= BG_DUAL_END_DATE);

    // Изчисляваме EUR + BGN независимо коя е "primary" в DB
    if ($currency === 'eur') {
        $eur_amount = $amount;
        $bgn_amount = $amount * BGN_TO_EUR_RATE;
    } else {
        $bgn_amount = $amount;
        $eur_amount = $amount / BGN_TO_EUR_RATE;
    }

    return [
        'eur'      => '€ ' . number_format($eur_amount, 2, '.', ''),
        'bgn'      => number_format($bgn_amount, 2, '.', '') . ' лв',
        'show_bgn' => $show_bgn,
        'eur_raw'  => $eur_amount,
        'bgn_raw'  => $bgn_amount
    ];
}

function htmlsafe($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function jsonsafe($s) {
    return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// ────────────────────────────────────────────────────────────
// LOAD TENANT
// ────────────────────────────────────────────────────────────

$tenant = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
if (!$tenant) { http_response_code(403); die('Tenant not found'); }

$store_name = $tenant['name'] ?? 'Магазин';
$country_code = strtoupper($tenant['country_code'] ?? 'BG');
$default_format = ($country_code === 'BG' && date('Y-m-d') <= BG_DUAL_END_DATE) ? 'both' : 'eur';

// ────────────────────────────────────────────────────────────
// PARSE INPUT — product_id или product_ids
// ────────────────────────────────────────────────────────────

$product_ids = [];
if (isset($_GET['product_id'])) {
    $pid = (int)$_GET['product_id'];
    if ($pid > 0) $product_ids[] = $pid;
}
if (isset($_GET['product_ids'])) {
    foreach (explode(',', $_GET['product_ids']) as $p) {
        $p = (int)trim($p);
        if ($p > 0) $product_ids[] = $p;
    }
}
$product_ids = array_values(array_unique($product_ids));

if (empty($product_ids)) {
    http_response_code(400);
    $msg = 'Липсва product_id. Използвайте print.php?product_id=N';
    require __DIR__ . '/_print_error.php' ?? null;
    die($msg);
}

// ────────────────────────────────────────────────────────────
// LOAD PRODUCTS + VARIATIONS + INVENTORY
// 
// Вариантите са деца на parent (parent_id = product_id).
// За всеки product_id зареждаме самия него + всичките му деца.
// ────────────────────────────────────────────────────────────

$ph = implode(',', array_fill(0, count($product_ids), '?'));
$params = array_merge($product_ids, $product_ids, [$tenant_id]);

// SELECT — country_origin/origin_country е optional колона, проверяваме съществува ли
$has_origin = false;
try {
    $col_check = DB::run("SHOW COLUMNS FROM products LIKE 'country_origin'")->fetch();
    $has_origin = !empty($col_check);
} catch (Exception $e) { $has_origin = false; }

$origin_col = $has_origin ? 'p.country_origin' : "'' AS country_origin";

$rows = DB::run(
    "SELECT 
        p.id, p.parent_id, p.name, p.code, p.retail_price, p.barcode, 
        p.size, p.color, p.image_url, $origin_col,
        COALESCE(i.quantity, 0) AS qty
     FROM products p
     LEFT JOIN inventory i ON i.product_id = p.id AND i.store_id = ?
     WHERE (p.id IN ($ph) OR p.parent_id IN ($ph))
       AND p.tenant_id = ?
       AND p.is_active = 1
     ORDER BY COALESCE(p.parent_id, p.id), p.size, p.color, p.id",
    array_merge([$store_id], $params)
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    http_response_code(404);
    die('Не намерих продукт за печат.');
}

// Групираме по parent
$groups = []; // [parent_id => ['parent' => row, 'variants' => [rows]]]
foreach ($rows as $r) {
    $parent_pid = $r['parent_id'] ?: $r['id'];

    if (!isset($groups[$parent_pid])) {
        $groups[$parent_pid] = ['parent' => null, 'variants' => []];
    }

    if ($r['parent_id'] === null && (int)$r['id'] === (int)$parent_pid) {
        // Това е parent product
        $groups[$parent_pid]['parent'] = $r;
    } else {
        $groups[$parent_pid]['variants'][] = $r;
    }
}

// За продукт без варианти — parent сам по себе си е "вариант" за печат
$print_items = []; // плосък списък за печат
$has_unknown_origin = false;

foreach ($groups as $pid => $g) {
    $parent = $g['parent'];
    $variants = $g['variants'];

    if (empty($parent)) {
        // Защитен fallback — поне един от rows-овете беше дете без зареден parent
        continue;
    }

    // Проверка origin
    $origin = trim($parent['country_origin'] ?? '');
    if ($origin === '' || strtoupper($origin) === 'BG' || mb_strtolower($origin) === 'българия') {
        // BG — origin не е задължителен на етикета
    } else {
        // Внос — но имаме origin, OK
    }

    if (empty($variants)) {
        // Продукт БЕЗ варианти → самият parent е "вариант" за печат
        $price = pricePair($parent['retail_price'], $tenant);
        $print_items[] = [
            'product_id' => (int)$parent['id'],
            'parent_id'  => (int)$parent['id'],
            'name'       => $parent['name'],
            'variant'    => '',
            'code'       => $parent['code'] ?? '',
            'barcode'    => $parent['barcode'] ?? '',
            'image_url'  => $parent['image_url'] ?? '',
            'origin'     => $origin,
            'price_eur'  => $price['eur'],
            'price_bgn'  => $price['bgn'],
            'show_bgn'   => $price['show_bgn'],
            'qty_default'=> (int)$parent['qty'],
            'has_variants' => false
        ];
    } else {
        // Продукт С варианти → всеки variant е отделен ред
        foreach ($variants as $v) {
            $price = pricePair($v['retail_price'] ?: $parent['retail_price'], $tenant);
            $variant_label = trim(($v['size'] ?? '') . ' · ' . ($v['color'] ?? ''), ' ·');
            if ($variant_label === '') $variant_label = $v['name'] ?? '';

            $print_items[] = [
                'product_id' => (int)$v['id'],
                'parent_id'  => (int)$parent['id'],
                'name'       => $parent['name'],
                'variant'    => $variant_label,
                'code'       => $v['code'] ?? '',
                'barcode'    => $v['barcode'] ?? '',
                'image_url'  => $parent['image_url'] ?? '',
                'origin'     => $origin,
                'price_eur'  => $price['eur'],
                'price_bgn'  => $price['bgn'],
                'show_bgn'   => $price['show_bgn'],
                'qty_default'=> (int)$v['qty'],
                'has_variants' => true,
                'size'       => $v['size'] ?? '',
                'color'      => $v['color'] ?? ''
            ];
        }
    }
}

if (empty($print_items)) {
    http_response_code(404);
    die('Няма варианти за печат.');
}

// За UI hint — кой продукт е "главен" (за заглавие)
$main = $print_items[0];
$total_groups = count($groups);

// JSON за JS
$items_json = jsonsafe($print_items);
$store_json = jsonsafe([
    'name' => $store_name,
    'country_code' => $country_code,
    'default_format' => $default_format,
]);

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>🖨 Печат етикети — <?= htmlsafe($main['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
    --hue1: 200; --hue2: 225;
    --border: 1px;
    --border-color: hsl(var(--hue2), 12%, 20%);
    --radius: 22px; --radius-sm: 14px; --radius-lg: 28px;
    --ease: cubic-bezier(0.5, 1, 0.89, 1);
    --bg-main: #08090d;
    --text-primary: #f1f5f9;
    --text-secondary: rgba(255, 255, 255, 0.6);
    --text-muted: rgba(255, 255, 255, 0.4);
}
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html, body {
    background: var(--bg-main);
    color: var(--text-primary);
    font-family: 'Montserrat', Inter, system-ui, sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
    -webkit-user-select: none;
    user-select: none;
}
input, button { font-family: inherit; }
input { -webkit-user-select: text; user-select: text; }
body {
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / 0.22) 0%, transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / 0.22) 0%, transparent 60%),
        linear-gradient(180deg, #0a0b14 0%, #050609 100%);
    background-attachment: fixed;
    padding-bottom: 40px;
    position: relative;
}
body::before {
    content: ''; position: fixed; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity: 0.03; pointer-events: none; z-index: 1; mix-blend-mode: overlay;
}
.app { position: relative; z-index: 2; max-width: 480px; margin: 0 auto; padding: 14px 12px 20px; }

/* ═══ NEON GLASS ═══ */
.glass {
    position: relative;
    border-radius: var(--radius);
    border: var(--border) solid var(--border-color);
    background:
        linear-gradient(235deg, hsl(var(--hue1) 50% 10% / 0.8), hsl(var(--hue1) 50% 10% / 0) 33%),
        linear-gradient(45deg, hsl(var(--hue2) 50% 10% / 0.8), hsl(var(--hue2) 50% 10% / 0) 33%),
        linear-gradient(hsl(220deg 25% 4.8% / 0.78));
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    box-shadow: hsl(var(--hue2) 50% 2%) 0 10px 16px -8px;
    isolation: isolate;
    padding: 16px 14px;
    margin-bottom: 12px;
}
.glass.sm { border-radius: var(--radius-sm); padding: 12px 12px; }

/* ═══ TOP BAR ═══ */
.top-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 4px 14px;
}
.back-btn {
    width: 38px; height: 38px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    cursor: pointer; color: var(--text-primary);
    display: flex; align-items: center; justify-content: center;
}
.back-btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.top-title { flex: 1; min-width: 0; }
.top-title h1 {
    font-size: 16px; font-weight: 800; letter-spacing: -0.01em;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.top-title small {
    font-size: 11px; color: var(--text-muted); font-weight: 500;
}
.printer-status {
    padding: 6px 10px; border-radius: 100px;
    font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    display: inline-flex; align-items: center; gap: 5px;
    cursor: pointer;
    white-space: nowrap;
}
.printer-status::before {
    content: ''; width: 5px; height: 5px; border-radius: 50%;
    background: #ef4444; box-shadow: 0 0 8px #ef4444;
}
.printer-status.ok { background: rgba(34, 197, 94, 0.12); border-color: rgba(34, 197, 94, 0.3); color: #86efac; }
.printer-status.ok::before { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
.printer-status.busy { background: rgba(251, 191, 36, 0.12); border-color: rgba(251, 191, 36, 0.3); color: #fcd34d; }
.printer-status.busy::before { background: #fbbf24; box-shadow: 0 0 8px #fbbf24; animation: pulse 1s infinite; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* ═══ FORMAT TABS ═══ */
.format-tabs {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 4px; padding: 4px; margin-bottom: 12px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 100px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.format-tab {
    padding: 10px 8px;
    border-radius: 100px;
    font-size: 11px; font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    border: none;
    background: transparent;
    transition: all 0.2s var(--ease);
    letter-spacing: 0.02em;
}
.format-tab.active {
    background: linear-gradient(135deg, hsl(var(--hue1) 60% 32%), hsl(var(--hue1) 65% 24%));
    color: white;
    box-shadow: 0 0 14px hsl(var(--hue1) 60% 45% / 0.45);
}

/* ═══ ORIGIN WARNING ═══ */
.origin-warn {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px;
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 14px;
    margin-bottom: 12px;
}
.origin-warn-icon {
    width: 22px; height: 22px; flex-shrink: 0;
    color: #fbbf24;
}
.origin-warn-icon svg { width: 100%; height: 100%; stroke: currentColor; fill: none; stroke-width: 2; }
.origin-warn-text { font-size: 12px; color: #fcd34d; line-height: 1.4; }
.origin-warn-text b { color: #fef3c7; }
.origin-warn-input {
    margin-top: 6px;
    width: 100%;
    padding: 8px 12px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(251, 191, 36, 0.3);
    border-radius: 8px;
    color: white; font-size: 13px;
    outline: none;
}

/* ═══ PREVIEW LABEL ═══ */
.preview-card { padding: 18px 14px; }
.preview-label {
    position: relative;
    margin: 0 auto;
    background: #ffffff;
    color: #0a0a0a;
    border-radius: 6px;
    padding: 14px 12px 10px;
    font-family: 'Montserrat', sans-serif;
    box-shadow:
        0 20px 50px rgba(0, 0, 0, 0.5),
        0 0 40px hsl(var(--hue1) 60% 50% / 0.2);
    width: 200px;
    transition: transform 0.3s var(--ease);
}
.preview-label.big { transform: scale(1.35); margin: 24px auto 36px; }
.preview-variant-tag {
    position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
    background: linear-gradient(135deg, hsl(var(--hue1) 60% 35%), hsl(var(--hue1) 60% 28%));
    color: white;
    padding: 3px 12px;
    border-radius: 100px;
    font-size: 9px; font-weight: 800; letter-spacing: 0.05em;
}
.preview-label-brand {
    font-size: 7px; font-weight: 700; letter-spacing: 0.08em;
    color: #4b5563; text-transform: uppercase;
    text-align: center; margin-bottom: 5px;
}
.preview-label-name {
    font-size: 11px; font-weight: 700;
    color: #111827;
    text-align: center; line-height: 1.25;
    margin-bottom: 8px;
    letter-spacing: -0.01em;
}
.preview-price-row {
    display: flex; align-items: baseline; justify-content: space-between;
    margin-bottom: 6px;
    padding: 0 4px;
}
.preview-price-row.eur-only { justify-content: center; }
.preview-price-row.hidden { display: none; }
.preview-price-eur { font-size: 18px; font-weight: 800; color: #0a0a0a; }
.preview-price-bgn { font-size: 11px; font-weight: 600; color: #6b7280; }
.preview-barcode {
    font-family: 'Courier New', monospace;
    font-size: 16px; letter-spacing: -1.5px;
    text-align: center;
    color: #0a0a0a;
    margin-bottom: 4px;
}
.preview-code {
    font-size: 8px; font-weight: 600;
    color: #6b7280; text-align: center;
    letter-spacing: 0.04em;
}
.zoom-row {
    display: flex; gap: 6px;
    background: rgba(0, 0, 0, 0.3);
    padding: 4px;
    border-radius: 100px;
    margin-top: 14px;
}
.zoom-btn {
    flex: 1;
    padding: 8px 10px;
    border-radius: 100px;
    font-size: 10px; font-weight: 700;
    color: var(--text-muted);
    cursor: pointer; border: none; background: transparent;
}
.zoom-btn.active {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-primary);
}

/* ═══ VARIANT LIST ═══ */
.list-card { padding: 12px 12px 8px; }
.section-title {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 10px;
    padding: 0 4px;
}
.section-title-text {
    font-size: 13px; font-weight: 700;
    color: var(--text-primary);
}
.section-title-count {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-secondary);
    padding: 2px 8px;
    border-radius: 100px;
    font-size: 11px; font-weight: 700;
}
.section-title-actions { margin-left: auto; display: flex; gap: 6px; }
.section-action {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 10px; font-weight: 700;
    cursor: pointer;
}
.section-action:active { transform: scale(0.96); }

.variant-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 6px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}
.variant-row:last-child { border-bottom: none; }
.variant-info {
    display: flex; align-items: center; gap: 8px;
    flex: 1; min-width: 0;
}
.variant-swatch {
    width: 18px; height: 18px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.15);
    flex-shrink: 0;
}
.variant-text { min-width: 0; flex: 1; }
.variant-label {
    font-size: 13px; font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.variant-code {
    font-size: 10px; color: var(--text-muted);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    margin-top: 2px;
}
.variant-qty {
    display: flex; align-items: center; gap: 6px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 100px;
    padding: 3px;
}
.qty-minus, .qty-plus {
    width: 28px; height: 28px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.06);
    color: var(--text-primary);
    font-size: 16px; font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.qty-minus:active, .qty-plus:active { transform: scale(0.92); }
.qty-num {
    min-width: 24px; text-align: center;
    font-size: 14px; font-weight: 800;
    color: var(--text-primary);
}
.qty-num.zero { color: var(--text-muted); }
.print-one {
    width: 32px; height: 32px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.print-one svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }
.print-one:active { transform: scale(0.9); }

/* ═══ TOTAL BAR ═══ */
.total-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(135deg, hsl(var(--hue1) 50% 14% / 0.6), hsl(var(--hue2) 50% 14% / 0.6));
    border: 1px solid hsl(var(--hue1) 60% 40% / 0.3);
    border-radius: 16px;
    margin-bottom: 12px;
}
.total-icon {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    color: hsl(var(--hue1) 70% 70%);
    display: flex; align-items: center; justify-content: center;
}
.total-icon svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }
.total-text { flex: 1; }
.total-label { font-size: 11px; color: var(--text-muted); font-weight: 600; }
.total-value { font-size: 18px; font-weight: 800; color: var(--text-primary); margin-top: 2px; }

/* ═══ ACTIONS ═══ */
.actions-row { display: flex; flex-direction: column; gap: 8px; }
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 20px;
    border: none; border-radius: 14px;
    font-weight: 700; font-size: 14px;
    cursor: pointer;
    transition: all 0.2s var(--ease);
    width: 100%;
    color: white;
    background: linear-gradient(135deg, hsl(var(--hue1) 60% 32%), hsl(var(--hue1) 65% 24%));
    box-shadow: 0 6px 18px hsl(var(--hue1) 70% 40% / 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.18);
}
.btn:active { transform: translateY(1px) scale(0.98); }
.btn:disabled { opacity: 0.4; cursor: not-allowed; }
.btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.btn-secondary {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: none;
    color: var(--text-primary);
}

@keyframes printing {
    0%, 100% { box-shadow: 0 8px 24px hsl(var(--hue1) 70% 40% / 0.4), 0 0 24px hsl(var(--hue1) 70% 50% / 0.3); }
    50% { box-shadow: 0 8px 30px hsl(var(--hue1) 70% 45% / 0.6), 0 0 40px hsl(var(--hue1) 70% 55% / 0.6); }
}
.btn.printing { animation: printing 1s ease-in-out infinite; }

/* ═══ TOAST ═══ */
.toast {
    position: fixed; bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(120px);
    background: rgba(0, 0, 0, 0.92);
    color: white; padding: 12px 22px;
    border-radius: 30px; font-size: 14px; font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.15);
    z-index: 1000;
    transition: transform 0.3s var(--ease);
    max-width: 360px; text-align: center;
}
.toast.show { transform: translateX(-50%) translateY(0); }
.toast.error { border-color: rgba(239, 68, 68, 0.5); }
.toast.success { border-color: rgba(34, 197, 94, 0.5); }

@media (max-width: 380px) {
    .preview-label { width: 180px; }
    .preview-label.big { transform: scale(1.25); }
    .preview-price-eur { font-size: 17px; }
}
</style>
</head>
<body>

<div class="app">

    <!-- TOP BAR -->
    <div class="top-bar">
        <button class="back-btn" onclick="history.back()" aria-label="Назад">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <div class="top-title">
            <h1>🖨 Печат етикети</h1>
            <small><?= htmlsafe($main['name']) ?> · <?= count($print_items) ?> <?= count($print_items)==1?'вариант':'варианта' ?></small>
        </div>
        <div class="printer-status" id="printerStatus" onclick="window.location.href='print-test.php'" title="Управление от Настройки">Принтер</div>
    </div>

    <!-- FORMAT TABS -->
    <div class="format-tabs" id="formatTabs">
        <button class="format-tab <?= $default_format==='both'?'active':'' ?>" data-format="both">€ + лв</button>
        <button class="format-tab <?= $default_format==='eur'?'active':'' ?>" data-format="eur">Само €</button>
        <button class="format-tab" data-format="no-price">Без цена</button>
    </div>

    <!-- ORIGIN WARNING (показва се динамично от JS ако трябва) -->
    <div class="origin-warn" id="originWarn" style="display:none">
        <div class="origin-warn-icon">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div style="flex:1">
            <div class="origin-warn-text">
                <b>Внос:</b> Държавата на произход липсва. По закон е задължителна на етикета.
            </div>
            <input type="text" class="origin-warn-input" id="originInput" placeholder="напр. Турция, Китай, Италия...">
        </div>
    </div>

    <!-- PREVIEW CARD -->
    <div class="glass preview-card">
        <div class="preview-label big" id="labelPreview">
            <div class="preview-variant-tag" id="pvVariant" style="display:none"></div>
            <div class="preview-label-brand" id="pvBrand"><?= htmlsafe(mb_strtoupper($store_name, 'UTF-8')) ?></div>
            <div class="preview-label-name" id="pvName">—</div>
            <div class="preview-price-row" id="priceRow">
                <span class="preview-price-eur" id="pvPriceEur">—</span>
                <span class="preview-price-bgn" id="pvPriceBgn">—</span>
            </div>
            <div class="preview-barcode" id="pvBarcode">|||||||||||||||||||||||</div>
            <div class="preview-code" id="pvCode">—</div>
        </div>
        <div class="zoom-row">
            <button class="zoom-btn active" data-zoom="big">x2 преглед</button>
            <button class="zoom-btn" data-zoom="real">Реален размер 1:1</button>
        </div>
    </div>

    <!-- VARIANT LIST -->
    <div class="glass sm list-card">
        <div class="section-title">
            <span class="section-title-text">Варианти</span>
            <span class="section-title-count" id="variantCount">0</span>
            <div class="section-title-actions">
                <button class="section-action" data-bulk="+">Всички +1</button>
                <button class="section-action" data-bulk="0">Нулирай</button>
            </div>
        </div>
        <div id="variantsList"></div>
    </div>

    <!-- TOTAL BAR -->
    <div class="total-bar">
        <div class="total-icon">
            <svg viewBox="0 0 24 24">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
        </div>
        <div class="total-text">
            <div class="total-label">Общо за печат</div>
            <div class="total-value"><span id="totalCount">0</span> етикета</div>
        </div>
    </div>

    <!-- ACTIONS -->
    <div class="actions-row">
        <button class="btn" id="printAllBtn" disabled>
            <svg viewBox="0 0 24 24">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Печатай всички (<span id="btnCount">0</span> етикета)
        </button>
    </div>

</div>

<div class="toast" id="toast"></div>

<script src="js/printer.js?v=<?= @filemtime(__DIR__ . '/js/printer.js') ?: time() ?>"></script>
<script>
// ═══ DATA FROM PHP ═══
const STORE = <?= $store_json ?>;
const ITEMS = <?= $items_json ?>; // [{product_id, name, variant, code, barcode, price_eur, price_bgn, show_bgn, qty_default, origin, ...}]
let format = STORE.default_format || 'both';
let qtyMap = {};   // product_id => copies
let selectedIdx = 0;
let originManual = ''; // ако Пешо попълни origin override

// Init qty
ITEMS.forEach(it => qtyMap[it.product_id] = Math.max(0, parseInt(it.qty_default, 10) || 0));

// ═══ DOM ═══
const $ = id => document.getElementById(id);
const variantsList = $('variantsList');

// ═══ COLOR SWATCH ═══
function colorSwatch(name) {
    if (!name) return '#374151';
    const map = {
        'черен':'#1a1a1a','black':'#1a1a1a','navy':'#1e40af','син':'#3b82f6','blue':'#3b82f6',
        'червен':'#dc2626','red':'#dc2626','зелен':'#16a34a','green':'#16a34a',
        'жълт':'#facc15','yellow':'#facc15','бял':'#f5f5f5','white':'#f5f5f5',
        'сив':'#6b7280','grey':'#6b7280','gray':'#6b7280','розов':'#ec4899','pink':'#ec4899',
        'кафяв':'#92400e','brown':'#92400e','лилав':'#9333ea','purple':'#9333ea',
        'оранж':'#f97316','orange':'#f97316','бежов':'#d6c4a8','beige':'#d6c4a8'
    };
    return map[String(name).toLowerCase()] || '#374151';
}

// ═══ RENDER VARIANTS ═══
function renderVariants() {
    variantsList.innerHTML = '';
    ITEMS.forEach((it, idx) => {
        const row = document.createElement('div');
        row.className = 'variant-row';
        row.dataset.idx = idx;
        const swatch = it.color ? colorSwatch(it.color) : '#374151';
        const label = it.variant || it.name;
        const code = it.code || it.barcode || '';
        const qty = qtyMap[it.product_id];
        row.innerHTML = `
            <div class="variant-info">
                <div class="variant-swatch" style="background:${swatch}"></div>
                <div class="variant-text">
                    <div class="variant-label">${escapeHtml(label)}</div>
                    <div class="variant-code">${escapeHtml(code)}</div>
                </div>
            </div>
            <div class="variant-qty">
                <button class="qty-minus" data-act="minus" data-idx="${idx}">−</button>
                <span class="qty-num ${qty === 0 ? 'zero' : ''}" data-num="${idx}">${qty}</span>
                <button class="qty-plus" data-act="plus" data-idx="${idx}">+</button>
            </div>
            <button class="print-one" data-act="one" data-idx="${idx}" aria-label="Печатай 1">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            </button>
        `;
        row.addEventListener('click', e => {
            if (e.target.closest('.qty-minus, .qty-plus, .print-one')) return;
            selectedIdx = idx;
            updatePreview();
        });
        variantsList.appendChild(row);
    });
    $('variantCount').textContent = ITEMS.length;
    updatePreview();
    updateTotals();
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c]));
}

// ═══ PREVIEW UPDATE ═══
function updatePreview() {
    const it = ITEMS[selectedIdx] || ITEMS[0];
    if (!it) return;
    $('pvName').textContent = it.name;
    $('pvCode').textContent = it.code || it.barcode || '';
    $('pvBarcode').textContent = '|||||||||||||||||||||||';
    $('pvPriceEur').textContent = it.price_eur || '';
    $('pvPriceBgn').textContent = it.price_bgn || '';

    if (it.variant && it.variant.trim()) {
        $('pvVariant').textContent = it.variant;
        $('pvVariant').style.display = '';
    } else {
        $('pvVariant').style.display = 'none';
    }

    const priceRow = $('priceRow');
    priceRow.className = 'preview-price-row';
    if (format === 'eur' || !it.show_bgn) priceRow.classList.add('eur-only');
    if (format === 'no-price') priceRow.classList.add('hidden');

    // Origin warn
    const needsOrigin = checkNeedsOrigin();
    $('originWarn').style.display = needsOrigin ? 'flex' : 'none';
}

function checkNeedsOrigin() {
    // Показва warning ако някой от items е внос (origin не е BG/празно/българия)
    // и ако origin липсва за някой item
    if (originManual) return false;
    return ITEMS.some(it => {
        const o = (it.origin || '').trim();
        return o === ''; // Ако е празно — нямаме информация и не е безопасно за внос
    }) && false; // Засега изключено — иначе ще warning-не за всеки BG продукт
    // TODO: правилна логика — ако имаме поле supplier_country или business_type='import'
}

// ═══ FORMAT TABS ═══
$('formatTabs').addEventListener('click', e => {
    if (!e.target.classList.contains('format-tab')) return;
    document.querySelectorAll('.format-tab').forEach(t => t.classList.remove('active'));
    e.target.classList.add('active');
    format = e.target.dataset.format;
    updatePreview();
    if (navigator.vibrate) navigator.vibrate(6);
});

// ═══ ZOOM BUTTONS ═══
document.querySelectorAll('.zoom-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.zoom-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const label = $('labelPreview');
        if (btn.dataset.zoom === 'big') label.classList.add('big');
        else label.classList.remove('big');
        if (navigator.vibrate) navigator.vibrate(5);
    });
});

// ═══ ORIGIN INPUT ═══
$('originInput').addEventListener('input', e => {
    originManual = e.target.value.trim();
});

// ═══ QTY ACTIONS (event delegation) ═══
variantsList.addEventListener('click', e => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const act = btn.dataset.act;
    const idx = parseInt(btn.dataset.idx, 10);
    const it = ITEMS[idx];
    if (!it) return;

    if (act === 'plus') {
        qtyMap[it.product_id] = Math.min(99, (qtyMap[it.product_id] || 0) + 1);
        if (navigator.vibrate) navigator.vibrate(4);
    } else if (act === 'minus') {
        qtyMap[it.product_id] = Math.max(0, (qtyMap[it.product_id] || 0) - 1);
        if (navigator.vibrate) navigator.vibrate(4);
    } else if (act === 'one') {
        printSingle(idx);
        return;
    }

    const numEl = variantsList.querySelector(`.qty-num[data-num="${idx}"]`);
    if (numEl) {
        numEl.textContent = qtyMap[it.product_id];
        numEl.classList.toggle('zero', qtyMap[it.product_id] === 0);
    }
    selectedIdx = idx;
    updatePreview();
    updateTotals();
});

// ═══ BULK ACTIONS ═══
document.querySelectorAll('.section-action').forEach(btn => {
    btn.addEventListener('click', () => {
        const mode = btn.dataset.bulk;
        ITEMS.forEach(it => {
            if (mode === '+') qtyMap[it.product_id] = Math.min(99, (qtyMap[it.product_id] || 0) + 1);
            else if (mode === '0') qtyMap[it.product_id] = 0;
        });
        renderVariants();
        if (navigator.vibrate) navigator.vibrate(10);
    });
});

// ═══ TOTALS ═══
function updateTotals() {
    let total = 0;
    ITEMS.forEach(it => total += (qtyMap[it.product_id] || 0));
    $('totalCount').textContent = total;
    $('btnCount').textContent = total;
    const btn = $('printAllBtn');
    btn.disabled = (total === 0 || !RmsPrinter.isPaired());
}

// ═══ PRINTER STATUS ═══
function updatePrinterStatus() {
    const pill = $('printerStatus');
    const status = RmsPrinter.status;
    const paired = RmsPrinter.isPaired();
    if (status === 'connecting') { pill.textContent = 'Свързване…'; pill.className = 'printer-status busy'; }
    else if (status === 'printing') { pill.textContent = 'Печата…'; pill.className = 'printer-status busy'; }
    else if (paired) {
        const info = RmsPrinter.getPairedInfo();
        pill.textContent = '✓ ' + (info?.name || 'Свързан');
        pill.className = 'printer-status ok';
    } else {
        pill.textContent = 'Не е сдвоен';
        pill.className = 'printer-status';
    }
    updateTotals();
}
RmsPrinter.onStatus(updatePrinterStatus);

// ═══ BUILD LABEL DATA FOR PRINTER ═══
function labelDataFromItem(it) {
    return {
        store: STORE.name,
        name: it.name + (it.variant ? ' · ' + it.variant : ''),
        variant: it.variant || '',
        barcode: it.barcode || '',
        priceEur: it.price_eur || '',
        priceBgn: (it.show_bgn && format === 'both') ? it.price_bgn : '',
        code: it.code || '',
        format: format
    };
}

// ═══ PRINT SINGLE (1 копие на ред) ═══
async function printSingle(idx) {
    const it = ITEMS[idx];
    if (!it) return;
    if (!RmsPrinter.isPaired()) {
        showToast('Сдвои принтера от Настройки', 'error');
        return;
    }
    try {
        await RmsPrinter.printLabel(labelDataFromItem(it), 1);
        showToast('✓ 1 етикет', 'success', 1500);
    } catch (e) {
        showToast('Грешка: ' + e.message, 'error');
    }
}

// ═══ PRINT ALL ═══
$('printAllBtn').addEventListener('click', async () => {
    if (!RmsPrinter.isPaired()) {
        showToast('Сдвои принтера от Настройки', 'error');
        return;
    }
    const batch = [];
    ITEMS.forEach(it => {
        const c = qtyMap[it.product_id] || 0;
        if (c > 0) batch.push({ data: labelDataFromItem(it), copies: c });
    });
    if (batch.length === 0) {
        showToast('Няма какво да се печата', 'error');
        return;
    }

    const total = batch.reduce((s, b) => s + b.copies, 0);
    const btn = $('printAllBtn');
    btn.classList.add('printing');
    btn.disabled = true;

    try {
        const results = await RmsPrinter.printBatch(batch);
        const failed = results.filter(r => !r.ok).length;
        if (failed === 0) {
            showToast(`✓ Изпратени ${total} етикета`, 'success');
        } else {
            showToast(`⚠ ${total - failed}/${total} ОК · ${failed} грешка`, 'error', 5000);
        }
    } catch (e) {
        showToast('Грешка: ' + e.message, 'error');
    } finally {
        btn.classList.remove('printing');
        updateTotals();
    }
});

// ═══ TOAST ═══
let toastTimer;
function showToast(msg, type = '', duration = 3000) {
    const toast = $('toast');
    clearTimeout(toastTimer);
    toast.textContent = msg;
    toast.className = 'toast show' + (type ? ' ' + type : '');
    toastTimer = setTimeout(() => toast.classList.remove('show'), duration);
}

// ═══ INIT ═══
renderVariants();
updatePrinterStatus();

if (!RmsPrinter.isSupported()) {
    showToast('Принтерът работи на Android Chrome. iOS — скоро.', 'error', 6000);
}
</script>
</body>
</html>
