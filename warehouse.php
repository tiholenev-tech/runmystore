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

$product_count = 0;
try { $s = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND is_active = 1"); $s->execute([$tenant_id]); $product_count = (int)$s->fetchColumn(); } catch (Exception $e) {}

$low_stock_count = 0;
try { $s = $pdo->prepare("SELECT COUNT(*) FROM inventory i JOIN stores s ON s.id = i.store_id WHERE s.tenant_id = ? AND i.min_quantity > 0 AND i.quantity < i.min_quantity"); $s->execute([$tenant_id]); $low_stock_count = (int)$s->fetchColumn(); } catch (Exception $e) {}

$pending_deliveries = 0;
if (!$is_seller) { try { $s = $pdo->prepare("SELECT COUNT(*) FROM deliveries d JOIN stores s ON s.id = d.store_id WHERE s.tenant_id = ? AND d.status = 'pending'"); $s->execute([$tenant_id]); $pending_deliveries = (int)$s->fetchColumn(); } catch (Exception $e) {} }

$pending_transfers = 0;
try { $s = $pdo->prepare("SELECT COUNT(*) FROM transfers t JOIN stores s ON s.id = t.from_store_id WHERE s.tenant_id = ? AND t.status = 'pending'"); $s->execute([$tenant_id]); $pending_transfers = (int)$s->fetchColumn(); } catch (Exception $e) {}

$supplier_count = 0;
if (!$is_seller) { try { $s = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE tenant_id = ? AND is_active = 1"); $s->execute([$tenant_id]); $supplier_count = (int)$s->fetchColumn(); } catch (Exception $e) {} }

$active_inventory = 0;
if (!$is_seller) { try { $s = $pdo->prepare("SELECT COUNT(*) FROM inventories i JOIN stores s ON s.id = i.store_id WHERE s.tenant_id = ? AND i.status = 'in_progress'"); $s->execute([$tenant_id]); $active_inventory = (int)$s->fetchColumn(); } catch (Exception $e) {} }
?>
<!doctype html>
<html lang="bg">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover" />
    <title>Склад — RunMyStore.ai</title>
    <link href="./css/vendors/aos.css" rel="stylesheet" />
    <style>
        /* ═══════════════════════════════════════════════════════════
           UNIFIED DESIGN SYSTEM 2026 — Based on stats.php
           ═══════════════════════════════════════════════════════════ */
        :root {
            --bg-main: #030712;
            --bg-card: rgba(15, 15, 40, 0.75);
            --bg-card-hover: rgba(23, 28, 58, 0.9);
            --border-subtle: rgba(99, 102, 241, 0.15);
            --border-glow: rgba(99, 102, 241, 0.4);
            --indigo-500: #6366f1;
            --indigo-400: #818cf8;
            --indigo-300: #a5b4fc;
            --text-primary: #f1f5f9;
            --text-secondary: #6b7280;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #22c55e;
            --bottom-nav-h: 64px;
            --nav-h: 64px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        
        body { 
            background: var(--bg-main); 
            color: var(--text-primary); 
            font-family: 'Montserrat', Inter, system-ui, sans-serif;
            min-height: 100vh; 
            overflow-x: hidden; 
            padding-bottom: var(--bottom-nav-h);
        }

        /* Animated Background — unified across all modules */
        body::before {
            content: '';
            position: fixed;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 700px;
            height: 400px;
            background: radial-gradient(ellipse, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .page-wrap { position: relative; z-index: 1; max-width: 480px; margin: 0 auto; padding: 0 12px; }

        /* ═══ Header — stats.php style ═══ */
        .page-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(3, 7, 18, 0.93);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 12px 16px 0;
            margin: 0 -12px;
        }

        .page-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 10px;
            background: linear-gradient(135deg, #f1f5f9, #a5b4fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ═══ Indigo Separator ═══ */
        .indigo-sep { 
            height: 1px; 
            background: linear-gradient(to right, transparent, rgba(99, 102, 241, 0.25), transparent); 
            margin: 16px 0; 
        }

        /* ═══ Alert Banner — with pulse animation ═══ */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 20px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            animation: fadeUp 0.35s ease both;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .alert-banner:active { transform: scale(0.98); }
        
        .alert-dot { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            background: #ef4444; 
            flex-shrink: 0; 
            box-shadow: 0 0 10px #ef4444; 
            animation: pulseRed 2s infinite; 
        }
        
        @keyframes pulseRed {
            0%, 100% { box-shadow: 0 0 6px rgba(239, 68, 68, 0.8); }
            50% { box-shadow: 0 0 16px rgba(239, 68, 68, 1), 0 0 24px rgba(239, 68, 68, 0.4); }
        }
        
        .alert-text { font-size: 13px; font-weight: 700; color: #fca5a5; flex: 1; }
        .alert-arr { color: #f87171; font-size: 20px; font-weight: bold; }

        /* ═══ Grid & Cards — stats.php glassmorphism ═══ */
        .wh-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 10px; 
            margin-bottom: 10px;
        }

        .wh-card {
            position: relative;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 14px;
            text-decoration: none;
            min-height: 140px;
            overflow: hidden;
            cursor: pointer;
            animation: cardIn 0.4s ease both;
            transition: all 0.25s;
            backdrop-filter: blur(12px);
        }

        .wh-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.06), transparent);
            pointer-events: none;
        }

        .wh-card:active { transform: scale(0.97); }
        .wh-card:hover { border-color: var(--border-glow); box-shadow: 0 0 28px rgba(99, 102, 241, 0.18); }

        /* Glow variants */
        .wh-card.glow-red { border-color: rgba(239, 68, 68, 0.25); }
        .wh-card.glow-red:hover { box-shadow: 0 0 28px rgba(239, 68, 68, 0.2); }
        
        .wh-card.glow-yellow { border-color: rgba(245, 158, 11, 0.25); }
        .wh-card.glow-yellow:hover { box-shadow: 0 0 28px rgba(245, 158, 11, 0.2); }

        /* Icon styling — 2026 glowing effect */
        .wh-icon { 
            width: 44px; 
            height: 44px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 12px;
            position: relative;
            background: rgba(99, 102, 241, 0.12);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.25);
        }
        
        .wh-icon svg { width: 22px; height: 22px; fill: none; stroke: var(--indigo-300); stroke-width: 2; }

        .wh-label { font-size: 14px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.02em; }
        .wh-sub { font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-top: 4px; }
        .wh-sub.danger { color: #f87171; font-weight: 700; }

        /* Badge */
        .wh-badge { 
            position: absolute; 
            top: 12px; 
            right: 12px; 
            min-width: 22px; 
            height: 22px; 
            border-radius: 11px; 
            font-size: 10px; 
            font-weight: 800; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0 6px; 
            color: #fff; 
            z-index: 2;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .b-red { background: #ef4444; box-shadow: 0 0 12px rgba(239, 68, 68, 0.5); animation: pulseRed 2s infinite; }
        .b-amber { background: #f59e0b; box-shadow: 0 0 12px rgba(245, 158, 11, 0.5); }
        .b-indigo { background: #6366f1; box-shadow: 0 0 12px rgba(99, 102, 241, 0.5); }

        /* Stats Row — bottom summary */
        .stats-row { 
            display: flex; 
            justify-content: space-around; 
            padding: 16px 0; 
            background: var(--bg-card); 
            border-radius: 16px; 
            border: 1px solid var(--border-subtle);
            margin: 10px 0;
            animation: cardIn 0.4s ease both;
            animation-delay: 0.2s;
        }
        
        .stat-item { text-align: center; }
        .stat-num { 
            font-size: 20px; 
            font-weight: 900; 
            color: var(--indigo-300);
            text-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
        }
        .stat-lbl { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* ═══ BOTTOM NAV — Unified across all modules ═══ */
        .bottom-nav { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            z-index: 100; 
            height: var(--bottom-nav-h);
            background: rgba(3, 7, 18, 0.95); 
            backdrop-filter: blur(16px); 
            border-top: 1px solid var(--border-subtle); 
            display: flex; 
            align-items: center;
            box-shadow: 0 -5px 25px rgba(99, 102, 241, 0.1);
        }
        
        .bnav-tab { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            gap: 3px; 
            font-size: 10px; 
            font-weight: 600; 
            color: rgba(165, 180, 252, 0.5); 
            text-decoration: none; 
            transition: all 0.3s;
            height: 100%;
        }
        
        .bnav-tab.active { 
            color: var(--indigo-400); 
            text-shadow: 0 0 12px rgba(129, 140, 248, 0.9); 
        }
        
        .bnav-tab .bnav-icon { 
            font-size: 20px; 
            transition: all 0.3s; 
            filter: drop-shadow(0 0 4px rgba(99,102,241,0.3));
        }
        
        .bnav-tab.active .bnav-icon { 
            transform: translateY(-2px); 
            filter: drop-shadow(0 0 12px rgba(129, 140, 248, 0.8)); 
        }

        /* ═══ Animations from stats.php ═══ */
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }

        /* Shimmer text effect for important numbers */
        .shimmer-text {
            background: linear-gradient(90deg, var(--indigo-400) 25%, #c7d2fe 50%, var(--indigo-400) 75%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s linear infinite;
        }

        /* Card animation delays */
        .wh-card:nth-child(1) { animation-delay: 0.05s; }
        .wh-card:nth-child(2) { animation-delay: 0.1s; }
        .wh-card:nth-child(3) { animation-delay: 0.15s; }
        .wh-card:nth-child(4) { animation-delay: 0.2s; }
        .wh-card:nth-child(5) { animation-delay: 0.25s; }
        .wh-card:nth-child(6) { animation-delay: 0.3s; }

        /* Drawer system — unified */
        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 200;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s;
            backdrop-filter: blur(4px);
        }
        
        .drawer-overlay.open { opacity: 1; pointer-events: all; }
        
        .drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 201;
            background: #080818;
            border-top: 1px solid var(--border-glow);
            border-radius: 22px 22px 0 0;
            padding: 0 0 40px;
            transform: translateY(100%);
            transition: transform 0.32s cubic-bezier(0.32, 0, 0.67, 0);
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 -20px 60px rgba(99, 102, 241, 0.2);
        }
        
        .drawer.open { transform: translateY(0); }
        
        .drawer-handle {
            width: 36px;
            height: 4px;
            background: rgba(99, 102, 241, 0.3);
            border-radius: 2px;
            margin: 14px auto 18px;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.3);
            border-radius: 3px;
        }
    </style>
</head>
<body>

<div class="page-wrap">

    <div class="page-header">
        <h1 class="page-title">Склад</h1>
    </div>

    <div class="indigo-sep"></div>

    <?php if ($low_stock_count > 0): ?>
    <a href="products.php?filter=low_stock" class="alert-banner">
        <div class="alert-dot"></div>
        <span class="alert-text"><?= $low_stock_count ?> <?= $low_stock_count === 1 ? 'артикул' : 'артикула' ?> под минимална наличност</span>
        <span class="alert-arr">›</span>
    </a>
    <?php endif; ?>

    <div class="wh-grid">
        <a href="products.php" class="wh-card" style="--delay:0.05s">
            <?php if ($low_stock_count > 0): ?><div class="wh-badge b-red"><?= $low_stock_count ?></div><?php endif; ?>
            <div class="wh-icon">
                <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
            <div class="wh-label">Артикули</div>
            <div class="wh-sub <?= $low_stock_count > 0 ? 'danger' : '' ?>"><?= $low_stock_count > 0 ? $low_stock_count . ' под минимум' : $product_count . ' активни' ?></div>
        </a>

        <a href="transfers.php" class="wh-card" style="--delay:0.1s">
            <?php if ($pending_transfers > 0): ?><div class="wh-badge b-amber"><?= $pending_transfers ?></div><?php endif; ?>
            <div class="wh-icon">
                <svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m15 7 5 5-5 5"/><path d="m9 7-5 5 5 5"/></svg>
            </div>
            <div class="wh-label">Трансфери</div>
            <div class="wh-sub"><?= $pending_transfers > 0 ? $pending_transfers . ' чакащи' : 'Между обекти' ?></div>
        </a>

        <?php if (!$is_seller): ?>
        <a href="deliveries.php" class="wh-card" style="--delay:0.15s">
            <?php if ($pending_deliveries > 0): ?><div class="wh-badge b-indigo"><?= $pending_deliveries ?></div><?php endif; ?>
            <div class="wh-icon">
                <svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div class="wh-label">Доставки</div>
            <div class="wh-sub"><?= $pending_deliveries > 0 ? $pending_deliveries . ' чакащи' : 'Получени стоки' ?></div>
        </a>

        <a href="suppliers.php" class="wh-card" style="--delay:0.2s">
            <div class="wh-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="wh-label">Доставчици</div>
            <div class="wh-sub"><?= $supplier_count ?> активни</div>
        </a>

        <a href="inventory.php" class="wh-card" style="--delay:0.25s">
            <?php if ($active_inventory > 0): ?><div class="wh-badge b-amber"><?= $active_inventory ?></div><?php endif; ?>
            <div class="wh-icon">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div class="wh-label">Инвентаризация</div>
            <div class="wh-sub"><?= $active_inventory > 0 ? $active_inventory . ' в ход' : 'Броене по артикул' ?></div>
        </a>

        <a href="revision.php" class="wh-card" style="--delay:0.3s">
            <div class="wh-icon">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="wh-label">Ревизия</div>
            <div class="wh-sub">Парична проверка</div>
        </a>
        <?php endif; ?>
    </div>

    <div class="indigo-sep"></div>
    
    <div class="stats-row">
        <div class="stat-item">
            <div class="stat-num shimmer-text" data-count="<?= $product_count ?>">0</div>
            <div class="stat-lbl">Артикули</div>
        </div>
        <div class="stat-item">
            <div class="stat-num" style="color:<?= $low_stock_count > 0 ? '#ef4444' : '#22c55e' ?>" data-count="<?= $low_stock_count ?>">0</div>
            <div class="stat-lbl">Ниска нал.</div>
        </div>
        <div class="stat-item">
            <div class="stat-num" data-count="<?= $pending_transfers ?>">0</div>
            <div class="stat-lbl">Трансфери</div>
        </div>
        <?php if (!$is_seller): ?>
        <div class="stat-item">
            <div class="stat-num" data-count="<?= $supplier_count ?>">0</div>
            <div class="stat-lbl">Доставчици</div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="indigo-sep"></div>

</div>

<nav class="bottom-nav">
    <a href="chat.php" class="bnav-tab"><span class="bnav-icon">✦</span>AI</a>
    <a href="warehouse.php" class="bnav-tab active"><span class="bnav-icon">📦</span>Склад</a>
    <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
    <a href="actions.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
</nav>

<script>
// Count up animation from stats.php
function animCount(el, target, dur = 900) {
    const s = performance.now();
    const u = (n) => {
        const p = Math.min((n - s) / dur, 1);
        const e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * e).toLocaleString('bg-BG');
        if (p < 1) requestAnimationFrame(u);
        else el.textContent = Math.round(target).toLocaleString('bg-BG');
    };
    requestAnimationFrame(u);
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-count]').forEach(el => {
        animCount(el, parseFloat(el.dataset.count) || 0);
    });
});
</script>

</body>
</html>
