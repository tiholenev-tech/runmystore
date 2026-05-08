<?php
/**
 * order.php — Single purchase order (view / new draft / send).
 *
 * Spec: docs/ORDERS_DESIGN_LOGIC.md, DELIVERY_ORDERS_DECISIONS_FINAL §G/U
 *
 * URLs:
 *   order.php?id=N                 — преглед/редакция
 *   order.php?action=new            — нова чернова (празна)
 *   order.php?action=draft&supplier=S — pre-filled с low stock от supplier
 *
 * AJAX:
 *   ?api=add_item     — добави артикул в чернова
 *   ?api=remove_item  — премахни ред
 *   ?api=update_item  — qty/cost edit
 *   ?api=send         — статус draft → sent + генерира текст за Viber/email
 *   ?api=cancel       — статус → cancelled
 *   ?api=mark_received — статус → received (или partial ако частично)
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = DB::get();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];

$stmt = $pdo->prepare("
    SELECT u.name, u.role, u.store_id, t.currency, t.language, t.ui_mode
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
$store_id = (int)($user['store_id'] ?? 0);
$mode     = ($role === 'seller') ? 'simple' : ($user['ui_mode'] ?: 'simple');

// AJAX
$api = $_GET['api'] ?? '';
if ($api && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($api) {
            case 'add_item':       echo json_encode(api_add_item($tenant_id, $user_id)); break;
            case 'remove_item':    echo json_encode(api_remove_item($tenant_id)); break;
            case 'update_item':    echo json_encode(api_update_item($tenant_id)); break;
            case 'send':           echo json_encode(api_send($tenant_id, $user_id)); break;
            case 'cancel':         echo json_encode(api_cancel($tenant_id, $user_id)); break;
            case 'mark_received':  echo json_encode(api_mark_received($tenant_id, $user_id)); break;
            default:
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'unknown api']);
        }
    } catch (Throwable $e) {
        error_log('order.php api ' . $api . ': ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action   = $_GET['action'] ?? '';
$preselect_supplier = isset($_GET['supplier']) ? (int)$_GET['supplier'] : 0;

$order = null;
$items = [];

// New / draft creation if no id
if (!$order_id && ($action === 'new' || $action === 'draft')) {
    $order_id = (int)DB::tx(function (PDO $pdo) use ($tenant_id, $store_id, $user_id, $preselect_supplier) {
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (tenant_id, supplier_id, store_id, created_by, status)
            VALUES (?, ?, ?, ?, 'draft')
        ");
        $stmt->execute([$tenant_id, $preselect_supplier ?: null, $store_id, $user_id]);
        $oid = (int)$pdo->lastInsertId();

        // Pre-fill: ако имаме supplier — auto-add low stock products от тоя supplier
        if ($preselect_supplier) {
            $low = $pdo->prepare("
                SELECT p.id, p.cost_price, p.min_quantity, COALESCE(i.quantity, 0) AS current_qty
                FROM products p
                LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
                WHERE p.tenant_id = ? AND p.supplier_id = ? AND p.is_active = 1
                  AND p.min_quantity > 0 AND COALESCE(i.quantity, 0) < p.min_quantity
                LIMIT 50
            ");
            $low->execute([$tenant_id, $preselect_supplier]);
            $low = $low->fetchAll(PDO::FETCH_ASSOC);
            foreach ($low as $p) {
                $needed = max(1, (int)((float)$p['min_quantity'] - (float)$p['current_qty']));
                $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, qty_ordered, cost_price)
                               VALUES (?, ?, ?, ?)")
                    ->execute([$oid, (int)$p['id'], $needed, (float)$p['cost_price']]);
            }
        }
        return $oid;
    });
    header('Location: /order.php?id=' . $order_id);
    exit;
}

if ($order_id > 0) {
    $stmt = $pdo->prepare("
        SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.id = po.supplier_id
        WHERE po.id = ? AND po.tenant_id = ?
    ");
    $stmt->execute([$order_id, $tenant_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { http_response_code(404); echo 'Order not found'; exit; }

    $stmt = $pdo->prepare("
        SELECT poi.*, p.name AS product_name, p.code AS product_code, p.barcode
        FROM purchase_order_items poi
        LEFT JOIN products p ON p.id = poi.product_id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$suppliers = $pdo->prepare("SELECT id, name FROM suppliers WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
$suppliers->execute([$tenant_id]);
$suppliers = $suppliers->fetchAll(PDO::FETCH_ASSOC);

// API impls
function api_add_item(int $tenant_id, int $user_id): array {
    $oid = (int)($_POST['order_id'] ?? 0);
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = (float)($_POST['qty'] ?? 0);
    $cost = (float)($_POST['cost_price'] ?? 0);

    if ($oid <= 0 || $pid <= 0 || $qty <= 0) return ['ok' => false, 'error' => 'invalid params'];

    $stmt = DB::run("SELECT id, status FROM purchase_orders WHERE id=? AND tenant_id=?", [$oid, $tenant_id])->fetch();
    if (!$stmt || $stmt['status'] !== 'draft') return ['ok' => false, 'error' => 'order not editable'];

    DB::run("
        INSERT INTO purchase_order_items (purchase_order_id, product_id, qty_ordered, cost_price)
        VALUES (?, ?, ?, ?)
    ", [$oid, $pid, $qty, $cost]);
    return ['ok' => true];
}

function api_remove_item(int $tenant_id): array {
    $iid = (int)($_POST['item_id'] ?? 0);
    if ($iid <= 0) return ['ok' => false, 'error' => 'missing'];

    DB::run("
        DELETE poi FROM purchase_order_items poi
        JOIN purchase_orders po ON po.id = poi.purchase_order_id
        WHERE poi.id = ? AND po.tenant_id = ? AND po.status = 'draft'
    ", [$iid, $tenant_id]);
    return ['ok' => true];
}

function api_update_item(int $tenant_id): array {
    $iid = (int)($_POST['item_id'] ?? 0);
    $qty = isset($_POST['qty']) ? (float)$_POST['qty'] : null;
    $cost = isset($_POST['cost_price']) ? (float)$_POST['cost_price'] : null;
    if ($iid <= 0) return ['ok' => false, 'error' => 'missing'];

    $sets = [];
    $params = [];
    if ($qty !== null)  { $sets[] = 'qty_ordered = ?'; $params[] = $qty; }
    if ($cost !== null) { $sets[] = 'cost_price = ?';  $params[] = $cost; }
    if (!$sets) return ['ok' => false, 'error' => 'nothing to update'];

    $params[] = $iid;
    $params[] = $tenant_id;
    DB::run("
        UPDATE purchase_order_items poi
        JOIN purchase_orders po ON po.id = poi.purchase_order_id
        SET " . implode(', ', $sets) . "
        WHERE poi.id = ? AND po.tenant_id = ? AND po.status = 'draft'
    ", $params);
    return ['ok' => true];
}

function api_send(int $tenant_id, int $user_id): array {
    $oid = (int)($_POST['order_id'] ?? 0);
    if ($oid <= 0) return ['ok' => false, 'error' => 'missing'];

    $order = DB::run("SELECT * FROM purchase_orders WHERE id=? AND tenant_id=?", [$oid, $tenant_id])->fetch();
    if (!$order || $order['status'] !== 'draft') return ['ok' => false, 'error' => 'cannot send'];

    DB::run("UPDATE purchase_orders SET status='sent', sent_at=NOW() WHERE id=?", [$oid]);

    // Текст за Viber/email — generated copy-paste
    $items = DB::run("
        SELECT poi.qty_ordered, p.name, p.code, p.barcode
        FROM purchase_order_items poi
        LEFT JOIN products p ON p.id = poi.product_id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id
    ", [$oid])->fetchAll();

    $lines = ['Здравейте,', '', 'Моля поръчайте:'];
    foreach ($items as $i => $it) {
        $name = $it['name'] ?: 'артикул';
        $code = $it['code'] ?: $it['barcode'] ?: '';
        $qty = rtrim(rtrim(number_format((float)$it['qty_ordered'], 2, '.', ''), '0'), '.');
        $lines[] = ($i + 1) . '. ' . $name . ($code ? ' (' . $code . ')' : '') . ' — ' . $qty . ' бр';
    }
    $lines[] = '';
    $lines[] = 'Благодаря.';
    $text = implode("\n", $lines);

    return ['ok' => true, 'message' => 'Изпратена', 'copy_paste_text' => $text];
}

function api_cancel(int $tenant_id, int $user_id): array {
    $oid = (int)($_POST['order_id'] ?? 0);
    if ($oid <= 0) return ['ok' => false, 'error' => 'missing'];
    DB::run("UPDATE purchase_orders SET status='canceled' WHERE id=? AND tenant_id=? AND status IN ('draft','sent','partial','stale')",
            [$oid, $tenant_id]);
    return ['ok' => true];
}

function api_mark_received(int $tenant_id, int $user_id): array {
    $oid = (int)($_POST['order_id'] ?? 0);
    if ($oid <= 0) return ['ok' => false, 'error' => 'missing'];
    DB::run("UPDATE purchase_orders SET status='received', received_at=NOW() WHERE id=? AND tenant_id=?", [$oid, $tenant_id]);
    return ['ok' => true];
}

$total = 0;
foreach ($items as $it) $total += (float)$it['qty_ordered'] * (float)$it['cost_price'];
$is_editable = $order && $order['status'] === 'draft';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Поръчка — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">

<script>(function(){try{var s=localStorage.getItem('rms_theme');document.documentElement.setAttribute('data-theme',s||'light')}catch(_){document.documentElement.setAttribute('data-theme','light')}})();</script>

<style>
.mod-ord-row{
    display:flex;align-items:center;gap:10px;padding:10px 12px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);
    border-radius:var(--radius);margin-bottom:6px
}
.mod-ord-row-body{flex:1;min-width:0}
.mod-ord-row-name{font-size:13px;font-weight:800;color:#f1f5f9;line-height:1.2;text-overflow:ellipsis;overflow:hidden;white-space:nowrap}
.mod-ord-row-meta{font-size:10px;font-weight:600;color:rgba(255,255,255,.45);margin-top:3px;display:flex;gap:8px;flex-wrap:wrap}
.mod-ord-qty-input{
    width:60px;padding:6px 8px;border-radius:var(--radius-sm);
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    color:#f1f5f9;font-size:13px;font-weight:800;font-family:inherit;
    font-variant-numeric:tabular-nums;text-align:center
}
.mod-ord-row-amt{font-size:13px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums;text-align:right;flex-shrink:0;line-height:1}
.mod-ord-row-rm{
    width:24px;height:24px;border-radius:var(--radius-sm);flex-shrink:0;
    background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
    color:hsl(0 93% 82%);cursor:pointer;font-family:inherit;
    display:flex;align-items:center;justify-content:center
}
.mod-ord-totals{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-radius:var(--radius);margin-top:10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07)}
.mod-ord-totals-label{font-size:10px;font-weight:700;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.06em}
.mod-ord-totals-amt{font-size:18px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums}

.mod-ord-action{
    display:flex;align-items:center;justify-content:center;gap:8px;
    padding:14px 20px;border-radius:var(--radius);width:100%;
    font-size:14px;font-weight:900;letter-spacing:.02em;
    text-decoration:none;border:none;cursor:pointer;font-family:inherit;color:#fff
}
.mod-ord-action.primary{background:linear-gradient(135deg,hsl(145 65% 45%),hsl(160 65% 38%));box-shadow:0 0 16px hsl(145 65% 45% / .4)}
.mod-ord-action.warn{background:linear-gradient(135deg,hsl(38 75% 48%),hsl(28 75% 42%));box-shadow:0 0 16px hsl(38 75% 50% / .4)}
.mod-ord-action.danger{background:linear-gradient(135deg,hsl(0 70% 48%),hsl(15 70% 42%));box-shadow:0 0 14px hsl(0 75% 50% / .4)}
.mod-ord-action:disabled{opacity:.4;cursor:not-allowed;box-shadow:none}

.mod-ord-sec-label{font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:hsl(255 50% 70%);margin:14px 4px 8px}

.mod-ord-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;opacity:0;pointer-events:none;transition:opacity .2s;display:flex;align-items:center;justify-content:center;padding:20px}
.mod-ord-overlay.open{opacity:1;pointer-events:auto}
.mod-ord-modal{background:rgba(8,9,13,.98);border:1px solid rgba(255,255,255,.12);border-radius:var(--radius);padding:18px;max-width:420px;width:100%;max-height:80vh;overflow-y:auto}
.mod-ord-modal h3{font-size:14px;font-weight:900;color:#f1f5f9;margin-bottom:12px}
.mod-ord-modal pre{font-family:var(--font-mono);font-size:12px;color:#e0e0e0;background:rgba(255,255,255,.04);padding:10px;border-radius:var(--radius-sm);white-space:pre-wrap;word-break:break-word;max-height:50vh;overflow-y:auto}

.mod-ord-toast{position:fixed;left:16px;right:16px;bottom:80px;z-index:300;padding:12px 16px;border-radius:var(--radius);background:rgba(8,9,13,.95);border:1px solid rgba(34,197,94,.5);color:hsl(141 79% 73%);font-size:13px;font-weight:800;transform:translateY(120%);transition:transform .3s}
.mod-ord-toast.show{transform:translateY(0)}
.mod-ord-toast.error{border-color:rgba(239,68,68,.6);color:hsl(0 93% 82%)}
.mod-ord-toast.warn{border-color:rgba(245,158,11,.6);color:hsl(43 96% 56%)}

.mod-ord-status-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:var(--radius-pill);font-size:10px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}
.mod-ord-status-pill.draft{background:rgba(165,180,252,.12);border:1px solid rgba(165,180,252,.3);color:hsl(229 100% 89%)}
.mod-ord-status-pill.sent{background:rgba(99,150,255,.14);border:1px solid rgba(99,150,255,.32);color:#93c5fd}
.mod-ord-status-pill.partial{background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.32);color:hsl(43 96% 56%)}
.mod-ord-status-pill.received{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:hsl(141 79% 73%)}
.mod-ord-status-pill.stale{background:rgba(239,68,68,.16);border:1px solid rgba(239,68,68,.4);color:hsl(0 93% 82%)}
.mod-ord-status-pill.cancelled{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.45)}


/* ── S106: BICHROMATIC theme support (auto-injected) ── */
[data-theme="light"] body{background:var(--bg);color:var(--text)}
[data-theme="light"] .glass{background:var(--surface,rgba(255,255,255,.6));border-color:var(--border-color,rgba(0,0,0,.06))}
[data-theme="light"] h1,[data-theme="light"] h2,[data-theme="light"] h3{color:var(--text)}
[data-theme="dark"] body{background:var(--bg);color:var(--text)}
[data-theme="dark"] .glass{background:var(--surface,rgba(20,22,30,.55))}

@media (prefers-reduced-motion: reduce){
  *{transition:none!important;animation:none!important}
}

/* glass content stays above shine/glow spans */
.glass > *:not(.shine):not(.glow){position:relative;z-index:5}
</style>
</head>
<body class="has-rms-shell mode-<?= htmlspecialchars($mode) ?>">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="app">

<?php if (!$order): ?>
    <div class="glass qd" style="padding:24px;text-align:center">
        <span class="shine"></span><span class="glow"></span>
        <div style="font-size:14px;font-weight:800;color:#f1f5f9;margin-bottom:8px">Поръчката не е намерена</div>
        <a href="/orders.php" class="mod-ord-action primary" style="margin-top:12px">Назад към поръчки</a>
    </div>
<?php else: ?>

    <div class="mod-ord-sec-label">
        <?= htmlspecialchars($order['supplier_name'] ?: 'Без доставчик') ?>
        <span class="mod-ord-status-pill <?= htmlspecialchars($order['status']) ?>" style="margin-left:6px"><?= htmlspecialchars($order['status']) ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="glass qd" style="padding:20px;text-align:center;margin-bottom:10px">
            <span class="shine"></span><span class="glow"></span>
            <div style="font-size:12px;font-weight:700;color:rgba(255,255,255,.7)">Празна поръчка. Добави артикули.</div>
        </div>
    <?php else: ?>
        <?php foreach ($items as $it):
            $line_total = (float)$it['qty_ordered'] * (float)$it['cost_price'];
        ?>
        <div class="mod-ord-row" data-item-id="<?= (int)$it['id'] ?>">
            <div class="mod-ord-row-body">
                <div class="mod-ord-row-name"><?= htmlspecialchars($it['product_name'] ?: 'Артикул #' . $it['product_id']) ?></div>
                <div class="mod-ord-row-meta">
                    <?php if (!empty($it['product_code'])): ?><span><?= htmlspecialchars($it['product_code']) ?></span><?php endif; ?>
                    <span><?= fmtMoney((float)$it['cost_price'], $currency) ?> / бр</span>
                    <?php if ((float)$it['qty_received'] > 0): ?>
                        <span style="color:hsl(145 65% 60%)">получени <?= rtrim(rtrim(number_format((float)$it['qty_received'], 2, '.', ''), '0'), '.') ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($is_editable): ?>
                <input type="number" class="mod-ord-qty-input" value="<?= rtrim(rtrim(number_format((float)$it['qty_ordered'], 2, '.', ''), '0'), '.') ?>"
                       step="1" inputmode="numeric"
                       onchange="modOrdUpdateQty(<?= (int)$it['id'] ?>, this.value)">
                <button class="mod-ord-row-rm" type="button" onclick="modOrdRemove(<?= (int)$it['id'] ?>)" aria-label="Премахни">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            <?php else: ?>
                <div class="mod-ord-row-amt" style="font-size:11px;color:rgba(255,255,255,.6)"><?= rtrim(rtrim(number_format((float)$it['qty_ordered'], 2, '.', ''), '0'), '.') ?> бр</div>
            <?php endif; ?>
            <div class="mod-ord-row-amt"><?= fmtMoney($line_total, $currency) ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="mod-ord-totals">
        <div>
            <div class="mod-ord-totals-label">Общо</div>
            <div class="mod-ord-totals-amt"><?= fmtMoney($total, $currency) ?></div>
        </div>
        <div style="text-align:right">
            <div class="mod-ord-totals-label">Артикули</div>
            <div class="mod-ord-totals-amt" style="font-size:14px;color:rgba(255,255,255,.7)"><?= count($items) ?></div>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
        <?php if ($is_editable): ?>
            <button class="mod-ord-action primary" type="button" onclick="modOrdSend()" <?= empty($items) ? 'disabled' : '' ?>>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Изпрати поръчката
            </button>
            <button class="mod-ord-action warn" type="button" onclick="modOrdAddPick()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Добави артикул
            </button>
            <button class="mod-ord-action danger" type="button" onclick="modOrdCancel()">Отмени поръчката</button>
        <?php elseif ($order['status'] === 'sent' || $order['status'] === 'partial' || $order['status'] === 'stale'): ?>
            <button class="mod-ord-action primary" type="button" onclick="modOrdMarkReceived()">Отбележи като получена</button>
            <button class="mod-ord-action danger" type="button" onclick="modOrdCancel()">Отмени поръчката</button>
        <?php endif; ?>
        <a class="mod-ord-action" href="/orders.php" style="background:rgba(255,255,255,.08);color:#f1f5f9">Назад</a>
    </div>

<?php endif; ?>

</main>

<!-- SEND modal — copy-paste текст -->
<div class="mod-ord-overlay" id="modOrdSendOverlay" onclick="if(event.target===this) modOrdCloseModal()">
    <div class="mod-ord-modal" onclick="event.stopPropagation()">
        <h3>Поръчката е готова</h3>
        <div style="font-size:11px;color:rgba(255,255,255,.6);margin-bottom:8px">Копирай текста и го изпрати във Viber, WhatsApp или email:</div>
        <pre id="modOrdSendText"></pre>
        <div style="display:flex;gap:8px;margin-top:12px">
            <button class="mod-ord-action primary" type="button" onclick="modOrdCopyText()">Копирай</button>
            <button class="mod-ord-action" type="button" onclick="modOrdCloseModal()" style="background:rgba(255,255,255,.08);color:#f1f5f9">Затвори</button>
        </div>
    </div>
</div>

<div class="mod-ord-toast" id="modOrdToast"></div>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
<script>
(function () {
    var ORDER_ID = <?= $order_id ? (int)$order_id : 'null' ?>;

    function $(id) { return document.getElementById(id); }
    function toast(msg, kind) {
        var t = $('modOrdToast');
        t.textContent = msg;
        t.className = 'mod-ord-toast' + (kind ? ' ' + kind : '') + ' show';
        setTimeout(function () { t.classList.remove('show'); }, 3000);
    }

    window.modOrdUpdateQty = function (item_id, value) {
        var fd = new FormData();
        fd.append('item_id', item_id);
        fd.append('qty', value);
        fetch('/order.php?api=update_item', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) window.location.reload();
                else toast(j.error || 'Грешка', 'error');
            });
    };
    window.modOrdRemove = function (item_id) {
        if (!confirm('Премахни този ред?')) return;
        var fd = new FormData();
        fd.append('item_id', item_id);
        fetch('/order.php?api=remove_item', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function () { window.location.reload(); });
    };
    window.modOrdAddPick = function () {
        // TODO: пълен product picker. Засега redirect to products.php с return_to.
        window.location = '/products.php?action=pick&return_to=order&order_id=' + ORDER_ID;
    };
    window.modOrdSend = function () {
        var fd = new FormData();
        fd.append('order_id', ORDER_ID);
        fetch('/order.php?api=send', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) {
                    $('modOrdSendText').textContent = j.copy_paste_text || '';
                    $('modOrdSendOverlay').classList.add('open');
                } else {
                    toast(j.error || 'Грешка', 'error');
                }
            });
    };
    window.modOrdCloseModal = function () {
        $('modOrdSendOverlay').classList.remove('open');
        setTimeout(function () { window.location.reload(); }, 300);
    };
    window.modOrdCopyText = function () {
        var txt = $('modOrdSendText').textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(function () { toast('Копирано'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = txt; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); toast('Копирано'); } catch (e) { toast('Не можах да копирам', 'error'); }
            document.body.removeChild(ta);
        }
    };
    window.modOrdCancel = function () {
        if (!confirm('Сигурен ли си че искаш да отмениш?')) return;
        var fd = new FormData();
        fd.append('order_id', ORDER_ID);
        fetch('/order.php?api=cancel', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function () { window.location = '/orders.php'; });
    };
    window.modOrdMarkReceived = function () {
        var fd = new FormData();
        fd.append('order_id', ORDER_ID);
        fetch('/order.php?api=mark_received', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) {
                    toast('Маркирана като получена');
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    toast(j.error || 'Грешка', 'error');
                }
            });
    };
})();
</script>
</body>
</html>
