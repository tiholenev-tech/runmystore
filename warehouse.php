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
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
    <title>Склад — RunMyStore.ai</title>
    <link href="./css/vendors/aos.css" rel="stylesheet" />
    <link href="./style.css" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #030712;
            color: #e5e7eb;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .orb-1 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, rgba(99,102,241,0.16) 0%, transparent 70%);
            top: -150px; left: -120px;
        }
        .orb-2 {
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(16,185,129,0.10) 0%, transparent 70%);
            bottom: 60px; right: -100px;
        }
        .orb-3 {
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(251,191,36,0.07) 0%, transparent 70%);
            top: 50%; left: 40%;
        }

        /* Page */
        .page-wrap {
            position: relative;
            z-index: 1;
            max-width: 480px;
            margin: 0 auto;
            padding: 0 16px;
            padding-bottom: calc(80px + env(safe-area-inset-bottom));
        }

        /* Header */
        .page-header { padding: 58px 0 22px; }
        .page-title {
            font-size: 34px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: -0.03em;
        }

        /* Alert banner */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(239,68,68,0.10);
            border: 1px solid rgba(239,68,68,0.35);
            border-radius: 14px;
            padding: 13px 16px;
            margin-bottom: 22px;
            text-decoration: none;
            box-shadow: 0 0 30px rgba(239,68,68,0.12);
        }
        .alert-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #ef4444;
            flex-shrink: 0;
            box-shadow: 0 0 10px #ef4444;
            animation: blink 2s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.35; }
        }
        .alert-text {
            font-size: 13px;
            font-weight: 700;
            color: #fca5a5;
            flex: 1;
        }
        .alert-arr { color: #f87171; font-size: 18px; line-height: 1; }

        /* Grid */
        .wh-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* Card */
        .wh-card {
            position: relative;
            display: flex;
            flex-direction: column;
            border-radius: 22px;
            padding: 20px 18px 18px;
            text-decoration: none;
            min-height: 152px;
            overflow: hidden;
            -webkit-tap-highlight-color: transparent;
            transition: transform 0.13s ease;
            background: rgba(255,255,255,0.045);
            border: 1px solid rgba(255,255,255,0.09);
        }
        /* Glass sheen */
        .wh-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, transparent 100%);
            border-radius: 22px 22px 0 0;
            pointer-events: none;
        }
        .wh-card:active { transform: scale(0.96); }

        /* Per-card glow */
        .g-indigo { box-shadow: 0 8px 32px rgba(99,102,241,0.22),  0 0 0 1px rgba(99,102,241,0.18) inset; }
        .g-purple  { box-shadow: 0 8px 32px rgba(139,92,246,0.22),  0 0 0 1px rgba(139,92,246,0.18) inset; }
        .g-green   { box-shadow: 0 8px 32px rgba(16,185,129,0.20),  0 0 0 1px rgba(16,185,129,0.18) inset; }
        .g-orange  { box-shadow: 0 8px 32px rgba(249,115,22,0.20),  0 0 0 1px rgba(249,115,22,0.18) inset; }
        .g-teal    { box-shadow: 0 8px 32px rgba(6,182,212,0.20),   0 0 0 1px rgba(6,182,212,0.18) inset; }
        .g-gold    { box-shadow: 0 8px 32px rgba(251,191,36,0.18),  0 0 0 1px rgba(251,191,36,0.15) inset; }

        /* Icon */
        .wh-icon {
            width: 50px; height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .wh-icon svg { width: 24px; height: 24px; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }

        .i-indigo { background: rgba(99,102,241,0.22);  box-shadow: 0 0 20px rgba(99,102,241,0.50); }
        .i-indigo svg { stroke: #a5b4fc; }
        .i-purple { background: rgba(139,92,246,0.22);  box-shadow: 0 0 20px rgba(139,92,246,0.50); }
        .i-purple svg { stroke: #c4b5fd; }
        .i-green  { background: rgba(16,185,129,0.20);  box-shadow: 0 0 20px rgba(16,185,129,0.50); }
        .i-green svg  { stroke: #6ee7b7; }
        .i-orange { background: rgba(249,115,22,0.20);  box-shadow: 0 0 20px rgba(249,115,22,0.50); }
        .i-orange svg { stroke: #fdba74; }
        .i-teal   { background: rgba(6,182,212,0.20);   box-shadow: 0 0 20px rgba(6,182,212,0.50); }
        .i-teal svg   { stroke: #67e8f9; }
        .i-gold   { background: rgba(251,191,36,0.18);  box-shadow: 0 0 20px rgba(251,191,36,0.45); }
        .i-gold svg   { stroke: #fde68a; }

        /* Labels */
        .wh-label {
            font-size: 15px;
            font-weight: 800;
            color: #f9fafb;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }
        .wh-sub {
            font-size: 11px;
            font-weight: 500;
            color: rgba(156,163,175,0.80);
            margin-top: 5px;
        }
        .wh-sub.danger { color: #f87171; font-weight: 700; }

        /* Badge */
        .wh-badge {
            position: absolute;
            top: 14px; right: 14px;
            min-width: 24px; height: 24px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 7px;
        }
        .b-red    { background: #ef4444; color: #fff; box-shadow: 0 0 12px rgba(239,68,68,0.70); }
        .b-amber  { background: #f59e0b; color: #fff; box-shadow: 0 0 12px rgba(245,158,11,0.70); }
        .b-indigo { background: #6366f1; color: #fff; box-shadow: 0 0 12px rgba(99,102,241,0.70); }

        /* Bottom nav */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0; z-index: 50;
            background: rgba(3,7,18,0.82);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-top: 1px solid rgba(255,255,255,0.07);
            padding-bottom: env(safe-area-inset-bottom);
        }
        .bottom-nav-inner {
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 64px;
            max-width: 480px;
            margin: 0 auto;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex: 1;
            text-decoration: none;
            padding: 8px 0;
            position: relative;
            -webkit-tap-highlight-color: transparent;
        }
        .nav-item svg { width: 22px; height: 22px; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
        .nav-item span { font-size: 10px; font-weight: 700; letter-spacing: 0.02em; }
        .nav-item.inactive svg  { stroke: #374151; }
        .nav-item.inactive span { color: #374151; }
        .nav-item.active svg    { stroke: #6366f1; filter: drop-shadow(0 0 6px rgba(99,102,241,0.9)); }
        .nav-item.active span   { color: #6366f1; }
        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: 2px;
            width: 20px; height: 3px;
            border-radius: 2px;
            background: #6366f1;
            box-shadow: 0 0 10px rgba(99,102,241,0.9);
        }
    </style>
</head>
<body>

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

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

        <!-- Артикули — всички роли -->
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

        <!-- Трансфери — всички роли -->
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

        <!-- Доставки -->
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

        <!-- Доставчици -->
        <a href="suppliers.php" class="wh-card g-orange">
            <div class="wh-icon i-orange">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="wh-label">Доставчици</div>
            <div class="wh-sub"><?= $supplier_count ?> активни</div>
        </a>

        <!-- Инвентаризация -->
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

        <!-- Ревизия -->
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

<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <a href="chat.php" class="nav-item inactive">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Чат</span>
        </a>
        <a href="warehouse.php" class="nav-item active">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Склад</span>
        </a>
        <a href="stats.php" class="nav-item inactive">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>Статистики</span>
        </a>
        <a href="actions.php" class="nav-item inactive">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            <span>Въвеждане</span>
        </a>
    </div>
</nav>

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/main.js"></script>
</body>
</html>
