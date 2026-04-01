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

// Fetch user + tenant info
$stmt = $pdo->prepare("
    SELECT u.name, u.role, u.store_id,
           t.supato_mode, t.currency, t.language
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1
");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: logout.php');
    exit;
}

$role        = $user['role'];
$supato_mode = (bool)$user['supato_mode'];
$currency    = htmlspecialchars($user['currency']);

$is_seller = ($role === 'seller');

// ── Live stats ────────────────────────────────────────────────

// 1. Total active products
$product_count = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND is_active = 1");
    $s->execute([$tenant_id]);
    $product_count = (int)$s->fetchColumn();
} catch (Exception $e) {}

// 2. Low stock count (quantity < min_quantity, min_quantity > 0)
$low_stock_count = 0;
try {
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM inventory i
        JOIN stores s ON s.id = i.store_id
        WHERE s.tenant_id = ?
          AND i.min_quantity > 0
          AND i.quantity < i.min_quantity
    ");
    $s->execute([$tenant_id]);
    $low_stock_count = (int)$s->fetchColumn();
} catch (Exception $e) {}

// 3. Pending deliveries (owner/manager only)
$pending_deliveries = 0;
if (!$is_seller) {
    try {
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM deliveries d
            JOIN stores s ON s.id = d.store_id
            WHERE s.tenant_id = ? AND d.status = 'pending'
        ");
        $s->execute([$tenant_id]);
        $pending_deliveries = (int)$s->fetchColumn();
    } catch (Exception $e) {}
}

// 4. Pending transfers
$pending_transfers = 0;
try {
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM transfers t
        JOIN stores s ON s.id = t.from_store_id
        WHERE s.tenant_id = ? AND t.status = 'pending'
    ");
    $s->execute([$tenant_id]);
    $pending_transfers = (int)$s->fetchColumn();
} catch (Exception $e) {}

// 5. Active suppliers (owner/manager only)
$supplier_count = 0;
if (!$is_seller) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE tenant_id = ? AND is_active = 1");
        $s->execute([$tenant_id]);
        $supplier_count = (int)$s->fetchColumn();
    } catch (Exception $e) {}
}

// 6. Active inventory sessions (owner/manager only)
$active_inventory = 0;
if (!$is_seller) {
    try {
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM inventories i
            JOIN stores s ON s.id = i.store_id
            WHERE s.tenant_id = ? AND i.status = 'in_progress'
        ");
        $s->execute([$tenant_id]);
        $active_inventory = (int)$s->fetchColumn();
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
        * { box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #030712;
            color: #e5e7eb;
            margin: 0;
            min-height: 100vh;
        }

        /* ── Page layout ── */
        .page-wrap {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 16px;
            padding-bottom: calc(72px + env(safe-area-inset-bottom));
        }

        /* ── Header ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 56px 0 8px;
        }
        .page-title {
            font-size: 22px;
            font-weight: 900;
            color: #f9fafb;
            letter-spacing: -0.02em;
        }

        /* ── Low stock banner ── */
        .low-stock-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(239,68,68,0.10);
            border: 1px solid rgba(239,68,68,0.28);
            border-radius: 12px;
            padding: 11px 14px;
            margin-bottom: 20px;
            text-decoration: none;
        }
        .low-stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            flex-shrink: 0;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.4; }
        }
        .low-stock-text {
            font-size: 13px;
            font-weight: 600;
            color: #fca5a5;
            flex: 1;
        }
        .low-stock-arrow {
            font-size: 16px;
            color: #f87171;
        }

        /* ── Grid ── */
        .wh-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* ── Card ── */
        .wh-card {
            display: flex;
            flex-direction: column;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 18px 16px 16px;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
            position: relative;
            min-height: 118px;
            -webkit-tap-highlight-color: transparent;
        }
        .wh-card:active {
            background: rgba(99,102,241,0.12);
            border-color: rgba(99,102,241,0.40);
        }

        /* Icon box */
        .wh-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            flex-shrink: 0;
        }
        .wh-icon svg {
            width: 20px;
            height: 20px;
        }

        /* Text */
        .wh-label {
            font-size: 14px;
            font-weight: 700;
            color: #f3f4f6;
            line-height: 1.2;
        }
        .wh-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
            line-height: 1.3;
        }
        .wh-sub.alert {
            color: #f87171;
            font-weight: 600;
        }

        /* Badge */
        .wh-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            min-width: 22px;
            height: 22px;
            border-radius: 11px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
        }
        .wh-badge.red   { background: #ef4444; color: #fff; }
        .wh-badge.amber { background: #f59e0b; color: #fff; }
        .wh-badge.indigo { background: #6366f1; color: #fff; }

        /* Icon color variants */
        .icon-indigo { background: rgba(99,102,241,0.15); }
        .icon-indigo svg { stroke: #818cf8; }
        .icon-green  { background: rgba(34,197,94,0.13); }
        .icon-green svg { stroke: #4ade80; }
        .icon-amber  { background: rgba(245,158,11,0.13); }
        .icon-amber svg { stroke: #fbbf24; }
        .icon-rose   { background: rgba(244,63,94,0.13); }
        .icon-rose svg { stroke: #fb7185; }
        .icon-teal   { background: rgba(20,184,166,0.13); }
        .icon-teal svg { stroke: #2dd4bf; }
        .icon-purple { background: rgba(168,85,247,0.13); }
        .icon-purple svg { stroke: #c084fc; }

        /* ── Bottom navigation ── */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 50;
            background: rgba(3,7,18,0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255,255,255,0.07);
            padding-bottom: env(safe-area-inset-bottom);
        }
        .bottom-nav-inner {
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 60px;
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
            -webkit-tap-highlight-color: transparent;
        }
        .nav-item svg { width: 22px; height: 22px; }
        .nav-item span {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .nav-item.inactive svg { stroke: #3f3f5a; }
        .nav-item.inactive span { color: #3f3f5a; }
        .nav-item.active svg { stroke: #6366f1; }
        .nav-item.active span { color: #6366f1; }
    </style>
</head>
<body>

<div class="page-wrap">

    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">Склад</h1>
    </div>

    <!-- Low stock banner (само ако има проблем) -->
    <?php if ($low_stock_count > 0): ?>
    <a href="products.php?filter=low_stock" class="low-stock-banner">
        <div class="low-stock-dot"></div>
        <span class="low-stock-text">
            <?= $low_stock_count ?> <?= $low_stock_count === 1 ? 'артикул' : 'артикула' ?> под минимална наличност
        </span>
        <span class="low-stock-arrow">›</span>
    </a>
    <?php endif; ?>

    <!-- Cards grid -->
    <div class="wh-grid">

        <!-- 1. Артикули (всички роли) -->
        <a href="products.php" class="wh-card">
            <?php if ($low_stock_count > 0): ?>
            <div class="wh-badge red"><?= $low_stock_count ?></div>
            <?php endif; ?>
            <div class="wh-icon icon-indigo">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                    <line x1="12" y1="12" x2="12" y2="16"/>
                    <line x1="10" y1="14" x2="14" y2="14"/>
                </svg>
            </div>
            <div class="wh-label">Артикули</div>
            <div class="wh-sub <?= $low_stock_count > 0 ? 'alert' : '' ?>">
                <?php if ($low_stock_count > 0): ?>
                    <?= $low_stock_count ?> под минимум
                <?php else: ?>
                    <?= $product_count ?> активни
                <?php endif; ?>
            </div>
        </a>

        <!-- 2. Трансфери (всички роли) -->
        <a href="transfers.php" class="wh-card">
            <?php if ($pending_transfers > 0): ?>
            <div class="wh-badge amber"><?= $pending_transfers ?></div>
            <?php endif; ?>
            <div class="wh-icon icon-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/>
                    <path d="m15 7 5 5-5 5"/>
                    <path d="m9 7-5 5 5 5"/>
                </svg>
            </div>
            <div class="wh-label">Трансфери</div>
            <div class="wh-sub">
                <?= $pending_transfers > 0 ? $pending_transfers . ' чакащи' : 'Прехвърляне' ?>
            </div>
        </a>

        <?php if (!$is_seller): ?>

        <!-- 3. Доставки (owner/manager) -->
        <a href="deliveries.php" class="wh-card">
            <?php if ($pending_deliveries > 0): ?>
            <div class="wh-badge indigo"><?= $pending_deliveries ?></div>
            <?php endif; ?>
            <div class="wh-icon icon-green">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13" rx="1"/>
                    <path d="M16 8h4l3 3v5h-7V8Z"/>
                    <circle cx="5.5" cy="18.5" r="2.5"/>
                    <circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <div class="wh-label">Доставки</div>
            <div class="wh-sub">
                <?= $pending_deliveries > 0 ? $pending_deliveries . ' чакащи' : 'Получени стоки' ?>
            </div>
        </a>

        <!-- 4. Доставчици (owner/manager) -->
        <a href="suppliers.php" class="wh-card">
            <div class="wh-icon icon-rose">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="wh-label">Доставчици</div>
            <div class="wh-sub">
                <?= $supplier_count ?> активни
            </div>
        </a>

        <!-- 5. Инвентаризация (owner/manager) -->
        <a href="inventory.php" class="wh-card">
            <?php if ($active_inventory > 0): ?>
            <div class="wh-badge amber"><?= $active_inventory ?></div>
            <?php endif; ?>
            <div class="wh-icon icon-teal">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <div class="wh-label">Инвентаризация</div>
            <div class="wh-sub">
                <?= $active_inventory > 0 ? $active_inventory . ' в ход' : 'Броене по артикул' ?>
            </div>
        </a>

        <!-- 6. Ревизия (owner/manager) -->
        <a href="revision.php" class="wh-card">
            <div class="wh-icon icon-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="wh-label">Ревизия</div>
            <div class="wh-sub">Парична проверка</div>
        </a>

        <?php endif; ?>

    </div>
</div>

<!-- Bottom navigation -->
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <!-- Чат -->
        <a href="chat.php" class="nav-item inactive">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span>Чат</span>
        </a>
        <!-- Склад (active) -->
        <a href="warehouse.php" class="nav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>Склад</span>
        </a>
        <!-- Статистики -->
        <a href="stats.php" class="nav-item inactive">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6"  y1="20" x2="6"  y2="14"/>
            </svg>
            <span>Статистики</span>
        </a>
        <!-- Въвеждане -->
        <a href="actions.php" class="nav-item inactive">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="16"/>
                <line x1="8"  y1="12" x2="16" y2="12"/>
            </svg>
            <span>Въвеждане</span>
        </a>
    </div>
</nav>

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/main.js"></script>
</body>
</html>
