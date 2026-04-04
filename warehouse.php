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
    <link href="./style.css" rel="stylesheet" />
    <style>
        :root { --nav-h: 64px; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: Inter, system-ui, sans-serif; 
            background: radial-gradient(circle at top right, #13172c, #0b0f1a); 
            color: #e2e8f0; 
            min-height: 100vh; 
            overflow-x: hidden; 
            padding-bottom: var(--nav-h);
        }

        /* ── SVG Backgrounds ── */
        .bg-deco { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; opacity: 0.7; }
        .bg-deco .d1 { position: absolute; left: 50%; top: 0; transform: translateX(-25%); width: 846px; max-width: none; }
        .bg-deco .d2 { position: absolute; left: 50%; top: 400px; transform: translateX(-100%); width: 760px; max-width: none; opacity: .4; }
        .bg-deco .d3 { position: absolute; left: 50%; top: 440px; transform: translateX(-33%); width: 760px; max-width: none; opacity: 0.5; }

        .page-wrap { position: relative; z-index: 1; max-width: 480px; margin: 0 auto; padding: 0 16px; }

        /* ── Header ── */
        .page-header { padding: 24px 0 12px; }
        .page-title {
            font-size: 24px; font-weight: 800; font-family: Nacelle, Inter, sans-serif;
            background: linear-gradient(135deg, #ffffff, #a5b4fc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; letter-spacing: -0.5px;
        }

        /* ── Indigo line separator ── */
        .indigo-sep { height: 1px; background: linear-gradient(to right, transparent, rgba(99,102,241,0.25), transparent); margin: 16px 0; }

        /* ── Alert banner ── */
        .alert-banner {
            display: flex; align-items: center; gap: 12px;
            background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
            border-radius: 16px; padding: 14px 16px; margin-bottom: 20px; text-decoration: none;
            backdrop-filter: blur(10px); animation: fadeUp .35s ease both;
        }
        .alert-dot { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; flex-shrink: 0; box-shadow: 0 0 10px #ef4444; animation: blink 2s infinite; }
        @keyframes blink { 0%,100% { opacity:1 } 50% { opacity:.4 } }
        .alert-text { font-size: 13px; font-weight: 700; color: #fca5a5; flex: 1; }
        .alert-arr { color: #f87171; font-size: 20px; font-weight: bold; }

        /* ── Grid ── */
        .wh-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* ── Card with Modern GLOW ── */
        .wh-card {
            position: relative; display: flex; flex-direction: column;
            border-radius: 24px; padding: 20px; text-decoration: none;
            min-height: 160px; overflow: hidden; transition: all 0.2s ease;
            background: rgba(30, 35, 60, 0.5); backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .wh-card:active { transform: scale(0.95); background: rgba(99, 102, 241, 0.15); }

        /* Glow effects */
        .g-indigo { box-shadow: 0 8px 24px rgba(79, 70, 229, 0.15); border-color: rgba(79, 70, 229, 0.2); }
        .g-purple { box-shadow: 0 8px 24px rgba(147, 51, 234, 0.15); border-color: rgba(147, 51, 234, 0.2); }
        .g-green  { box-shadow: 0 8px 24px rgba(34, 197, 94, 0.12); border-color: rgba(34, 197, 94, 0.2); }
        .g-orange { box-shadow: 0 8px 24px rgba(249, 115, 22, 0.12); border-color: rgba(249, 115, 22, 0.2); }
        .g-teal   { box-shadow: 0 8px 24px rgba(20, 184, 166, 0.12); border-color: rgba(20, 184, 166, 0.2); }
        .g-gold   { box-shadow: 0 8px 24px rgba(234, 179, 8, 0.12); border-color: rgba(234, 179, 8, 0.2); }

        /* Icon styling */
        .wh-icon { 
            width: 48px; height: 48px; border-radius: 16px; 
            display: flex; align-items: center; justify-content: center; 
            margin-bottom: 16px; position: relative;
        }
        .wh-icon svg { width: 24px; height: 24px; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2; }
        
        .i-indigo { background: rgba(79, 70, 229, 0.2); box-shadow: 0 0 15px rgba(79, 70, 229, 0.4); }
        .i-indigo svg { stroke: #a5b4fc; }
        .i-purple { background: rgba(147, 51, 234, 0.2); box-shadow: 0 0 15px rgba(147, 51, 234, 0.4); }
        .i-purple svg { stroke: #c4b5fd; }
        .i-green  { background: rgba(34, 197, 94, 0.2); box-shadow: 0 0 15px rgba(34, 197, 94, 0.4); }
        .i-green svg  { stroke: #86efac; }
        .i-orange { background: rgba(249, 115, 22, 0.2); box-shadow: 0 0 15px rgba(249, 115, 22, 0.4); }
        .i-orange svg { stroke: #fdba74; }
        .i-teal   { background: rgba(20, 184, 166, 0.2); box-shadow: 0 0 15px rgba(20, 184, 166, 0.4); }
        .i-teal svg   { stroke: #5eead4; }
        .i-gold   { background: rgba(234, 179, 8, 0.2); box-shadow: 0 0 15px rgba(234, 179, 8, 0.4); }
        .i-gold svg   { stroke: #fde047; }

        /* Labels */
        .wh-label { font-size: 15px; font-weight: 800; color: #f8fafc; letter-spacing: -0.02em; }
        .wh-sub { font-size: 12px; font-weight: 500; color: #6b7280; margin-top: 4px; }
        .wh-sub.danger { color: #f87171; font-weight: 700; }

        /* Badge */
        .wh-badge { 
            position: absolute; top: 12px; right: 12px; 
            min-width: 22px; height: 22px; border-radius: 11px; 
            font-size: 10px; font-weight: 800; display: flex; 
            align-items: center; justify-content: center; 
            padding: 0 6px; color: #fff; z-index: 2;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .b-red     { background: #ef4444; box-shadow: 0 0 12px rgba(239,68,68,0.5); }
        .b-amber   { background: #f59e0b; box-shadow: 0 0 12px rgba(245,158,11,0.5); }
        .b-indigo  { background: #6366f1; box-shadow: 0 0 12px rgba(99,102,241,0.5); }

        /* Stats Section */
        .stats-row { display: flex; justify-content: space-around; padding: 12px 0; background: rgba(255,255,255,0.02); border-radius: 20px; margin-top: 10px; }
        .stat-item { text-align: center; }
        .stat-num { font-size: 18px; font-weight: 800; color: #c7d2fe; }
        .stat-lbl { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* ── BOTTOM NAV (Идентичен с чата) ── */
        .bottom-nav { 
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; 
            background: rgba(11,15,26,0.92); 
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); 
            border-top: 1px solid rgba(99,102,241,0.25); 
            display: flex; height: var(--nav-h); 
            box-shadow: 0 -5px 25px rgba(99,102,241,0.2);
        }
        .bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; font-size: 0.65rem; font-weight: 600; color: rgba(165,180,252,0.5); text-decoration: none; transition: all 0.3s; }
        .bnav-tab.active { color: #c7d2fe; text-shadow: 0 0 12px rgba(129,140,248,0.9); }
        .bnav-tab .bnav-icon { font-size: 1.3rem; transition: all 0.3s; }
        .bnav-tab.active .bnav-icon { transform: translateY(-2px); filter: drop-shadow(0 0 8px rgba(129,140,248,0.8)); }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(12px) } to { opacity: 1; transform: translateY(0) } }
    </style>
</head>
<body>

<div class="bg-deco" aria-hidden="true">
    <img class="d1" src="./images/page-illustration.svg" alt="">
    <img class="d2" src="./images/blurred-shape-gray.svg" alt="">
    <img class="d3" src="./images/blurred-shape.svg" alt="">
</div>

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
        <a href="products.php" class="wh-card g-indigo">
            <?php if ($low_stock_count > 0): ?><div class="wh-badge b-red"><?= $low_stock_count ?></div><?php endif; ?>
            <div class="wh-icon i-indigo"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
            <div class="wh-label">Артикули</div>
            <div class="wh-sub <?= $low_stock_count > 0 ? 'danger' : '' ?>"><?= $low_stock_count > 0 ? $low_stock_count . ' под минимум' : $product_count . ' активни' ?></div>
        </a>

        <a href="transfers.php" class="wh-card g-purple">
            <?php if ($pending_transfers > 0): ?><div class="wh-badge b-amber"><?= $pending_transfers ?></div><?php endif; ?>
            <div class="wh-icon i-purple"><svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m15 7 5 5-5 5"/><path d="m9 7-5 5 5 5"/></svg></div>
            <div class="wh-label">Трансфери</div>
            <div class="wh-sub"><?= $pending_transfers > 0 ? $pending_transfers . ' чакащи' : 'Между обекти' ?></div>
        </a>

        <?php if (!$is_seller): ?>
        <a href="deliveries.php" class="wh-card g-green">
            <?php if ($pending_deliveries > 0): ?><div class="wh-badge b-indigo"><?= $pending_deliveries ?></div><?php endif; ?>
            <div class="wh-icon i-green"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
            <div class="wh-label">Доставки</div>
            <div class="wh-sub"><?= $pending_deliveries > 0 ? $pending_deliveries . ' чакащи' : 'Получени стоки' ?></div>
        </a>

        <a href="suppliers.php" class="wh-card g-orange">
            <div class="wh-icon i-orange"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div class="wh-label">Доставчици</div>
            <div class="wh-sub"><?= $supplier_count ?> активни</div>
        </a>

        <a href="inventory.php" class="wh-card g-teal">
            <?php if ($active_inventory > 0): ?><div class="wh-badge b-amber"><?= $active_inventory ?></div><?php endif; ?>
            <div class="wh-icon i-teal"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
            <div class="wh-label">Инвентаризация</div>
            <div class="wh-sub"><?= $active_inventory > 0 ? $active_inventory . ' в ход' : 'Броене по артикул' ?></div>
        </a>

        <a href="revision.php" class="wh-card g-gold">
            <div class="wh-icon i-gold"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="wh-label">Ревизия</div>
            <div class="wh-sub">Парична проверка</div>
        </a>
        <?php endif; ?>
    </div>

    <div class="indigo-sep"></div>
    <div class="stats-row">
        <div class="stat-item"><div class="stat-num"><?= $product_count ?></div><div class="stat-lbl">Артикули</div></div>
        <div class="stat-item"><div class="stat-num"><?= $low_stock_count ?></div><div class="stat-lbl">Ниска нал.</div></div>
        <div class="stat-item"><div class="stat-num"><?= $pending_transfers ?></div><div class="stat-lbl">Трансфери</div></div>
        <?php if (!$is_seller): ?>
        <div class="stat-item"><div class="stat-num"><?= $supplier_count ?></div><div class="stat-lbl">Доставчици</div></div>
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

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/main.js"></script>
</body>
</html>
