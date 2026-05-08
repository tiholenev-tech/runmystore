<?php
/**
 * orders.php — Orders hub (Simple + Detailed Mode).
 *
 * Spec: docs/ORDERS_DESIGN_LOGIC.md, DELIVERY_ORDERS_DECISIONS_FINAL §G/U
 * Simple Mode: voice cart flow + recent orders quick rows
 * Detailed Mode: per-supplier drilldown + alt views (status / 6 въпроса / calendar)
 *
 * Design-kit v1.0 — без свой .glass / .lb-card / .briefing / .pill / .rms-*
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = DB::get();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];

$stmt = $pdo->prepare("
    SELECT u.name, u.role, u.store_id, t.supato_mode, t.currency, t.language, t.ui_mode, t.plan, t.plan_effective, t.trial_ends_at
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1
");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: logout.php'); exit; }

$role     = $user['role'];
$lang     = $user['language'] ?? 'bg';
$currency = $user['currency'] ?? 'EUR';
$mode     = ($role === 'seller') ? 'simple' : ($user['ui_mode'] ?: 'simple');

// Recent orders с aggregated info (последни 12)
$recent_orders = $pdo->prepare("
    SELECT po.*, s.name AS supplier_name,
           (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS item_count,
           (SELECT COALESCE(SUM(poi.qty_ordered * poi.cost_price), 0)
              FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.tenant_id = ?
    ORDER BY po.created_at DESC
    LIMIT 12
");
$recent_orders->execute([$tenant_id]);
$recent_orders = $recent_orders->fetchAll(PDO::FETCH_ASSOC);

// Aggregations за Simple cards
$pending_count = 0;
$stale_count = 0;
$draft_count = 0;
foreach ($recent_orders as $o) {
    if ($o['status'] === 'sent' || $o['status'] === 'partial') $pending_count++;
    if ($o['status'] === 'stale') $stale_count++;
    if ($o['status'] === 'draft') $draft_count++;
}

// Suggested orders (low stock products grouped by supplier) — quick access за Simple Mode
$low_stock_by_supplier = $pdo->prepare("
    SELECT s.id AS supplier_id, s.name AS supplier_name, COUNT(*) AS items_under_min
    FROM products p
    JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    WHERE p.tenant_id = ?
      AND p.is_active = 1
      AND s.id IS NOT NULL
      AND p.min_quantity > 0
      AND i.quantity < p.min_quantity
    GROUP BY s.id, s.name
    ORDER BY items_under_min DESC
    LIMIT 6
");
$low_stock_by_supplier->execute([$tenant_id]);
$low_stock_by_supplier = $low_stock_by_supplier->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Поръчки — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">

<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

<style>
.mod-ord-hero-cta{display:flex;align-items:center;gap:12px;padding:16px;cursor:pointer;text-decoration:none;color:inherit;border:none;width:100%;font-family:inherit}
.mod-ord-hero-ico{
    width:48px;height:48px;border-radius:14px;flex-shrink:0;
    background:linear-gradient(135deg,hsl(38 75% 52%),hsl(28 75% 46%));
    box-shadow:0 0 16px hsl(38 75% 50% / .5),inset 0 1px 0 rgba(255,255,255,.25);
    display:flex;align-items:center;justify-content:center;color:#fff
}
.mod-ord-hero-ico svg{width:22px;height:22px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.mod-ord-hero-text{flex:1;min-width:0;text-align:left}
.mod-ord-hero-title{font-size:15px;font-weight:900;color:#f1f5f9;letter-spacing:-.01em;line-height:1.2}
.mod-ord-hero-sub{font-size:10px;font-weight:600;color:rgba(255,255,255,.5);margin-top:3px}
.mod-ord-hero-arr{color:hsl(38 80% 70%);font-size:18px;font-weight:900;flex-shrink:0}

.mod-ord-row{
    display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:12px;
    background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.06);
    cursor:pointer;margin-bottom:6px;text-decoration:none;color:inherit
}
.mod-ord-ico{
    width:30px;height:30px;border-radius:9px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;color:#fff
}
.mod-ord-ico.draft{background:linear-gradient(135deg,hsl(255 50% 50%),hsl(222 50% 44%));box-shadow:0 0 8px hsl(255 50% 50% / .35)}
.mod-ord-ico.sent{background:linear-gradient(135deg,hsl(200 75% 48%),hsl(225 75% 40%));box-shadow:0 0 8px hsl(200 75% 50% / .35)}
.mod-ord-ico.partial{background:linear-gradient(135deg,hsl(38 75% 48%),hsl(28 75% 40%));box-shadow:0 0 8px hsl(38 75% 50% / .35)}
.mod-ord-ico.received{background:linear-gradient(135deg,hsl(145 65% 42%),hsl(160 65% 36%));box-shadow:0 0 8px hsl(145 65% 45% / .35)}
.mod-ord-ico.stale{background:linear-gradient(135deg,hsl(0 70% 48%),hsl(15 70% 40%));box-shadow:0 0 8px hsl(0 75% 50% / .35)}
.mod-ord-ico.cancelled{background:rgba(255,255,255,.1);color:rgba(255,255,255,.4)}
.mod-ord-ico svg{width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none;stroke-linecap:round;stroke-linejoin:round}
.mod-ord-body{flex:1;min-width:0}
.mod-ord-name{font-size:12px;font-weight:800;color:#f1f5f9;line-height:1.2}
.mod-ord-meta{font-size:9px;font-weight:600;color:rgba(255,255,255,.4);margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;letter-spacing:.02em}
.mod-ord-status{
    display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:100px;
    font-size:8px;font-weight:900;letter-spacing:.05em;text-transform:uppercase
}
.mod-ord-status.draft{background:rgba(165,180,252,.12);border:1px solid rgba(165,180,252,.3);color:#c7d2fe}
.mod-ord-status.sent{background:rgba(99,150,255,.14);border:1px solid rgba(99,150,255,.32);color:#93c5fd}
.mod-ord-status.partial{background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.32);color:#fbbf24}
.mod-ord-status.received{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac}
.mod-ord-status.stale{background:rgba(239,68,68,.16);border:1px solid rgba(239,68,68,.4);color:#fca5a5}
.mod-ord-status.cancelled{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.45)}
.mod-ord-amt{font-size:13px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums;text-align:right;flex-shrink:0;line-height:1}
.mod-ord-amt small{display:block;font-size:8px;font-weight:700;color:rgba(255,255,255,.4);letter-spacing:.06em;margin-top:3px;text-transform:uppercase}

.mod-ord-sec-label{
    font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;
    color:hsl(255 50% 70%);text-shadow:0 0 12px hsl(255 70% 60% / .25);
    margin:14px 4px 8px
}
.mod-ord-see-all{
    display:block;text-align:center;padding:10px;
    font-size:10px;font-weight:800;color:hsl(255 60% 78%);
    letter-spacing:.08em;text-transform:uppercase;
    background:transparent;border:none;font-family:inherit;cursor:pointer;
    text-decoration:none;width:100%;margin-top:4px
}

.mode-simple .mod-ord-detail-only{display:none}
.mode-detailed .mod-ord-simple-only{display:none}
</style>
</head>
<body class="has-rms-shell mode-<?= htmlspecialchars($mode) ?>">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="app">

    <!-- HERO CTA — Нова поръчка (q4=amber) -->
    <a class="glass q4 mod-ord-hero-cta card-stagger" href="/order.php?action=new">
        <span class="shine"></span>
        <span class="shine shine-bottom"></span>
        <span class="glow"></span>
        <span class="glow glow-bottom"></span>
        <div class="mod-ord-hero-ico">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="mod-ord-hero-text">
            <div class="mod-ord-hero-title">Нова поръчка</div>
            <div class="mod-ord-hero-sub">говори с AI или избери от под минимум</div>
        </div>
        <div class="mod-ord-hero-arr">›</div>
    </a>

    <?php if (!empty($low_stock_by_supplier)): ?>
    <!-- AI PROACTIVE — под минимум по доставчик (lb-card.q5 = order/amber) -->
    <div class="lb-card glass q5 card-stagger">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="lb-top">
            <div class="lb-fq-tag">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                Време за поръчка
            </div>
        </div>
        <div class="lb-card-title">Артикули под минимум</div>
        <div class="lb-body">
            <?php foreach (array_slice($low_stock_by_supplier, 0, 3) as $g): ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:12px;font-weight:700;color:rgba(255,255,255,.85)">
                    <span><?= htmlspecialchars($g['supplier_name']) ?></span>
                    <span style="color:hsl(38 75% 65%)"><?= (int)$g['items_under_min'] ?> арт</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="lb-actions">
            <a class="lb-action primary" href="/order.php?action=draft&supplier=<?= (int)$low_stock_by_supplier[0]['supplier_id'] ?>">
                Подготви за <?= htmlspecialchars($low_stock_by_supplier[0]['supplier_name']) ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($stale_count > 0): ?>
    <!-- STALE WARNING (q1 = loss / red) -->
    <div class="briefing-section q1 card-stagger">
        <div class="briefing-head">
            <span class="briefing-emoji" aria-hidden="true">▼</span>
            <span class="briefing-name">ЗАПОЧВА ДА ЗАГРЯВА</span>
        </div>
        <div class="briefing-title"><?= $stale_count ?> поръчки чакат над 14 дни</div>
        <div class="briefing-detail">
            Тези доставчици не доставят. Обади се или отмени.
        </div>
        <div class="briefing-actions">
            <a class="briefing-btn-primary" href="/orders.php?view=stale">Виж кои</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="mod-ord-sec-label">Последни поръчки</div>

    <?php if (empty($recent_orders)): ?>
        <div class="glass qd" style="padding:24px;text-align:center">
            <span class="shine"></span><span class="glow"></span>
            <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-bottom:6px">Все още няма поръчки</div>
            <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.55)">Tap-ни „Нова поръчка" горе за първа.</div>
        </div>
    <?php else: ?>
        <?php foreach ($recent_orders as $o):
            $status = (string)$o['status'];
            $ico = $status; // CSS клас
            $ico_svg = '';
            if ($status === 'draft')      $ico_svg = '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
            elseif ($status === 'sent')   $ico_svg = '<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
            elseif ($status === 'partial') $ico_svg = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
            elseif ($status === 'received') $ico_svg = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
            elseif ($status === 'stale')   $ico_svg = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            else                            $ico_svg = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        ?>
        <a class="mod-ord-row card-stagger" href="/order.php?id=<?= (int)$o['id'] ?>">
            <div class="mod-ord-ico <?= $ico ?>"><?= $ico_svg ?></div>
            <div class="mod-ord-body">
                <div class="mod-ord-name"><?= htmlspecialchars($o['supplier_name'] ?: 'Без доставчик') ?></div>
                <div class="mod-ord-meta">
                    <span><?= date('d.m', strtotime((string)$o['created_at'])) ?></span>
                    <span>·</span>
                    <span><?= (int)$o['item_count'] ?> арт</span>
                    <span class="mod-ord-status <?= $status ?>"><?= htmlspecialchars($status) ?></span>
                </div>
            </div>
            <div class="mod-ord-amt"><?= number_format((float)$o['total'], 0, '.', ' ') ?><small><?= htmlspecialchars($currency === 'EUR' ? '€' : 'лв') ?></small></div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
</body>
</html>
