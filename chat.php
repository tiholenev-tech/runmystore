<?php
/**
 * chat.php — RunMyStore.ai Home Dashboard v5.0
 * S39 — Full rewrite: dashboard + AI chat overlay (WhatsApp-style)
 * Pure PHP + SQL, zero Gemini API calls
 * Design: stats.php 1:1 (dark indigo theme, SVG only, no emojis)
 *
 * SECTIONS ON SCREEN:
 * 1. Header (sticky, blur) — brand + plan + logout + settings
 * 2. Loss Chips — horizontal scroll, red/yellow/blue
 * 3. Big Number — today revenue + Оборот/Печалба toggle with confidence
 * 4. Comparison Bar — 7d/30d/365d sliding tabs + store selector
 * 5. Four Main Buttons — 2x2 grid with badges
 * 6. Signal Cards — role-based, color-coded
 * 7. "Попитай AI" button — opens chat overlay
 * 8. Chat Overlay — 80% screen, blur, WhatsApp-style, voice+keyboard
 * 9. Bottom Nav — SVG icons
 */
session_start();
require_once __DIR__ . '/config/database.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)$_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

// ══════════════════════════════════════════════
// TENANT & PLAN
// ══════════════════════════════════════════════
$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$currency_symbol = htmlspecialchars($tenant['currency'] ?? '€');

$plan = 'FREE';
try {
    $sub = DB::run('SELECT plan FROM subscriptions WHERE tenant_id=? ORDER BY created_at DESC LIMIT 1', [$tenant_id])->fetch();
    if ($sub) $plan = strtoupper($sub['plan'] ?? 'FREE');
} catch (Exception $e) {}
if (!in_array($plan, ['FREE','BUSINESS','PRO','ENTERPRISE'])) $plan = 'FREE';

// ══════════════════════════════════════════════
// STORES
// ══════════════════════════════════════════════
$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════
// NIGHT MODE & HELPERS
// ══════════════════════════════════════════════
$is_night = (int)date('H') >= 20 || (int)date('H') < 8;

function fmtMoney($v, $cs) {
    return number_format($v, 0, ',', '.') . ' ' . $cs;
}

// ══════════════════════════════════════════════
// CONFIDENCE: % of products with cost_price
// ══════════════════════════════════════════════
$total_products = (int)DB::run(
    'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1',
    [$tenant_id]
)->fetchColumn();

$with_cost = (int)DB::run(
    'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0',
    [$tenant_id]
)->fetchColumn();

$confidence_pct = $total_products > 0 ? round($with_cost / $total_products * 100) : 0;

// ══════════════════════════════════════════════
// TODAY'S REVENUE + PROFIT
// ══════════════════════════════════════════════
$rev_today = DB::run(
    'SELECT COALESCE(SUM(total),0) AS revenue, COUNT(*) AS cnt
     FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)=CURDATE() AND status!="canceled"',
    [$tenant_id, $store_id]
)->fetch();
$today_revenue = (float)$rev_today['revenue'];
$today_count   = (int)$rev_today['cnt'];

$today_profit = 0;
$margin_pct = 0;
if ($role === 'owner' && $today_count > 0) {
    $profit_data = DB::run(
        'SELECT COALESCE(SUM(si.quantity * (si.unit_price - COALESCE(si.cost_price,0))),0) AS profit
         FROM sale_items si
         JOIN sales s ON s.id = si.sale_id
         WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"',
        [$tenant_id, $store_id]
    )->fetch();
    $today_profit = (float)$profit_data['profit'];
    $margin_pct = $today_revenue > 0 ? round($today_profit / $today_revenue * 100) : 0;
}

// ══════════════════════════════════════════════
// COMPARISON DATA (7d / 30d / 365d)
// ══════════════════════════════════════════════
$dow_names = ['','понеделник','вторник','сряда','четвъртък','петък','събота','неделя'];
$dow = (int)date('N');

// 7 days: today vs same weekday last week
$prev_7d = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status!="canceled"',
    [$tenant_id, $store_id]
)->fetchColumn();
$cmp_7d_pct = $prev_7d > 0 ? round(($today_revenue - $prev_7d) / $prev_7d * 100) : 0;

// 30 days: this week daily avg vs last week daily avg
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_week_days  = max(1, (int)date('N'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end   = date('Y-m-d', strtotime('sunday last week'));

$this_week_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=CURDATE() AND status!="canceled"',
    [$tenant_id, $store_id, $this_week_start]
)->fetchColumn();

$last_week_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
    [$tenant_id, $store_id, $last_week_start, $last_week_end]
)->fetchColumn();

$this_week_avg = $this_week_rev / $this_week_days;
$last_week_avg = $last_week_rev / 7;
$cmp_30d_pct = $last_week_avg > 0 ? round(($this_week_avg - $last_week_avg) / $last_week_avg * 100) : 0;

// 365 days: this month daily avg vs same month last year
$month_start    = date('Y-m-01');
$month_day      = max(1, (int)date('j'));
$ly_month_start = date('Y-m-01', strtotime('-1 year'));
$ly_month_end   = date('Y-m-t', strtotime('-1 year'));
$ly_month_days  = max(1, (int)date('t', strtotime('-1 year')));

$this_month_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=CURDATE() AND status!="canceled"',
    [$tenant_id, $store_id, $month_start]
)->fetchColumn();

$last_year_month_rev = (float)DB::run(
    'SELECT COALESCE(SUM(total),0) FROM sales
     WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
    [$tenant_id, $store_id, $ly_month_start, $ly_month_end]
)->fetchColumn();

$this_month_avg      = $this_month_rev / $month_day;
$last_year_month_avg = $last_year_month_rev / $ly_month_days;
$cmp_365d_pct = $last_year_month_avg > 0 ? round(($this_month_avg - $last_year_month_avg) / $last_year_month_avg * 100) : 0;

// AI comparison text (S38 philosophy: fact + soft suggestion, no predictions)
$top_product_today = DB::run(
    'SELECT p.name, SUM(si.quantity) AS qty FROM sale_items si
     JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
     WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"
     GROUP BY si.product_id ORDER BY qty DESC LIMIT 1',
    [$tenant_id, $store_id]
)->fetch();

$ai_comparison_text = '';
if ($top_product_today) {
    $ai_comparison_text = $top_product_today['name'] . ' дърпа — ' . (int)$top_product_today['qty'] . ' продажби днес.';
    if ($cmp_7d_pct < -15 && (int)date('H') >= 14) {
        $ai_comparison_text = ucfirst($dow_names[$dow]) . ' е по-слаб от обичайно. Миналата седмица: ' . fmtMoney(round($prev_7d), $currency_symbol) . '.';
    }
} elseif ($today_count === 0) {
    $ai_comparison_text = 'Няма продажби днес.';
}

// JSON for JavaScript
$comparisons_json = json_encode([
    '7d' => [
        'pct'   => $cmp_7d_pct,
        'label' => 'Спрямо миналия ' . $dow_names[$dow],
        'sub'   => fmtMoney(round($prev_7d), $currency_symbol) . ' → ' . fmtMoney(round($today_revenue), $currency_symbol),
    ],
    '30d' => [
        'pct'   => $cmp_30d_pct,
        'label' => 'Тази vs миналата седмица (ср. ден)',
        'sub'   => fmtMoney(round($last_week_avg), $currency_symbol) . '/ден → ' . fmtMoney(round($this_week_avg), $currency_symbol) . '/ден',
    ],
    '365d' => [
        'pct'   => $cmp_365d_pct,
        'label' => 'Този месец vs ' . date('m.Y', strtotime('-1 year')) . ' (ср. ден)',
        'sub'   => fmtMoney(round($last_year_month_avg), $currency_symbol) . '/ден → ' . fmtMoney(round($this_month_avg), $currency_symbol) . '/ден',
    ],
], JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════
// LOSS CHIPS
// ══════════════════════════════════════════════
$loss_chips = [];

if (!$is_night) {
    // 1. Zero stock on items with sales in last 30 days → RED
    $zero_stock_count = (int)DB::run(
        'SELECT COUNT(DISTINCT p.id) FROM products p
         JOIN inventory i ON i.product_id=p.id AND i.store_id=?
         WHERE p.tenant_id=? AND p.is_active=1 AND i.quantity=0
         AND p.id IN (
             SELECT si.product_id FROM sale_items si
             JOIN sales s ON s.id=si.sale_id
             WHERE s.tenant_id=? AND s.store_id=? AND s.status!="canceled"
             AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         )',
        [$store_id, $tenant_id, $tenant_id, $store_id]
    )->fetchColumn();
    if ($zero_stock_count > 0) {
        $loss_chips[] = ['type'=>'red', 'num'=>$zero_stock_count, 'text'=>'на нула', 'link'=>'products.php?filter=zero'];
    }

    // 2. Invoices due within 3 days → BLUE (owner/manager)
    if ($role !== 'seller') {
        try {
            $due_inv = DB::run(
                'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total
                 FROM invoices WHERE tenant_id=? AND status="unpaid" AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)',
                [$tenant_id]
            )->fetch();
            if ((int)$due_inv['cnt'] > 0) {
                $loss_chips[] = ['type'=>'blue', 'num'=>fmtMoney(round((float)$due_inv['total']), $currency_symbol), 'text'=>'падеж', 'link'=>'#'];
            }
        } catch (Exception $e) { /* invoices table may not exist yet */ }
    }

    // 3. Items sold below cost → RED (owner only)
    $below_cost_count = 0;
    if ($role === 'owner') {
        $below_cost_count = (int)DB::run(
            'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0 AND retail_price < cost_price',
            [$tenant_id]
        )->fetchColumn();
        if ($below_cost_count > 0) {
            $loss_chips[] = ['type'=>'red', 'num'=>$below_cost_count, 'text'=>'под цена', 'link'=>'products.php'];
        }
    }

    // 4. Zombie stock (45+ days without sale, quantity > 0) → YELLOW
    $zombie_data = DB::run(
        'SELECT COUNT(*) AS cnt,
                COALESCE(SUM(i.quantity * COALESCE(p.cost_price, p.retail_price * 0.6)), 0) AS val
         FROM inventory i
         JOIN products p ON p.id = i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.quantity>0 AND p.is_active=1 AND p.parent_id IS NULL
         AND DATEDIFF(NOW(), COALESCE(
             (SELECT MAX(s2.created_at) FROM sales s2
              JOIN sale_items si2 ON si2.sale_id=s2.id
              WHERE si2.product_id=p.id AND s2.store_id=i.store_id AND s2.status!="canceled"),
             p.created_at
         )) >= 45',
        [$store_id, $tenant_id]
    )->fetch();
    $zombie_count = (int)$zombie_data['cnt'];
    $zombie_value = round((float)$zombie_data['val']);
    if ($zombie_count > 0) {
        $loss_chips[] = ['type'=>'yellow', 'num'=>fmtMoney($zombie_value, $currency_symbol), 'text'=>'zombie', 'link'=>'products.php?filter=zombie'];
    }

    // 5. Low inventory (quantity <= min_quantity) → YELLOW
    $low_stock_count = (int)DB::run(
        'SELECT COUNT(*) FROM inventory i
         JOIN products p ON p.id = i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.quantity <= i.min_quantity AND i.min_quantity > 0 AND p.is_active=1',
        [$store_id, $tenant_id]
    )->fetchColumn();
    if ($low_stock_count > 0) {
        $loss_chips[] = ['type'=>'yellow', 'num'=>$low_stock_count, 'text'=>'ниски', 'link'=>'products.php?filter=low'];
    }
}

// ══════════════════════════════════════════════
// BADGES FOR 4 BUTTONS
// ══════════════════════════════════════════════
$badge_delivery = 0;
try {
    $badge_delivery = (int)DB::run(
        'SELECT COUNT(*) FROM invoices WHERE tenant_id=? AND status="unpaid"',
        [$tenant_id]
    )->fetchColumn();
} catch (Exception $e) {}

$badge_products = $zero_stock_count ?? 0;

// ══════════════════════════════════════════════
// SIGNAL CARDS
// ══════════════════════════════════════════════
$signals = [];
if (!$is_night) {
    if (($zero_stock_count ?? 0) > 0) {
        $signals[] = ['color'=>'red', 'title'=>'Нулева наличност', 'desc'=>$zero_stock_count . ' топ артикула на 0 бр.', 'link'=>'products.php?filter=zero', 'pulse'=>true];
    }
    if ($role === 'owner' && $below_cost_count > 0) {
        $signals[] = ['color'=>'red', 'title'=>'Под себестойност', 'desc'=>$below_cost_count . ' артикула се продават под цена.', 'link'=>'products.php', 'pulse'=>true];
    }
    if ($zombie_count > 0) {
        $signals[] = ['color'=>'yellow', 'title'=>'Zombie стока', 'desc'=>$zombie_count . ' арт. стоят 45+ дни. Стойност ' . fmtMoney($zombie_value, $currency_symbol) . '.', 'link'=>'products.php?filter=zombie', 'pulse'=>false];
    }
    if ($low_stock_count > 0) {
        $signals[] = ['color'=>'yellow', 'title'=>'Ниски наличности', 'desc'=>$low_stock_count . ' артикула под минимума.', 'link'=>'products.php?filter=low', 'pulse'=>false];
    }
    if ($role !== 'seller') {
        try {
            $pending_deliveries = (int)DB::run(
                'SELECT COUNT(*) FROM deliveries WHERE tenant_id=? AND status="pending"',
                [$tenant_id]
            )->fetchColumn();
            if ($pending_deliveries > 0) {
                $signals[] = ['color'=>'blue', 'title'=>'Доставки в път', 'desc'=>$pending_deliveries . ' доставки чакат приемане.', 'link'=>'#', 'pulse'=>false];
            }
        } catch (Exception $e) {}
    }
}

// ══════════════════════════════════════════════
// EXISTING CHAT MESSAGES (for overlay)
// ══════════════════════════════════════════════
$chat_messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id, $store_id]
)->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════
// PLAN BADGE COLORS
// ══════════════════════════════════════════════
$plan_colors = match($plan) {
    'BUSINESS'   => ['bg'=>'rgba(99,102,241,.15)',  'border'=>'rgba(99,102,241,.3)',  'text'=>'#818cf8'],
    'PRO'        => ['bg'=>'rgba(168,85,247,.15)',  'border'=>'rgba(168,85,247,.3)',  'text'=>'#c084fc'],
    'ENTERPRISE' => ['bg'=>'rgba(234,179,8,.15)',   'border'=>'rgba(234,179,8,.3)',   'text'=>'#fbbf24'],
    default      => ['bg'=>'rgba(107,114,128,.15)', 'border'=>'rgba(107,114,128,.3)', 'text'=>'#9ca3af'],
};
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#030712">
<title>RunMyStore.ai</title>
<style>
/* ══════════════════════════════════════════════
   CSS DESIGN SYSTEM — stats.php 1:1
   Background: #030712, Indigo accents, blur glass
   SVG icons only, NO emojis anywhere
   ══════════════════════════════════════════════ */
:root {
    --bg-main: #030712;
    --bg-card: rgba(15, 15, 40, 0.75);
    --border-subtle: rgba(99, 102, 241, 0.15);
    --border-glow: rgba(99, 102, 241, 0.4);
    --indigo-600: #4f46e5;
    --indigo-500: #6366f1;
    --indigo-400: #818cf8;
    --indigo-300: #a5b4fc;
    --text-primary: #f1f5f9;
    --text-secondary: #6b7280;
    --danger: #ef4444;
    --warning: #fbbf24;
    --success: #22c55e;
    --nav-height: 56px;
}
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
html { background: var(--bg-main); }
body {
    background: var(--bg-main); color: var(--text-primary);
    font-family: 'Montserrat', Inter, system-ui, sans-serif;
    height: 100dvh; display: flex; flex-direction: column; overflow: hidden;
    padding-bottom: var(--nav-height);
}
/* Radial glow at top */
body::before {
    content: ''; position: fixed; top: -200px; left: 50%; transform: translateX(-50%);
    width: 600px; height: 350px;
    background: radial-gradient(ellipse, rgba(99,102,241,.1) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
}

/* ═══ MAIN SCROLL AREA ═══ */
.main-scroll {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    position: relative; z-index: 1;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.main-scroll::-webkit-scrollbar { display: none; }

/* ═══ HEADER ═══ */
.header {
    position: sticky; top: 0; z-index: 50;
    padding: 10px 14px 0;
    background: rgba(3, 7, 18, 0.93);
    backdrop-filter: blur(16px);
}
.header-top {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 4px;
}
.header-brand {
    font-size: 11px; font-weight: 700;
    color: rgba(165, 180, 252, 0.6); letter-spacing: 0.3px;
}
.header-right { display: flex; align-items: center; gap: 6px; }

/* Plan pill */
.plan-pill {
    padding: 3px 8px; border-radius: 20px;
    font-size: 9px; font-weight: 800; letter-spacing: 0.5px;
    background: <?= $plan_colors['bg'] ?>;
    border: 1px solid <?= $plan_colors['border'] ?>;
    color: <?= $plan_colors['text'] ?>;
}

/* Header icon buttons (settings, logout) */
.header-icon-btn {
    width: 28px; height: 28px; border-radius: 50%;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.2s; position: relative;
}
.header-icon-btn:active { background: rgba(99,102,241,0.3); }
.header-icon-btn svg { width: 14px; height: 14px; color: var(--indigo-400); }

/* Logout dropdown */
.logout-dropdown {
    position: absolute; top: 34px; right: 0;
    background: #0f0f2a; border: 1px solid rgba(239,68,68,0.3);
    border-radius: 10px; padding: 8px 14px; white-space: nowrap;
    z-index: 60; box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    font-size: 12px; color: #fca5a5; font-weight: 600; cursor: pointer;
    display: none; text-decoration: none;
}
.logout-dropdown.show { display: block; }

/* ═══ LOSS CHIPS ═══ */
.loss-chips-row {
    display: flex; gap: 6px; padding: 6px 14px 10px;
    overflow-x: auto; scrollbar-width: none; flex-shrink: 0;
}
.loss-chips-row::-webkit-scrollbar { display: none; }
.loss-chip {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 10px; flex-shrink: 0;
    cursor: pointer; white-space: nowrap; text-decoration: none;
    transition: transform 0.1s;
}
.loss-chip:active { transform: scale(0.95); }
.loss-chip-num { font-size: 12px; font-weight: 800; }
.loss-chip-text { font-size: 10px; font-weight: 600; }
/* Red chip */
.loss-chip-red { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); }
.loss-chip-red .loss-chip-num { color: #f87171; }
.loss-chip-red .loss-chip-text { color: rgba(248,113,113,0.7); }
/* Yellow chip */
.loss-chip-yellow { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.25); }
.loss-chip-yellow .loss-chip-num { color: #fbbf24; }
.loss-chip-yellow .loss-chip-text { color: rgba(251,191,36,0.7); }
/* Blue chip */
.loss-chip-blue { background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.25); }
.loss-chip-blue .loss-chip-num { color: #818cf8; }
.loss-chip-blue .loss-chip-text { color: rgba(129,140,248,0.7); }

/* ═══ BIG NUMBER ═══ */
.big-number-wrap { text-align: center; padding: 16px 14px 6px; }
.big-number-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    color: var(--text-secondary); letter-spacing: 1.5px; margin-bottom: 4px;
}
.big-number-value {
    font-size: 46px; font-weight: 900; line-height: 1;
    background: linear-gradient(90deg, #818cf8, #c7d2fe, #818cf8);
    background-size: 200% auto;
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: shimmer-text 3s linear infinite;
}
@keyframes shimmer-text {
    0%   { background-position: -200% center; }
    100% { background-position: 200% center; }
}
.big-number-sub { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }

/* Mode pills (Оборот / Печалба) */
.mode-pills { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
.mode-pill {
    padding: 5px 14px; border-radius: 14px;
    font-size: 11px; font-weight: 700; color: #4b5563;
    background: rgba(15,15,40,0.4); border: 1px solid rgba(99,102,241,0.1);
    cursor: pointer; transition: all 0.25s;
    display: flex; align-items: center; gap: 4px;
}
.mode-pill.active {
    color: #e2e8f0;
    background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(139,92,246,0.15));
    border-color: rgba(99,102,241,0.3);
}
.confidence-warning {
    display: inline-flex; align-items: center; justify-content: center;
    width: 16px; height: 16px; border-radius: 50%;
    background: rgba(251,191,36,0.15); color: #fbbf24;
    font-size: 10px; font-weight: 900;
    border: 1px solid rgba(251,191,36,0.3);
    animation: dot-pulse 2s infinite;
}
.confidence-note {
    display: flex; align-items: center; gap: 6px;
    margin: 8px auto 0; padding: 6px 10px; border-radius: 10px;
    background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.15);
    font-size: 10px; color: #fcd34d; max-width: 320px; justify-content: center;
}
.confidence-note svg { flex-shrink: 0; }

/* ═══ COMPARISON CARD ═══ */
.comparison-card {
    margin: 10px 14px 12px;
    background: var(--bg-card); border: 1px solid var(--border-subtle);
    border-radius: 16px; padding: 14px; backdrop-filter: blur(12px);
    position: relative; overflow: hidden;
}
.comparison-card::before {
    content: ''; position: absolute; inset: 0; border-radius: inherit;
    background: linear-gradient(135deg, rgba(99,102,241,0.06), transparent);
    pointer-events: none;
}
.comparison-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 8px; position: relative;
}
.comparison-title {
    font-size: 9px; font-weight: 700; color: var(--indigo-400);
    text-transform: uppercase; letter-spacing: 0.5px;
}
.comparison-store {
    font-size: 10px; font-weight: 600; color: rgba(165,180,252,0.5);
}
.comparison-store select {
    background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);
    border-radius: 8px; color: #a5b4fc; font-size: 10px; font-weight: 600;
    padding: 3px 8px; font-family: inherit; cursor: pointer; outline: none;
}
.comparison-top { display: flex; align-items: center; gap: 14px; margin-bottom: 10px; position: relative; }
.comparison-percent { font-size: 26px; font-weight: 900; line-height: 1; flex-shrink: 0; }
.comparison-percent.up { color: #4ade80; }
.comparison-percent.down { color: #f87171; }
.comparison-percent.zero { color: var(--text-secondary); }
.comparison-info { flex: 1; min-width: 0; }
.comparison-label { font-size: 11px; font-weight: 600; color: #d1d5db; }
.comparison-sub { font-size: 10px; color: var(--text-secondary); margin-top: 2px; }
/* Progress bar */
.comparison-bar-track {
    height: 5px; border-radius: 3px;
    background: rgba(255,255,255,0.06); margin-bottom: 10px; overflow: hidden;
}
.comparison-bar-fill {
    height: 100%; border-radius: 3px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}
.comparison-bar-fill.up { background: linear-gradient(90deg, #22c55e, #4ade80); }
.comparison-bar-fill.down { background: linear-gradient(90deg, #ef4444, #f87171); }
.comparison-bar-fill.zero { background: var(--text-secondary); }
/* AI explanation */
.comparison-ai { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px; position: relative; }
.comparison-ai-badge {
    flex-shrink: 0; padding: 2px 6px; border-radius: 6px;
    font-size: 8px; font-weight: 800; color: var(--indigo-400);
    background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.2);
    letter-spacing: 0.5px;
}
.comparison-ai-text { font-size: 11px; color: #d1d5db; line-height: 1.5; }

/* Period selector tabs */
.period-tabs {
    display: flex; position: relative;
    background: rgba(15,15,40,0.4);
    border: 1px solid rgba(99,102,241,0.1); border-radius: 14px; overflow: hidden;
}
.period-slider {
    position: absolute; top: 2px; bottom: 2px; left: 2px;
    width: calc(33.33% - 2px); border-radius: 12px;
    background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(139,92,246,0.15));
    border: 1px solid rgba(99,102,241,0.3);
    transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.period-tab {
    flex: 1; text-align: center; padding: 10px 0;
    font-size: 11px; font-weight: 700; color: #4b5563;
    cursor: pointer; position: relative; z-index: 1;
    display: flex; align-items: center; justify-content: center; gap: 5px;
    transition: color 0.3s;
}
.period-tab.active { color: #e2e8f0; }
.period-tab svg { width: 13px; height: 13px; opacity: 0.5; transition: opacity 0.3s; }
.period-tab.active svg { opacity: 1; }

/* ═══ 4 MAIN BUTTONS ═══ */
.buttons-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 14px; margin-bottom: 14px; }
.main-button {
    background: var(--bg-card); border: 1px solid var(--border-subtle);
    border-radius: 16px; padding: 20px 14px 16px; cursor: pointer;
    position: relative; overflow: hidden; backdrop-filter: blur(12px);
    text-decoration: none; display: block; transition: all 0.25s;
}
.main-button:active { transform: scale(0.97); }
.main-button:hover { border-color: var(--border-glow); box-shadow: 0 0 28px rgba(99,102,241,0.18); }
.main-button::before {
    content: ''; position: absolute; inset: 0; border-radius: inherit;
    background: linear-gradient(135deg, rgba(99,102,241,0.06), transparent);
    pointer-events: none;
}
.main-button-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; margin-bottom: 10px;
}
.main-button-icon svg { width: 20px; height: 20px; }
.main-button-name { font-size: 13px; font-weight: 700; color: var(--text-primary); }
.main-button-desc { font-size: 10px; color: var(--text-secondary); margin-top: 2px; }
.button-badge {
    position: absolute; top: 8px; right: 10px;
    background: rgba(239,68,68,0.15); color: #ef4444;
    font-size: 10px; font-weight: 800; border-radius: 10px;
    min-width: 18px; height: 18px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 5px; animation: pulse-red 2s infinite; z-index: 2;
}
@keyframes pulse-red {
    0%, 100% { opacity: 1; box-shadow: 0 0 4px rgba(239,68,68,0.3); }
    50% { opacity: 0.7; box-shadow: 0 0 12px rgba(239,68,68,0.6); }
}

/* ═══ INDIGO SEPARATOR ═══ */
.indigo-separator {
    height: 1px; margin: 0 14px;
    background: linear-gradient(to right, transparent, rgba(99,102,241,0.25), transparent);
}

/* ═══ SIGNAL CARDS ═══ */
.signals-section { padding: 14px; }
.signals-title {
    font-size: 10px; font-weight: 700; color: var(--indigo-400);
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;
}
.signal-card {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 12px;
    background: var(--bg-card); backdrop-filter: blur(12px);
    margin-bottom: 8px; cursor: pointer; transition: transform 0.15s;
    text-decoration: none;
}
.signal-card:active { transform: scale(0.98); }
.signal-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.signal-body { flex: 1; min-width: 0; }
.signal-name { font-size: 12px; font-weight: 700; }
.signal-desc { font-size: 12px; color: #d1d5db; margin-top: 2px; }
.signal-link { color: var(--indigo-400); font-weight: 600; white-space: nowrap; }
/* Signal colors */
.signal-red { border: 1px solid rgba(239,68,68,0.2); }
.signal-red .signal-name { color: #fca5a5; }
.signal-red .signal-dot { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.6); }
.signal-yellow { border: 1px solid rgba(251,191,36,0.2); }
.signal-yellow .signal-name { color: #fcd34d; }
.signal-yellow .signal-dot { background: #fbbf24; box-shadow: 0 0 8px rgba(251,191,36,0.6); }
.signal-blue { border: 1px solid rgba(99,102,241,0.2); }
.signal-blue .signal-name { color: #a5b4fc; }
.signal-blue .signal-dot { background: #818cf8; box-shadow: 0 0 8px rgba(99,102,241,0.6); }

/* ═══ ASK AI BUTTON ═══ */
.ask-ai-wrap { padding: 16px 14px 20px; display: flex; justify-content: center; }
.ask-ai-btn {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 24px; border-radius: 20px;
    background: rgba(15,15,40,0.75); border: 1px solid rgba(99,102,241,0.2);
    cursor: pointer; backdrop-filter: blur(12px); transition: all 0.25s;
}
.ask-ai-btn:hover { border-color: rgba(99,102,241,0.5); box-shadow: 0 0 24px rgba(99,102,241,0.2); }
.ask-ai-btn:active { transform: scale(0.97); }
.ask-ai-btn span { font-size: 13px; font-weight: 600; color: #a5b4fc; }
.ai-waves { display: flex; align-items: flex-end; gap: 2px; height: 18px; }
.ai-wave-bar { width: 3px; border-radius: 2px; background: currentColor; animation: wave-anim 1s ease-in-out infinite; }
@keyframes wave-anim { 0%, 100% { transform: scaleY(0.35); } 50% { transform: scaleY(1); } }

/* ═══ CHAT OVERLAY (80% screen, WhatsApp style) ═══ */
.chat-overlay {
    position: fixed; inset: 0; z-index: 400;
    background: rgba(3,7,18,0.6); backdrop-filter: blur(8px);
    display: none; flex-direction: column; align-items: center; justify-content: flex-end;
    padding: 0;
}
.chat-overlay.open { display: flex; }
.chat-panel {
    width: 100%; height: 80vh; max-width: 500px;
    background: rgba(10, 12, 28, 0.97);
    border: 1px solid var(--border-glow);
    border-radius: 22px 22px 0 0;
    display: flex; flex-direction: column;
    box-shadow: 0 -12px 50px rgba(99,102,241,0.25);
    animation: slide-up 0.25s ease;
    overflow: hidden;
}
@keyframes slide-up {
    from { opacity: 0; transform: translateY(40px); }
    to   { opacity: 1; transform: translateY(0); }
}
/* Chat header */
.chat-panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px 10px; flex-shrink: 0;
    border-bottom: 1px solid var(--border-subtle);
}
.chat-panel-title {
    display: flex; align-items: center; gap: 8px;
}
.chat-panel-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #9333ea);
    display: flex; align-items: center; justify-content: center;
}
.chat-panel-avatar svg { width: 14px; height: 14px; }
.chat-panel-name { font-size: 14px; font-weight: 700; color: var(--indigo-300); }
.chat-close-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-secondary); transition: background 0.2s;
}
.chat-close-btn:active { background: rgba(239,68,68,0.3); }
.chat-close-btn svg { width: 16px; height: 16px; }

/* Chat messages area */
.chat-messages {
    flex: 1; overflow-y: auto; padding: 12px 14px;
    display: flex; flex-direction: column; gap: 10px;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.chat-messages::-webkit-scrollbar { display: none; }
.chat-msg-group { display: flex; flex-direction: column; gap: 4px; }
.chat-msg-meta {
    font-size: 9px; color: #4b5563;
    display: flex; align-items: center; gap: 4px;
}
.chat-msg-meta.right { justify-content: flex-end; }
.chat-msg {
    max-width: 82%; padding: 8px 12px;
    font-size: 13px; line-height: 1.5; word-break: break-word;
}
.chat-msg.ai-msg {
    background: rgba(15,20,40,0.8);
    border: 0.5px solid rgba(99,102,241,0.15);
    color: #e2e8f0; border-radius: 4px 14px 14px 14px;
    white-space: pre-wrap;
}
.chat-msg.user-msg {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff; border-radius: 14px 14px 4px 14px;
    margin-left: auto; border: 0.5px solid rgba(255,255,255,0.1);
}
/* Typing indicator */
.chat-typing {
    display: none; padding: 8px 12px;
    background: rgba(15,20,40,0.8); border: 0.5px solid rgba(99,102,241,0.15);
    border-radius: 4px 14px 14px 14px; width: fit-content;
}
.typing-dots { display: flex; gap: 4px; align-items: center; }
.typing-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #818cf8; animation: bounce-dot 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce-dot { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-4px); } }

/* Chat input area */
.chat-input-area {
    padding: 8px 12px 12px; flex-shrink: 0;
    border-top: 1px solid var(--border-subtle);
}
.chat-input-row {
    display: flex; gap: 6px; align-items: center;
    background: rgba(10,14,28,0.9); border-radius: 20px;
    padding: 4px 4px 4px 12px;
    border: 0.5px solid rgba(99,102,241,0.2);
}
.chat-text-input {
    flex: 1; background: transparent; border: none;
    color: var(--text-primary); font-size: 13px; padding: 8px 0;
    font-family: inherit; outline: none; resize: none;
    max-height: 80px; line-height: 1.4;
}
.chat-text-input::placeholder { color: #374151; }
/* Voice button in chat */
.chat-voice-btn {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    position: relative; display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden;
    background: linear-gradient(135deg, #4f46e5, #9333ea);
    box-shadow: 0 0 12px rgba(99,102,241,0.3);
    transition: all 0.2s;
}
.chat-voice-btn.recording {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    box-shadow: 0 0 18px rgba(239,68,68,0.5);
}
.chat-voice-btn svg { width: 16px; height: 16px; color: #fff; z-index: 1; }
/* Pulse ring for recording */
.voice-pulse-ring {
    position: absolute; border-radius: 50%;
    border: 1.5px solid rgba(255,255,255,0.3); opacity: 0;
}
.chat-voice-btn.recording .voice-pulse-ring {
    border-color: rgba(255,255,255,0.5);
}
.voice-ring-1 { width: 20px; height: 20px; animation: ring-pulse 2s 0s ease-in-out infinite; }
.voice-ring-2 { width: 32px; height: 32px; animation: ring-pulse 2s 0.3s ease-in-out infinite; }
.voice-ring-3 { width: 44px; height: 44px; animation: ring-pulse 2s 0.6s ease-in-out infinite; }
@keyframes ring-pulse { 0% { transform: scale(0.5); opacity: 0.7; } 100% { transform: scale(1.6); opacity: 0; } }
/* Send button */
.chat-send-btn {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,0.08); border: 0.5px solid rgba(255,255,255,0.1);
    color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: opacity 0.2s;
}
.chat-send-btn:disabled { opacity: 0.2; }
.chat-send-btn svg { width: 16px; height: 16px; }

/* Recording status bar inside chat */
.chat-rec-status {
    display: none; align-items: center; gap: 8px;
    padding: 6px 12px; margin-bottom: 6px;
    background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 12px;
}
.chat-rec-status.active { display: flex; }
.rec-dot-small {
    width: 10px; height: 10px; border-radius: 50%;
    background: #ef4444; animation: dot-pulse 1s ease infinite;
    box-shadow: 0 0 8px rgba(239,68,68,0.6);
}
.rec-label-small { font-size: 11px; font-weight: 700; color: #fca5a5; text-transform: uppercase; letter-spacing: 0.5px; }
.rec-transcript-text { font-size: 12px; color: #e2e8f0; flex: 1; }

/* ═══ BOTTOM NAV ═══ */
.bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0;
    height: var(--nav-height); background: rgba(3,7,18,0.95);
    backdrop-filter: blur(15px); border-top: 0.5px solid rgba(99,102,241,0.2);
    display: flex; z-index: 100;
    box-shadow: 0 -4px 20px rgba(99,102,241,0.1);
}
.bottom-nav-tab {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 3px;
    font-size: 9px; font-weight: 600; color: rgba(165,180,252,0.4);
    text-decoration: none; transition: all 0.3s;
}
.bottom-nav-tab.active { color: #c7d2fe; text-shadow: 0 0 10px rgba(129,140,248,0.8); }
.bottom-nav-tab svg { width: 18px; height: 18px; transition: all 0.3s; }
.bottom-nav-tab.active svg { filter: drop-shadow(0 0 7px rgba(129,140,248,0.8)); }

/* ═══ TOAST ═══ */
.toast-notification {
    position: fixed; bottom: 65px; left: 50%; transform: translateX(-50%);
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff; padding: 7px 16px; border-radius: 20px;
    font-size: 11px; font-weight: 600; z-index: 500;
    opacity: 0; transition: opacity 0.3s, transform 0.3s;
    pointer-events: none; white-space: nowrap;
}
.toast-notification.show {
    opacity: 1; transform: translateX(-50%) translateY(-6px);
}

/* ═══ SHARED ANIMATIONS ═══ */
@keyframes card-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes dot-pulse { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.4); } }
@keyframes fade-up { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>

<!-- ═══ MAIN SCROLLABLE AREA ═══ -->
<div class="main-scroll" id="mainScroll">

<!-- ═══ HEADER ═══ -->
<div class="header">
  <div class="header-top">
    <span class="header-brand">RUNMYSTORE.AI</span>
    <div class="header-right">
      <span class="plan-pill"><?= $plan ?></span>
      <!-- Settings -->
      <div class="header-icon-btn" onclick="location.href='settings.php'" title="Настройки">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
      </div>
      <!-- Logout -->
      <div class="header-icon-btn" onclick="toggleLogout()" id="logoutWrap" style="position:relative">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        <a href="logout.php" class="logout-dropdown" id="logoutDrop">Изход →</a>
      </div>
    </div>
  </div>

  <!-- Loss Chips (hidden at night or if empty) -->
  <?php if (!empty($loss_chips)): ?>
  <div class="loss-chips-row">
    <?php foreach ($loss_chips as $chip):
      $chip_class = 'loss-chip-' . $chip['type'];
    ?>
    <a href="<?= htmlspecialchars($chip['link']) ?>" class="loss-chip <?= $chip_class ?>">
      <span class="loss-chip-num"><?= htmlspecialchars((string)$chip['num']) ?></span>
      <span class="loss-chip-text"><?= htmlspecialchars($chip['text']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ BIG NUMBER ═══ -->
<div class="big-number-wrap" style="animation: card-in 0.5s ease both">
  <div class="big-number-label">ДНЕС</div>
  <div class="big-number-value" id="bigNumber">0 <?= htmlspecialchars($currency_symbol) ?></div>
  <div class="big-number-sub" id="bigNumberSub">
    <?= $today_count ?> продажби<?php if ($role === 'owner' && $today_count > 0): ?> · <?= $margin_pct ?>% марж<?php endif; ?>
  </div>
  <?php if ($role === 'owner'): ?>
  <div class="mode-pills">
    <div class="mode-pill active" id="modeRevenue" onclick="setMode('revenue')">Оборот</div>
    <div class="mode-pill" id="modeProfit" onclick="setMode('profit')">
      Печалба
      <?php if ($confidence_pct < 100): ?>
        <span class="confidence-warning">!</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($confidence_pct < 100): ?>
  <div class="confidence-note" id="confidenceNote" style="display:none">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5">
      <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <span>Сумата е за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?> с доставна цена)</span>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ═══ COMPARISON ═══ -->
<div class="comparison-card" style="animation: card-in 0.5s 0.15s ease both">
  <div class="comparison-header">
    <span class="comparison-title">СРАВНЕНИЕ</span>
    <?php if (count($all_stores) > 1): ?>
    <select class="comparison-store" onchange="location.href='?store='+this.value">
      <?php foreach ($all_stores as $st): ?>
      <option value="<?= $st['id'] ?>" <?= $st['id'] == $store_id ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php else: ?>
    <span class="comparison-store"><?= htmlspecialchars($store_name) ?></span>
    <?php endif; ?>
  </div>
  <div class="comparison-top">
    <div class="comparison-percent <?= $cmp_7d_pct > 0 ? 'up' : ($cmp_7d_pct < 0 ? 'down' : 'zero') ?>" id="cmpPercent">
      <?= ($cmp_7d_pct >= 0 ? '+' : '') . $cmp_7d_pct ?>%
    </div>
    <div class="comparison-info">
      <div class="comparison-label" id="cmpLabel"><?= htmlspecialchars('Спрямо миналия ' . $dow_names[$dow]) ?></div>
      <div class="comparison-sub" id="cmpSub"><?= htmlspecialchars(fmtMoney(round($prev_7d), $currency_symbol) . ' → ' . fmtMoney(round($today_revenue), $currency_symbol)) ?></div>
    </div>
  </div>
  <div class="comparison-bar-track">
    <div class="comparison-bar-fill <?= $cmp_7d_pct > 0 ? 'up' : ($cmp_7d_pct < 0 ? 'down' : 'zero') ?>" id="cmpBar" style="width:0%"></div>
  </div>
  <?php if ($ai_comparison_text): ?>
  <div class="comparison-ai">
    <span class="comparison-ai-badge">AI</span>
    <div class="comparison-ai-text"><?= htmlspecialchars($ai_comparison_text) ?></div>
  </div>
  <?php endif; ?>
  <div class="period-tabs">
    <div class="period-slider" id="periodSlider"></div>
    <div class="period-tab active" onclick="setPeriod('7d', 0)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      7 дни
    </div>
    <div class="period-tab" onclick="setPeriod('30d', 1)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      30 дни
    </div>
    <div class="period-tab" onclick="setPeriod('365d', 2)">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 9l-5 5-2-2-4 4"/></svg>
      365 дни
    </div>
  </div>
</div>

<!-- ═══ 4 MAIN BUTTONS ═══ -->
<div class="buttons-grid">
  <!-- Продажба (green) -->
  <a href="sale.php" class="main-button" style="animation: card-in 0.4s 0.25s ease both">
    <div class="main-button-icon" style="background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.04));border:1px solid rgba(34,197,94,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round">
        <rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/><path d="M6 14h4"/></svg>
    </div>
    <div class="main-button-name">Продажба</div>
    <div class="main-button-desc">Каса и скенер</div>
  </a>
  <!-- Поръчка (indigo) -->
  <a href="#" class="main-button" style="animation: card-in 0.4s 0.35s ease both">
    <div class="main-button-icon" style="background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(99,102,241,.04));border:1px solid rgba(99,102,241,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2" stroke-linecap="round">
        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div class="main-button-name">Поръчка</div>
    <div class="main-button-desc">Към доставчици</div>
  </a>
  <!-- Доставка (amber) + badge -->
  <a href="#" class="main-button" style="animation: card-in 0.4s 0.45s ease both">
    <?php if ($badge_delivery > 0): ?><div class="button-badge"><?= $badge_delivery ?></div><?php endif; ?>
    <div class="main-button-icon" style="background:linear-gradient(135deg,rgba(251,191,36,.12),rgba(251,191,36,.04));border:1px solid rgba(251,191,36,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round">
        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
    </div>
    <div class="main-button-name">Доставка</div>
    <div class="main-button-desc">Приемане на стока</div>
  </a>
  <!-- Артикули (purple) + badge -->
  <a href="products.php" class="main-button" style="animation: card-in 0.4s 0.55s ease both">
    <?php if ($badge_products > 0): ?><div class="button-badge"><?= $badge_products ?></div><?php endif; ?>
    <div class="main-button-icon" style="background:linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.04));border:1px solid rgba(192,132,252,.2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="#c084fc" stroke-width="2" stroke-linecap="round">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
        <circle cx="7" cy="7" r="1.5" fill="#c084fc"/></svg>
    </div>
    <div class="main-button-name">Артикули</div>
    <div class="main-button-desc">Добави и редактирай</div>
  </a>
</div>

<!-- ═══ SIGNAL CARDS ═══ -->
<?php if (!empty($signals)): ?>
<div class="indigo-separator"></div>
<div class="signals-section">
  <div class="signals-title">СИГНАЛИ</div>
  <?php foreach ($signals as $si => $signal):
    $signal_class = 'signal-' . $signal['color'];
  ?>
  <a href="<?= htmlspecialchars($signal['link']) ?>" class="signal-card <?= $signal_class ?>" style="animation: card-in 0.4s <?= $si * 80 ?>ms ease both">
    <div class="signal-dot" <?= $signal['pulse'] ? 'style="animation: dot-pulse 2s infinite"' : '' ?>></div>
    <div class="signal-body">
      <div class="signal-name"><?= htmlspecialchars($signal['title']) ?></div>
      <div class="signal-desc"><?= htmlspecialchars($signal['desc']) ?> <span class="signal-link">Виж ги →</span></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ ASK AI ═══ -->
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

<!-- ═══ CHAT OVERLAY (80% screen, WhatsApp style) ═══ -->
<div class="chat-overlay" id="chatOverlay">
  <div class="chat-panel">
    <!-- Chat Header -->
    <div class="chat-panel-header">
      <div class="chat-panel-title">
        <div class="chat-panel-avatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
        </div>
        <span class="chat-panel-name">AI Асистент</span>
      </div>
      <div class="chat-close-btn" onclick="closeChat()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/>
        </svg>
      </div>
    </div>

    <!-- Chat Messages (WhatsApp style, scrollable) -->
    <div class="chat-messages" id="chatMessages">
      <?php if (empty($chat_messages)): ?>
      <div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:12px">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;background:linear-gradient(135deg,#e5e7eb,#c7d2fe);-webkit-background-clip:text;-webkit-text-fill-color:transparent">
          Здравей<?= $user_name ? ', ' . htmlspecialchars($user_name) : '' ?>!
        </div>
        Попитай каквото искаш — говори или пиши.
      </div>
      <?php else: ?>
        <?php foreach ($chat_messages as $msg): ?>
        <div class="chat-msg-group">
          <?php if ($msg['role'] === 'assistant'): ?>
            <div class="chat-msg-meta">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              AI · <?= date('H:i', strtotime($msg['created_at'])) ?>
            </div>
            <div class="chat-msg ai-msg"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
          <?php else: ?>
            <div class="chat-msg-meta right"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
            <div class="chat-msg user-msg"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <!-- Typing indicator -->
      <div class="chat-typing" id="chatTyping">
        <div class="typing-dots"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>
      </div>
    </div>

    <!-- Recording status bar -->
    <div class="chat-rec-status" id="chatRecStatus">
      <div class="rec-dot-small"></div>
      <span class="rec-label-small">ЗАПИСВА</span>
      <span class="rec-transcript-text" id="recTranscriptText">Слушам...</span>
    </div>

    <!-- Chat Input -->
    <div class="chat-input-area">
      <div class="chat-input-row">
        <textarea class="chat-text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1"
          oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatSendBtn').disabled=!this.value.trim()"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatMessage()}"></textarea>
        <div class="chat-voice-btn" id="chatVoiceBtn" onclick="toggleVoice()">
          <div class="voice-pulse-ring voice-ring-1"></div>
          <div class="voice-pulse-ring voice-ring-2"></div>
          <div class="voice-pulse-ring voice-ring-3"></div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
            <path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>
          </svg>
        </div>
        <button class="chat-send-btn" id="chatSendBtn" onclick="sendChatMessage()" disabled>
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ BOTTOM NAV ═══ -->
<nav class="bottom-nav">
  <a href="chat.php" class="bottom-nav-tab active">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </svg>AI
  </a>
  <a href="warehouse.php" class="bottom-nav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
      <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>
    </svg>Склад
  </a>
  <a href="stats.php" class="bottom-nav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
    </svg>Справки
  </a>
  <a href="sale.php" class="bottom-nav-tab">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>Продажба
  </a>
</nav>

<div class="toast-notification" id="toastNotification"></div>
<script>
// ══════════════════════════════════════════════
// CONFIGURATION
// ══════════════════════════════════════════════
const CONFIG = {
    comparisons: <?= $comparisons_json ?>,
    todayRevenue: <?= round($today_revenue) ?>,
    todayProfit: <?= round($today_profit) ?>,
    confidencePct: <?= $confidence_pct ?>,
    marginPct: <?= $margin_pct ?>,
    currency: <?= json_encode($currency_symbol) ?>,
    todayCount: <?= $today_count ?>,
    isOwner: <?= $role === 'owner' ? 'true' : 'false' ?>
};

// ══════════════════════════════════════════════
// UTILITY
// ══════════════════════════════════════════════
function $(id) { return document.getElementById(id); }
function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function escapeHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showToast(msg) {
    const t = $('toastNotification');
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ══════════════════════════════════════════════
// COUNT-UP ANIMATION
// ══════════════════════════════════════════════
(function animateCountUp() {
    const el = $('bigNumber');
    const target = CONFIG.todayRevenue;
    if (target === 0) { el.textContent = '0 ' + CONFIG.currency; return; }

    const duration = 1200;
    const startTime = performance.now();

    function step(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        const current = Math.round(target * eased);
        el.textContent = formatNumber(current) + ' ' + CONFIG.currency;
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
})();

// ══════════════════════════════════════════════
// MODE TOGGLE (Оборот / Печалба)
// ══════════════════════════════════════════════
let currentMode = 'revenue';

function setMode(mode) {
    currentMode = mode;
    const bigNum = $('bigNumber');
    const bigSub = $('bigNumberSub');
    const modeRevBtn = $('modeRevenue');
    const modeProfBtn = $('modeProfit');
    const confNote = $('confidenceNote');

    if (modeRevBtn) modeRevBtn.classList.toggle('active', mode === 'revenue');
    if (modeProfBtn) modeProfBtn.classList.toggle('active', mode === 'profit');
    if (confNote) confNote.style.display = mode === 'profit' ? 'flex' : 'none';

    if (mode === 'revenue') {
        bigNum.textContent = formatNumber(CONFIG.todayRevenue) + ' ' + CONFIG.currency;
        let sub = CONFIG.todayCount + ' продажби';
        if (CONFIG.isOwner && CONFIG.todayCount > 0) sub += ' · ' + CONFIG.marginPct + '% марж';
        bigSub.textContent = sub;
    } else {
        bigNum.textContent = formatNumber(CONFIG.todayProfit) + ' ' + CONFIG.currency;
        bigSub.textContent = 'Печалба · ' + CONFIG.marginPct + '% марж · ' + CONFIG.confidencePct + '% покритие';
    }
}

// ══════════════════════════════════════════════
// PERIOD TABS (7d / 30d / 365d)
// ══════════════════════════════════════════════
function setPeriod(period, index) {
    // Move slider
    $('periodSlider').style.left = (index * 33.33 + 0.5) + '%';

    // Update active tab
    document.querySelectorAll('.period-tab').forEach((tab, i) => {
        tab.classList.toggle('active', i === index);
    });

    // Update data
    const data = CONFIG.comparisons[period];
    if (!data) return;

    const pctEl = $('cmpPercent');
    const sign = data.pct >= 0 ? '+' : '';
    pctEl.textContent = sign + data.pct + '%';
    pctEl.className = 'comparison-percent ' + (data.pct > 0 ? 'up' : (data.pct < 0 ? 'down' : 'zero'));

    $('cmpLabel').textContent = data.label;
    $('cmpSub').textContent = data.sub;

    // Progress bar
    const bar = $('cmpBar');
    bar.className = 'comparison-bar-fill ' + (data.pct > 0 ? 'up' : (data.pct < 0 ? 'down' : 'zero'));
    const maxPct = Math.min(Math.abs(data.pct), 100);
    const barWidth = 50 + (data.pct >= 0 ? maxPct / 2 : -(maxPct / 2));
    setTimeout(() => { bar.style.width = Math.max(5, Math.min(95, barWidth)) + '%'; }, 50);
}

// Init period on load
window.addEventListener('DOMContentLoaded', () => setPeriod('7d', 0));

// ══════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════
function toggleLogout() {
    $('logoutDrop').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!$('logoutWrap').contains(e.target)) {
        $('logoutDrop').classList.remove('show');
    }
});

// ══════════════════════════════════════════════
// CHAT OVERLAY
// ══════════════════════════════════════════════
function openChat() {
    $('chatOverlay').classList.add('open');
    history.pushState({ chat: true }, '');
    scrollChatBottom();
    // Focus input
    setTimeout(() => $('chatInput').focus(), 300);
}

function closeChat() {
    $('chatOverlay').classList.remove('open');
    stopVoice();
}

function scrollChatBottom() {
    const area = $('chatMessages');
    area.scrollTop = area.scrollHeight;
}

// ══════════════════════════════════════════════
// SEND CHAT MESSAGE
// ══════════════════════════════════════════════
async function sendChatMessage() {
    const input = $('chatInput');
    const text = input.value.trim();
    if (!text) return;

    // Add user message bubble
    addUserBubble(text);

    // Clear input
    input.value = '';
    input.style.height = '';
    $('chatSendBtn').disabled = true;

    // Show typing
    $('chatTyping').style.display = 'block';
    scrollChatBottom();

    // Send to backend
    try {
        const response = await fetch('chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        });
        const data = await response.json();
        $('chatTyping').style.display = 'none';
        addAIBubble(data.reply || data.error || 'Грешка при обработка.');
    } catch (error) {
        $('chatTyping').style.display = 'none';
        addAIBubble('Грешка при свързване. Опитай пак.');
    }
}

function addUserBubble(text) {
    const group = document.createElement('div');
    group.className = 'chat-msg-group';
    const time = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    group.innerHTML = `
        <div class="chat-msg-meta right">${time}</div>
        <div class="chat-msg user-msg">${escapeHtml(text)}</div>
    `;
    const area = $('chatMessages');
    area.insertBefore(group, $('chatTyping'));
    scrollChatBottom();
}

function addAIBubble(text) {
    const group = document.createElement('div');
    group.className = 'chat-msg-group';
    const time = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    group.innerHTML = `
        <div class="chat-msg-meta">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            AI · ${time}
        </div>
        <div class="chat-msg ai-msg">${escapeHtml(text)}</div>
    `;
    const area = $('chatMessages');
    area.insertBefore(group, $('chatTyping'));
    scrollChatBottom();
}

// ══════════════════════════════════════════════
// VOICE INPUT (inside chat overlay)
// ══════════════════════════════════════════════
let voiceRecognition = null;
let isRecording = false;
let voiceTranscript = '';

function toggleVoice() {
    if (isRecording) { stopVoice(); return; }

    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласов вход'); return; }

    isRecording = true;
    voiceTranscript = '';

    // UI: show recording state
    $('chatVoiceBtn').classList.add('recording');
    $('chatRecStatus').classList.add('active');
    $('recTranscriptText').innerText = 'Слушам...';

    voiceRecognition = new SR();
    voiceRecognition.lang = 'bg-BG';
    voiceRecognition.continuous = false;
    voiceRecognition.interimResults = true;

    voiceRecognition.onresult = function(event) {
        let interim = '', final = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) {
                final += event.results[i][0].transcript;
            } else {
                interim += event.results[i][0].transcript;
            }
        }
        if (final) voiceTranscript = final;
        $('recTranscriptText').innerText = voiceTranscript || interim || 'Слушам...';
    };

    voiceRecognition.onend = function() {
        isRecording = false;
        $('chatVoiceBtn').classList.remove('recording');
        $('chatRecStatus').classList.remove('active');

        if (voiceTranscript) {
            // Put transcript in input and send
            $('chatInput').value = voiceTranscript;
            $('chatSendBtn').disabled = false;
            sendChatMessage();
        }
    };

    voiceRecognition.onerror = function(event) {
        stopVoice();
        if (event.error === 'no-speech') showToast('Не чух — опитай пак');
        else if (event.error === 'not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: ' + event.error);
    };

    try { voiceRecognition.start(); } catch (e) { stopVoice(); }
}

function stopVoice() {
    isRecording = false;
    voiceTranscript = '';
    $('chatVoiceBtn').classList.remove('recording');
    $('chatRecStatus').classList.remove('active');
    if (voiceRecognition) {
        try { voiceRecognition.stop(); } catch (e) {}
        voiceRecognition = null;
    }
}

// ══════════════════════════════════════════════
// BACK BUTTON SUPPORT
// ══════════════════════════════════════════════
window.addEventListener('popstate', function() {
    if ($('chatOverlay').classList.contains('open')) {
        closeChat();
        return;
    }
});

// Close chat overlay on click outside panel
$('chatOverlay').addEventListener('click', function(event) {
    if (event.target === this) closeChat();
});

// ══════════════════════════════════════════════
// INIT: scroll chat to bottom on load
// ══════════════════════════════════════════════
window.addEventListener('DOMContentLoaded', function() {
    scrollChatBottom();
});
</script>
</body>
</html>
