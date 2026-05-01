<?php
/**
 * deliveries.php — Главен hub за модул Доставки.
 *
 * Simple Mode (Пешо): briefing cards (6 fundamental въпроса) + последни доставки.
 * Detailed Mode (Митко): KPI grid 4 cells + tabs filter + supplier reliability +
 *                        cost variance graphs + secondary tiles (defectives, payments).
 *
 * Spec: DELIVERY_ORDERS_DECISIONS_FINAL §K, §L
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
    SELECT u.name, u.role, u.store_id, t.currency, t.language, t.ui_mode, t.plan, t.plan_effective, t.trial_ends_at
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

$plan_effective = effectivePlan($user);

// ─────────────────────────────────────────────────────────────────────
// DATA: последни доставки (10) + payment status
// ─────────────────────────────────────────────────────────────────────
$recent = $pdo->prepare("
    SELECT d.*, s.name AS supplier_name,
           (SELECT COUNT(*) FROM delivery_items di WHERE di.delivery_id = d.id) AS item_count,
           CASE
             WHEN d.payment_status = 'paid' THEN 'paid'
             WHEN d.payment_due_date IS NULL THEN 'paid'
             WHEN d.payment_due_date < CURDATE() THEN 'over'
             ELSE 'due'
           END AS pay_state,
           DATEDIFF(d.payment_due_date, CURDATE()) AS days_to_due
    FROM deliveries d
    LEFT JOIN suppliers s ON s.id = d.supplier_id
    WHERE d.tenant_id = ?
      AND d.status NOT IN ('voided','superseded')
    ORDER BY d.created_at DESC
    LIMIT 10
");
$recent->execute([$tenant_id]);
$recent = $recent->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$kpi_week = (float)$pdo->prepare("
    SELECT COALESCE(SUM(total), 0) FROM deliveries
    WHERE tenant_id = ? AND status = 'committed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->execute([$tenant_id]) ? null : null;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM deliveries WHERE tenant_id=? AND status='committed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$tenant_id]);
$kpi_week = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM deliveries WHERE tenant_id=? AND status='committed' AND YEAR(created_at) = YEAR(NOW())");
$stmt->execute([$tenant_id]);
$kpi_year = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM deliveries WHERE tenant_id=? AND has_mismatch=1 AND status NOT IN ('voided','superseded')");
$stmt->execute([$tenant_id]);
$kpi_mismatches = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM supplier_defectives WHERE tenant_id=? AND status='pending'");
$stmt->execute([$tenant_id]);
$kpi_defectives_value = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier_defectives WHERE tenant_id=? AND status='pending'");
$stmt->execute([$tenant_id]);
$kpi_defectives_count = (int)$stmt->fetchColumn();

// AI insights за life-board cards (6 fundamental въпроса)
$insights = $pdo->prepare("
    SELECT * FROM ai_insights
    WHERE tenant_id = ? AND module IN ('warehouse','home') AND fundamental_question IS NOT NULL
      AND (expires_at IS NULL OR expires_at > NOW())
    ORDER BY FIELD(urgency,'critical','warning','info','passive'), created_at DESC
    LIMIT 12
");
$insights->execute([$tenant_id]);
$insights = $insights->fetchAll(PDO::FETCH_ASSOC);

// Group insights by FQ for briefing cards
$by_fq = ['loss' => [], 'loss_cause' => [], 'gain' => [], 'gain_cause' => [], 'order' => [], 'anti_order' => []];
foreach ($insights as $ins) {
    $fq = $ins['fundamental_question'];
    if (isset($by_fq[$fq])) $by_fq[$fq][] = $ins;
}

// Detailed Mode: filter tab
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'mismatch') {
    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS supplier_name FROM deliveries d
        LEFT JOIN suppliers s ON s.id = d.supplier_id
        WHERE d.tenant_id = ? AND d.has_mismatch = 1 AND d.status NOT IN ('voided','superseded')
        ORDER BY d.created_at DESC LIMIT 30
    ");
    $stmt->execute([$tenant_id]);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($filter === 'unpaid') {
    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS supplier_name FROM deliveries d
        LEFT JOIN suppliers s ON s.id = d.supplier_id
        WHERE d.tenant_id = ? AND d.payment_status IN ('unpaid','partially_paid') AND d.status='committed'
        ORDER BY d.payment_due_date ASC LIMIT 30
    ");
    $stmt->execute([$tenant_id]);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($filter === 'reviewing') {
    $stmt = $pdo->prepare("
        SELECT d.*, s.name AS supplier_name FROM deliveries d
        LEFT JOIN suppliers s ON s.id = d.supplier_id
        WHERE d.tenant_id = ? AND d.status IN ('draft','reviewing','pending')
        ORDER BY d.created_at DESC LIMIT 30
    ");
    $stmt->execute([$tenant_id]);
    $filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $filtered = $recent;
}

// Detailed: supplier reliability
$reliability = $pdo->prepare("
    SELECT s.id, s.name, s.reliability_score, COUNT(d.id) AS delivery_count
    FROM suppliers s
    LEFT JOIN deliveries d ON d.supplier_id = s.id AND d.status = 'committed' AND d.tenant_id = s.tenant_id
    WHERE s.tenant_id = ? AND s.is_active = 1
    GROUP BY s.id, s.name, s.reliability_score
    HAVING delivery_count > 0
    ORDER BY s.reliability_score DESC, delivery_count DESC
    LIMIT 5
");
$reliability->execute([$tenant_id]);
$reliability = $reliability->fetchAll(PDO::FETCH_ASSOC);

function fmtMoney(float $v, string $currency = 'EUR'): string {
    $sym = $currency === 'EUR' ? '€' : ($currency === 'BGN' ? 'лв' : $currency);
    return number_format($v, 2, '.', ' ') . ' ' . $sym;
}

function payState(array $d): string {
    if ($d['payment_status'] === 'paid' || empty($d['payment_due_date'])) return 'paid';
    if (strtotime((string)$d['payment_due_date']) < time()) return 'over';
    return 'due';
}

function payLabel(array $d, string $state): string {
    if ($state === 'paid') return 'платена';
    if ($state === 'over') {
        $days = max(1, (int)floor((time() - strtotime((string)$d['payment_due_date'])) / 86400));
        return 'просрочена ' . $days . 'д';
    }
    return 'до ' . date('d.m', strtotime((string)$d['payment_due_date']));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Доставки — RunMyStore.ai</title>
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
.mod-del-hero-cta{display:flex;align-items:center;gap:12px;padding:16px;cursor:pointer;text-decoration:none;color:inherit}
.mod-del-hero-cta-ico{
    width:48px;height:48px;border-radius:14px;flex-shrink:0;
    background:linear-gradient(135deg,hsl(38 75% 52%),hsl(28 75% 46%));
    box-shadow:0 0 16px hsl(38 75% 50% / .5),inset 0 1px 0 rgba(255,255,255,.25);
    display:flex;align-items:center;justify-content:center;color:#fff
}
.mod-del-hero-cta-ico svg{width:22px;height:22px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.mod-del-hero-cta-text{flex:1;min-width:0}
.mod-del-hero-cta-title{font-size:15px;font-weight:900;color:#f1f5f9;letter-spacing:-.01em;line-height:1.2}
.mod-del-hero-cta-sub{font-size:10px;font-weight:600;color:rgba(255,255,255,.5);margin-top:3px}
.mod-del-hero-cta-arr{color:hsl(38 80% 70%);font-size:18px;font-weight:900;flex-shrink:0}

.mod-del-quick-row{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:12px;background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.06);cursor:pointer;margin-bottom:6px;text-decoration:none;color:inherit}
.mod-del-quick-ico{width:30px;height:30px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff}
.mod-del-quick-ico.ok{background:linear-gradient(135deg,hsl(145 65% 42%),hsl(160 65% 36%));box-shadow:0 0 8px hsl(145 65% 45% / .35)}
.mod-del-quick-ico.due{background:linear-gradient(135deg,hsl(38 75% 48%),hsl(28 75% 40%));box-shadow:0 0 8px hsl(38 75% 50% / .35)}
.mod-del-quick-ico.over{background:linear-gradient(135deg,hsl(0 70% 48%),hsl(15 70% 40%));box-shadow:0 0 8px hsl(0 75% 50% / .35)}
.mod-del-quick-ico svg{width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none;stroke-linecap:round;stroke-linejoin:round}
.mod-del-quick-body{flex:1;min-width:0}
.mod-del-quick-name{font-size:12px;font-weight:800;color:#f1f5f9;line-height:1.2}
.mod-del-quick-meta{font-size:9px;font-weight:600;color:rgba(255,255,255,.4);margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;letter-spacing:.02em}
.mod-del-pay{display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:100px;font-size:8px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}
.mod-del-pay.paid{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac}
.mod-del-pay.due{background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.32);color:#fbbf24}
.mod-del-pay.over{background:rgba(239,68,68,.16);border:1px solid rgba(239,68,68,.4);color:#fca5a5;animation:modDelPulse 2s ease-in-out infinite}
@keyframes modDelPulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 8px 0 rgba(239,68,68,.55)}}
.mod-del-quick-amt{font-size:13px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums;text-align:right;flex-shrink:0;line-height:1}
.mod-del-quick-amt small{display:block;font-size:8px;font-weight:700;color:rgba(255,255,255,.4);letter-spacing:.06em;margin-top:3px;text-transform:uppercase}

.mod-del-see-all{display:block;text-align:center;padding:10px;font-size:10px;font-weight:800;color:hsl(255 60% 78%);letter-spacing:.08em;text-transform:uppercase;background:transparent;border:none;font-family:inherit;cursor:pointer;text-decoration:none;width:100%;margin-top:4px}

.mod-del-sec-label{font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:hsl(255 50% 70%);text-shadow:0 0 12px hsl(255 70% 60% / .25);margin:14px 4px 8px}

/* DETAILED MODE specific */
.mod-del-kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
.mod-del-kpi{padding:14px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}
.mod-del-kpi-label{font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em}
.mod-del-kpi-val{font-size:20px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums;margin-top:4px;line-height:1}
.mod-del-kpi-sub{font-size:9px;font-weight:600;color:rgba(255,255,255,.4);margin-top:3px}
.mod-del-kpi.warn .mod-del-kpi-val{color:#fbbf24}
.mod-del-kpi.danger .mod-del-kpi-val{color:#fca5a5}

.mod-del-tabs{display:flex;gap:4px;margin-bottom:10px;overflow-x:auto;padding:2px}
.mod-del-tab{padding:7px 12px;border-radius:8px;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.7);cursor:pointer;text-decoration:none;flex-shrink:0;font-family:inherit}
.mod-del-tab.active{background:rgba(165,180,252,.14);border-color:rgba(165,180,252,.3);color:#c7d2fe}

.mod-del-rel-row{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px dashed rgba(255,255,255,.06);font-size:11px;font-weight:700;color:#f1f5f9}
.mod-del-rel-row:last-child{border-bottom:none}
.mod-del-rel-bar{width:80px;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden;flex-shrink:0}
.mod-del-rel-fill{height:100%;background:linear-gradient(90deg,hsl(0 70% 50%),hsl(38 75% 55%),hsl(145 65% 50%));transition:width .3s}

.mode-simple .mod-del-detail-only{display:none}
.mode-detailed .mod-del-simple-only{display:none}
</style>
</head>
<body class="has-rms-shell mode-<?= htmlspecialchars($mode) ?>">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="app">

    <!-- 1. HERO CTA — Нова доставка (винаги видим) -->
    <a class="glass q4 mod-del-hero-cta card-stagger" href="/delivery.php?action=new">
        <span class="shine"></span>
        <span class="shine shine-bottom"></span>
        <span class="glow"></span>
        <span class="glow glow-bottom"></span>
        <div class="mod-del-hero-cta-ico">
            <svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"/><circle cx="12" cy="13" r="3"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </div>
        <div class="mod-del-hero-cta-text">
            <div class="mod-del-hero-cta-title">Нова доставка</div>
            <div class="mod-del-hero-cta-sub">снимай фактурата или говори</div>
        </div>
        <div class="mod-del-hero-cta-arr">›</div>
    </a>

    <!-- DETAILED MODE — KPI grid (4 cells) -->
    <div class="mod-del-detail-only">
        <div class="mod-del-kpi-grid">
            <div class="mod-del-kpi">
                <div class="mod-del-kpi-label">Седмица</div>
                <div class="mod-del-kpi-val"><?= fmtMoney($kpi_week, $currency) ?></div>
                <div class="mod-del-kpi-sub">последни 7 дни</div>
            </div>
            <div class="mod-del-kpi">
                <div class="mod-del-kpi-label">Година</div>
                <div class="mod-del-kpi-val"><?= number_format($kpi_year, 0, '.', ' ') ?> <?= $currency === 'EUR' ? '€' : 'лв' ?></div>
                <div class="mod-del-kpi-sub">общо <?= date('Y') ?></div>
            </div>
            <div class="mod-del-kpi <?= $kpi_mismatches > 0 ? 'warn' : '' ?>">
                <div class="mod-del-kpi-label">Разлики</div>
                <div class="mod-del-kpi-val"><?= $kpi_mismatches ?></div>
                <div class="mod-del-kpi-sub">нерезолвирани</div>
            </div>
            <div class="mod-del-kpi <?= $kpi_defectives_count > 0 ? 'danger' : '' ?>">
                <div class="mod-del-kpi-label">Дефектни</div>
                <div class="mod-del-kpi-val"><?= $kpi_defectives_count ?></div>
                <div class="mod-del-kpi-sub"><?= fmtMoney($kpi_defectives_value, $currency) ?> към връщане</div>
            </div>
        </div>

        <!-- Tabs filter -->
        <div class="mod-del-tabs">
            <a class="mod-del-tab <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">Всички</a>
            <a class="mod-del-tab <?= $filter === 'mismatch' ? 'active' : '' ?>" href="?filter=mismatch">С разлика</a>
            <a class="mod-del-tab <?= $filter === 'unpaid' ? 'active' : '' ?>" href="?filter=unpaid">Чакащи плащане</a>
            <a class="mod-del-tab <?= $filter === 'reviewing' ? 'active' : '' ?>" href="?filter=reviewing">В прегледи</a>
        </div>
    </div>

    <!-- SIMPLE MODE — payment proactive cue (lb-card.q2 виолет) -->
    <?php
    // Find next-due unpaid за proactive insight
    $next_due = null;
    foreach ($recent as $r) {
        if ($r['payment_status'] !== 'paid' && !empty($r['payment_due_date'])) {
            if (!$next_due || strtotime($r['payment_due_date']) < strtotime($next_due['payment_due_date'])) {
                $next_due = $r;
            }
        }
    }
    if ($next_due && $mode === 'simple'):
        $days = (int)$next_due['days_to_due'];
    ?>
    <div class="lb-card glass q2 card-stagger mod-del-simple-only">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="lb-top">
            <div class="lb-fq-tag">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= $days < 0 ? 'Просрочено плащане' : 'Плащане наближава' ?>
            </div>
        </div>
        <div class="lb-card-title"><?= htmlspecialchars($next_due['supplier_name'] ?: 'Доставчик') ?> · <?= fmtMoney((float)$next_due['total'], $currency) ?></div>
        <div class="lb-body">
            <?php if ($days < 0): ?>
                Просрочено с <b><?= abs($days) ?> дни</b> — обади се незабавно.
            <?php elseif ($days === 0): ?>
                Падежът е <b>днес</b>.
            <?php else: ?>
                Падежът е <b><?= date('d.m', strtotime((string)$next_due['payment_due_date'])) ?></b> — остават <?= $days ?> дни.
            <?php endif; ?>
        </div>
        <div class="lb-actions">
            <a class="lb-action" href="/chat.php?q=<?= urlencode('плащане ' . $next_due['supplier_name']) ?>">Питай повече</a>
            <a class="lb-action primary" href="/delivery.php?id=<?= (int)$next_due['id'] ?>">Виж доставката</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6 СИГНАЛНИ КАРТИ — fundamental questions (само Simple Mode) -->
    <?php if ($mode === 'simple' && !empty(array_filter($by_fq))): ?>
    <div class="mod-del-sec-label mod-del-simple-only">Какво казват доставките</div>

    <?php
    $fq_meta = [
        'loss'       => ['q' => 'q1', 'name' => 'КАКВО ГУБЯ',     'emoji' => '▼'],
        'loss_cause' => ['q' => 'q2', 'name' => 'ОТ КАКВО',        'emoji' => '●'],
        'gain'       => ['q' => 'q3', 'name' => 'КАКВО ПЕЧЕЛЯ',    'emoji' => '▲'],
        'gain_cause' => ['q' => 'q4', 'name' => 'ОТ КАКВО',        'emoji' => '●'],
        'order'      => ['q' => 'q5', 'name' => 'КАКВО ДА ПОРЪЧАМ', 'emoji' => '►'],
        'anti_order' => ['q' => 'q6', 'name' => 'НЕ ПОРЪЧВАЙ',     'emoji' => '×'],
    ];
    foreach ($fq_meta as $fq => $meta):
        $card = $by_fq[$fq][0] ?? null;
        if (!$card) continue;
    ?>
    <div class="briefing-section <?= $meta['q'] ?> card-stagger mod-del-simple-only">
        <div class="briefing-head">
            <span class="briefing-emoji" aria-hidden="true"><?= $meta['emoji'] ?></span>
            <span class="briefing-name"><?= $meta['name'] ?></span>
        </div>
        <div class="briefing-title"><?= htmlspecialchars((string)$card['title']) ?></div>
        <?php if (!empty($card['detail_text'])): ?>
        <div class="briefing-detail"><?= htmlspecialchars((string)$card['detail_text']) ?></div>
        <?php endif; ?>
        <?php if (!empty($card['action_label'])): ?>
        <div class="briefing-actions">
            <a class="briefing-btn-primary" href="<?= htmlspecialchars((string)($card['action_url'] ?: '/chat.php')) ?>">
                <?= htmlspecialchars((string)$card['action_label']) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- DETAILED MODE — supplier reliability -->
    <?php if (!empty($reliability)): ?>
    <div class="mod-del-detail-only">
        <div class="mod-del-sec-label">Надеждност на доставчиците</div>
        <div class="glass qd" style="padding:6px 10px">
            <span class="shine"></span><span class="glow"></span>
            <?php foreach ($reliability as $r): $score = (int)($r['reliability_score'] ?? 0); ?>
            <div class="mod-del-rel-row">
                <span style="flex:1;text-overflow:ellipsis;overflow:hidden"><?= htmlspecialchars($r['name']) ?></span>
                <div class="mod-del-rel-bar"><div class="mod-del-rel-fill" style="width:<?= $score ?>%"></div></div>
                <span style="width:36px;text-align:right;font-variant-numeric:tabular-nums;font-size:11px"><?= $score ? $score . '%' : '—' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- LIST: последни доставки -->
    <div class="mod-del-sec-label">
        <?= $filter === 'all' ? 'Последни доставки' : 'Филтрирани' ?>
    </div>

    <?php if (empty($filtered)): ?>
        <div class="glass qd" style="padding:24px;text-align:center">
            <span class="shine"></span><span class="glow"></span>
            <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-bottom:6px">Все още няма доставки</div>
            <div style="font-size:11px;color:rgba(255,255,255,.5)">Снимай първата фактура с „Нова доставка".</div>
        </div>
    <?php else: ?>
        <?php foreach ($filtered as $d):
            $state = payState($d);
            $ico_svg = $state === 'paid'
                ? '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>'
                : ($state === 'over'
                    ? '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                    : '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>');
        ?>
        <a class="mod-del-quick-row card-stagger" href="/delivery.php?id=<?= (int)$d['id'] ?>">
            <div class="mod-del-quick-ico <?= $state ?>"><?= $ico_svg ?></div>
            <div class="mod-del-quick-body">
                <div class="mod-del-quick-name"><?= htmlspecialchars($d['supplier_name'] ?: 'Без доставчик') ?></div>
                <div class="mod-del-quick-meta">
                    <span><?= date('d.m', strtotime((string)$d['created_at'])) ?> · <?= (int)$d['item_count'] ?> арт</span>
                    <span class="mod-del-pay <?= $state ?>"><?= htmlspecialchars(payLabel($d, $state)) ?></span>
                </div>
            </div>
            <div class="mod-del-quick-amt"><?= number_format((float)$d['total'], 0, '.', ' ') ?><small><?= $currency === 'EUR' ? '€' : 'лв' ?></small></div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- DETAILED MODE — secondary tiles -->
    <div class="mod-del-detail-only">
        <div class="mod-del-sec-label">Свързани</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <a class="glass q1" href="/defectives.php" style="padding:14px;text-decoration:none;color:inherit;display:block">
                <span class="shine"></span><span class="glow"></span>
                <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em">Дефектни</div>
                <div style="font-size:18px;font-weight:900;color:#fca5a5;font-variant-numeric:tabular-nums;margin-top:4px"><?= $kpi_defectives_count ?></div>
                <div style="font-size:9px;color:rgba(255,255,255,.45);margin-top:2px"><?= fmtMoney($kpi_defectives_value, $currency) ?></div>
            </a>
            <a class="glass q4" href="/orders.php" style="padding:14px;text-decoration:none;color:inherit;display:block">
                <span class="shine"></span><span class="glow"></span>
                <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em">Поръчки</div>
                <div style="font-size:18px;font-weight:900;color:#fbbf24;font-variant-numeric:tabular-nums;margin-top:4px">→</div>
                <div style="font-size:9px;color:rgba(255,255,255,.45);margin-top:2px">отвори поръчките</div>
            </a>
        </div>
    </div>

</main>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
</body>
</html>
