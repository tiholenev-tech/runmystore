<?php
/**
 * chat.php — RunMyStore.ai Home Dashboard v5.0
 * S39 — Full rewrite: dashboard home screen (not chat)
 * Pure PHP + SQL, zero Gemini API calls
 * Design: stats.php 1:1 (dark indigo theme, SVG only, no emojis)
 */
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)$_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

// ── PLAN (from subscriptions) ──
$sub = DB::run(
    'SELECT plan, status FROM subscriptions WHERE tenant_id=? ORDER BY created_at DESC LIMIT 1',
    [$tenant_id]
)->fetch();
$plan = strtoupper($sub['plan'] ?? 'FREE');
if (!in_array($plan, ['FREE','BUSINESS','PRO','ENTERPRISE'])) $plan = 'FREE';

// ── CURRENCY ──
$cs = '€';

function fmtMoney($v, $cs) {
    return number_format($v, 0, ',', '.') . ' ' . $cs;
}

$store = DB::run('SELECT name FROM stores WHERE id=? LIMIT 1', [$store_id])->fetch();
$store_name = $store['name'] ?? '';

$is_night = (int)date('H') >= 20 || (int)date('H') < 8;

// ══════════════════════════════════════════════
// SECTION 1: TODAY'S REVENUE
// ══════════════════════════════════════════════
$rev_today = DB::run(
    'SELECT COALESCE(SUM(total),0) AS revenue, COUNT(*) AS cnt
     FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=CURDATE() AND status!="canceled"',
    [$tenant_id, $store_id]
)->fetch();
$today_revenue = (float)$rev_today['revenue'];
$today_count   = (int)$rev_today['cnt'];

// Margin (owner only)
$today_profit = 0;
$margin_pct = 0;
if ($role === 'owner' && $today_count > 0) {
    $margin_data = DB::run(
        'SELECT COALESCE(SUM(si.total - si.cost_price * si.quantity),0) AS profit
         FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"',
        [$tenant_id, $store_id]
    )->fetch();
    $today_profit = (float)$margin_data['profit'];
    $margin_pct = $today_revenue > 0 ? round($today_profit / $today_revenue * 100) : 0;
}

// ══════════════════════════════════════════════
// SECTION 2: COMPARISON DATA (7d / 30d / 365d)
// ══════════════════════════════════════════════
$dow_names_bg = ['','понеделник','вторник','сряда','четвъртък','петък','събота','неделя'];
$dow = (int)date('N');

// 7d: today vs same weekday last week
$prev_7d = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status!="canceled"',
    [$tenant_id, $store_id]
)->fetchColumn();

$cmp_7d_pct = $prev_7d > 0 ? round(($today_revenue - $prev_7d) / $prev_7d * 100) : 0;
$cmp_7d_label = 'Спрямо миналия ' . $dow_names_bg[$dow];
$cmp_7d_sub = fmtMoney(round($prev_7d), $cs) . ' → ' . fmtMoney(round($today_revenue), $cs);

// 30d: this week daily avg vs last week daily avg
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_week_days = max(1, (int)date('N'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end = date('Y-m-d', strtotime('sunday last week'));

$tw_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=CURDATE() AND status!="canceled"',
    [$tenant_id, $store_id, $this_week_start]
)->fetchColumn();

$lw_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
    [$tenant_id, $store_id, $last_week_start, $last_week_end]
)->fetchColumn();

$tw_avg = $tw_rev / $this_week_days;
$lw_avg = $lw_rev / 7;
$cmp_30d_pct = $lw_avg > 0 ? round(($tw_avg - $lw_avg) / $lw_avg * 100) : 0;
$cmp_30d_label = 'Тази vs миналата седмица (ср. ден)';
$cmp_30d_sub = fmtMoney(round($lw_avg), $cs) . '/ден → ' . fmtMoney(round($tw_avg), $cs) . '/ден';

// 365d: this month daily avg vs same month last year daily avg
$month_start = date('Y-m-01');
$month_day = max(1, (int)date('j'));
$ly_month_start = date('Y-m-01', strtotime('-1 year'));
$ly_month_end = date('Y-m-t', strtotime('-1 year'));
$ly_month_days = max(1, (int)date('t', strtotime('-1 year')));

$tm_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=CURDATE() AND status!="canceled"',
    [$tenant_id, $store_id, $month_start]
)->fetchColumn();

$lym_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
    [$tenant_id, $store_id, $ly_month_start, $ly_month_end]
)->fetchColumn();

$tm_avg = $tm_rev / $month_day;
$lym_avg = $lym_rev / $ly_month_days;
$cmp_365d_pct = $lym_avg > 0 ? round(($tm_avg - $lym_avg) / $lym_avg * 100) : 0;
$cmp_365d_label = 'Този месец vs ' . date('F Y', strtotime('-1 year')) . ' (ср. ден)';
$cmp_365d_sub = fmtMoney(round($lym_avg), $cs) . '/ден → ' . fmtMoney(round($tm_avg), $cs) . '/ден';

// AI comparison text (follows S38 philosophy: fact + soft suggestion, no predictions)
$top_today = DB::run(
    'SELECT p.name, SUM(si.quantity) AS qty FROM sale_items si
     JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
     WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"
     GROUP BY si.product_id ORDER BY qty DESC LIMIT 1',
    [$tenant_id, $store_id]
)->fetch();

$ai_cmp_text = '';
if ($today_count > 0 && $top_today) {
    $tname = $top_today['name'];
    $tqty = (int)$top_today['qty'];
    if ($cmp_7d_pct > 0) {
        $ai_cmp_text = "{$tname} дърпа — {$tqty} продажби днес. Помисли за наличността.";
    } elseif ($cmp_7d_pct < -15 && (int)date('H') >= 14) {
        $ai_cmp_text = $dow_names_bg[$dow] . " е по-слаб от обичайно. " . fmtMoney(round($prev_7d), $cs) . " миналата седмица.";
    } else {
        $ai_cmp_text = "Топ артикул днес: {$tname} ({$tqty} бр.)";
    }
} elseif ($today_count === 0) {
    $ai_cmp_text = 'Няма продажби днес.';
}

// Pack comparison data for JS
$comparisons_json = json_encode([
    '7d'   => ['pct'=>$cmp_7d_pct,   'label'=>$cmp_7d_label,   'sub'=>$cmp_7d_sub,   'prev'=>round($prev_7d), 'curr'=>round($today_revenue)],
    '30d'  => ['pct'=>$cmp_30d_pct,  'label'=>$cmp_30d_label,  'sub'=>$cmp_30d_sub,  'prev'=>round($lw_avg),  'curr'=>round($tw_avg)],
    '365d' => ['pct'=>$cmp_365d_pct, 'label'=>$cmp_365d_label, 'sub'=>$cmp_365d_sub, 'prev'=>round($lym_avg), 'curr'=>round($tm_avg)],
], JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════
// SECTION 3: LOSS CHIPS
// ══════════════════════════════════════════════
$chips = [];

if (!$is_night) {
    // 1. Zero stock on items with sales in last 30 days → RED
    $zero_stock = (int)DB::run(
        'SELECT COUNT(DISTINCT p.id) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity=0
         AND p.id IN (SELECT si.product_id FROM sale_items si JOIN sales s ON s.id=si.sale_id
                      WHERE s.tenant_id=? AND s.store_id=? AND s.status!="canceled"
                      AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))',
        [$store_id, $tenant_id, $tenant_id, $store_id]
    )->fetchColumn();
    if ($zero_stock > 0) {
        $chips[] = ['type'=>'red','num'=>$zero_stock,'text'=>'на нула','link'=>'products.php?filter=zero'];
    }

    // 2. Invoices due within 3 days → BLUE (owner/manager only)
    if ($role !== 'seller') {
        try {
            $due_invoices = DB::run(
                'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM invoices
                 WHERE tenant_id=? AND status="unpaid" AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)',
                [$tenant_id]
            )->fetch();
            $due_cnt = (int)($due_invoices['cnt'] ?? 0);
            if ($due_cnt > 0) {
                $chips[] = ['type'=>'blue','num'=>fmtMoney(round((float)$due_invoices['total']), $cs),'text'=>'падеж','link'=>'#'];
            }
        } catch (Exception $e) { /* invoices table may not exist yet */ }
    }

    // 3. Items sold below cost → RED (owner only)
    if ($role === 'owner') {
        $below_cost = (int)DB::run(
            'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0 AND retail_price < cost_price',
            [$tenant_id]
        )->fetchColumn();
        if ($below_cost > 0) {
            $chips[] = ['type'=>'red','num'=>$below_cost,'text'=>'под цена','link'=>'products.php'];
        }
    }

    // 4. Zombie stock (45+ days, quantity > 0) → YELLOW
    $zombie = DB::run(
        'SELECT COUNT(*) AS cnt, COALESCE(SUM(i.quantity*COALESCE(p.cost_price,p.retail_price*0.6)),0) AS val
         FROM inventory i JOIN products p ON p.id=i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.quantity>0 AND p.is_active=1 AND p.parent_id IS NULL
         AND DATEDIFF(NOW(),COALESCE(
            (SELECT MAX(s2.created_at) FROM sales s2 JOIN sale_items si2 ON si2.sale_id=s2.id
             WHERE si2.product_id=p.id AND s2.store_id=i.store_id AND s2.status!="canceled"),
            p.created_at))>=45',
        [$store_id, $tenant_id]
    )->fetch();
    $zombie_cnt = (int)$zombie['cnt'];
    $zombie_val = round((float)$zombie['val']);
    if ($zombie_cnt > 0) {
        $chips[] = ['type'=>'yellow','num'=>fmtMoney($zombie_val, $cs),'text'=>'zombie','link'=>'products.php?filter=zombie'];
    }

    // 5. Low inventory (quantity <= min_quantity) → YELLOW
    $low_cnt = (int)DB::run(
        'SELECT COUNT(*) FROM inventory i JOIN products p ON p.id=i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.quantity<=i.min_quantity AND i.min_quantity>0 AND p.is_active=1',
        [$store_id, $tenant_id]
    )->fetchColumn();
    if ($low_cnt > 0) {
        $chips[] = ['type'=>'yellow','num'=>$low_cnt,'text'=>'ниски','link'=>'products.php?filter=low'];
    }
}

// ══════════════════════════════════════════════
// SECTION 4: BADGE COUNTS FOR 4 BUTTONS
// ══════════════════════════════════════════════
// Deliveries badge = unpaid invoices
$badge_delivery = 0;
if ($role !== 'seller') {
    try {
        $badge_delivery = (int)DB::run(
            'SELECT COUNT(*) FROM invoices WHERE tenant_id=? AND status="unpaid"',
            [$tenant_id]
        )->fetchColumn();
    } catch (Exception $e) {}
}

// Products badge = zero stock items with recent sales
$badge_products = $zero_stock ?? 0;

// ══════════════════════════════════════════════
// SECTION 5: SIGNAL CARDS
// ══════════════════════════════════════════════
$signals = [];

if (!$is_night) {
    // RED: Zero stock on top items
    if ($zero_stock > 0) {
        $zero_names = DB::run(
            'SELECT p.name FROM products p
             JOIN inventory i ON i.product_id=p.id AND i.store_id=?
             WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity=0
             AND p.id IN (SELECT si.product_id FROM sale_items si JOIN sales s ON s.id=si.sale_id
                          WHERE s.tenant_id=? AND s.store_id=? AND s.status!="canceled"
                          AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
             ORDER BY (SELECT SUM(si2.quantity) FROM sale_items si2 JOIN sales s2 ON s2.id=si2.sale_id
                       WHERE si2.product_id=p.id AND s2.store_id=? AND s2.status!="canceled"
                       AND s2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) DESC
             LIMIT 5',
            [$store_id, $tenant_id, $tenant_id, $store_id, $store_id]
        )->fetchAll(PDO::FETCH_COLUMN);
        $signals[] = ['color'=>'red','title'=>'Нулева наличност','desc'=>$zero_stock . ' топ артикула на 0 бр.','link'=>'products.php?filter=zero','pulse'=>true];
    }

    // RED: Sold below cost (owner)
    if ($role === 'owner' && isset($below_cost) && $below_cost > 0) {
        $signals[] = ['color'=>'red','title'=>'Под себестойност','desc'=>$below_cost . ' артикула се продават под цена.','link'=>'products.php','pulse'=>true];
    }

    // YELLOW: Zombie stock
    if ($zombie_cnt > 0) {
        $signals[] = ['color'=>'yellow','title'=>'Zombie стока','desc'=>$zombie_cnt . ' арт. стоят 45+ дни. Стойност ' . fmtMoney($zombie_val, $cs) . '.','link'=>'products.php?filter=zombie','pulse'=>false];
    }

    // YELLOW: Low inventory
    if ($low_cnt > 0) {
        $signals[] = ['color'=>'yellow','title'=>'Ниски наличности','desc'=>$low_cnt . ' артикула под минимума.','link'=>'products.php?filter=low','pulse'=>false];
    }

    // BLUE: Invoices due (owner/manager)
    if ($role !== 'seller' && isset($due_cnt) && $due_cnt > 0) {
        $signals[] = ['color'=>'blue','title'=>'Фактури с падеж','desc'=>$due_cnt . ' фактури с падеж до 3 дни.','link'=>'#','pulse'=>false];
    }

    // BLUE: Pending deliveries (owner/manager)
    if ($role !== 'seller') {
        try {
            $pending_del = (int)DB::run(
                'SELECT COUNT(*) FROM deliveries WHERE tenant_id=? AND status="pending"',
                [$tenant_id]
            )->fetchColumn();
            if ($pending_del > 0) {
                $signals[] = ['color'=>'blue','title'=>'Доставки в път','desc'=>$pending_del . ' доставки чакат приемане.','link'=>'#','pulse'=>false];
            }
        } catch (Exception $e) {}
    }
}

// ══════════════════════════════════════════════
// SECTION 6: PLAN BADGE COLORS
// ══════════════════════════════════════════════
$plan_colors = match($plan) {
    'FREE'       => ['bg'=>'rgba(107,114,128,.15)','border'=>'rgba(107,114,128,.3)','text'=>'#9ca3af'],
    'BUSINESS'   => ['bg'=>'rgba(99,102,241,.15)','border'=>'rgba(99,102,241,.3)','text'=>'#818cf8'],
    'PRO'        => ['bg'=>'rgba(168,85,247,.15)','border'=>'rgba(168,85,247,.3)','text'=>'#c084fc'],
    'ENTERPRISE' => ['bg'=>'rgba(234,179,8,.15)','border'=>'rgba(234,179,8,.3)','text'=>'#fbbf24'],
    default      => ['bg'=>'rgba(107,114,128,.15)','border'=>'rgba(107,114,128,.3)','text'=>'#9ca3af'],
};
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#030712">
<title>RunMyStore.ai</title>
<style>
/* ══════════════════════════════════════════════
   DESIGN SYSTEM — stats.php 1:1
   ══════════════════════════════════════════════ */
:root{
    --bg-main:#030712;--bg-card:rgba(15,15,40,0.75);
    --border-subtle:rgba(99,102,241,0.15);--border-glow:rgba(99,102,241,0.4);
    --indigo-600:#4f46e5;--indigo-500:#6366f1;--indigo-400:#818cf8;--indigo-300:#a5b4fc;
    --text-primary:#f1f5f9;--text-secondary:#6b7280;
    --danger:#ef4444;--warning:#fbbf24;--success:#22c55e;
    --nav-h:56px
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg-main)}
body{background:var(--bg-main);color:var(--text-primary);
    font-family:'Montserrat',Inter,system-ui,sans-serif;
    height:100dvh;display:flex;flex-direction:column;overflow:hidden;
    padding-bottom:var(--nav-h)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:600px;height:350px;
    background:radial-gradient(ellipse,rgba(99,102,241,.1) 0%,transparent 70%);
    pointer-events:none;z-index:0}

/* ══ SCROLLABLE AREA ══ */
.main-scroll{flex:1;overflow-y:auto;overflow-x:hidden;position:relative;z-index:1;
    -webkit-overflow-scrolling:touch;scrollbar-width:none}
.main-scroll::-webkit-scrollbar{display:none}

/* ══ HEADER ══ */
.hdr{position:sticky;top:0;z-index:50;padding:10px 14px 0;
    background:rgba(3,7,18,.93);backdrop-filter:blur(16px)}
.hdr-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.brand{font-size:11px;font-weight:700;color:rgba(165,180,252,.6);letter-spacing:.3px}
.hdr-right{display:flex;align-items:center;gap:6px}

/* Plan pill */
.plan-pill{padding:3px 8px;border-radius:20px;font-size:9px;font-weight:800;letter-spacing:.5px;
    background:<?= $plan_colors['bg'] ?>;border:1px solid <?= $plan_colors['border'] ?>;color:<?= $plan_colors['text'] ?>}

/* Settings button */
.set-btn{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:background .2s}
.set-btn:active{background:rgba(99,102,241,.3)}
.set-btn svg{width:14px;height:14px;color:var(--indigo-400)}

/* ══ LOSS CHIPS ══ */
.lchip-row{display:flex;gap:6px;padding:6px 14px 10px;overflow-x:auto;scrollbar-width:none;flex-shrink:0}
.lchip-row::-webkit-scrollbar{display:none}
.lchip{display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:10px;flex-shrink:0;
    cursor:pointer;white-space:nowrap;text-decoration:none;transition:transform .1s}
.lchip:active{transform:scale(.95)}
.lchip .ln{font-size:12px;font-weight:800}
.lchip .lt{font-size:10px;font-weight:600}
.lchip-red{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25)}
.lchip-red .ln{color:#f87171}.lchip-red .lt{color:rgba(248,113,113,.7)}
.lchip-yellow{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25)}
.lchip-yellow .ln{color:#fbbf24}.lchip-yellow .lt{color:rgba(251,191,36,.7)}
.lchip-blue{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25)}
.lchip-blue .ln{color:#818cf8}.lchip-blue .lt{color:rgba(129,140,248,.7)}

/* ══ BIG NUMBER ══ */
.big-num-wrap{text-align:center;padding:16px 14px 10px}
.big-label{font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-secondary);letter-spacing:1.5px;margin-bottom:4px}
.big-num{font-size:46px;font-weight:900;line-height:1;
    background:linear-gradient(90deg,#818cf8,#c7d2fe,#818cf8);background-size:200% auto;
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    animation:shimtxt 3s linear infinite}
@keyframes shimtxt{0%{background-position:-200% center}100%{background-position:200% center}}
.big-sub{font-size:12px;color:var(--text-secondary);margin-top:4px}

/* ══ COMPARISON BAR ══ */
.cmp-card{margin:0 14px 12px;background:var(--bg-card);border:1px solid var(--border-subtle);
    border-radius:16px;padding:14px;backdrop-filter:blur(12px);position:relative;overflow:hidden}
.cmp-card::before{content:'';position:absolute;inset:0;border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);pointer-events:none}
.cmp-top{display:flex;align-items:center;gap:14px;margin-bottom:10px;position:relative}
.cmp-pct{font-size:26px;font-weight:900;line-height:1;flex-shrink:0}
.cmp-pct.up{color:#4ade80}.cmp-pct.down{color:#f87171}.cmp-pct.zero{color:var(--text-secondary)}
.cmp-info{flex:1;min-width:0}
.cmp-label{font-size:11px;font-weight:600;color:#d1d5db}
.cmp-sub{font-size:10px;color:var(--text-secondary);margin-top:2px}

/* Progress bar */
.cmp-bar-track{height:5px;border-radius:3px;background:rgba(255,255,255,.06);margin-bottom:10px;overflow:hidden;position:relative}
.cmp-bar-fill{height:100%;border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.cmp-bar-fill.up{background:linear-gradient(90deg,#22c55e,#4ade80)}
.cmp-bar-fill.down{background:linear-gradient(90deg,#ef4444,#f87171)}
.cmp-bar-fill.zero{background:var(--text-secondary)}

/* AI explanation */
.cmp-ai{display:flex;gap:8px;align-items:flex-start;margin-bottom:12px;position:relative}
.cmp-ai-badge{flex-shrink:0;padding:2px 6px;border-radius:6px;font-size:8px;font-weight:800;
    color:var(--indigo-400);background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);letter-spacing:.5px}
.cmp-ai-text{font-size:11px;color:#d1d5db;line-height:1.5}

/* Period selector — sliding tabs */
.period-tabs{display:flex;position:relative;background:rgba(15,15,40,.4);
    border:1px solid rgba(99,102,241,.1);border-radius:14px;overflow:hidden}
.period-slider{position:absolute;top:2px;bottom:2px;left:2px;width:calc(33.33% - 2px);border-radius:12px;
    background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(139,92,246,.15));
    border:1px solid rgba(99,102,241,.3);transition:left .35s cubic-bezier(.4,0,.2,1)}
.period-tab{flex:1;text-align:center;padding:10px 0;font-size:11px;font-weight:700;color:#4b5563;
    cursor:pointer;position:relative;z-index:1;display:flex;align-items:center;justify-content:center;gap:5px;
    transition:color .3s}
.period-tab.active{color:#e2e8f0}
.period-tab svg{width:13px;height:13px;opacity:.5;transition:opacity .3s}
.period-tab.active svg{opacity:1}

/* ══ 4 MAIN BUTTONS ══ */
.btn-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 14px;margin-bottom:14px}
.main-btn{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:16px;
    padding:20px 14px 16px;cursor:pointer;position:relative;overflow:hidden;backdrop-filter:blur(12px);
    text-decoration:none;display:block;transition:all .25s}
.main-btn:active{transform:scale(.97)}
.main-btn:hover{border-color:var(--border-glow);box-shadow:0 0 28px rgba(99,102,241,.18)}
.main-btn::before{content:'';position:absolute;inset:0;border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);pointer-events:none}
.main-btn-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;
    margin-bottom:10px;position:relative}
.main-btn-icon svg{width:20px;height:20px}
.main-btn-name{font-size:13px;font-weight:700;color:var(--text-primary);position:relative}
.main-btn-desc{font-size:10px;color:var(--text-secondary);margin-top:2px;position:relative}

/* Button badge */
.bdg{position:absolute;top:8px;right:10px;background:rgba(239,68,68,.15);color:#ef4444;
    font-size:10px;font-weight:800;border-radius:10px;min-width:18px;height:18px;
    display:flex;align-items:center;justify-content:center;padding:0 5px;
    animation:pulseR 2s infinite;z-index:2}
@keyframes pulseR{0%,100%{opacity:1;box-shadow:0 0 4px rgba(239,68,68,.3)}50%{opacity:.7;box-shadow:0 0 12px rgba(239,68,68,.6)}}

/* ══ INDIGO SEPARATOR ══ */
.indigo-sep{height:1px;margin:0 14px;background:linear-gradient(to right,transparent,rgba(99,102,241,.25),transparent)}

/* ══ SIGNALS ══ */
.sig-section{padding:14px}
.sig-title{font-size:10px;font-weight:700;color:var(--indigo-400);text-transform:uppercase;
    letter-spacing:1px;margin-bottom:10px}
.sig{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;
    background:var(--bg-card);backdrop-filter:blur(12px);margin-bottom:8px;cursor:pointer;
    transition:transform .15s;text-decoration:none}
.sig:active{transform:scale(.98)}
.sdot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.sig-body{flex:1;min-width:0}
.sig-name{font-size:12px;font-weight:700}
.sig-desc{font-size:12px;color:#d1d5db;margin-top:2px}
.sig-link{color:var(--indigo-400);font-weight:600;white-space:nowrap}

/* Signal colors */
.sig-red{border:1px solid rgba(239,68,68,.2)}
.sig-red .sig-name{color:#fca5a5}
.sig-red .sdot{background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,.6)}
.sig-yellow{border:1px solid rgba(251,191,36,.2)}
.sig-yellow .sig-name{color:#fcd34d}
.sig-yellow .sdot{background:#fbbf24;box-shadow:0 0 8px rgba(251,191,36,.6)}
.sig-blue{border:1px solid rgba(99,102,241,.2)}
.sig-blue .sig-name{color:#a5b4fc}
.sig-blue .sdot{background:#818cf8;box-shadow:0 0 8px rgba(99,102,241,.6)}

/* ══ ASK AI BUTTON ══ */
.ai-ask-wrap{padding:16px 14px 20px;display:flex;justify-content:center}
.ai-ask-btn{display:flex;align-items:center;gap:12px;padding:10px 24px;border-radius:20px;
    background:rgba(15,15,40,.75);border:1px solid rgba(99,102,241,.2);cursor:pointer;
    backdrop-filter:blur(12px);transition:all .25s}
.ai-ask-btn:hover{border-color:rgba(99,102,241,.5);box-shadow:0 0 24px rgba(99,102,241,.2)}
.ai-ask-btn:active{transform:scale(.97)}
.ai-ask-btn span{font-size:13px;font-weight:600;color:#a5b4fc}
.ai-waves{display:flex;align-items:flex-end;gap:2px;height:18px}
.ai-wave-bar{width:3px;border-radius:2px;background:currentColor;animation:wv 1s ease-in-out infinite}
@keyframes wv{0%,100%{transform:scaleY(.35)}50%{transform:scaleY(1)}}

/* ══ VOICE OVERLAY ══ */
.rec-ov{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,.6);backdrop-filter:blur(8px);
    display:none;align-items:flex-end;justify-content:center;padding:0 16px 80px}
.rec-ov.open{display:flex}
.rec-box{width:100%;max-width:400px;background:rgba(15,15,40,.95);border:1px solid var(--border-glow);
    border-radius:20px;padding:18px;box-shadow:0 -12px 50px rgba(99,102,241,.25);animation:recSlideUp .25s ease}
.rec-status{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.rec-dot{width:14px;height:14px;border-radius:50%;background:#ef4444;flex-shrink:0;
    box-shadow:0 0 10px #ef4444;animation:recPulse 1s ease infinite}
.rec-dot.ready{background:#22c55e;box-shadow:0 0 10px #22c55e;animation:none}
.rec-label{font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px}
.rec-label.recording{color:#ef4444}
.rec-label.ready{color:#22c55e}
.rec-transcript{min-height:40px;padding:9px 12px;margin-bottom:10px;
    background:rgba(99,102,241,.06);border:1px solid var(--border-subtle);
    border-radius:11px;font-size:14px;font-weight:500;color:var(--text-primary);line-height:1.4;word-wrap:break-word}
.rec-transcript.empty{color:var(--text-secondary);font-style:italic}
.rec-hint{font-size:11px;color:var(--text-secondary);margin-bottom:12px;text-align:center}
.rec-actions{display:flex;gap:8px}
.rec-btn-cancel{flex:1;height:40px;border-radius:11px;border:.5px solid var(--border-subtle);
    background:var(--bg-card);color:var(--indigo-300);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.rec-btn-send{flex:2;height:40px;border-radius:11px;border:none;
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
    box-shadow:0 4px 14px rgba(99,102,241,.35)}
.rec-btn-send:disabled{opacity:.3;pointer-events:none}
.rec-close-hint{font-size:10px;color:rgba(107,114,128,.6);text-align:center;margin-top:8px}

/* ══ AI RESPONSE DRAWER ══ */
.ai-resp-ovl{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);
    z-index:300;display:none;align-items:flex-end}
.ai-resp-ovl.show{display:flex}
.ai-resp-box{width:100%;background:#080818;border-top:.5px solid var(--border-glow);
    border-radius:22px 22px 0 0;padding:0 16px 32px;
    transform:translateY(100%);transition:transform .3s cubic-bezier(.32,0,.67,0);
    max-height:72vh;overflow-y:auto;box-shadow:0 -20px 60px rgba(99,102,241,.2)}
.ai-resp-ovl.show .ai-resp-box{transform:translateY(0)}
.ai-resp-handle{width:30px;height:3px;background:rgba(99,102,241,.3);border-radius:2px;margin:11px auto 14px}
.ai-resp-header{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.ai-resp-ava{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);
    display:flex;align-items:center;justify-content:center}
.ai-resp-title{font-size:13px;font-weight:700;color:var(--indigo-300)}
.ai-resp-text{font-size:14px;color:var(--text-primary);line-height:1.6;padding:14px;
    background:rgba(99,102,241,.06);border:1px solid var(--border-subtle);border-radius:14px}
.ai-resp-close{display:block;width:100%;margin-top:14px;padding:13px;
    background:rgba(99,102,241,.12);border:1px solid var(--border-subtle);
    border-radius:14px;color:var(--indigo-300);font-size:13px;font-weight:600;
    text-align:center;cursor:pointer;font-family:inherit}

/* ══ BOTTOM NAV ══ */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.95);
    backdrop-filter:blur(15px);border-top:.5px solid rgba(99,102,241,.2);display:flex;z-index:100;
    box-shadow:0 -4px 20px rgba(99,102,241,.1)}
.btab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
    font-size:9px;font-weight:600;color:rgba(165,180,252,.4);text-decoration:none;transition:all .3s}
.btab.active{color:#c7d2fe;text-shadow:0 0 10px rgba(129,140,248,.8)}
.btab svg{width:18px;height:18px;transition:all .3s}
.btab.active svg{filter:drop-shadow(0 0 7px rgba(129,140,248,.8))}

/* ══ TOAST ══ */
.toast{position:fixed;bottom:65px;left:50%;transform:translateX(-50%);
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    color:#fff;padding:7px 16px;border-radius:20px;font-size:11px;font-weight:600;z-index:500;
    opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

/* ══ ANIMATIONS ══ */
@keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
@keyframes recPulse{0%,100%{opacity:1;box-shadow:0 0 8px #ef4444}50%{opacity:.5;box-shadow:0 0 20px #ef4444}}
@keyframes recSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}
@keyframes countUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>

<!-- ══ MAIN SCROLLABLE AREA ══ -->
<div class="main-scroll" id="mainScroll">

<!-- ══ HEADER (sticky) ══ -->
<div class="hdr">
  <div class="hdr-top">
    <span class="brand">RUNMYSTORE.AI</span>
    <div class="hdr-right">
      <span class="plan-pill"><?= htmlspecialchars($plan) ?></span>
      <div class="set-btn" onclick="location.href='settings.php'" title="Настройки">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
      </div>
    </div>
  </div>

  <!-- Loss Chips (hidden at night or if no issues) -->
  <?php if (!empty($chips)): ?>
  <div class="lchip-row">
    <?php foreach ($chips as $ch): ?>
    <a href="<?= htmlspecialchars($ch['link']) ?>" class="lchip lchip-<?= $ch['type'] ?>">
      <span class="ln"><?= htmlspecialchars((string)$ch['num']) ?></span>
      <span class="lt"><?= htmlspecialchars($ch['text']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══ BIG NUMBER — TODAY'S REVENUE ══ -->
<div class="big-num-wrap" style="animation:cardIn .5s ease both">
  <div class="big-label">ДНЕС</div>
  <div class="big-num" id="bigNum">0 <?= htmlspecialchars($cs) ?></div>
  <div class="big-sub">
    <?= $today_count ?> продажби<?php if ($role === 'owner' && $today_count > 0): ?> · <?= $margin_pct ?>% марж<?php endif; ?>
  </div>
</div>

<!-- ══ COMPARISON BAR ══ -->
<div class="cmp-card" style="animation:cardIn .5s .15s ease both">
  <!-- Percentage + label -->
  <div class="cmp-top">
    <div class="cmp-pct <?= $cmp_7d_pct >= 0 ? ($cmp_7d_pct > 0 ? 'up' : 'zero') : 'down' ?>" id="cmpPct">
      <?= ($cmp_7d_pct >= 0 ? '+' : '') . $cmp_7d_pct ?>%
    </div>
    <div class="cmp-info">
      <div class="cmp-label" id="cmpLabel"><?= htmlspecialchars($cmp_7d_label) ?></div>
      <div class="cmp-sub" id="cmpSub"><?= htmlspecialchars($cmp_7d_sub) ?></div>
    </div>
  </div>

  <!-- Progress bar -->
  <div class="cmp-bar-track">
    <div class="cmp-bar-fill <?= $cmp_7d_pct >= 0 ? ($cmp_7d_pct > 0 ? 'up' : 'zero') : 'down' ?>"
         id="cmpBar" style="width:0%"></div>
  </div>

  <!-- AI explanation -->
  <?php if ($ai_cmp_text): ?>
  <div class="cmp-ai">
    <span class="cmp-ai-badge">AI</span>
    <div class="cmp-ai-text" id="cmpAiText"><?= htmlspecialchars($ai_cmp_text) ?></div>
  </div>
  <?php endif; ?>

  <!-- Period tabs -->
  <div class="period-tabs" id="periodTabs">
    <div class="period-slider" id="periodSlider"></div>
    <div class="period-tab active" data-period="7d" onclick="setPeriod('7d',0)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      7 дни
    </div>
    <div class="period-tab" data-period="30d" onclick="setPeriod('30d',1)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      30 дни
    </div>
    <div class="period-tab" data-period="365d" onclick="setPeriod('365d',2)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 9l-5 5-2-2-4 4"/></svg>
      365 дни
    </div>
  </div>
</div>

<!-- ══ 4 MAIN BUTTONS ══ -->
<div class="btn-grid">
  <!-- Продажба (green) -->
  <a href="sale.php" class="main-btn" style="animation:cardIn .4s .25s ease both">
    <div class="main-btn-icon" style="background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:1px solid rgba(34,197,94,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/><path d="M6 14h4"/></svg>
    </div>
    <div class="main-btn-name">Продажба</div>
    <div class="main-btn-desc">Каса и скенер</div>
  </a>

  <!-- Поръчка (indigo) -->
  <a href="#" class="main-btn" style="animation:cardIn .4s .35s ease both">
    <div class="main-btn-icon" style="background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(99,102,241,.04));border:1px solid rgba(99,102,241,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div class="main-btn-name">Поръчка</div>
    <div class="main-btn-desc">Към доставчици</div>
  </a>

  <!-- Доставка (amber) + badge -->
  <a href="#" class="main-btn" style="animation:cardIn .4s .45s ease both">
    <?php if ($badge_delivery > 0): ?>
    <div class="bdg"><?= $badge_delivery ?></div>
    <?php endif; ?>
    <div class="main-btn-icon" style="background:linear-gradient(135deg,rgba(251,191,36,.12),rgba(251,191,36,.04));border:1px solid rgba(251,191,36,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
    </div>
    <div class="main-btn-name">Доставка</div>
    <div class="main-btn-desc">Приемане на стока</div>
  </a>

  <!-- Артикули (purple) + badge -->
  <a href="products.php" class="main-btn" style="animation:cardIn .4s .55s ease both">
    <?php if ($badge_products > 0): ?>
    <div class="bdg"><?= $badge_products ?></div>
    <?php endif; ?>
    <div class="main-btn-icon" style="background:linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.04));border:1px solid rgba(192,132,252,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#c084fc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
        <circle cx="7" cy="7" r="1.5" fill="#c084fc"/></svg>
    </div>
    <div class="main-btn-name">Артикули</div>
    <div class="main-btn-desc">Добави и редактирай</div>
  </a>
</div>

<!-- ══ INDIGO SEPARATOR ══ -->
<?php if (!empty($signals)): ?>
<div class="indigo-sep"></div>

<!-- ══ SIGNAL CARDS ══ -->
<div class="sig-section">
  <div class="sig-title">СИГНАЛИ</div>
  <?php foreach ($signals as $si => $sig):
    $delay = $si * 80;
    $cls = 'sig-' . $sig['color'];
    $dotStyle = $sig['pulse'] ? 'animation:dotPulse 2s infinite' : '';
  ?>
  <a href="<?= htmlspecialchars($sig['link']) ?>" class="sig <?= $cls ?>" style="animation:cardIn .4s <?= $delay ?>ms ease both">
    <div class="sdot" style="<?= $dotStyle ?>"></div>
    <div class="sig-body">
      <div class="sig-name"><?= htmlspecialchars($sig['title']) ?></div>
      <div class="sig-desc"><?= htmlspecialchars($sig['desc']) ?> <span class="sig-link">Виж ги →</span></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ ASK AI BUTTON ══ -->
<div class="ai-ask-wrap">
  <div class="ai-ask-btn" onclick="openVoice()">
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

<!-- spacer for bottom nav -->
<div style="height:20px"></div>

</div><!-- /.main-scroll -->

<!-- ══ VOICE OVERLAY ══ -->
<div class="rec-ov" id="recOv">
  <div class="rec-box">
    <div class="rec-status">
      <div class="rec-dot" id="recDot"></div>
      <span class="rec-label recording" id="recLabel">● ЗАПИСВА</span>
    </div>
    <div class="rec-transcript empty" id="recTranscript">Слушам...</div>
    <div class="rec-hint" id="recHint">Говори свободно на български</div>
    <div class="rec-actions">
      <button class="rec-btn-cancel" onclick="stopVoice()">Затвори</button>
      <button class="rec-btn-send" id="recSendBtn" onclick="sendVoice()" disabled>Изпрати →</button>
    </div>
    <div class="rec-close-hint">Натисни навсякъде извън прозореца за затваряне</div>
  </div>
</div>

<!-- ══ AI RESPONSE DRAWER ══ -->
<div class="ai-resp-ovl" id="aiRespOvl" onclick="closeAiResp()">
  <div class="ai-resp-box" onclick="event.stopPropagation()">
    <div class="ai-resp-handle"></div>
    <div class="ai-resp-header">
      <div class="ai-resp-ava">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
      </div>
      <span class="ai-resp-title">AI</span>
    </div>
    <div class="ai-resp-text" id="aiRespText"></div>
    <button class="ai-resp-close" onclick="closeAiResp()">Затвори</button>
  </div>
</div>

<!-- ══ BOTTOM NAV ══ -->
<nav class="bnav">
  <a href="chat.php" class="btab active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </svg>
    AI
  </a>
  <a href="warehouse.php" class="btab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
      <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>
    </svg>
    Склад
  </a>
  <a href="stats.php" class="btab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
    </svg>
    Справки
  </a>
  <a href="sale.php" class="btab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
    Продажба
  </a>
</nav>

<div class="toast" id="toast"></div>
<script>
// ══════════════════════════════════════════════
// CONFIGURATION
// ══════════════════════════════════════════════
const COMPARISONS = <?= $comparisons_json ?>;
const TODAY_REVENUE = <?= round($today_revenue) ?>;
const CURRENCY = <?= json_encode($cs) ?>;

// ══════════════════════════════════════════════
// COUNT-UP ANIMATION FOR BIG NUMBER
// ══════════════════════════════════════════════
(function(){
    const el = document.getElementById('bigNum');
    const target = TODAY_REVENUE;
    if (target === 0) { el.textContent = '0 ' + CURRENCY; return; }
    const duration = 1200;
    const start = performance.now();
    function step(now) {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        // easeOutExpo
        const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        const current = Math.round(target * eased);
        el.textContent = formatNum(current) + ' ' + CURRENCY;
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
})();

function formatNum(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// ══════════════════════════════════════════════
// COMPARISON BAR — ANIMATE ON LOAD + PERIOD SWITCH
// ══════════════════════════════════════════════
let currentPeriod = '7d';

function setPeriod(period, idx) {
    currentPeriod = period;
    // Move slider
    const slider = document.getElementById('periodSlider');
    slider.style.left = (idx * 33.33 + 0.5) + '%';

    // Update active tab
    document.querySelectorAll('.period-tab').forEach((t, i) => {
        t.classList.toggle('active', i === idx);
    });

    // Update comparison data
    const d = COMPARISONS[period];
    if (!d) return;

    const pctEl = document.getElementById('cmpPct');
    const sign = d.pct >= 0 ? '+' : '';
    pctEl.textContent = sign + d.pct + '%';
    pctEl.className = 'cmp-pct ' + (d.pct > 0 ? 'up' : (d.pct < 0 ? 'down' : 'zero'));

    document.getElementById('cmpLabel').textContent = d.label;
    document.getElementById('cmpSub').textContent = d.sub;

    // Progress bar
    const bar = document.getElementById('cmpBar');
    bar.className = 'cmp-bar-fill ' + (d.pct > 0 ? 'up' : (d.pct < 0 ? 'down' : 'zero'));
    const maxPct = Math.min(Math.abs(d.pct), 100);
    const barWidth = 50 + (d.pct >= 0 ? maxPct / 2 : -(maxPct / 2));
    setTimeout(() => { bar.style.width = Math.max(5, Math.min(95, barWidth)) + '%'; }, 50);
}

// Animate initial bar on load
window.addEventListener('DOMContentLoaded', () => {
    setPeriod('7d', 0);
});

// ══════════════════════════════════════════════
// VOICE INPUT
// ══════════════════════════════════════════════
let voiceRec = null, isRec = false, voiceText = '';

function openVoice() {
    if (isRec) { stopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласов вход'); return; }

    isRec = true;
    voiceText = '';
    document.getElementById('recOv').classList.add('open');
    document.getElementById('recDot').classList.remove('ready');
    document.getElementById('recLabel').textContent = '● ЗАПИСВА';
    document.getElementById('recLabel').className = 'rec-label recording';
    document.getElementById('recTranscript').innerText = 'Слушам...';
    document.getElementById('recTranscript').className = 'rec-transcript empty';
    document.getElementById('recSendBtn').disabled = true;
    history.pushState({voice: true}, '');

    voiceRec = new SR();
    voiceRec.lang = 'bg-BG';
    voiceRec.continuous = false;
    voiceRec.interimResults = true;

    voiceRec.onresult = e => {
        let interim = '', final = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            if (e.results[i].isFinal) final += e.results[i][0].transcript;
            else interim += e.results[i][0].transcript;
        }
        if (final) voiceText = final;
        const el = document.getElementById('recTranscript');
        el.innerText = voiceText || interim || 'Слушам...';
        el.className = 'rec-transcript' + (voiceText || interim ? '' : ' empty');
    };

    voiceRec.onend = () => {
        isRec = false;
        if (voiceText) {
            document.getElementById('recDot').classList.add('ready');
            document.getElementById('recLabel').textContent = '✓ ГОТОВО';
            document.getElementById('recLabel').className = 'rec-label ready';
            document.getElementById('recSendBtn').disabled = false;
        } else {
            stopVoice();
        }
    };

    voiceRec.onerror = e => {
        stopVoice();
        if (e.error === 'no-speech') showToast('Не чух — опитай пак');
        else if (e.error === 'not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: ' + e.error);
    };

    try { voiceRec.start(); } catch (e) { stopVoice(); }
}

async function sendVoice() {
    if (!voiceText) return;
    const text = voiceText;
    stopVoice();
    showToast('Обработвам...');

    try {
        const res = await fetch('chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        });
        const data = await res.json();
        showAiResponse(data.reply || data.error || 'Грешка при обработка.');
    } catch (e) {
        showAiResponse('Грешка при свързване. Опитай пак.');
    }
}

function stopVoice() {
    isRec = false;
    voiceText = '';
    document.getElementById('recOv').classList.remove('open');
    if (voiceRec) { try { voiceRec.stop(); } catch (e) {} voiceRec = null; }
}

// ══════════════════════════════════════════════
// AI RESPONSE DRAWER
// ══════════════════════════════════════════════
function showAiResponse(text) {
    document.getElementById('aiRespText').textContent = text;
    const ovl = document.getElementById('aiRespOvl');
    ovl.style.display = 'flex';
    setTimeout(() => ovl.classList.add('show'), 10);
    history.pushState({aiResp: true}, '');
}

function closeAiResp() {
    const ovl = document.getElementById('aiRespOvl');
    ovl.classList.remove('show');
    setTimeout(() => { ovl.style.display = 'none'; }, 300);
}

// ══════════════════════════════════════════════
// BACK BUTTON SUPPORT
// ══════════════════════════════════════════════
window.addEventListener('popstate', e => {
    const voice = document.getElementById('recOv');
    const aiResp = document.getElementById('aiRespOvl');
    if (voice.classList.contains('open')) { stopVoice(); return; }
    if (aiResp.classList.contains('show')) { closeAiResp(); return; }
});

// Close voice overlay on tap outside rec-box
document.getElementById('recOv').addEventListener('click', function(e) {
    if (e.target === this) stopVoice();
});

// ══════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ══════════════════════════════════════════════
// UTILITY
// ══════════════════════════════════════════════
function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
</body>
</html>
