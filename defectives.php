<?php
/**
 * defectives.php — Supplier defectives pool (само Detailed Mode).
 *
 * Spec: DELIVERY_ORDERS_DECISIONS_FINAL §E (E1-E7)
 *
 * Закон №10: Simple Mode (Пешо) НЕ вижда този модул като UI element. AI го пита
 * проактивно при доставка („Има ли нещо счупено?") и при плащане
 * („имаш €68 кредит — приспадаме ли?"). Ако seller отвори този URL — redirect.
 *
 * Action бутони: [Върни всички към Marina] [Отпиши като загуба]
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
$mode     = ($role === 'seller') ? 'simple' : ($user['ui_mode'] ?: 'simple');

// Закон №10 — Simple Mode не отваря direct
if ($mode === 'simple') {
    header('Location: /chat.php?q=' . urlencode('имам ли дефектни?'));
    exit;
}

$api = $_GET['api'] ?? '';
if ($api && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($api) {
            case 'return_all':  echo json_encode(api_return_all($tenant_id, $user_id)); break;
            case 'write_off':   echo json_encode(api_write_off($tenant_id, $user_id)); break;
            case 'apply_credit':echo json_encode(api_apply_credit($tenant_id, $user_id)); break;
            default:
                http_response_code(400); echo json_encode(['ok' => false, 'error' => 'unknown api']);
        }
    } catch (Throwable $e) {
        error_log('defectives.php api ' . $api . ': ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Group pending defectives by supplier
$grouped = $pdo->prepare("
    SELECT sd.supplier_id, s.name AS supplier_name, s.email, s.phone,
           COUNT(*) AS line_count,
           SUM(sd.quantity) AS total_qty,
           SUM(sd.total_cost) AS total_value,
           MAX(sd.created_at) AS last_at
    FROM supplier_defectives sd
    LEFT JOIN suppliers s ON s.id = sd.supplier_id
    WHERE sd.tenant_id = ? AND sd.status = 'pending'
    GROUP BY sd.supplier_id, s.name, s.email, s.phone
    ORDER BY total_value DESC
");
$grouped->execute([$tenant_id]);
$grouped = $grouped->fetchAll(PDO::FETCH_ASSOC);

$details = $pdo->prepare("
    SELECT sd.*, p.name AS product_name, p.code AS product_code
    FROM supplier_defectives sd
    LEFT JOIN products p ON p.id = sd.product_id
    WHERE sd.tenant_id = ? AND sd.status = 'pending'
    ORDER BY sd.supplier_id, sd.created_at
");
$details->execute([$tenant_id]);
$details = $details->fetchAll(PDO::FETCH_ASSOC);

$by_supplier = [];
foreach ($details as $d) {
    $sid = (int)$d['supplier_id'];
    $by_supplier[$sid][] = $d;
}

// Resolved historical (last 30 days)
$resolved = $pdo->prepare("
    SELECT sd.status, COUNT(*) AS line_count, SUM(sd.total_cost) AS total_value,
           s.name AS supplier_name
    FROM supplier_defectives sd
    LEFT JOIN suppliers s ON s.id = sd.supplier_id
    WHERE sd.tenant_id = ?
      AND sd.status IN ('returned','written_off','credited','resolved')
      AND sd.resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY sd.status, sd.supplier_id, s.name
    ORDER BY sd.status, total_value DESC
    LIMIT 20
");
$resolved->execute([$tenant_id]);
$resolved = $resolved->fetchAll(PDO::FETCH_ASSOC);

// API impls
function api_return_all(int $tenant_id, int $user_id): array {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    if ($supplier_id <= 0) return ['ok' => false, 'error' => 'missing supplier'];

    $rows = DB::run("
        SELECT sd.*, p.name AS product_name
        FROM supplier_defectives sd
        LEFT JOIN products p ON p.id = sd.product_id
        WHERE sd.tenant_id = ? AND sd.supplier_id = ? AND sd.status = 'pending'
    ", [$tenant_id, $supplier_id])->fetchAll();

    if (empty($rows)) return ['ok' => false, 'error' => 'няма pending'];

    $supplier = DB::run("SELECT name FROM suppliers WHERE id = ?", [$supplier_id])->fetch();
    $sup_name = $supplier['name'] ?? 'доставчика';

    DB::tx(function (PDO $pdo) use ($rows, $user_id, $tenant_id) {
        foreach ($rows as $r) {
            $pdo->prepare("UPDATE supplier_defectives SET status='returned', resolved_by=?, resolved_at=NOW() WHERE id=?")
                ->execute([$user_id, (int)$r['id']]);

            $pdo->prepare("INSERT INTO delivery_events (tenant_id, store_id, delivery_id, user_id, event_type, payload)
                           VALUES (?, ?, ?, ?, 'defective_returned', ?)")
                ->execute([
                    $tenant_id, (int)$r['store_id'], (int)$r['delivery_id'], $user_id,
                    json_encode(['defective_id' => (int)$r['id'], 'qty' => $r['quantity'], 'value' => $r['total_cost']]),
                ]);
        }
    });

    // Generate Viber/email текст
    $lines = ['Здравейте,', '', 'Връщам следните артикули (дефектни):'];
    foreach ($rows as $i => $r) {
        $name = $r['product_name'] ?: 'артикул';
        $qty = rtrim(rtrim(number_format((float)$r['quantity'], 2, '.', ''), '0'), '.');
        $lines[] = ($i + 1) . '. ' . $name . ' — ' . $qty . ' бр (причина: ' . $r['reason'] . ')';
    }
    $total_value = array_sum(array_map(fn($r) => (float)$r['total_cost'], $rows));
    $lines[] = '';
    $lines[] = 'Обща стойност: ' . number_format($total_value, 2, '.', ' ') . ' €';
    $lines[] = 'Моля приспаднете при следваща фактура.';
    $lines[] = '';
    $lines[] = 'Благодаря.';

    return [
        'ok' => true,
        'message' => 'Маркирани като върнати към ' . $sup_name,
        'copy_paste_text' => implode("\n", $lines),
    ];
}

function api_write_off(int $tenant_id, int $user_id): array {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $defective_id = (int)($_POST['defective_id'] ?? 0);

    if ($defective_id > 0) {
        DB::run("UPDATE supplier_defectives SET status='written_off', resolved_by=?, resolved_at=NOW()
                 WHERE id=? AND tenant_id=? AND status='pending'", [$user_id, $defective_id, $tenant_id]);
    } elseif ($supplier_id > 0) {
        DB::run("UPDATE supplier_defectives SET status='written_off', resolved_by=?, resolved_at=NOW()
                 WHERE supplier_id=? AND tenant_id=? AND status='pending'", [$user_id, $supplier_id, $tenant_id]);
    } else {
        return ['ok' => false, 'error' => 'missing'];
    }
    return ['ok' => true, 'message' => 'Отписани като загуба'];
}

function api_apply_credit(int $tenant_id, int $user_id): array {
    return ['ok' => false, 'error' => 'apply_credit requires payments module (not yet implemented)'];
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Дефектни — RunMyStore.ai</title>
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
.mod-defect-supplier{margin-bottom:14px}
.mod-defect-supplier-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-radius:12px 12px 0 0}
.mod-defect-supplier-name{font-size:14px;font-weight:900;color:#f1f5f9}
.mod-defect-supplier-stats{display:flex;gap:10px;align-items:center}
.mod-defect-stat{text-align:right}
.mod-defect-stat-val{font-size:14px;font-weight:900;color:#fca5a5;font-variant-numeric:tabular-nums;line-height:1}
.mod-defect-stat-lbl{font-size:8px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}

.mod-defect-line{display:flex;align-items:center;gap:10px;padding:9px 14px;border-left:1px solid rgba(239,68,68,.15);border-right:1px solid rgba(239,68,68,.15);border-bottom:1px dashed rgba(255,255,255,.06);background:rgba(239,68,68,.02)}
.mod-defect-line:last-of-type{border-bottom:1px solid rgba(239,68,68,.25)}
.mod-defect-line-body{flex:1;min-width:0}
.mod-defect-line-name{font-size:12px;font-weight:700;color:#f1f5f9;line-height:1.2;text-overflow:ellipsis;overflow:hidden;white-space:nowrap}
.mod-defect-line-meta{font-size:10px;font-weight:600;color:rgba(255,255,255,.5);margin-top:2px}
.mod-defect-line-amt{font-size:12px;font-weight:800;color:#fca5a5;font-variant-numeric:tabular-nums;text-align:right;flex-shrink:0;line-height:1}

.mod-defect-actions{display:flex;gap:6px;padding:10px 14px;background:rgba(239,68,68,.04);border-radius:0 0 12px 12px;border-left:1px solid rgba(239,68,68,.25);border-right:1px solid rgba(239,68,68,.25);border-bottom:1px solid rgba(239,68,68,.25)}
.mod-defect-btn{flex:1;padding:10px;border-radius:8px;font-size:11px;font-weight:800;font-family:inherit;cursor:pointer;border:none;color:#fff;letter-spacing:.02em}
.mod-defect-btn.return{background:linear-gradient(135deg,hsl(38 75% 48%),hsl(28 75% 42%));box-shadow:0 0 8px hsl(38 75% 50% / .35)}
.mod-defect-btn.write-off{background:rgba(255,255,255,.08);color:#cbd5e1;border:1px solid rgba(255,255,255,.12)}

.mod-defect-sec-label{font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:hsl(255 50% 70%);margin:14px 4px 8px}

.mod-defect-empty{text-align:center;padding:40px 20px}
.mod-defect-empty-ico{font-size:32px;color:hsl(145 65% 55%);margin-bottom:12px}
.mod-defect-empty-title{font-size:14px;font-weight:800;color:#f1f5f9;margin-bottom:6px}
.mod-defect-empty-sub{font-size:11px;font-weight:600;color:rgba(255,255,255,.5)}

.mod-defect-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;opacity:0;pointer-events:none;transition:opacity .2s;display:flex;align-items:center;justify-content:center;padding:20px}
.mod-defect-overlay.open{opacity:1;pointer-events:auto}
.mod-defect-modal{background:rgba(8,9,13,.98);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:18px;max-width:420px;width:100%;max-height:80vh;overflow-y:auto}
.mod-defect-modal h3{font-size:14px;font-weight:900;color:#f1f5f9;margin-bottom:12px}
.mod-defect-modal pre{font-family:'Courier New',monospace;font-size:12px;color:#e0e0e0;background:rgba(255,255,255,.04);padding:10px;border-radius:8px;white-space:pre-wrap;word-break:break-word;max-height:50vh;overflow-y:auto}

.mod-defect-toast{position:fixed;left:16px;right:16px;bottom:80px;z-index:300;padding:12px 16px;border-radius:12px;background:rgba(8,9,13,.95);border:1px solid rgba(34,197,94,.5);color:#86efac;font-size:13px;font-weight:800;transform:translateY(120%);transition:transform .3s}
.mod-defect-toast.show{transform:translateY(0)}
.mod-defect-toast.error{border-color:rgba(239,68,68,.6);color:#fca5a5}
</style>
</head>
<body class="has-rms-shell mode-<?= htmlspecialchars($mode) ?>">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="app">

<div class="mod-defect-sec-label">Дефектни към доставчиците</div>

<?php if (empty($grouped)): ?>
    <div class="glass q3" style="padding:24px;text-align:center">
        <span class="shine"></span><span class="glow"></span>
        <div class="mod-defect-empty">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="hsl(145 65% 55%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px"><polyline points="20 6 9 17 4 12"/></svg>
            <div class="mod-defect-empty-title">Няма pending дефектни</div>
            <div class="mod-defect-empty-sub">Когато при доставка отбелязваш ред като счупен/дефектен, той влиза тук.</div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $g): $sid = (int)$g['supplier_id']; $lines = $by_supplier[$sid] ?? []; ?>
    <div class="mod-defect-supplier" data-supplier-id="<?= $sid ?>">
        <div class="mod-defect-supplier-head">
            <div class="mod-defect-supplier-name">
                <?= htmlspecialchars($g['supplier_name'] ?: 'Без доставчик') ?>
            </div>
            <div class="mod-defect-supplier-stats">
                <div class="mod-defect-stat">
                    <div class="mod-defect-stat-val"><?= rtrim(rtrim(number_format((float)$g['total_qty'], 2, '.', ''), '0'), '.') ?> бр</div>
                    <div class="mod-defect-stat-lbl">общо</div>
                </div>
                <div class="mod-defect-stat">
                    <div class="mod-defect-stat-val"><?= fmtMoney((float)$g['total_value'], $currency) ?></div>
                    <div class="mod-defect-stat-lbl">стойност</div>
                </div>
            </div>
        </div>

        <?php foreach ($lines as $ln): ?>
        <div class="mod-defect-line">
            <div class="mod-defect-line-body">
                <div class="mod-defect-line-name"><?= htmlspecialchars($ln['product_name'] ?: 'Артикул #' . $ln['product_id']) ?></div>
                <div class="mod-defect-line-meta">
                    <?= rtrim(rtrim(number_format((float)$ln['quantity'], 2, '.', ''), '0'), '.') ?> бр
                    · <?= htmlspecialchars($ln['reason']) ?>
                    · <?= date('d.m', strtotime((string)$ln['created_at'])) ?>
                </div>
            </div>
            <div class="mod-defect-line-amt"><?= fmtMoney((float)$ln['total_cost'], $currency) ?></div>
        </div>
        <?php endforeach; ?>

        <div class="mod-defect-actions">
            <button class="mod-defect-btn return" type="button" onclick="modDefectReturn(<?= $sid ?>, '<?= htmlspecialchars(addslashes((string)$g['supplier_name']), ENT_QUOTES) ?>')">
                Върни всички към <?= htmlspecialchars((string)($g['supplier_name'] ?: 'доставчика')) ?>
            </button>
            <button class="mod-defect-btn write-off" type="button" onclick="modDefectWriteOff(<?= $sid ?>)">Отпиши</button>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($resolved)): ?>
<div class="mod-defect-sec-label" style="margin-top:24px">Последни 30 дни — резолвирани</div>
<div class="glass qd" style="padding:14px">
    <span class="shine"></span><span class="glow"></span>
    <?php foreach ($resolved as $r): ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:11px;font-weight:700;color:rgba(255,255,255,.75);border-bottom:1px dashed rgba(255,255,255,.06)">
        <span><?= htmlspecialchars($r['supplier_name'] ?: 'Без') ?> · <?= htmlspecialchars($r['status']) ?></span>
        <span style="font-variant-numeric:tabular-nums"><?= (int)$r['line_count'] ?> бр · <?= fmtMoney((float)$r['total_value'], $currency) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</main>

<div class="mod-defect-overlay" id="modDefectOverlay" onclick="if(event.target===this) modDefectCloseModal()">
    <div class="mod-defect-modal" onclick="event.stopPropagation()">
        <h3 id="modDefectModalTitle">Готово</h3>
        <div style="font-size:11px;color:rgba(255,255,255,.6);margin-bottom:8px">Копирай и изпрати на доставчика:</div>
        <pre id="modDefectModalText"></pre>
        <div style="display:flex;gap:8px;margin-top:12px">
            <button class="mod-defect-btn return" type="button" onclick="modDefectCopyText()">Копирай</button>
            <button class="mod-defect-btn write-off" type="button" onclick="modDefectCloseModal()">Затвори</button>
        </div>
    </div>
</div>

<div class="mod-defect-toast" id="modDefectToast"></div>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
<script>
(function () {
    function $(id) { return document.getElementById(id); }
    function toast(msg, kind) {
        var t = $('modDefectToast');
        t.textContent = msg;
        t.className = 'mod-defect-toast' + (kind ? ' ' + kind : '') + ' show';
        setTimeout(function () { t.classList.remove('show'); }, 3000);
    }

    window.modDefectReturn = function (supplier_id, supplier_name) {
        if (!confirm('Маркирай всички дефектни от ' + (supplier_name || 'доставчика') + ' като върнати?')) return;
        var fd = new FormData();
        fd.append('supplier_id', supplier_id);
        fetch('/defectives.php?api=return_all', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) {
                    $('modDefectModalTitle').textContent = j.message || 'Готово';
                    $('modDefectModalText').textContent = j.copy_paste_text || '';
                    $('modDefectOverlay').classList.add('open');
                } else {
                    toast(j.error || 'Грешка', 'error');
                }
            });
    };
    window.modDefectWriteOff = function (supplier_id) {
        if (!confirm('Отписвам всички pending като загуба. Сигурен?')) return;
        var fd = new FormData();
        fd.append('supplier_id', supplier_id);
        fetch('/defectives.php?api=write_off', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) { toast(j.message || 'Отписани'); setTimeout(function () { window.location.reload(); }, 800); }
                else { toast(j.error || 'Грешка', 'error'); }
            });
    };
    window.modDefectCopyText = function () {
        var txt = $('modDefectModalText').textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(function () { toast('Копирано'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = txt; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); toast('Копирано'); } catch (e) {}
            document.body.removeChild(ta);
        }
    };
    window.modDefectCloseModal = function () {
        $('modDefectOverlay').classList.remove('open');
        setTimeout(function () { window.location.reload(); }, 300);
    };
})();
</script>
</body>
</html>
