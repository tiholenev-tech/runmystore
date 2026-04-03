<?php
session_start();
require_once 'config/database.php';
$pdo = DB::get();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$stmt = $pdo->prepare("
    SELECT u.name, u.role, u.store_id, t.supato_mode, t.currency
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1
");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: logout.php'); exit; }

$role      = $user['role'];
$currency  = htmlspecialchars($user['currency']);
$is_seller = ($role === 'seller');

// ── Live stats ─────────────────────────────────────────────
$product_count = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND is_active = 1");
    $s->execute([$tenant_id]); $product_count = (int)$s->fetchColumn();
} catch (Exception $e) {}

$low_stock_count = 0;
try {
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM inventory i
        JOIN stores s ON s.id = i.store_id
        WHERE s.tenant_id = ? AND i.min_quantity > 0 AND i.quantity < i.min_quantity
    ");
    $s->execute([$tenant_id]); $low_stock_count = (int)$s->fetchColumn();
} catch (Exception $e) {}

$pending_deliveries = 0;
if (!$is_seller) {
    try {
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM deliveries d
            JOIN stores s ON s.id = d.store_id
            WHERE s.tenant_id = ? AND d.status = 'pending'
        ");
        $s->execute([$tenant_id]); $pending_deliveries = (int)$s->fetchColumn();
    } catch (Exception $e) {}
}

$pending_transfers = 0;
try {
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM transfers t
        JOIN stores s ON s.id = t.from_store_id
        WHERE s.tenant_id = ? AND t.status = 'pending'
    ");
    $s->execute([$tenant_id]); $pending_transfers = (int)$s->fetchColumn();
} catch (Exception $e) {}

$supplier_count = 0;
if (!$is_seller) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE tenant_id = ? AND is_active = 1");
        $s->execute([$tenant_id]); $supplier_count = (int)$s->fetchColumn();
    } catch (Exception $e) {}
}

$active_inventory = 0;
if (!$is_seller) {
    try {
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM inventories i
            JOIN stores s ON s.id = i.store_id
            WHERE s.tenant_id = ? AND i.status = 'in_progress'
        ");
        $s->execute([$tenant_id]); $active_inventory = (int)$s->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!doctype html>
<html lang="bg">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover" />
    <title>Склад — RunMyStore.ai</title>
    <link href="./css/vendors/aos.css" rel="stylesheet" />
    <link href="./style.css" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: Inter, system-ui, sans-serif;
            background: #0b0f1a;
            color: #e5e7eb;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* ── SVG Backgrounds (Cruip Dark) ── */
        .bg-illus { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .bg-illus img { position: absolute; max-width: none; }
        .bg-illus .ill1 { left: 50%; top: 0; transform: translateX(-25%); width: 846px; height: 594px; opacity: 0.15; }
        .bg-illus .ill2 { left: 50%; top: 400px; transform: translateX(-100%); width: 760px; height: 668px; opacity: .5; }
        .bg-illus .ill3 { left: 50%; top: 440px; transform: translateX(-33%); width: 760px; height: 668px; }

        /* Blur orbs */
        .bg-blur { position: fixed; border-radius: 50%; filter: blur(100px); opacity: 0.06; pointer-events: none; z-index: 0; }
        .bg-blur-1 { width: 400px; height: 400px; top: -10%; left: -10%; background: #6366f1; }
        .bg-blur-2 { width: 300px; height: 300px; bottom: 15%; right: -10%; background: #4f46e5; }

        /* Page */
        .page-wrap {
            position: relative;
            z-index: 1;
            max-width: 480px;
            margin: 0 auto;
            padding: 0 16px;
            padding-bottom: calc(72px + env(safe-area-inset-bottom));
        }

        /* Header */
        .page-header {
            padding: 20px 0 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 800;
            font-family: Nacelle, Inter, sans-serif;
            background: linear-gradient(to right, #e5e7eb, #c7d2fe, #f9fafb, #a5b4fc, #e5e7eb);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gShift 6s linear infinite;
        }
        @keyframes gShift { 0% { background-position: 0% center } 100% { background-position: 200% center } }

        /* Alert banner */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(239,68,68,0.07);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            text-decoration: none;
        }
        .alert-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #ef4444;
            flex-shrink: 0;
            box-shadow: 0 0 8px #ef4444;
            animation: blink 2s infinite;
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .alert-text { font-size: 0.8rem; font-weight: 700; color: #fca5a5; flex: 1; }
        .alert-arr { color: #f87171; font-size: 18px; }

        /* Grid */
        .wh-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Card */
        .wh-card {
            position: relative;
            display: flex;
            flex-direction: column;
            border-radius: 14px;
            padding: 16px 14px 14px;
            text-decoration: none;
            min-height: 140px;
            overflow: hidden;
            transition: transform 0.15s ease;
            background: rgba(17,24,44,0.85);
            border: 1px solid rgba(99,102,241,0.15);
        }
        .wh-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            border: 1px solid transparent;
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(79,70,229,0.05)) border-box;
            mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
            pointer-events: none;
        }
        .wh-card:active { transform: scale(0.97); }

        /* Per-card glow */
        .g-indigo { box-shadow: 0 4px 24px rgba(99,102,241,0.15); }
        .g-purple { box-shadow: 0 4px 24px rgba(139,92,246,0.15); }
        .g-green  { box-shadow: 0 4px 24px rgba(16,185,129,0.15); }
        .g-orange { box-shadow: 0 4px 24px rgba(249,115,22,0.15); }
        .g-teal   { box-shadow: 0 4px 24px rgba(6,182,212,0.15); }
        .g-gold   { box-shadow: 0 4px 24px rgba(251,191,36,0.12); }

        /* Icon */
        .wh-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .wh-icon svg { width: 22px; height: 22px; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }

        .i-indigo { background: rgba(99,102,241,0.15); }
        .i-indigo svg { stroke: #a5b4fc; }
        .i-purple { background: rgba(139,92,246,0.15); }
        .i-purple svg { stroke: #c4b5fd; }
        .i-green  { background: rgba(16,185,129,0.15); }
        .i-green svg  { stroke: #6ee7b7; }
        .i-orange { background: rgba(249,115,22,0.15); }
        .i-orange svg { stroke: #fdba74; }
        .i-teal   { background: rgba(6,182,212,0.15); }
        .i-teal svg   { stroke: #67e8f9; }
        .i-gold   { background: rgba(251,191,36,0.12); }
        .i-gold svg   { stroke: #fde68a; }

        /* Labels */
        .wh-label { font-size: 0.9rem; font-weight: 700; color: #f9fafb; line-height: 1.2; }
        .wh-sub { font-size: 0.7rem; font-weight: 500; color: rgba(165,180,252,0.65); margin-top: 4px; }
        .wh-sub.danger { color: #f87171; font-weight: 700; }

        /* Badge */
        .wh-badge {
            position: absolute;
            top: 12px; right: 12px;
            min-width: 22px; height: 22px;
            border-radius: 11px;
            font-size: 0.65rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            color: #fff;
        }
        .b-red    { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }
        .b-amber  { background: #f59e0b; box-shadow: 0 0 8px rgba(245,158,11,0.5); }
        .b-indigo { background: #6366f1; box-shadow: 0 0 8px rgba(99,102,241,0.5); }

        /* ── BOTTOM NAV (унифициран С17) ── */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; background: rgba(11,15,26,0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-top: 1px solid rgba(99,102,241,0.15); display: flex; height: 56px; }
        .bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 0.6rem; color: rgba(165,180,252,0.65); text-decoration: none; transition: color 0.2s; }
        .bnav-tab.active { color: #818cf8; }
        .bnav-tab .bnav-icon { font-size: 1.2rem; }
    </style>
</head>
<body>

<!-- SVG Backgrounds -->
<div class="bg-illus" aria-hidden="true">
    <img class="ill1" src="./images/page-illustration.svg" alt="">
    <img class="ill2" src="./images/blurred-shape-gray.svg" alt="">
    <img class="ill3" src="./images/blurred-shape.svg" alt="">
</div>
<div class="bg-blur bg-blur-1"></div>
<div class="bg-blur bg-blur-2"></div>

<div class="page-wrap">

    <div class="page-header">
        <h1 class="page-title">Склад</h1>
    </div>

    <?php if ($low_stock_count > 0): ?>
    <a href="products.php?filter=low_stock" class="alert-banner">
        <div class="alert-dot"></div>
        <span class="alert-text">
            <?= $low_stock_count ?> <?= $low_stock_count === 1 ? 'артикул' : 'артикула' ?> под минимална наличност
        </span>
        <span class="alert-arr">›</span>
    </a>
    <?php endif; ?>

    <div class="wh-grid">

        <a href="products.php" class="wh-card g-indigo">
            <?php if ($low_stock_count > 0): ?>
            <div class="wh-badge b-red"><?= $low_stock_count ?></div>
            <?php endif; ?>
            <div class="wh-icon i-indigo">
                <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
            <div class="wh-label">Артикули</div>
            <div class="wh-sub <?= $low_stock_count > 0 ? 'danger' : '' ?>">
                <?= $low_stock_count > 0 ? $low_stock_count . ' под минимум' : $product_count . ' активни' ?>
            </div>
        </a>

        <a href="transfers.php" class="wh-card g-purple">
            <?php if ($pending_transfers > 0): ?>
            <div class="wh-badge b-amber"><?= $pending_transfers ?></div>
            <?php endif; ?>
            <div class="wh-icon i-purple">
                <svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m15 7 5 5-5 5"/><path d="m9 7-5 5 5 5"/></svg>
            </div>
            <div class="wh-label">Трансфери</div>
            <div class="wh-sub"><?= $pending_transfers > 0 ? $pending_transfers . ' чакащи' : 'Между обекти' ?></div>
        </a>

        <?php if (!$is_seller): ?>

        <a href="deliveries.php" class="wh-card g-green">
            <?php if ($pending_deliveries > 0): ?>
            <div class="wh-badge b-indigo"><?= $pending_deliveries ?></div>
            <?php endif; ?>
            <div class="wh-icon i-green">
                <svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div class="wh-label">Доставки</div>
            <div class="wh-sub"><?= $pending_deliveries > 0 ? $pending_deliveries . ' чакащи' : 'Получени стоки' ?></div>
        </a>

        <a href="suppliers.php" class="wh-card g-orange">
            <div class="wh-icon i-orange">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="wh-label">Доставчици</div>
            <div class="wh-sub"><?= $supplier_count ?> активни</div>
        </a>

        <a href="inventory.php" class="wh-card g-teal">
            <?php if ($active_inventory > 0): ?>
            <div class="wh-badge b-amber"><?= $active_inventory ?></div>
            <?php endif; ?>
            <div class="wh-icon i-teal">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div class="wh-label">Инвентаризация</div>
            <div class="wh-sub"><?= $active_inventory > 0 ? $active_inventory . ' в ход' : 'Броене по артикул' ?></div>
        </a>

        <a href="revision.php" class="wh-card g-gold">
            <div class="wh-icon i-gold">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="wh-label">Ревизия</div>
            <div class="wh-sub">Парична проверка</div>
        </a>

        <?php endif; ?>

    </div>
</div>

<!-- BOTTOM NAV (унифициран С17) -->
<nav class="bottom-nav">
    <a href="chat.php" class="bnav-tab"><span class="bnav-icon">✦</span>AI</a>
    <a href="warehouse.php" class="bnav-tab active"><span class="bnav-icon">📦</span>Склад</a>
    <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
    <a href="actions.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
</nav>

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/main.js"></script>
</body>
</html>
