<?php
/**
 * chat.php — AI First Dashboard with Dynamic Cards
 * FIXED: quantity вместо qty, min_quantity вместо min_qty
 */
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

// ═══════════════════════════════════════════════════════════
// DYNAMIC DATA FETCHING FOR CARDS (FIXED COLUMN NAMES)
// ═══════════════════════════════════════════════════════════

// 1. ZOMBIE STOCK (45+ days no movement)
try {
    $zombie = DB::run("
        SELECT 
            COUNT(DISTINCT p.id) as count,
            SUM(p.retail_price * i.quantity) as frozen_capital
        FROM products p
        JOIN inventory i ON p.id = i.product_id
        WHERE i.quantity > 0
          AND p.tenant_id = ?
          AND p.id NOT IN (
              SELECT product_id FROM sale_items 
              WHERE created_at > DATE_SUB(NOW(), INTERVAL 45 DAY)
          )
    ", [$tenant_id])->fetch();
    $zombie_count = $zombie['count'] ?? 0;
    $zombie_value = $zombie['frozen_capital'] ?? 0;
} catch (Exception $e) {
    $zombie_count = 0;
    $zombie_value = 0;
}

// 2. TODAY'S PROFIT (owner only)
try {
    $profit_today = DB::run("
        SELECT 
            SUM((si.unit_price - si.cost_price) * si.qty) as profit,
            COUNT(DISTINCT s.id) as sales_count,
            AVG((si.unit_price - si.cost_price) / si.unit_price * 100) as margin
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        WHERE DATE(s.created_at) = CURDATE()
          AND s.tenant_id = ?
    ", [$tenant_id])->fetch();
    $profit = $profit_today['profit'] ?? 0;
    $sales_count = $profit_today['sales_count'] ?? 0;
    $margin = round($profit_today['margin'] ?? 0);
} catch (Exception $e) {
    $profit = 0;
    $sales_count = 0;
    $margin = 0;
}

// 3. LOW STOCK (below min_quantity)
try {
    $low_stock = DB::run("
        SELECT 
            COUNT(*) as count,
            SUM(p.retail_price * (i.min_quantity - i.quantity)) as needed_value
        FROM products p
        JOIN inventory i ON p.id = i.product_id
        WHERE i.quantity < i.min_quantity
          AND p.tenant_id = ?
    ", [$tenant_id])->fetch();
    $low_count = $low_stock['count'] ?? 0;
    $low_value = $low_stock['needed_value'] ?? 0;
} catch (Exception $e) {
    $low_count = 0;
    $low_value = 0;
}

// 4. DEAD SIZES (popular sizes out of stock)
try {
    $dead_sizes = DB::run("
        SELECT 
            COUNT(DISTINCT pv.id) as count
        FROM products p
        JOIN products pv ON pv.parent_id = p.id
        LEFT JOIN inventory i ON pv.id = i.product_id
        WHERE (i.quantity = 0 OR i.quantity IS NULL)
          AND p.tenant_id = ?
          AND pv.id IN (
              SELECT product_id FROM sale_items 
              WHERE created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
              GROUP BY product_id
              HAVING SUM(qty) >= 3
          )
    ", [$tenant_id])->fetch();
    $dead_count = $dead_sizes['count'] ?? 0;
} catch (Exception $e) {
    $dead_count = 0;
}

// 5. TOP PRODUCT TODAY
try {
    $top_product = DB::run("
        SELECT 
            p.name,
            SUM(si.qty) as qty_sold,
            SUM(si.unit_price * si.qty) as revenue,
            i.quantity as remaining
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE DATE(s.created_at) = CURDATE()
          AND s.tenant_id = ?
        GROUP BY p.id
        ORDER BY qty_sold DESC
        LIMIT 1
    ", [$tenant_id])->fetch();
    $top_name = $top_product['name'] ?? 'Няма';
    $top_qty = $top_product['qty_sold'] ?? 0;
    $top_revenue = $top_product['revenue'] ?? 0;
    $top_remaining = $top_product['remaining'] ?? 0;
} catch (Exception $e) {
    $top_name = 'Няма';
    $top_qty = 0;
    $top_revenue = 0;
    $top_remaining = 0;
}

// 6. WHAT TO ORDER (manager)
try {
    $order_needed = DB::run("
        SELECT 
            SUM(p.cost_price * (i.min_quantity - i.quantity)) as order_value
        FROM products p
        JOIN inventory i ON p.id = i.product_id
        WHERE i.quantity < i.min_quantity
          AND p.tenant_id = ?
    ", [$tenant_id])->fetch();
    $order_value = $order_needed['order_value'] ?? 0;
} catch (Exception $e) {
    $order_value = 0;
}

// Fetch messages
$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id = ? AND store_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id, $store_id]
)->fetchAll();

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';

$unread = DB::run(
    'SELECT COUNT(*) as cnt FROM store_messages WHERE tenant_id = ? AND to_store_id = ? AND is_read = 0',
    [$tenant_id, $store_id]
)->fetch();
$unread_count = $unread ? (int)$unread['cnt'] : 0;

$quick_cmds = [
    ['icon' => '📦', 'label' => 'Склад',      'msg' => 'Покажи склада'],
    ['icon' => '💰', 'label' => 'Продажби',   'msg' => 'Колко продадох днес?'],
    ['icon' => '⚠️', 'label' => 'Ниска нал.', 'msg' => 'Кои артикули са под минимума?'],
];
if (in_array($role, ['owner','manager'])) {
    $quick_cmds[] = ['icon' => '🚚', 'label' => 'Доставка', 'msg' => 'Нова доставка'];
}
if ($role === 'owner') {
    $quick_cmds[] = ['icon' => '📊', 'label' => 'Печалба', 'msg' => 'Каква е печалбата ми днес?'];
}
$quick_cmds[] = ['icon' => '🎁', 'label' => 'Лоялна', 'msg' => 'Лоялна програма'];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#0b0f1a">
<title>RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
html { background-color: #0b0f1a; }
:root { --nav-h: 64px; }
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
body { 
    background: radial-gradient(circle at top right, #13172c, #0b0f1a); 
    color: #e2e8f0; 
    font-family: Inter, sans-serif; 
    height: 100dvh; 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
    padding-bottom: var(--nav-h); 
}

/* ── SVG BACKGROUNDS ── */
.bg-illus { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; opacity: 0.7; }
.bg-illus img { position: absolute; max-width: none; }
.bg-illus .ill1 { left: 50%; top: 0; transform: translateX(-25%); width: 846px; height: 594px; }
.bg-illus .ill2 { left: 50%; top: 400px; transform: translateX(-100%); width: 760px; height: 668px; opacity: .4; }
.bg-illus .ill3 { left: 50%; top: 440px; transform: translateX(-33%); width: 760px; height: 668px; opacity: 0.5; }

/* ── HEADER ── */
.hdr { position: relative; z-index: 50; background: transparent; flex-shrink: 0; padding-bottom: 5px; }
.hdr-top { display: flex; align-items: center; justify-content: space-between; padding: 16px 16px 8px; gap: 8px; }
.brand { font-size: 20px; font-weight: 800; flex: 1; background: linear-gradient(135deg, #ffffff, #a5b4fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-family: 'Nacelle', Inter, sans-serif; letter-spacing: -0.5px; }
.store-pill { font-size: 11px; font-weight: 700; color: #a5b4fc; background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3); border-radius: 30px; padding: 5px 12px; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 0 10px rgba(99,102,241,0.2); }
.hdr-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); display: flex; align-items: center; justify-content: center; cursor: pointer; color: #c7d2fe; position: relative; flex-shrink: 0; backdrop-filter: blur(10px); transition: 0.2s; }
.hdr-btn:active { background: rgba(99,102,241,.3); border-color: rgba(99,102,241,.5); transform: scale(0.95); }
.hdr-badge { position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; border-radius: 9px; background: #ef4444; font-size: 10px; font-weight: 800; color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 4px; box-shadow: 0 2px 5px rgba(239,68,68,0.5); }

/* ── TABS (Modern Pills) ── */
.tabs { display: flex; padding: 0 16px; gap: 8px; justify-content: flex-start; }
.tab { padding: 6px 14px; font-size: 12px; font-weight: 700; color: #9ca3af; text-align: center; border-radius: 20px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); cursor: pointer; text-decoration: none; display: flex; align-items: center; transition: all .2s; }
.tab.active { background: rgba(99,102,241,.2); border-color: rgba(99,102,241,.4); color: #fff; box-shadow: 0 0 15px rgba(99,102,241,0.15); }
.tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 16px; height: 16px; border-radius: 8px; background: #ef4444; font-size: 9px; font-weight: 800; color: #fff; margin-left: 6px; padding: 0 3px; }

/* ── BRIEFING ── */
.brief-area { padding: 10px 16px 0; flex-shrink: 0; position: relative; z-index: 1; }
.brief-greeting { font-size: 14px; font-weight: 700; color: #f8fafc; margin-bottom: 6px; }
.brief-card { border-radius: 14px; padding: 12px; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 10px; position: relative; text-decoration: none; animation: fadeUp .35s ease both; backdrop-filter: blur(10px); }
.brief-card.red    { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3); box-shadow: 0 4px 15px rgba(239,68,68,0.05); }
.brief-card.orange { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); box-shadow: 0 4px 15px rgba(245,158,11,0.05); }
.brief-card.yellow { background: rgba(234,179,8,.1);  border: 1px solid rgba(234,179,8,.3); box-shadow: 0 4px 15px rgba(234,179,8,0.05); }
.brief-card.green  { background: rgba(34,197,94,.1);  border: 1px solid rgba(34,197,94,.3); box-shadow: 0 4px 15px rgba(34,197,94,0.05); }
.brief-text { flex: 1; font-size: 13px; color: #f1f5f9; line-height: 1.4; }
.brief-close { width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,.1); border: none; color: #fff; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: 0.2s; }
.brief-close:active { background: rgba(255,255,255,.2); transform: scale(0.9); }
.brief-loading { font-size: 12px; color: #6b7280; padding: 4px 0; }

/* ── PULSE CARDS ── */
.pulse-cards-section { padding: 0 16px 10px; flex-shrink: 0; position: relative; z-index: 1; }
.pulse-cards-title {
    font-size: 11px;
    font-weight: 800;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pulse-card { 
    border-radius: 16px; 
    padding: 16px; 
    margin-bottom: 10px; 
    display: flex; 
    flex-direction: column; 
    gap: 6px; 
    position: relative; 
    animation: fadeUp .4s ease both; 
    backdrop-filter: blur(12px);
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}
.pulse-card:nth-child(2) { animation-delay: 0.05s; }
.pulse-card:nth-child(3) { animation-delay: 0.1s; }
.pulse-card:nth-child(4) { animation-delay: 0.15s; }
.pulse-card:nth-child(5) { animation-delay: 0.2s; }
.pulse-card:nth-child(6) { animation-delay: 0.25s; }
.pulse-card:active { transform: scale(0.98); }

.pulse-card.danger { 
    background: rgba(239,68,68,.08); 
    border: 1px solid rgba(239,68,68,.25); 
    border-left: 4px solid #ef4444;
    box-shadow: 0 4px 20px rgba(239,68,68,0.1);
}
.pulse-card.success { 
    background: rgba(34,197,94,.08); 
    border: 1px solid rgba(34,197,94,.25); 
    border-left: 4px solid #22c55e;
    box-shadow: 0 4px 20px rgba(34,197,94,0.1);
}
.pulse-card.warning { 
    background: rgba(245,158,11,.08); 
    border: 1px solid rgba(245,158,11,.25); 
    border-left: 4px solid #f59e0b;
    box-shadow: 0 4px 20px rgba(245,158,11,0.1);
}
.pulse-card.info { 
    background: rgba(99,102,241,.08); 
    border: 1px solid rgba(99,102,241,.25); 
    border-left: 4px solid #6366f1;
    box-shadow: 0 4px 20px rgba(99,102,241,0.1);
}

.pulse-card-header { display: flex; align-items: center; justify-content: space-between; }
.pulse-card-icon-wrap { display: flex; align-items: center; gap: 10px; }
.pulse-card-icon { 
    width: 40px; 
    height: 40px; 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 20px;
    background: rgba(255,255,255,.06);
}
.pulse-card.danger .pulse-card-icon { background: rgba(239,68,68,.18); }
.pulse-card.success .pulse-card-icon { background: rgba(34,197,94,.18); }
.pulse-card.warning .pulse-card-icon { background: rgba(245,158,11,.18); }
.pulse-card.info .pulse-card-icon { background: rgba(99,102,241,.18); }

.pulse-card-trend { 
    font-size: 11px; 
    padding: 4px 10px; 
    border-radius: 20px; 
    font-weight: 700;
}
.trend-up { background: rgba(34,197,94,.2); color: #22c55e; }
.trend-down { background: rgba(239,68,68,.2); color: #ef4444; }

.pulse-card-value { 
    font-family: 'Montserrat', Inter, sans-serif;
    font-size: 28px; 
    font-weight: 800; 
    color: #fff;
    letter-spacing: -0.5px;
    margin-top: 4px;
}
.pulse-card-label { 
    font-size: 15px; 
    font-weight: 700; 
    color: #f1f5f9;
}
.pulse-card-sub { 
    font-size: 13px; 
    color: #9ca3af;
    line-height: 1.4;
}
.pulse-card-voice { 
    margin-top: 10px;
    padding: 10px 14px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #c7d2fe;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.pulse-card-voice:active { 
    background: rgba(99,102,241,.2); 
    border-color: rgba(99,102,241,.4);
    transform: scale(0.98);
}

/* ── CHAT AREA ── */
.chat-area { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 16px 16px 0; display: flex; flex-direction: column; -webkit-overflow-scrolling: touch; scrollbar-width: none; position: relative; z-index: 1; }
.chat-area::-webkit-scrollbar { display: none; }
.msg-group { margin-bottom: 16px; animation: fadeUp .3s ease both; }
.msg-meta { font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
.msg-meta.right { justify-content: flex-end; }
.ai-ava { width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0; background: linear-gradient(135deg, #4f46e5, #9333ea); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 12px rgba(99,102,241,.6); border: 2px solid #0b0f1a; }
.ai-ava-bars { display: flex; gap: 2px; align-items: center; height: 10px; }
.ai-ava-bar { width: 2px; border-radius: 1px; background: #fff; animation: barDance 1s ease-in-out infinite; }
.ai-ava-bar:nth-child(1) { height: 4px; }
.ai-ava-bar:nth-child(2) { height: 8px; animation-delay: .15s; }
.ai-ava-bar:nth-child(3) { height: 10px; animation-delay: .3s; }
.ai-ava-bar:nth-child(4) { height: 5px; animation-delay: .45s; }
.msg { max-width: 88%; padding: 12px 16px; font-size: 14px; line-height: 1.5; word-break: break-word; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.msg.ai { background: rgba(30,35,60,.65); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,.2); color: #f8fafc; border-radius: 4px 20px 20px 20px; }
.msg.user { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; border-radius: 20px 20px 4px 20px; margin-left: auto; border: 1px solid rgba(255,255,255,0.1); }
.msg a.deeplink { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(0,0,0,.2); border: 1px solid rgba(165,180,252,.3); border-radius: 12px; color: #c7d2fe; font-size: 12px; font-weight: 700; text-decoration: none; margin: 6px 2px 0; transition: 0.2s; }
.msg a.deeplink:active { background: rgba(99,102,241,.4); border-color: #a5b4fc; }
.typing-wrap { display: none; padding: 12px 16px; background: rgba(30,35,60,.65); border: 1px solid rgba(99,102,241,.2); border-radius: 4px 20px 20px 20px; width: fit-content; margin-bottom: 16px; backdrop-filter: blur(10px); }
.typing-dots { display: flex; gap: 4px; align-items: center; }
.dot { width: 6px; height: 6px; border-radius: 50%; background: #818cf8; animation: bounce 1.2s infinite; }
.dot:nth-child(2) { animation-delay: .2s; }
.dot:nth-child(3) { animation-delay: .4s; }
.welcome { text-align: center; padding: 40px 20px 20px; color: #9ca3af; font-size: 14px; line-height: 1.6; }
.welcome-title { font-size: 24px; font-weight: 800; margin-bottom: 10px; background: linear-gradient(to right, #e5e7eb, #c7d2fe, #f9fafb); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gShift 6s linear infinite; }

/* ── QUICK COMMANDS ── */
.quick-wrap { padding: 10px 16px; flex-shrink: 0; position: relative; z-index: 1; }
.quick-row { display: flex; gap: 8px; flex-wrap: nowrap; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
.quick-row::-webkit-scrollbar { display: none; }
.quick-btn { padding: 8px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid rgba(99,102,241,.3); color: #c7d2fe; background: rgba(99,102,241,.1); cursor: pointer; font-family: inherit; white-space: nowrap; display: flex; align-items: center; gap: 6px; transition: 0.2s; backdrop-filter: blur(5px); }
.quick-btn:active { background: rgba(99,102,241,.4); border-color: #a5b4fc; transform: scale(0.96); }

/* ── INPUT AREA ── */
.input-area { background: transparent; padding: 0 16px 16px; flex-shrink: 0; position: relative; z-index: 10; border: none; }
.input-row { display: flex; gap: 8px; align-items: center; background: rgba(15,20,35,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(99,102,241,.3); border-radius: 32px; padding: 6px 6px 6px 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1); }
.text-input { flex: 1; background: transparent; border: none; color: #f8fafc; font-size: 15px; padding: 10px 0; font-family: inherit; outline: none; resize: none; max-height: 100px; line-height: 1.4; }
.text-input::placeholder { color: #6b7280; font-weight: 500; }

/* ── VOICE & SEND ── */
.voice-wrap { position: relative; flex-shrink: 0; width: 44px; height: 44px; cursor: pointer; }
.voice-ring { position: absolute; border-radius: 50%; border: 1px solid rgba(139,92,246,.5); animation: waveOut 2s ease-out infinite; pointer-events: none; }
.voice-ring:nth-child(1) { inset: -4px; }
.voice-ring:nth-child(2) { inset: -10px; animation-delay: .55s; }
.voice-ring:nth-child(3) { inset: -16px; animation-delay: 1.1s; }
.voice-inner { width: 100%; height: 100%; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #9333ea); display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; box-shadow: 0 0 15px rgba(99,102,241,.4); }
.voice-bars { display: flex; gap: 2px; align-items: center; height: 16px; }
.voice-bar { width: 3px; border-radius: 2px; background: #fff; animation: barDance 1s ease-in-out infinite; }
.voice-bar:nth-child(1) { height: 6px; }
.voice-bar:nth-child(2) { height: 12px; animation-delay: .15s; }
.voice-bar:nth-child(3) { height: 16px; animation-delay: .3s; }
.voice-bar:nth-child(4) { height: 10px; animation-delay: .45s; }
.voice-bar:nth-child(5) { height: 6px; animation-delay: .6s; }
.voice-wrap.recording .voice-inner { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 0 20px rgba(239,68,68,.6); }
.voice-wrap.recording .voice-ring { border-color: rgba(239,68,68,.5); }
.send-btn { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: 0.2s; }
.send-btn:active { background: rgba(99,102,241,.5); transform: scale(.92); }
.send-btn:disabled { opacity: .2; cursor: default; background: transparent; color: #6b7280; border-color: transparent; }

/* ── OVERLAYS ── */
.act-ovl { position: fixed; inset: 0; background: rgba(0,0,0,.7); backdrop-filter: blur(8px); z-index: 350; display: none; align-items: flex-end; justify-content: center; }
.act-ovl.show { display: flex; }
.act-box { background: #0f1423; border: 1px solid rgba(99,102,241,.3); border-radius: 24px 24px 0 0; width: 100%; max-width: 420px; padding: 28px 24px 36px; box-shadow: 0 -10px 40px rgba(0,0,0,0.5); }
.act-title { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 12px; text-align: center; }
.act-desc { font-size: 14px; color: #9ca3af; margin-bottom: 24px; text-align: center; line-height: 1.6; }
.act-btns { display: flex; gap: 12px; }
.act-yes { flex: 1; padding: 14px; border: none; border-radius: 16px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; box-shadow: 0 4px 15px rgba(99,102,241,0.4); }
.act-no { flex: 1; padding: 14px; border: 1px solid rgba(99,102,241,.3); border-radius: 16px; background: rgba(255,255,255,0.05); color: #d1d5db; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; }

.rec-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.9); z-index: 400; display: none; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(16px); }
.rec-overlay.show { display: flex; }
.rec-circle { width: 110px; height: 110px; border-radius: 50%; background: linear-gradient(135deg, #ef4444, #b91c1c); display: flex; align-items: center; justify-content: center; margin-bottom: 30px; animation: recPulse 1s ease-out infinite; box-shadow: 0 0 30px rgba(239,68,68,0.4); }
.rec-wave-bars { display: flex; gap: 5px; align-items: center; height: 36px; }
.rec-bar { width: 5px; border-radius: 3px; background: #fff; animation: barDance .7s ease-in-out infinite; }
.rec-bar:nth-child(1) { height: 12px; }
.rec-bar:nth-child(2) { height: 24px; animation-delay: .1s; }
.rec-bar:nth-child(3) { height: 36px; animation-delay: .2s; }
.rec-bar:nth-child(4) { height: 20px; animation-delay: .3s; }
.rec-bar:nth-child(5) { height: 12px; animation-delay: .4s; }
.rec-title { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 8px; }
.rec-sub { font-size: 15px; color: #9ca3af; margin-bottom: 30px; }
.rec-stop { padding: 14px 32px; background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.4); border-radius: 30px; color: #fca5a5; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; transition: 0.2s; }
.rec-stop:active { background: rgba(239,68,68,.3); transform: scale(0.95); }

/* ── BOTTOM NAV ── */
.bottom-nav { 
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; 
    background: rgba(11,15,26,0.92); 
    backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); 
    border-top: 1px solid rgba(99,102,241,0.25); 
    display: flex; height: var(--nav-h); 
    box-shadow: 0 -5px 25px rgba(99,102,241,0.2);
}
.bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; font-size: 0.65rem; font-weight: 600; color: rgba(165,180,252,0.5); text-decoration: none; transition: all 0.3s; }
.bnav-tab.active { 
    color: #c7d2fe; 
    text-shadow: 0 0 12px rgba(129,140,248,0.9);
}
.bnav-tab .bnav-icon { font-size: 1.3rem; transition: all 0.3s; }
.bnav-tab.active .bnav-icon {
    transform: translateY(-2px);
    filter: drop-shadow(0 0 8px rgba(129,140,248,0.8));
}

/* ── TOAST ── */
.toast { position: fixed; bottom: 85px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; padding: 12px 24px; border-radius: 30px; font-size: 14px; font-weight: 700; z-index: 500; opacity: 0; transition: opacity .3s, transform .3s; pointer-events: none; white-space: nowrap; box-shadow: 0 5px 20px rgba(99,102,241,0.5); }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(-10px); }

/* ── ANIMATIONS ── */
@keyframes gShift { 0% { background-position: 0% center } 100% { background-position: 200% center } }
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px) } to { opacity: 1; transform: translateY(0) } }
@keyframes bounce { 0%,60%,100% { transform: translateY(0) } 30% { transform: translateY(-5px) } }
@keyframes barDance { 0%,100% { transform: scaleY(1) } 50% { transform: scaleY(.3) } }
@keyframes waveOut { 0% { transform: scale(1); opacity: .6 } 100% { transform: scale(2); opacity: 0 } }
@keyframes recPulse { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,.6) } 70% { box-shadow: 0 0 0 20px rgba(239,68,68,0) } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0) } }

.indigo-sep { display: none; }
</style>
</head>
<body>

<div class="bg-illus" aria-hidden="true">
  <img class="ill1" src="./images/page-illustration.svg" alt="">
  <img class="ill2" src="./images/blurred-shape-gray.svg" alt="">
  <img class="ill3" src="./images/blurred-shape.svg" alt="">
</div>

<div class="hdr">
  <div class="hdr-top">
    <div class="brand">RunMyStore.ai</div>
    <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
    <div class="hdr-btn" onclick="doPulse()" title="Пулс">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
    </div>
    <div class="hdr-btn" onclick="fillAndSend('Покажи всички нотификации')">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    </div>
  </div>
  <div class="tabs">
    <div class="tab active">✦ AI Асистент</div>
    <a class="tab" href="store-chat.php">Чат Обекти<?php if ($unread_count > 0): ?><span class="tab-badge"><?= $unread_count ?></span><?php endif; ?></a>
  </div>
  <div class="indigo-sep"></div>
</div>

<div class="brief-area" id="briefArea">
  <div class="brief-loading" id="briefLoading">Зареждам...</div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- DYNAMIC CARDS BASED ON ROLE (FIXED COLUMN NAMES) -->
<!-- ═══════════════════════════════════════════════════════════ -->

<?php if ($role === 'owner'): ?>
<!-- OWNER: 5 CARDS -->
<div class="pulse-cards-section">
  <div class="pulse-cards-title">📊 Бърз преглед · Собственик</div>
  
  <!-- Card 1: Zombie -->
  <?php if ($zombie_count > 0): ?>
  <div class="pulse-card danger" onclick="fillAndSend('Покажи zombie стоката')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">💀</div>
      </div>
      <span class="pulse-card-trend trend-up">↑ Замразени</span>
    </div>
    <div class="pulse-card-value">€<?= number_format($zombie_value, 0, ',', '.') ?></div>
    <div class="pulse-card-label">Zombie стока</div>
    <div class="pulse-card-sub"><?= $zombie_count ?> артикула · 45+ дни без движение</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Намали цените на zombie стоката с 20%')">
      <span>🎤</span> Намали цените
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 2: Profit Today -->
  <div class="pulse-card success" onclick="fillAndSend('Каква е печалбата днес')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">🔥</div>
      </div>
      <span class="pulse-card-trend trend-up">↑ Днес</span>
    </div>
    <div class="pulse-card-value">€<?= number_format($profit, 0, ',', '.') ?></div>
    <div class="pulse-card-label">Печалба днес</div>
    <div class="pulse-card-sub"><?= $sales_count ?> продажби · Марж <?= $margin ?>%</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Покажи детайли за печалбата')">
      <span>🎤</span> Детайли
    </div>
  </div>
  
  <!-- Card 3: Dead Sizes -->
  <?php if ($dead_count > 0): ?>
  <div class="pulse-card warning" onclick="fillAndSend('Покажи липсващите размери')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">📐</div>
      </div>
      <span class="pulse-card-trend trend-down">↓ Липсват</span>
    </div>
    <div class="pulse-card-value"><?= $dead_count ?> размера</div>
    <div class="pulse-card-label">Липсващи размери</div>
    <div class="pulse-card-sub">Популярни, но изчерпани · Губим продажби</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Поръчай липсващите размери')">
      <span>🎤</span> Поръчай сега
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 4: Low Stock -->
  <?php if ($low_count > 0): ?>
  <div class="pulse-card warning" onclick="fillAndSend('Какво свършва')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">⚠️</div>
      </div>
      <span class="pulse-card-trend trend-down">↓ Свършва</span>
    </div>
    <div class="pulse-card-value"><?= $low_count ?> арт.</div>
    <div class="pulse-card-label">Свършва</div>
    <div class="pulse-card-sub">Под минимум · €<?= number_format($low_value, 0, ',', '.') ?> стойност</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Направи поръчка за свършващите артикули')">
      <span>🎤</span> Направи поръчка
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 5: Top Product -->
  <?php if ($top_qty > 0): ?>
  <div class="pulse-card info" onclick="fillAndSend('Покажи топ продаваните днес')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">🏆</div>
      </div>
      <span class="pulse-card-trend trend-up">↑ Топ</span>
    </div>
    <div class="pulse-card-value"><?= htmlspecialchars($top_name) ?></div>
    <div class="pulse-card-label">Топ артикул днес</div>
    <div class="pulse-card-sub"><?= $top_qty ?> бр · €<?= number_format($top_revenue, 0, ',', '.') ?> · Остават <?= $top_remaining ?> бр</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Поръчай още от <?= htmlspecialchars($top_name) ?>')">
      <span>🎤</span> Поръчай още
    </div>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($role === 'manager'): ?>
<!-- MANAGER: 4 CARDS -->
<div class="pulse-cards-section">
  <div class="pulse-cards-title">📊 Бърз преглед · Управител</div>
  
  <!-- Card 1: Low Stock -->
  <?php if ($low_count > 0): ?>
  <div class="pulse-card warning" onclick="fillAndSend('Какво свършва')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">⚠️</div>
      </div>
      <span class="pulse-card-trend trend-down">↓ Свършва</span>
    </div>
    <div class="pulse-card-value"><?= $low_count ?> арт.</div>
    <div class="pulse-card-label">Какво свършва</div>
    <div class="pulse-card-sub">Под минимум · Нужни за поръчка</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Направи поръчка')">
      <span>🎤</span> Направи поръчка
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 2: What to Order -->
  <?php if ($order_value > 0): ?>
  <div class="pulse-card info" onclick="fillAndSend('Какво трябва да поръчам')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">📋</div>
      </div>
      <span class="pulse-card-trend trend-up">→ Поръчка</span>
    </div>
    <div class="pulse-card-value">€<?= number_format($order_value, 0, ',', '.') ?></div>
    <div class="pulse-card-label">Какво да поръчам</div>
    <div class="pulse-card-sub">Мин. стойност за поръчка · <?= $low_count ?> артикула</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Генерирай поръчка към доставчици')">
      <span>🎤</span> Генерирай
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 3: Dead Sizes -->
  <?php if ($dead_count > 0): ?>
  <div class="pulse-card warning" onclick="fillAndSend('Покажи липсващите размери')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">📐</div>
      </div>
      <span class="pulse-card-trend trend-down">↓ Липсват</span>
    </div>
    <div class="pulse-card-value"><?= $dead_count ?> размера</div>
    <div class="pulse-card-label">Кои размери липсват</div>
    <div class="pulse-card-sub">Популярни, но изчерпани</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Поръчай липсващите размери')">
      <span>🎤</span> Поръчай
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 4: Top Product -->
  <?php if ($top_qty > 0): ?>
  <div class="pulse-card success" onclick="fillAndSend('Покажи топ продаваните днес')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">🏆</div>
      </div>
      <span class="pulse-card-trend trend-up">↑ Топ</span>
    </div>
    <div class="pulse-card-value"><?= htmlspecialchars($top_name) ?></div>
    <div class="pulse-card-label">Топ продавани</div>
    <div class="pulse-card-sub"><?= $top_qty ?> бр днес · Остават <?= $top_remaining ?> бр</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Провери наличност за <?= htmlspecialchars($top_name) ?>')">
      <span>🎤</span> Провери наличност
    </div>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- SELLER: 3 CARDS -->
<div class="pulse-cards-section">
  <div class="pulse-cards-title">📊 Бърз преглед · Продавач</div>
  
  <!-- Card 1: Dead Sizes -->
  <?php if ($dead_count > 0): ?>
  <div class="pulse-card warning" onclick="fillAndSend('Какви размери липсват')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">📐</div>
      </div>
    </div>
    <div class="pulse-card-value"><?= $dead_count ?> размера</div>
    <div class="pulse-card-label">Липсващи размери</div>
    <div class="pulse-card-sub">Клиенти питат, но нямаме</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Уведоми управителя за липсващите размери')">
      <span>🎤</span> Уведоми шефа
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 2: Low Stock -->
  <?php if ($low_count > 0): ?>
  <div class="pulse-card warning" onclick="fillAndSend('Какво свършва')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">⚠️</div>
      </div>
    </div>
    <div class="pulse-card-value"><?= $low_count ?> арт.</div>
    <div class="pulse-card-label">Какво свършва</div>
    <div class="pulse-card-sub">Под минимум · Предложи алтернатива</div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Покажи алтернативи за свършващите артикули')">
      <span>🎤</span> Алтернативи
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Card 3: Top Product -->
  <?php if ($top_qty > 0): ?>
  <div class="pulse-card success" onclick="fillAndSend('Какво се продава най-много днес')">
    <div class="pulse-card-header">
      <div class="pulse-card-icon-wrap">
        <div class="pulse-card-icon">🏆</div>
      </div>
    </div>
    <div class="pulse-card-value"><?= htmlspecialchars($top_name) ?></div>
    <div class="pulse-card-label">Топ днес</div>
    <div class="pulse-card-sub"><?= $top_qty ?> бр · €<?= number_format($top_revenue, 0, ',', '.') ?></div>
    <div class="pulse-card-voice" onclick="event.stopPropagation();fillAndSend('Предложи <?= htmlspecialchars($top_name) ?> на клиенти')">
      <span>🎤</span> Предложи на клиенти
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<!-- ═══════════════════════════════════════════════════════════ -->

<div class="chat-area" id="chatArea">
  <?php if (empty($messages)): ?>
  <div class="welcome">
    <div class="welcome-title">Здравей<?= $user_name ? ', ' . htmlspecialchars($user_name) : '' ?>!</div>
    Аз съм твоят AI асистент за <?= htmlspecialchars($store_name) ?>.<br>
    Натисни микрофона или пиши.
  </div>
  <?php else: ?>
  <?php foreach ($messages as $msg): ?>
  <div class="msg-group">
    <?php if ($msg['role'] === 'assistant'): ?>
      <div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div> AI</div>
      <div class="msg ai"><?= parseDeeplinks(nl2br(htmlspecialchars($msg['content']))) ?></div>
    <?php else: ?>
      <div class="msg-meta right"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
      <div style="display:flex;justify-content:flex-end"><div class="msg user"><?= nl2br(htmlspecialchars($msg['content'])) ?></div></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <div class="typing-wrap" id="typing"><div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
</div>

<div class="quick-wrap">
  <div class="quick-row">
    <?php foreach ($quick_cmds as $cmd): ?>
    <button class="quick-btn" onclick="fillAndSend(<?= htmlspecialchars(json_encode($cmd['msg']), ENT_QUOTES) ?>)"><?= $cmd['icon'] ?> <?= htmlspecialchars($cmd['label']) ?></button>
    <?php endforeach; ?>
  </div>
</div>

<div class="indigo-sep"></div>

<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1" oninput="autoResize(this)" onkeydown="handleKey(event)"></textarea>
    <div class="voice-wrap" id="voiceWrap" onclick="toggleVoice()">
      <div class="voice-ring"></div><div class="voice-ring"></div><div class="voice-ring"></div>
      <div class="voice-inner"><div class="voice-bars"><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div></div></div>
    </div>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>

<div class="act-ovl" id="actOvl">
  <div class="act-box">
    <div class="act-title" id="actTitle">Потвърждение</div>
    <div class="act-desc" id="actDesc"></div>
    <div class="act-btns">
      <button class="act-yes" onclick="confirmAction()">Да</button>
      <button class="act-no" onclick="cancelAction()">Не</button>
    </div>
  </div>
</div>

<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle"><div class="rec-wave-bars"><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div></div></div>
  <div class="rec-title">Слушам...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<nav class="bottom-nav">
    <a href="chat.php" class="bnav-tab active"><span class="bnav-icon">✦</span>AI</a>
    <a href="warehouse.php" class="bnav-tab"><span class="bnav-icon">📦</span>Склад</a>
    <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
    <a href="actions.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
</nav>

<div class="toast" id="toast"></div>

<?php
function parseDeeplinks($html) {
    $map = [
        '📦' => 'products.php?filter=low',
        '⚠️' => 'purchase-orders.php',
        '📊' => 'stats.php',
        '💰' => 'sale.php',
        '🔄' => 'transfers.php',
    ];
    return preg_replace_callback('/\[([^\]]+?)→\]/u', function($m) use ($map) {
        $text = trim($m[1]);
        $href = '#';
        foreach ($map as $emoji => $url) {
            if (mb_strpos($text, $emoji) !== false) { $href = $url; break; }
        }
        return '<a class="deeplink" href="' . $href . '">' . htmlspecialchars($text) . ' →</a>';
    }, $html);
}
?>

<script>
const chatArea  = document.getElementById('chatArea');
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');
const voiceWrap = document.getElementById('voiceWrap');
const recOverlay= document.getElementById('recOverlay');
let voiceRec = null, isRecording = false, pendingAction = null;

const dlMap = {'📦':'products.php?filter=low','⚠️':'purchase-orders.php','📊':'stats.php','💰':'sale.php','🔄':'transfers.php'};

function parseDeeplinksJS(text) {
  return text.replace(/\[([^\]]+?)→\]/gu, (m, inner) => {
    let href = '#';
    for (const [emoji, url] of Object.entries(dlMap)) {
      if (inner.includes(emoji)) { href = url; break; }
    }
    return `<a class="deeplink" href="${href}">${esc(inner.trim())} →</a>`;
  });
}

function scrollBottom() { chatArea.scrollTop = chatArea.scrollHeight; }
scrollBottom();

chatInput.addEventListener('input', function() { btnSend.disabled = !this.value.trim(); });
function autoResize(el) { el.style.height = ''; el.style.height = Math.min(el.scrollHeight, 100) + 'px'; }
function handleKey(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }
function fillAndSend(text) { chatInput.value = text; btnSend.disabled = false; sendMessage(); }

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;
  addUserMsg(text);
  chatInput.value = ''; chatInput.style.height = ''; btnSend.disabled = true;
  typing.style.display = 'block'; scrollBottom();
  try {
    const res  = await fetch('chat-send.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({message:text}) });
    const data = await res.json();
    typing.style.display = 'none';
    const reply = data.reply || data.error || 'Грешка';
    addAIMsg(reply);
    if (data.action) {
      pendingAction = data.action;
      document.getElementById('actDesc').textContent = data.action.details || JSON.stringify(data.action);
      document.getElementById('actOvl').classList.add('show');
    }
  } catch(e) {
    typing.style.display = 'none';
    addAIMsg('Грешка при свързване.');
  }
}

function addUserMsg(text) {
  const g = document.createElement('div'); g.className = 'msg-group';
  g.innerHTML = `<div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div><div style="display:flex;justify-content:flex-end"><div class="msg user">${esc(text)}</div></div>`;
  chatArea.insertBefore(g, typing); scrollBottom();
}

function addAIMsg(text) {
  const g = document.createElement('div'); g.className = 'msg-group';
  const parsed = parseDeeplinksJS(esc(text).replace(/\n/g,'<br>'));
  g.innerHTML = `<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div> AI</div><div class="msg ai">${parsed}</div>`;
  chatArea.insertBefore(g, typing); scrollBottom();
}

function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function confirmAction() {
  document.getElementById('actOvl').classList.remove('show');
  showToast('Ще бъде изпълнено');
  pendingAction = null;
}
function cancelAction() {
  document.getElementById('actOvl').classList.remove('show');
  pendingAction = null;
}

async function doPulse() {
  showToast('Проверявам...');
  try {
    const r = await fetch('ai-helper.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'pulse'}) });
    const d = await r.json();
    showToast(d.message || 'Готово');
  } catch(e) { showToast('Грешка'); }
}

async function loadBriefing() {
  const area = document.getElementById('briefArea');
  try {
    const r = await fetch('ai-helper.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'briefing'}) });
    const d = await r.json();
    let html = '';
    if (d.greeting) html += `<div class="brief-greeting">${esc(d.greeting)}</div>`;
    if (d.items && d.items.length) {
      d.items.forEach((item, i) => {
        const p = item.priority || 'green';
        const dl = item.deeplink ? ` onclick="location.href='${item.deeplink}'" style="cursor:pointer"` : '';
        html += `<div class="brief-card ${p}" id="bc${i}"${dl}>
          <div class="brief-text">${esc(item.text)}</div>
          <button class="brief-close" onclick="event.stopPropagation();closeBrief('bc${i}')">✕</button>
        </div>`;
      });
    }
    if (!html) html = `<div class="brief-greeting">Всичко е наред! 👍</div>`;
    area.innerHTML = html;
  } catch(e) {
    area.innerHTML = '';
  }
}

function closeBrief(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.transition = 'all .3s'; el.style.opacity = '0'; el.style.transform = 'scale(0.95)'; el.style.maxHeight = el.offsetHeight + 'px';
  setTimeout(() => { el.style.maxHeight = '0'; el.style.marginBottom = '0'; el.style.padding = '0'; }, 50);
  setTimeout(() => el.remove(), 350);
}

async function toggleVoice() {
  if (isRecording) { stopVoice(); return; }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { showToast('Браузърът не поддържа гласово въвеждане'); return; }
  isRecording = true;
  voiceWrap.classList.add('recording');
  recOverlay.classList.add('show');
  voiceRec = new SR();
  voiceRec.lang = 'bg-BG';
  voiceRec.interimResults = false;
  voiceRec.maxAlternatives = 1;
  voiceRec.continuous = false;
  voiceRec.onresult = (e) => {
    const text = e.results[0][0].transcript;
    stopVoice();
    chatInput.value = text; btnSend.disabled = false;
    sendMessage();
  };
  voiceRec.onerror = (e) => {
    stopVoice();
    if (e.error === 'no-speech') showToast('Не чух — опитай пак');
    else if (e.error === 'not-allowed') showToast('Разреши микрофона в настройките');
    else showToast('Грешка: ' + e.error);
  };
  voiceRec.onend = () => { if (isRecording) stopVoice(); };
  try { voiceRec.start(); } catch(e) { stopVoice(); showToast('Грешка при стартиране'); }
}

function stopVoice() {
  isRecording = false;
  voiceWrap.classList.remove('recording');
  recOverlay.classList.remove('show');
  if (voiceRec) { try { voiceRec.stop(); } catch(e){} voiceRec = null; }
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

loadBriefing();
</script>
</body>
</html><?php
