<?php
/**
 * chat.php — RunMyStore.ai Home Dashboard v5.3
 * S40 — Period tabs (Днес/7д/30д/365д) linked to big number
 *        Оборот/Печалба toggle works per period
 *        Loss chips per category + tap → AI chat
 */
session_start();
require_once __DIR__ . '/config/database.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) { $_SESSION['store_id'] = (int)$_GET['store']; }
    header('Location: chat.php'); exit;
}

// Fallback: if no store_id in session, pick first store
if (!$store_id) {
    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
    if ($first) { $store_id = (int)$first['id']; $_SESSION['store_id'] = $store_id; }
}

$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$cs = htmlspecialchars($tenant['currency'] ?? '€');

$plan = 'FREE';
try {
    $sub = DB::run('SELECT plan FROM subscriptions WHERE tenant_id=? ORDER BY created_at DESC LIMIT 1',
        [$tenant_id])->fetch();
    if ($sub) $plan = strtoupper($sub['plan'] ?? 'FREE');
} catch (Exception $e) {}
if (!in_array($plan, ['FREE','BUSINESS','PRO','ENTERPRISE'])) $plan = 'FREE';

$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
    [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name',
    [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

function fmtMoney($v, $cs) { return number_format((float)$v, 0, ',', '.') . ($cs ? ' '.$cs : ''); }

$total_products = (int)DB::run(
    'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
$with_cost = (int)DB::run(
    'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0',
    [$tenant_id])->fetchColumn();
$confidence_pct = $total_products > 0 ? round($with_cost / $total_products * 100) : 0;

// ══════════════════════════════════════════════
// REVENUE + PROFIT — ALL PERIODS
// ══════════════════════════════════════════════
function periodData($tenant_id, $store_id, $role, $date_from, $date_to = null) {
    $date_to = $date_to ?? date('Y-m-d');
    $rev = (float)DB::run(
        'SELECT COALESCE(SUM(total),0) FROM sales
         WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=?
         AND DATE(created_at)<=? AND status!="canceled"',
        [$tenant_id, $store_id, $date_from, $date_to])->fetchColumn();
    $cnt = (int)DB::run(
        'SELECT COUNT(*) FROM sales
         WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=?
         AND DATE(created_at)<=? AND status!="canceled"',
        [$tenant_id, $store_id, $date_from, $date_to])->fetchColumn();
    $profit = 0;
    if ($role === 'owner' && $cnt > 0) {
        $profit = (float)DB::run(
            'SELECT COALESCE(SUM(si.quantity*(si.unit_price-COALESCE(si.cost_price,0))),0)
             FROM sale_items si JOIN sales s ON s.id=si.sale_id
             WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)>=?
             AND DATE(s.created_at)<=? AND s.status!="canceled"',
            [$tenant_id, $store_id, $date_from, $date_to])->fetchColumn();
    }
    return ['rev' => $rev, 'profit' => $profit, 'cnt' => $cnt];
}

function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }

$today = date('Y-m-d');
$d0  = periodData($tenant_id, $store_id, $role, $today, $today);
$d0p = periodData($tenant_id, $store_id, $role,
    date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')));

$d7  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-6 days')), $today);
$d7p = periodData($tenant_id, $store_id, $role,
    date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-7 days')));

$d30  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-29 days')), $today);
$d30p = periodData($tenant_id, $store_id, $role,
    date('Y-m-d', strtotime('-59 days')), date('Y-m-d', strtotime('-30 days')));

$d365  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-364 days')), $today);
$d365p = periodData($tenant_id, $store_id, $role,
    date('Y-m-d', strtotime('-729 days')), date('Y-m-d', strtotime('-365 days')));

function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }

$dow_names = ['','понеделник','вторник','сряда','четвъртък','петък','събота','неделя'];
$dow = (int)date('N');

$top_product = DB::run(
    'SELECT p.name, SUM(si.quantity) AS qty FROM sale_items si
     JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
     WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"
     GROUP BY si.product_id ORDER BY qty DESC LIMIT 1',
    [$tenant_id, $store_id])->fetch();

$periods_json = json_encode([
    'today' => [
        'rev'    => round($d0['rev']),   'profit' => round($d0['profit']),
        'cnt'    => $d0['cnt'],          'margin' => mgn($d0),
        'cmp_rev'  => cmpPct($d0['rev'],    $d0p['rev']),
        'cmp_prof' => cmpPct($d0['profit'], $d0p['profit']),
        'label'  => 'Спрямо вчера',
        'sub_rev'  => fmtMoney(round($d0p['rev']),'') . ' → ' . fmtMoney(round($d0['rev']),''),
        'sub_prof' => fmtMoney(round($d0p['profit']),'') . ' → ' . fmtMoney(round($d0['profit']),''),
    ],
    '7d' => [
        'rev'    => round($d7['rev']),   'profit' => round($d7['profit']),
        'cnt'    => $d7['cnt'],          'margin' => mgn($d7),
        'cmp_rev'  => cmpPct($d7['rev'],    $d7p['rev']),
        'cmp_prof' => cmpPct($d7['profit'], $d7p['profit']),
        'label'  => 'Спрямо предишните 7 дни',
        'sub_rev'  => fmtMoney(round($d7p['rev']),'') . ' → ' . fmtMoney(round($d7['rev']),''),
        'sub_prof' => fmtMoney(round($d7p['profit']),'') . ' → ' . fmtMoney(round($d7['profit']),''),
    ],
    '30d' => [
        'rev'    => round($d30['rev']),  'profit' => round($d30['profit']),
        'cnt'    => $d30['cnt'],         'margin' => mgn($d30),
        'cmp_rev'  => cmpPct($d30['rev'],    $d30p['rev']),
        'cmp_prof' => cmpPct($d30['profit'], $d30p['profit']),
        'label'  => 'Спрямо предишните 30 дни',
        'sub_rev'  => fmtMoney(round($d30p['rev']),'') . ' → ' . fmtMoney(round($d30['rev']),''),
        'sub_prof' => fmtMoney(round($d30p['profit']),'') . ' → ' . fmtMoney(round($d30['profit']),''),
    ],
    '365d' => [
        'rev'    => round($d365['rev']), 'profit' => round($d365['profit']),
        'cnt'    => $d365['cnt'],        'margin' => mgn($d365),
        'cmp_rev'  => cmpPct($d365['rev'],    $d365p['rev']),
        'cmp_prof' => cmpPct($d365['profit'], $d365p['profit']),
        'label'  => 'Спрямо предишните 365 дни',
        'sub_rev'  => fmtMoney(round($d365p['rev']),'') . ' → ' . fmtMoney(round($d365['rev']),''),
        'sub_prof' => fmtMoney(round($d365p['profit']),'') . ' → ' . fmtMoney(round($d365['profit']),''),
    ],
], JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════
// LOSS CHIPS — PER CATEGORY
// ══════════════════════════════════════════════
$loss_chips = [];

// 1. Zero stock per category (had sales in last 30 days) → RED
$zero_by_cat = DB::run(
    'SELECT COALESCE(c.name,"Без категория") AS cat, COUNT(DISTINCT p.id) AS cnt
     FROM products p
     JOIN inventory i ON i.product_id=p.id AND i.store_id=?
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity=0
     AND p.id IN (
         SELECT si.product_id FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE s.tenant_id=? AND s.store_id=? AND s.status!="canceled"
         AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     )
     GROUP BY c.id, c.name ORDER BY cnt DESC LIMIT 15',
    [$store_id, $tenant_id, $tenant_id, $store_id])->fetchAll(PDO::FETCH_ASSOC);

$zero_stock_total = 0;
foreach ($zero_by_cat as $row) {
    $zero_stock_total += (int)$row['cnt'];
    $cat = $row['cat'];
    $q = 'Кои ' . mb_strtolower($cat) . ' са на нула наличност?';
    $loss_chips[] = ['type'=>'red', 'num'=>(int)$row['cnt'], 'text'=>'на нула · '.$cat, 'q'=>$q];
}

// 2. Below cost per category → RED (owner only)
$below_cost_total = 0;
if ($role === 'owner') {
    $below_by_cat = DB::run(
        'SELECT COALESCE(c.name,"Без категория") AS cat, COUNT(*) AS cnt
         FROM products p
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE p.tenant_id=? AND p.is_active=1 AND p.cost_price>0 AND p.retail_price < p.cost_price
         GROUP BY c.id, c.name ORDER BY cnt DESC LIMIT 10',
        [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
    foreach ($below_by_cat as $row) {
        $below_cost_total += (int)$row['cnt'];
        $cat = $row['cat'];
        $q = 'Кои ' . mb_strtolower($cat) . ' се продават под себестойност?';
        $loss_chips[] = ['type'=>'red', 'num'=>(int)$row['cnt'], 'text'=>'под цена · '.$cat, 'q'=>$q];
    }
}

// 3. Zombie stock per category (45+ days) → YELLOW
$zombie_by_cat = DB::run(
    'SELECT COALESCE(c.name,"Без категория") AS cat,
            COUNT(*) AS cnt,
            COALESCE(SUM(i.quantity * COALESCE(p.cost_price, p.retail_price * 0.6)), 0) AS val
     FROM inventory i
     JOIN products p ON p.id = i.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.quantity>0 AND p.is_active=1 AND p.parent_id IS NULL
     AND DATEDIFF(NOW(), COALESCE(
         (SELECT MAX(s2.created_at) FROM sales s2
          JOIN sale_items si2 ON si2.sale_id=s2.id
          WHERE si2.product_id=p.id AND s2.store_id=i.store_id AND s2.status!="canceled"),
         p.created_at
     )) >= 45
     GROUP BY c.id, c.name ORDER BY val DESC LIMIT 12',
    [$store_id, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

$zombie_total = 0; $zombie_val_total = 0;
foreach ($zombie_by_cat as $row) {
    $zombie_total += (int)$row['cnt'];
    $zombie_val_total += (float)$row['val'];
    $cat = $row['cat'];
    $q = 'Кои ' . mb_strtolower($cat) . ' са zombie стока?';
    $loss_chips[] = ['type'=>'yellow',
        'num' => fmtMoney(round((float)$row['val']), $cs),
        'text'=> 'zombie · '.$cat, 'q'=>$q];
}

// 4. Low stock per category → YELLOW (min_quantity is on products table)
$low_by_cat = DB::run(
    'SELECT COALESCE(c.name,"Без категория") AS cat, COUNT(*) AS cnt
     FROM inventory i
     JOIN products p ON p.id = i.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.quantity <= p.min_quantity
     AND p.min_quantity > 0 AND p.is_active=1
     GROUP BY c.id, c.name ORDER BY cnt DESC LIMIT 10',
    [$store_id, $tenant_id])->fetchAll(PDO::FETCH_ASSOC);

$low_stock_total = 0;
foreach ($low_by_cat as $row) {
    $low_stock_total += (int)$row['cnt'];
    $cat = $row['cat'];
    $q = 'Кои ' . mb_strtolower($cat) . ' са под минималното количество?';
    $loss_chips[] = ['type'=>'yellow', 'num'=>(int)$row['cnt'], 'text'=>'ниски · '.$cat, 'q'=>$q];
}

// 5. Invoices due → BLUE (owner/manager)
if ($role !== 'seller') {
    try {
        $due = DB::run(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total
             FROM invoices WHERE tenant_id=? AND status="unpaid"
             AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)',
            [$tenant_id])->fetch();
        if ((int)$due['cnt'] > 0) {
            $loss_chips[] = ['type'=>'blue',
                'num'  => fmtMoney(round((float)$due['total']), $cs),
                'text' => 'падеж фактури',
                'q'    => 'Кои фактури падежират скоро?'];
        }
    } catch (Exception $e) {}
}

// ══════════════════════════════════════════════
// BADGES FOR 4 BUTTONS
// ══════════════════════════════════════════════
$badge_delivery = 0;
try {
    $badge_delivery = (int)DB::run(
        'SELECT COUNT(*) FROM invoices WHERE tenant_id=? AND status="unpaid"',
        [$tenant_id])->fetchColumn();
} catch (Exception $e) {}
$badge_products = $zero_stock_total;

// ══════════════════════════════════════════════
// SIGNAL CARDS
// ══════════════════════════════════════════════
$signals = [];
if ($zero_stock_total > 0)
    $signals[] = ['color'=>'red',    'title'=>'Нулева наличност',
        'desc'=>$zero_stock_total.' топ артикула на 0 бр.', 'pulse'=>true,
        'q'=>'Покажи артикулите с нулева наличност'];
if ($role === 'owner' && $below_cost_total > 0)
    $signals[] = ['color'=>'red',    'title'=>'Под себестойност',
        'desc'=>$below_cost_total.' артикула се продават под цена.', 'pulse'=>true,
        'q'=>'Кои артикули се продават под себестойност?'];
if ($zombie_total > 0)
    $signals[] = ['color'=>'yellow', 'title'=>'Zombie стока',
        'desc'=>$zombie_total.' арт. стоят 45+ дни. '.fmtMoney(round($zombie_val_total), $cs).'.', 'pulse'=>false,
        'q'=>'Покажи zombie стоката — артикули без продажба 45+ дни'];
if ($low_stock_total > 0)
    $signals[] = ['color'=>'yellow', 'title'=>'Ниски наличности',
        'desc'=>$low_stock_total.' артикула под минимума.', 'pulse'=>false,
        'q'=>'Кои артикули са под минималното количество за поръчка?'];
if ($role !== 'seller') {
    try {
        $pend = (int)DB::run(
            'SELECT COUNT(*) FROM deliveries WHERE tenant_id=? AND status="pending"',
            [$tenant_id])->fetchColumn();
        if ($pend > 0)
            $signals[] = ['color'=>'blue', 'title'=>'Доставки в път',
                'desc'=>$pend.' доставки чакат приемане.', 'pulse'=>false,
                'q'=>'Кои доставки чакат приемане?'];
    } catch (Exception $e) {}
}

// ══════════════════════════════════════════════
// CHAT HISTORY
// ══════════════════════════════════════════════
$chat_messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id, $store_id])->fetchAll(PDO::FETCH_ASSOC);

$plan_colors = match($plan) {
    'BUSINESS'   => ['bg'=>'rgba(99,102,241,.15)',  'br'=>'rgba(99,102,241,.3)',  'tx'=>'#818cf8'],
    'PRO'        => ['bg'=>'rgba(168,85,247,.15)',  'br'=>'rgba(168,85,247,.3)',  'tx'=>'#c084fc'],
    'ENTERPRISE' => ['bg'=>'rgba(234,179,8,.15)',   'br'=>'rgba(234,179,8,.3)',   'tx'=>'#fbbf24'],
    default      => ['bg'=>'rgba(107,114,128,.15)', 'br'=>'rgba(107,114,128,.3)', 'tx'=>'#9ca3af'],
};
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#030712">
<title>RunMyStore.ai</title>
<style>
:root {
    --bg:#030712; --card:rgba(15,15,40,.75); --bdr:rgba(99,102,241,.15);
    --bglow:rgba(99,102,241,.4); --i6:#4f46e5; --i5:#6366f1; --i4:#818cf8;
    --i3:#a5b4fc; --tx:#f1f5f9; --muted:#6b7280;
    --red:#ef4444; --yellow:#fbbf24; --green:#22c55e; --nav:56px;
}
*,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg)}
body{background:var(--bg);color:var(--tx);font-family:'Montserrat',Inter,system-ui,sans-serif;
    height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:600px;height:350px;
    background:radial-gradient(ellipse,rgba(99,102,241,.1) 0%,transparent 70%);
    pointer-events:none;z-index:0}
.main-scroll{flex:1;overflow-y:auto;overflow-x:hidden;position:relative;z-index:1;
    -webkit-overflow-scrolling:touch;scrollbar-width:none}
.main-scroll::-webkit-scrollbar{display:none}

/* ── HEADER ── */
.hdr{position:sticky;top:0;z-index:50;padding:10px 14px 0;
    background:rgba(3,7,18,.93);backdrop-filter:blur(16px)}
.hdr-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.hdr-brand{font-size:11px;font-weight:700;color:rgba(165,180,252,.6);letter-spacing:.3px}
.hdr-right{display:flex;align-items:center;gap:6px}
.plan-pill{padding:3px 8px;border-radius:20px;font-size:9px;font-weight:800;letter-spacing:.5px;
    background:<?= $plan_colors['bg'] ?>;border:1px solid <?= $plan_colors['br'] ?>;color:<?= $plan_colors['tx'] ?>}
.hdr-btn{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:background .2s;position:relative}
.hdr-btn:active{background:rgba(99,102,241,.3)}
.hdr-btn svg{width:14px;height:14px;color:var(--i4)}
.logout-drop{position:absolute;top:34px;right:0;background:#0f0f2a;
    border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:8px 14px;white-space:nowrap;
    z-index:60;box-shadow:0 8px 24px rgba(0,0,0,.5);font-size:12px;color:#fca5a5;
    font-weight:600;cursor:pointer;display:none;text-decoration:none}
.logout-drop.show{display:block}

/* ── LOSS CHIPS ── */
.chips-row{display:flex;gap:6px;padding:6px 14px 10px;overflow-x:auto;
    scrollbar-width:none;flex-shrink:0}
.chips-row::-webkit-scrollbar{display:none}
.chip{display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:10px;
    flex-shrink:0;cursor:pointer;white-space:nowrap;transition:transform .1s;
    border:none;background:none;font-family:inherit;text-align:left}
.chip:active{transform:scale(.95)}
.chip-num{font-size:12px;font-weight:800}
.chip-txt{font-size:10px;font-weight:600}
.chip-red{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25)!important}
.chip-red .chip-num{color:#f87171}.chip-red .chip-txt{color:rgba(248,113,113,.7)}
.chip-yellow{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25)!important}
.chip-yellow .chip-num{color:#fbbf24}.chip-yellow .chip-txt{color:rgba(251,191,36,.7)}
.chip-blue{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25)!important}
.chip-blue .chip-num{color:#818cf8}.chip-blue .chip-txt{color:rgba(129,140,248,.7)}

/* ── BIG NUMBER ── */
.bnum-wrap{text-align:center;padding:16px 14px 6px}
.bnum-label{font-size:10px;font-weight:700;text-transform:uppercase;
    color:var(--muted);letter-spacing:1.5px;margin-bottom:4px}
.bnum-value{font-size:46px;font-weight:900;line-height:1;
    background:linear-gradient(90deg,#818cf8,#c7d2fe,#818cf8);background-size:200% auto;
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    animation:shimmer 3s linear infinite}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}
.bnum-sub{font-size:12px;color:var(--muted);margin-top:4px;min-height:18px}
.mode-pills{display:flex;justify-content:center;gap:6px;margin-top:10px}
.mode-pill{padding:5px 14px;border-radius:14px;font-size:11px;font-weight:700;color:#4b5563;
    background:rgba(15,15,40,.4);border:1px solid rgba(99,102,241,.1);
    cursor:pointer;transition:all .25s;display:flex;align-items:center;gap:4px}
.mode-pill.active{color:#e2e8f0;
    background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(139,92,246,.15));
    border-color:rgba(99,102,241,.3)}
.conf-warn{display:inline-flex;align-items:center;justify-content:center;
    width:16px;height:16px;border-radius:50%;background:rgba(251,191,36,.15);
    color:#fbbf24;font-size:10px;font-weight:900;border:1px solid rgba(251,191,36,.3);
    animation:dotpulse 2s infinite}
.conf-note{display:flex;align-items:center;gap:6px;margin:8px auto 0;padding:6px 10px;
    border-radius:10px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.15);
    font-size:10px;color:#fcd34d;max-width:320px;justify-content:center}

/* ── COMPARISON CARD ── */
.cmp-card{margin:10px 14px 12px;background:var(--card);border:1px solid var(--bdr);
    border-radius:16px;padding:14px;backdrop-filter:blur(12px);position:relative;overflow:hidden}
.cmp-card::before{content:'';position:absolute;inset:0;border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);pointer-events:none}
.cmp-hdr{display:flex;align-items:center;justify-content:space-between;
    margin-bottom:8px;position:relative}
.cmp-title{font-size:9px;font-weight:700;color:var(--i4);text-transform:uppercase;letter-spacing:.5px}
.cmp-store-name{font-size:10px;font-weight:600;color:rgba(165,180,252,.5)}
.cmp-store select{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);
    border-radius:8px;color:#a5b4fc;font-size:10px;font-weight:600;padding:3px 8px;
    font-family:inherit;cursor:pointer;outline:none}
.cmp-top{display:flex;align-items:center;gap:14px;margin-bottom:10px;position:relative}
.cmp-pct{font-size:26px;font-weight:900;line-height:1;flex-shrink:0}
.cmp-pct.up{color:#4ade80}.cmp-pct.down{color:#f87171}.cmp-pct.zero{color:var(--muted)}
.cmp-info{flex:1;min-width:0}
.cmp-label{font-size:11px;font-weight:600;color:#d1d5db}
.cmp-sub{font-size:10px;color:var(--muted);margin-top:2px}
.cmp-bar-track{height:5px;border-radius:3px;background:rgba(255,255,255,.06);
    margin-bottom:10px;overflow:hidden}
.cmp-bar-fill{height:100%;border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.cmp-bar-fill.up{background:linear-gradient(90deg,#22c55e,#4ade80)}
.cmp-bar-fill.down{background:linear-gradient(90deg,#ef4444,#f87171)}
.cmp-bar-fill.zero{background:var(--muted)}
.cmp-ai{display:flex;gap:8px;align-items:flex-start;margin-bottom:12px;position:relative}
.cmp-ai-badge{flex-shrink:0;padding:2px 6px;border-radius:6px;font-size:8px;font-weight:800;
    color:var(--i4);background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);
    letter-spacing:.5px}
.cmp-ai-text{font-size:11px;color:#d1d5db;line-height:1.5}

/* ── PERIOD TABS (4 tabs) ── */
.period-tabs{display:flex;position:relative;background:rgba(15,15,40,.4);
    border:1px solid rgba(99,102,241,.1);border-radius:14px;overflow:hidden}
.period-slider{position:absolute;top:2px;bottom:2px;left:2px;width:calc(25% - 4px);
    border-radius:12px;background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(139,92,246,.15));
    border:1px solid rgba(99,102,241,.3);transition:left .35s cubic-bezier(.4,0,.2,1)}
.period-tab{flex:1;text-align:center;padding:10px 0;font-size:10px;font-weight:700;
    color:#4b5563;cursor:pointer;position:relative;z-index:1;
    display:flex;align-items:center;justify-content:center;gap:4px;transition:color .3s}
.period-tab.active{color:#e2e8f0}
.period-tab svg{width:12px;height:12px;opacity:.5;transition:opacity .3s}
.period-tab.active svg{opacity:1}

/* ── 4 BUTTONS ── */
.btn-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 14px;margin-bottom:14px}
.main-btn{background:var(--card);border:1px solid var(--bdr);border-radius:16px;
    padding:20px 14px 16px;cursor:pointer;position:relative;overflow:hidden;
    backdrop-filter:blur(12px);text-decoration:none;display:block;transition:all .25s}
.main-btn:active{transform:scale(.97)}
.main-btn::before{content:'';position:absolute;inset:0;border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);pointer-events:none}
.btn-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;
    justify-content:center;margin-bottom:10px}
.btn-icon svg{width:20px;height:20px}
.btn-name{font-size:13px;font-weight:700;color:var(--tx)}
.btn-desc{font-size:10px;color:var(--muted);margin-top:2px}
.btn-badge{position:absolute;top:8px;right:10px;background:rgba(239,68,68,.15);
    color:#ef4444;font-size:10px;font-weight:800;border-radius:10px;min-width:18px;height:18px;
    display:flex;align-items:center;justify-content:center;padding:0 5px;
    animation:pulse-red 2s infinite;z-index:2}
@keyframes pulse-red{0%,100%{opacity:1;box-shadow:0 0 4px rgba(239,68,68,.3)}
    50%{opacity:.7;box-shadow:0 0 12px rgba(239,68,68,.6)}}

/* ── SEPARATOR ── */
.sep{height:1px;margin:0 14px;
    background:linear-gradient(to right,transparent,rgba(99,102,241,.25),transparent)}

/* ── SIGNALS ── */
.sigs{padding:14px}
.sigs-title{font-size:10px;font-weight:700;color:var(--i4);text-transform:uppercase;
    letter-spacing:1px;margin-bottom:10px}
.sig-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;
    background:var(--card);backdrop-filter:blur(12px);margin-bottom:8px;
    cursor:pointer;transition:transform .15s;border:none;width:100%;text-align:left;
    font-family:inherit}
.sig-card:active{transform:scale(.98)}
.sig-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.sig-body{flex:1;min-width:0}
.sig-name{font-size:12px;font-weight:700}
.sig-desc{font-size:12px;color:#d1d5db;margin-top:2px}
.sig-link{color:var(--i4);font-weight:600;white-space:nowrap}
.sig-red{border:1px solid rgba(239,68,68,.2)!important}
.sig-red .sig-name{color:#fca5a5}
.sig-red .sig-dot{background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,.6)}
.sig-yellow{border:1px solid rgba(251,191,36,.2)!important}
.sig-yellow .sig-name{color:#fcd34d}
.sig-yellow .sig-dot{background:#fbbf24;box-shadow:0 0 8px rgba(251,191,36,.6)}
.sig-blue{border:1px solid rgba(99,102,241,.2)!important}
.sig-blue .sig-name{color:#a5b4fc}
.sig-blue .sig-dot{background:#818cf8;box-shadow:0 0 8px rgba(99,102,241,.6)}

/* ── ASK AI ── */
.ask-ai-wrap{padding:16px 14px 20px;display:flex;justify-content:center}
.ask-ai-btn{display:flex;align-items:center;gap:12px;padding:10px 24px;border-radius:20px;
    background:rgba(15,15,40,.75);border:1px solid rgba(99,102,241,.2);
    cursor:pointer;backdrop-filter:blur(12px);transition:all .25s}
.ask-ai-btn:active{transform:scale(.97)}
.ask-ai-btn span{font-size:13px;font-weight:600;color:#a5b4fc}
.ai-waves{display:flex;align-items:flex-end;gap:2px;height:18px}
.ai-wave-bar{width:3px;border-radius:2px;background:currentColor;
    animation:wave 1s ease-in-out infinite}
@keyframes wave{0%,100%{transform:scaleY(.35)}50%{transform:scaleY(1)}}

/* ── CHAT OVERLAY ── */
.chat-ov{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,.6);
    backdrop-filter:blur(8px);display:none;flex-direction:column;
    align-items:center;justify-content:flex-end}
.chat-ov.open{display:flex}
.chat-panel{width:100%;height:80vh;max-width:500px;background:rgba(10,12,28,.97);
    border:1px solid var(--bglow);border-radius:22px 22px 0 0;
    display:flex;flex-direction:column;box-shadow:0 -12px 50px rgba(99,102,241,.25);
    animation:slideup .25s ease;overflow:hidden}
@keyframes slideup{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
.chat-ph{display:flex;align-items:center;justify-content:space-between;
    padding:14px 16px 10px;flex-shrink:0;border-bottom:1px solid var(--bdr)}
.chat-ph-title{display:flex;align-items:center;gap:8px}
.chat-avatar{width:28px;height:28px;border-radius:50%;
    background:linear-gradient(135deg,#4f46e5,#9333ea);
    display:flex;align-items:center;justify-content:center}
.chat-avatar svg{width:14px;height:14px}
.chat-ph-name{font-size:14px;font-weight:700;color:var(--i3)}
.chat-close{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:var(--muted);transition:background .2s}
.chat-close:active{background:rgba(239,68,68,.3)}
.chat-close svg{width:16px;height:16px}
.chat-msgs{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;
    gap:10px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.chat-msgs::-webkit-scrollbar{display:none}
.chat-mg{display:flex;flex-direction:column;gap:4px}
.chat-meta{font-size:9px;color:#4b5563;display:flex;align-items:center;gap:4px}
.chat-meta.r{justify-content:flex-end}
.chat-msg{max-width:82%;padding:8px 12px;font-size:13px;line-height:1.5;word-break:break-word}
.ai-msg{background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);
    color:#e2e8f0;border-radius:4px 14px 14px 14px;white-space:pre-wrap}
.usr-msg{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
    border-radius:14px 14px 4px 14px;margin-left:auto;border:.5px solid rgba(255,255,255,.1)}
.chat-typing{display:none;padding:8px 12px;background:rgba(15,20,40,.8);
    border:.5px solid rgba(99,102,241,.15);border-radius:4px 14px 14px 14px;width:fit-content}
.typing-dots{display:flex;gap:4px;align-items:center}
.typing-dot{width:5px;height:5px;border-radius:50%;background:#818cf8;
    animation:bounce 1.2s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
.chat-input-area{padding:8px 12px 12px;flex-shrink:0;border-top:1px solid var(--bdr)}
.chat-input-row{display:flex;gap:6px;align-items:center;background:rgba(10,14,28,.9);
    border-radius:20px;padding:4px 4px 4px 12px;border:.5px solid rgba(99,102,241,.2)}
.chat-txt{flex:1;background:transparent;border:none;color:var(--tx);font-size:13px;
    padding:8px 0;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4}
.chat-txt::placeholder{color:#374151}
.chat-voice{width:36px;height:36px;border-radius:50%;flex-shrink:0;position:relative;
    display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;
    background:linear-gradient(135deg,#4f46e5,#9333ea);
    box-shadow:0 0 12px rgba(99,102,241,.3);transition:all .2s}
.chat-voice.rec{background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 0 18px rgba(239,68,68,.5)}
.chat-voice svg{width:16px;height:16px;color:#fff;z-index:1}
.vring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.3);opacity:0}
.chat-voice.rec .vring{border-color:rgba(255,255,255,.5)}
.vr1{width:20px;height:20px;animation:rpulse 2s 0s ease-in-out infinite}
.vr2{width:32px;height:32px;animation:rpulse 2s .3s ease-in-out infinite}
.vr3{width:44px;height:44px;animation:rpulse 2s .6s ease-in-out infinite}
@keyframes rpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.chat-send{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.08);
    border:.5px solid rgba(255,255,255,.1);color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s}
.chat-send:disabled{opacity:.2}
.chat-send svg{width:16px;height:16px}
.rec-bar{display:none;align-items:center;gap:8px;padding:6px 12px;margin-bottom:6px;
    background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px}
.rec-bar.on{display:flex}
.rec-dot-s{width:10px;height:10px;border-radius:50%;background:#ef4444;
    animation:dotpulse 1s ease infinite;box-shadow:0 0 8px rgba(239,68,68,.6)}
.rec-lbl{font-size:11px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:.5px}
.rec-tr{font-size:12px;color:#e2e8f0;flex:1}

/* ── BOTTOM NAV ── */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav);
    background:rgba(3,7,18,.95);backdrop-filter:blur(15px);
    border-top:.5px solid rgba(99,102,241,.2);display:flex;z-index:100;
    box-shadow:0 -4px 20px rgba(99,102,241,.1)}
.bnav-tab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:3px;font-size:9px;font-weight:600;color:rgba(165,180,252,.4);
    text-decoration:none;transition:all .3s}
.bnav-tab.active{color:#c7d2fe;text-shadow:0 0 10px rgba(129,140,248,.8)}
.bnav-tab svg{width:18px;height:18px;transition:all .3s}
.bnav-tab.active svg{filter:drop-shadow(0 0 7px rgba(129,140,248,.8))}

/* ── TOAST ── */
.toast{position:fixed;bottom:65px;left:50%;transform:translateX(-50%);
    background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
    padding:7px 16px;border-radius:20px;font-size:11px;font-weight:600;z-index:500;
    opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

@keyframes cardin{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes dotpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
</style>
</head>
<body>
<div class="main-scroll" id="mainScroll">

<!-- HEADER -->
<div class="hdr">
  <div class="hdr-top">
    <span class="hdr-brand">RUNMYSTORE.AI</span>
    <div class="hdr-right">
      <span class="plan-pill"><?= $plan ?></span>
      <div class="hdr-btn" onclick="location.href='settings.php'">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <circle cx="12" cy="12" r="3"/></svg>
      </div>
      <div class="hdr-btn" onclick="toggleLogout()" id="logoutWrap" style="position:relative">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        <a href="logout.php" class="logout-drop" id="logoutDrop">Изход →</a>
      </div>
    </div>
  </div>

  <!-- LOSS CHIPS PER CATEGORY — tap opens AI chat -->
  <?php if (!empty($loss_chips)): ?>
  <div class="chips-row">
    <?php foreach ($loss_chips as $chip): ?>
    <button class="chip chip-<?= $chip['type'] ?>"
        onclick="openChatWithQuestion(<?= htmlspecialchars(json_encode($chip['q']), ENT_QUOTES) ?>)">
      <span class="chip-num"><?= htmlspecialchars((string)$chip['num']) ?></span>
      <span class="chip-txt"><?= htmlspecialchars($chip['text']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- BIG NUMBER -->
<div class="bnum-wrap" style="animation:cardin .5s ease both">
  <div class="bnum-label" id="bnumLabel">ДНЕС</div>
  <div class="bnum-value" id="bnumValue">0 <?= htmlspecialchars($cs) ?></div>
  <div class="bnum-sub" id="bnumSub">
    <?= $d0['cnt'] ?> продажби<?php if ($role==='owner' && $d0['cnt']>0): ?> · <?= mgn($d0) ?>% марж<?php endif; ?>
  </div>
  <?php if ($role === 'owner'): ?>
  <div class="mode-pills">
    <div class="mode-pill active" id="modeRev" onclick="setMode('rev')">Оборот</div>
    <div class="mode-pill" id="modePro" onclick="setMode('profit')">
      Печалба<?php if ($confidence_pct < 100): ?><span class="conf-warn">!</span><?php endif; ?>
    </div>
  </div>
  <?php if ($confidence_pct < 100): ?>
  <div class="conf-note" id="confNote" style="display:none">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5">
      <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    Сумата е за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?> с доставна цена)
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- COMPARISON CARD -->
<div class="cmp-card" style="animation:cardin .5s .15s ease both">
  <div class="cmp-hdr">
    <span class="cmp-title">СРАВНЕНИЕ</span>
    <?php if (count($all_stores) > 1): ?>
    <div class="cmp-store">
      <select onchange="location.href='?store='+this.value">
        <?php foreach ($all_stores as $st): ?>
        <option value="<?= $st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php else: ?>
    <span class="cmp-store-name"><?= htmlspecialchars($store_name) ?></span>
    <?php endif; ?>
  </div>
  <div class="cmp-top">
    <div class="cmp-pct" id="cmpPct">—</div>
    <div class="cmp-info">
      <div class="cmp-label" id="cmpLabel">Спрямо вчера</div>
      <div class="cmp-sub" id="cmpSub"></div>
    </div>
  </div>
  <div class="cmp-bar-track">
    <div class="cmp-bar-fill" id="cmpBar" style="width:0%"></div>
  </div>
  <?php if ($top_product): ?>
  <div class="cmp-ai">
    <span class="cmp-ai-badge">AI</span>
    <div class="cmp-ai-text"><?= htmlspecialchars($top_product['name']) ?> дърпа — <?= (int)$top_product['qty'] ?> продажби днес.</div>
  </div>
  <?php endif; ?>

  <!-- 4 PERIOD TABS -->
  <div class="period-tabs">
    <div class="period-slider" id="perSlider"></div>
    <div class="period-tab active" onclick="setPeriod('today',0)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      Днес
    </div>
    <div class="period-tab" onclick="setPeriod('7d',1)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      7 дни
    </div>
    <div class="period-tab" onclick="setPeriod('30d',2)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      30 дни
    </div>
    <div class="period-tab" onclick="setPeriod('365d',3)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 9l-5 5-2-2-4 4"/></svg>
      365 дни
    </div>
  </div>
</div>

<!-- 4 BUTTONS -->
<div class="btn-grid">
  <a href="sale.php" class="main-btn" style="animation:cardin .4s .25s ease both">
    <div class="btn-icon" style="background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:1px solid rgba(34,197,94,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round">
        <rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M6 14h4"/></svg>
    </div>
    <div class="btn-name">Продажба</div><div class="btn-desc">Каса и скенер</div>
  </a>
  <a href="#" class="main-btn" style="animation:cardin .4s .35s ease both">
    <div class="btn-icon" style="background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(99,102,241,.04));border:1px solid rgba(99,102,241,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2" stroke-linecap="round">
        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div class="btn-name">Поръчка</div><div class="btn-desc">Към доставчици</div>
  </a>
  <a href="#" class="main-btn" style="animation:cardin .4s .45s ease both">
    <?php if ($badge_delivery>0): ?><div class="btn-badge"><?= $badge_delivery ?></div><?php endif; ?>
    <div class="btn-icon" style="background:linear-gradient(135deg,rgba(251,191,36,.12),rgba(251,191,36,.04));border:1px solid rgba(251,191,36,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round">
        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
    </div>
    <div class="btn-name">Доставка</div><div class="btn-desc">Приемане на стока</div>
  </a>
  <a href="products.php" class="main-btn" style="animation:cardin .4s .55s ease both">
    <?php if ($badge_products>0): ?><div class="btn-badge"><?= $badge_products ?></div><?php endif; ?>
    <div class="btn-icon" style="background:linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.04));border:1px solid rgba(192,132,252,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#c084fc" stroke-width="2" stroke-linecap="round">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
        <circle cx="7" cy="7" r="1.5" fill="#c084fc"/></svg>
    </div>
    <div class="btn-name">Артикули</div><div class="btn-desc">Добави и редактирай</div>
  </a>
</div>

<!-- SIGNALS — tap opens AI chat -->
<?php if (!empty($signals)): ?>
<div class="sep"></div>
<div class="sigs">
  <div class="sigs-title">СИГНАЛИ</div>
  <?php foreach ($signals as $si => $sig): ?>
  <button class="sig-card sig-<?= $sig['color'] ?>"
      style="animation:cardin .4s <?= $si*80 ?>ms ease both"
      onclick="openChatWithQuestion(<?= htmlspecialchars(json_encode($sig['q']), ENT_QUOTES) ?>)">
    <div class="sig-dot" <?= $sig['pulse']?'style="animation:dotpulse 2s infinite"':'' ?>></div>
    <div class="sig-body">
      <div class="sig-name"><?= htmlspecialchars($sig['title']) ?></div>
      <div class="sig-desc"><?= htmlspecialchars($sig['desc']) ?> <span class="sig-link">Попитай AI →</span></div>
    </div>
  </button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ASK AI -->
<div class="ask-ai-wrap">
  <div class="ask-ai-btn" onclick="openChat()">
    <div class="ai-waves">
      <div class="ai-wave-bar" style="color:#6366f1;height:18px;animation-delay:0s"></div>
      <div class="ai-wave-bar" style="color:#818cf8;height:18px;animation-delay:.15s"></div>
      <div class="ai-wave-bar" style="color:#a5b4fc;height:18px;animation-delay:.3s"></div>
      <div class="ai-wave-bar" style="color:#818cf8;height:18px;animation-delay:.45s"></div>
      <div class="ai-wave-bar" style="color:#6366f1;height:18px;animation-delay:.6s"></div>
    </div>
    <span>Попитай AI</span>
  </div>
</div>
<div style="height:20px"></div>
</div><!-- /.main-scroll -->

<!-- CHAT OVERLAY -->
<div class="chat-ov" id="chatOv">
  <div class="chat-panel">
    <div class="chat-ph">
      <div class="chat-ph-title">
        <div class="chat-avatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <span class="chat-ph-name">AI Асистент</span>
      </div>
      <div class="chat-close" onclick="closeChat()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
      </div>
    </div>
    <div class="chat-msgs" id="chatMsgs">
      <?php if (empty($chat_messages)): ?>
      <div style="text-align:center;padding:20px;color:var(--muted);font-size:12px">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;
            background:linear-gradient(135deg,#e5e7eb,#c7d2fe);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent">
          Здравей<?= $user_name ? ', '.htmlspecialchars($user_name) : '' ?>!
        </div>
        Попитай каквото искаш — говори или пиши.
      </div>
      <?php else: ?>
      <?php foreach ($chat_messages as $m): ?>
      <div class="chat-mg">
        <?php if ($m['role']==='assistant'): ?>
        <div class="chat-meta">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          AI · <?= date('H:i', strtotime($m['created_at'])) ?>
        </div>
        <div class="chat-msg ai-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
        <?php else: ?>
        <div class="chat-meta r"><?= date('H:i', strtotime($m['created_at'])) ?></div>
        <div class="chat-msg usr-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
      <div class="chat-typing" id="chatTyping">
        <div class="typing-dots">
          <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
        </div>
      </div>
    </div>
    <div class="rec-bar" id="recBar">
      <div class="rec-dot-s"></div>
      <span class="rec-lbl">ЗАПИСВА</span>
      <span class="rec-tr" id="recTr">Слушам...</span>
    </div>
    <div class="chat-input-area">
      <div class="chat-input-row">
        <textarea class="chat-txt" id="chatIn" placeholder="Кажи или пиши..." rows="1"
          oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';
                   document.getElementById('chatSend').disabled=!this.value.trim()"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
        <div class="chat-voice" id="chatVoice" onclick="toggleVoice()">
          <div class="vring vr1"></div><div class="vring vr2"></div><div class="vring vr3"></div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
            <path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
        </div>
        <button class="chat-send" id="chatSend" onclick="sendMsg()" disabled>
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <a href="chat.php" class="bnav-tab active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI
  </a>
  <a href="warehouse.php" class="bnav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
      <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>Склад
  </a>
  <a href="stats.php" class="bnav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
      <line x1="6" y1="20" x2="6" y2="14"/></svg>Справки
  </a>
  <a href="sale.php" class="bnav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Продажба
  </a>
</nav>

<div class="toast" id="toast"></div>

<script>
// ── CONFIG ──
const P = <?= $periods_json ?>;
const CS = <?= json_encode($cs) ?>;
const IS_OWNER = <?= $role==='owner'?'true':'false' ?>;

function $(id){return document.getElementById(id)}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function fmt(n){return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.')}
function showToast(m){const t=$('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),3000)}

// ── STATE ──
let curPeriod = 'today';
let curMode   = 'rev';
let firstLoad = true;

// ── COUNT-UP ──
function countUp(target) {
    const el = $('bnumValue');
    if (target===0){el.textContent='0 '+CS;return}
    const dur=1000, t0=performance.now();
    function step(now){
        const p=Math.min((now-t0)/dur,1);
        const e=1-Math.pow(2,-10*p);
        el.textContent=fmt(target*e)+' '+CS;
        if(p<1)requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

// ── UPDATE BIG NUMBER ──
function updateBigNum() {
    const d = P[curPeriod];
    const val = curMode==='rev' ? d.rev : d.profit;

    if (firstLoad) {
        countUp(val);
        firstLoad = false;
    } else {
        $('bnumValue').textContent = fmt(val) + ' ' + CS;
    }

    const labels = {today:'ДНЕС', '7d':'7 ДНИ', '30d':'30 ДНИ', '365d':'365 ДНИ'};
    $('bnumLabel').textContent = labels[curPeriod];

    let sub = d.cnt + ' продажби';
    if (IS_OWNER && d.cnt>0) sub += ' · ' + d.margin + '% марж';
    $('bnumSub').textContent = sub;

    const cn = $('confNote');
    if (cn) cn.style.display = curMode==='profit' ? 'flex' : 'none';

    const mr=$('modeRev'), mp=$('modePro');
    if(mr) mr.classList.toggle('active', curMode==='rev');
    if(mp) mp.classList.toggle('active', curMode==='profit');
}

// ── UPDATE COMPARISON ──
function updateCmp() {
    const d = P[curPeriod];
    const pct  = curMode==='rev' ? d.cmp_rev  : d.cmp_prof;
    const sub  = curMode==='rev' ? d.sub_rev  : d.sub_prof;

    const pctEl = $('cmpPct');
    pctEl.textContent = (pct>=0?'+':'')+pct+'%';
    pctEl.className = 'cmp-pct ' + (pct>0?'up':pct<0?'down':'zero');

    $('cmpLabel').textContent = d.label;
    $('cmpSub').textContent   = sub + ' ' + CS;

    const bar = $('cmpBar');
    bar.className = 'cmp-bar-fill ' + (pct>0?'up':pct<0?'down':'zero');
    const w = 50 + (pct>=0 ? Math.min(pct,100)/2 : -Math.min(-pct,100)/2);
    setTimeout(()=>{bar.style.width=Math.max(5,Math.min(95,w))+'%'},50);
}

// ── PERIOD TAB ──
function setPeriod(period, idx) {
    curPeriod = period;
    $('perSlider').style.left = 'calc(' + idx + ' * 25% + 2px)';
    document.querySelectorAll('.period-tab').forEach((t,i)=>t.classList.toggle('active',i===idx));
    updateBigNum();
    updateCmp();
}

// ── MODE TOGGLE ──
function setMode(mode) {
    curMode = mode;
    updateBigNum();
    updateCmp();
}

// ── INIT ──
window.addEventListener('DOMContentLoaded', () => {
    setPeriod('today', 0);
});

// ── LOGOUT ──
function toggleLogout(){$('logoutDrop').classList.toggle('show')}
document.addEventListener('click',e=>{
    if(!$('logoutWrap').contains(e.target))$('logoutDrop').classList.remove('show')
})

// ── CHAT OVERLAY ──
function openChat() {
    $('chatOv').classList.add('open');
    history.pushState({chat:true},'');
    scrollBottom();
    setTimeout(()=>$('chatIn').focus(),300);
}
function closeChat() { $('chatOv').classList.remove('open'); stopVoice(); }
function scrollBottom() { const a=$('chatMsgs'); a.scrollTop=a.scrollHeight; }

// ── OPEN CHAT + AUTO-SEND ──
function openChatWithQuestion(q) {
    openChat();
    setTimeout(() => {
        $('chatIn').value = q;
        $('chatSend').disabled = false;
        sendMsg();
    }, 350);
}

// ── SEND MESSAGE ──
async function sendMsg() {
    const inp = $('chatIn');
    const txt = inp.value.trim();
    if (!txt) return;
    addUserBubble(txt);
    inp.value=''; inp.style.height='';
    $('chatSend').disabled=true;
    $('chatTyping').style.display='block';
    scrollBottom();
    try {
        const r = await fetch('chat-send.php',{
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({message:txt})
        });
        const d = await r.json();
        $('chatTyping').style.display='none';
        addAIBubble(d.reply || d.error || 'Грешка при обработка.');
    } catch(e) {
        $('chatTyping').style.display='none';
        addAIBubble('Грешка при свързване. Опитай пак.');
    }
}

function addUserBubble(txt) {
    const g=document.createElement('div'); g.className='chat-mg';
    const t=new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'});
    g.innerHTML=`<div class="chat-meta r">${t}</div><div class="chat-msg usr-msg">${esc(txt)}</div>`;
    $('chatMsgs').insertBefore(g,$('chatTyping')); scrollBottom();
}
function addAIBubble(txt) {
    const g=document.createElement('div'); g.className='chat-mg';
    const t=new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'});
    g.innerHTML=`<div class="chat-meta"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI · ${t}</div><div class="chat-msg ai-msg">${esc(txt)}</div>`;
    $('chatMsgs').insertBefore(g,$('chatTyping')); scrollBottom();
}

// ── VOICE ──
let vr=null, recording=false, vtr='';
function toggleVoice(){if(recording){stopVoice();return}
    const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Браузърът не поддържа гласов вход');return}
    recording=true; vtr='';
    $('chatVoice').classList.add('rec'); $('recBar').classList.add('on');
    $('recTr').innerText='Слушам...';
    vr=new SR(); vr.lang='bg-BG'; vr.continuous=false; vr.interimResults=true;
    vr.onresult=e=>{
        let fin='',int_='';
        for(let i=e.resultIndex;i<e.results.length;i++){
            if(e.results[i].isFinal) fin+=e.results[i][0].transcript;
            else int_+=e.results[i][0].transcript;
        }
        if(fin) vtr=fin;
        $('recTr').innerText=vtr||int_||'Слушам...';
    };
    vr.onend=()=>{
        recording=false;
        $('chatVoice').classList.remove('rec'); $('recBar').classList.remove('on');
        if(vtr){$('chatIn').value=vtr;$('chatSend').disabled=false;sendMsg();}
    };
    vr.onerror=e=>{
        stopVoice();
        if(e.error==='no-speech') showToast('Не чух — опитай пак');
        else if(e.error==='not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: '+e.error);
    };
    try{vr.start()}catch(e){stopVoice()}
}
function stopVoice(){
    recording=false; vtr='';
    $('chatVoice').classList.remove('rec'); $('recBar').classList.remove('on');
    if(vr){try{vr.stop()}catch(e){}; vr=null}
}

window.addEventListener('popstate',()=>{
    if($('chatOv').classList.contains('open')){closeChat();}
});
$('chatOv').addEventListener('click',e=>{if(e.target===$('chatOv'))closeChat()});
window.addEventListener('DOMContentLoaded',scrollBottom);
</script>
</body>
</html>
