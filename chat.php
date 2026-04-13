<?php
/**
 * chat.php — RunMyStore.ai Dashboard + AI Chat v7.0
 * S56 — FULL REWRITE per CHAT_PHP_SPEC_v7.md
 *
 * Затворен: Revenue карта + AI ТОЧНОСТ + AI Брифинг bubble + input бар
 * Отворен:  72% overlay, чист чат (WhatsApp стил), blur фон
 *
 * Закон №2: PHP смята, Gemini говори. Pills/Signals = чист PHP+SQL.
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

// Store switch via GET
if (!empty($_GET['store'])) {
    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
        [(int)$_GET['store'], $tenant_id])->fetch();
    if ($chk) { $_SESSION['store_id'] = (int)$_GET['store']; }
    header('Location: chat.php'); exit;
}

// Fallback: pick first store
if (!$store_id) {
    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
    if ($first) { $store_id = (int)$first['id']; $_SESSION['store_id'] = $store_id; }
}

// ══════════════════════════════════════════════
// TENANT, PLAN, STORE
// ══════════════════════════════════════════════
$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
$cs = htmlspecialchars($tenant['currency'] ?? '€');
$plan = effectivePlan($tenant);

$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';
$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);

// S56: silent geolocation from IP
autoGeolocateStore($store_id);

// Plan badge colors
$plan_colors = match($plan) {
    'pro'   => ['bg' => 'rgba(192,132,252,.15)', 'br' => 'rgba(192,132,252,.3)', 'tx' => '#c084fc'],
    'start' => ['bg' => 'rgba(99,102,241,.15)',  'br' => 'rgba(99,102,241,.3)',  'tx' => '#818cf8'],
    default => ['bg' => 'rgba(107,114,128,.15)', 'br' => 'rgba(107,114,128,.3)', 'tx' => '#9ca3af'],
};
$plan_label = strtoupper($plan);

// ══════════════════════════════════════════════
// REVENUE — ALL PERIODS
// ══════════════════════════════════════════════
function periodData($tid, $sid, $r, $from, $to = null) {
    $to = $to ?? date('Y-m-d');
    $rev = (float)DB::run(
        'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
        [$tid, $sid, $from, $to])->fetchColumn();
    $cnt = (int)DB::run(
        'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
        [$tid, $sid, $from, $to])->fetchColumn();
    $profit = 0;
    if ($r === 'owner' && $cnt > 0) {
        $profit = (float)DB::run(
            'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)>=? AND DATE(s.created_at)<=? AND s.status!="canceled"',
            [$tid, $sid, $from, $to])->fetchColumn();
    }
    return ['rev' => $rev, 'profit' => $profit, 'cnt' => $cnt];
}
function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }
function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }

$today = date('Y-m-d');
$d0  = periodData($tenant_id, $store_id, $role, $today, $today);
$d0p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')));
$d7  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-6 days')), $today);
$d7p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-7 days')));
$d30  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-29 days')), $today);
$d30p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-59 days')), date('Y-m-d', strtotime('-30 days')));
$d365  = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-364 days')), $today);
$d365p = periodData($tenant_id, $store_id, $role, date('Y-m-d', strtotime('-729 days')), date('Y-m-d', strtotime('-365 days')));

$periods_json = json_encode([
    'today' => [
        'rev' => round($d0['rev']), 'profit' => round($d0['profit']), 'cnt' => $d0['cnt'], 'margin' => mgn($d0),
        'cmp_rev' => cmpPct($d0['rev'], $d0p['rev']), 'cmp_prof' => cmpPct($d0['profit'], $d0p['profit']),
        'label' => 'Спрямо вчера',
        'sub_rev' => fmtMoney(round($d0p['rev']), '') . ' → ' . fmtMoney(round($d0['rev']), ''),
        'sub_prof' => fmtMoney(round($d0p['profit']), '') . ' → ' . fmtMoney(round($d0['profit']), ''),
    ],
    '7d' => [
        'rev' => round($d7['rev']), 'profit' => round($d7['profit']), 'cnt' => $d7['cnt'], 'margin' => mgn($d7),
        'cmp_rev' => cmpPct($d7['rev'], $d7p['rev']), 'cmp_prof' => cmpPct($d7['profit'], $d7p['profit']),
        'label' => 'Спрямо предишните 7 дни',
        'sub_rev' => fmtMoney(round($d7p['rev']), '') . ' → ' . fmtMoney(round($d7['rev']), ''),
        'sub_prof' => fmtMoney(round($d7p['profit']), '') . ' → ' . fmtMoney(round($d7['profit']), ''),
    ],
    '30d' => [
        'rev' => round($d30['rev']), 'profit' => round($d30['profit']), 'cnt' => $d30['cnt'], 'margin' => mgn($d30),
        'cmp_rev' => cmpPct($d30['rev'], $d30p['rev']), 'cmp_prof' => cmpPct($d30['profit'], $d30p['profit']),
        'label' => 'Спрямо предишните 30 дни',
        'sub_rev' => fmtMoney(round($d30p['rev']), '') . ' → ' . fmtMoney(round($d30['rev']), ''),
        'sub_prof' => fmtMoney(round($d30p['profit']), '') . ' → ' . fmtMoney(round($d30['profit']), ''),
    ],
    '365d' => [
        'rev' => round($d365['rev']), 'profit' => round($d365['profit']), 'cnt' => $d365['cnt'], 'margin' => mgn($d365),
        'cmp_rev' => cmpPct($d365['rev'], $d365p['rev']), 'cmp_prof' => cmpPct($d365['profit'], $d365p['profit']),
        'label' => 'Спрямо предишните 365 дни',
        'sub_rev' => fmtMoney(round($d365p['rev']), '') . ' → ' . fmtMoney(round($d365['rev']), ''),
        'sub_prof' => fmtMoney(round($d365p['profit']), '') . ' → ' . fmtMoney(round($d365['profit']), ''),
    ],
], JSON_UNESCAPED_UNICODE);

// ══════════════════════════════════════════════
// CONFIDENCE (for revenue card warning)
// ══════════════════════════════════════════════
$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
$with_cost = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
$confidence_pct = $total_products > 0 ? round($with_cost / $total_products * 100) : 0;

// ══════════════════════════════════════════════
// STORE HEALTH (AI ТОЧНОСТ bar)
// ══════════════════════════════════════════════
$health = 0;
try { $health = storeHealth($tenant_id, $store_id); } catch (Exception $e) {}

// ══════════════════════════════════════════════
// AI INSIGHTS (Briefing bubble)
// ══════════════════════════════════════════════
$insights = [];
$ghost_pills = [];
try {
    if (planAtLeast($plan, 'pro')) {
        $insights = getInsightsForModule($tenant_id, $store_id, $user_id, 'home', $plan, $role);
    } else {
        $ghost_pills = getGhostPills($tenant_id, $store_id, $user_id, $plan);
    }
} catch (Exception $e) {}

$briefing = array_slice($insights, 0, 3);
$remaining = max(0, count($insights) - 3);

// Generate action buttons from insights
function insightBtns(array $insight): array {
    $tid = $insight['topic_id'] ?? '';
    $btns = [];
    if (str_contains($tid, 'zero_stock')) $btns[] = ['t' => 'Покажи на нула', 'q' => 'Кои артикули са на нула?'];
    if (str_contains($tid, 'below_cost')) $btns[] = ['t' => 'Коригирай цена', 'q' => 'Кои артикули се продават под себестойност?'];
    if (str_contains($tid, 'zombie'))     $btns[] = ['t' => 'Покажи zombie', 'q' => 'Покажи zombie стоката'];
    if (str_contains($tid, 'no_photo'))   $btns[] = ['t' => 'Без снимка', 'q' => 'Кои артикули нямат снимка?'];
    if (str_contains($tid, 'top_profit')) $btns[] = ['t' => 'Топ печалба', 'q' => 'Най-печелившите артикули?'];
    if (str_contains($tid, 'low_stock'))  $btns[] = ['t' => 'Ниски наличности', 'q' => 'Кои артикули са под минимума?'];
    if (empty($btns)) $btns[] = ['t' => 'Разкажи повече', 'q' => $insight['title'] ?? 'Разкажи повече'];
    return $btns;
}

// Collect unique action buttons (max 3)
$action_btns = [];
foreach ($briefing as $ins) {
    foreach (insightBtns($ins) as $b) {
        $key = $b['q'];
        if (!isset($action_btns[$key]) && count($action_btns) < 3) {
            $action_btns[$key] = $b;
        }
    }
}

// Urgency colors
function urgencyClass(string $u): string {
    return match($u) { 'critical' => 'sig-critical', 'warning' => 'sig-warning', default => 'sig-info' };
}

// ══════════════════════════════════════════════
// GREETING
// ══════════════════════════════════════════════
$hour = (int)date('H');
$greeting = match(true) {
    $hour >= 5 && $hour < 12 => 'Добро утро',
    $hour >= 12 && $hour < 18 => 'Добър ден',
    default => 'Добър вечер'
};
if ($user_name) $greeting .= ', ' . htmlspecialchars($user_name);
$greeting .= '!';

// ══════════════════════════════════════════════
// CHAT HISTORY
// ══════════════════════════════════════════════
$chat_messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id, $store_id])->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#030712">
<title>RunMyStore.ai</title>
<style>
:root{--bg:#030712;--nav:52px}
*,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg)}
body{background:var(--bg);color:#f1f5f9;font-family:'Montserrat',Inter,system-ui,sans-serif;
    height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:600px;height:350px;background:radial-gradient(ellipse,rgba(99,102,241,.07)0%,transparent 70%);
    pointer-events:none;z-index:0}

/* ── HEADER ── */
.header{position:sticky;top:0;z-index:50;padding:10px 14px 8px;
    background:rgba(3,7,18,.96);border-bottom:1px solid rgba(99,102,241,.07)}
.header-top{display:flex;align-items:center;justify-content:space-between}
.header-brand{font-size:10px;font-weight:700;color:rgba(165,180,252,.5);letter-spacing:.6px}
.header-right{display:flex;align-items:center;gap:6px}
.plan-badge{padding:3px 8px;border-radius:10px;font-size:7px;font-weight:700;letter-spacing:.3px;
    background:<?= $plan_colors['bg'] ?>;border:1px solid <?= $plan_colors['br'] ?>;color:<?= $plan_colors['tx'] ?>}
.simple-toggle{font-size:7px;color:rgba(165,180,252,.35);border:1px solid rgba(99,102,241,.08);
    border-radius:10px;padding:2px 7px;cursor:pointer;text-decoration:none}
.header-icon{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.03);
    display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative}
.header-icon svg{width:12px;height:12px;stroke:rgba(165,180,252,.35);fill:none;stroke-width:1.8}
.logout-dropdown{position:absolute;top:28px;right:0;background:#0f0f2a;
    border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:8px 14px;white-space:nowrap;
    z-index:60;box-shadow:0 8px 24px rgba(0,0,0,.5);font-size:11px;color:#fca5a5;
    font-weight:600;cursor:pointer;display:none;text-decoration:none}
.logout-dropdown.show{display:block}

/* ── MAIN SCROLL ── */
.main-scroll{flex:1;overflow-y:auto;overflow-x:hidden;position:relative;z-index:1;
    -webkit-overflow-scrolling:touch;scrollbar-width:none;transition:opacity .3s}
.main-scroll::-webkit-scrollbar{display:none}

/* ── REVENUE CARD ── */
.revenue-card{background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.04);
    border-radius:14px;padding:12px 14px 10px;margin:10px 12px 0}
.revenue-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2px}
.revenue-period-label{font-size:7px;font-weight:700;color:rgba(255,255,255,.2);
    text-transform:uppercase;letter-spacing:.5px}
.revenue-store-name{font-size:8px;color:rgba(165,180,252,.3)}
.revenue-store-name select{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);
    border-radius:8px;color:#a5b4fc;font-size:8px;font-weight:600;padding:2px 6px;
    font-family:inherit;cursor:pointer;outline:none}
.revenue-number-row{display:flex;align-items:baseline;gap:6px}
.revenue-number{font-size:28px;font-weight:800;color:#f1f5f9;letter-spacing:-1px}
.revenue-currency{font-size:11px;color:#4b5563;font-weight:600}
.revenue-change{font-size:16px;font-weight:800}
.revenue-change.up{color:#4ade80}
.revenue-change.down{color:#f87171}
.revenue-change.zero{color:#4b5563}
.revenue-comparison{font-size:8px;color:#4b5563;margin:1px 0 0}
.revenue-meta{font-size:9px;color:#4b5563;margin-top:3px}
.revenue-pills{display:flex;gap:0;margin-top:9px;align-items:center}
.revenue-pill-group{display:flex;gap:3px}
.revenue-pill{font-size:8px;padding:4px 9px;border-radius:10px;cursor:pointer;white-space:nowrap;
    border:1px solid transparent;transition:all .2s}
.revenue-pill.active{color:#e2e8f0;background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.18)}
.revenue-pill.inactive{color:#4b5563}
.revenue-divider{width:1px;height:16px;background:rgba(255,255,255,.06);margin:0 6px}
.confidence-warning{display:flex;align-items:center;gap:6px;margin:6px 0 0;padding:5px 8px;
    border-radius:8px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.15);
    font-size:9px;color:#fcd34d}
.confidence-warning svg{width:12px;height:12px;stroke:#fbbf24;fill:none;stroke-width:2.5;flex-shrink:0}

/* ── STORE HEALTH BAR ── */
.health-bar{margin:8px 12px 4px;display:flex;align-items:center;gap:8px}
.health-label{font-size:7px;font-weight:700;color:rgba(255,255,255,.18);letter-spacing:.5px;
    text-transform:uppercase;white-space:nowrap}
.health-track{flex:1;height:5px;border-radius:3px;background:rgba(255,255,255,.04);overflow:hidden}
.health-fill{height:100%;border-radius:3px;
    background:linear-gradient(90deg,#ef4444 0%,#f97316 25%,#eab308 50%,#84cc16 75%,#22c55e 100%)}
.health-percent{font-size:9px;font-weight:700}
.health-link{font-size:7px;color:#818cf8;white-space:nowrap;cursor:pointer}

/* ── SEPARATOR ── */
.separator{height:1px;margin:6px 12px;background:rgba(255,255,255,.04)}

/* ── AI META ── */
.ai-meta{display:flex;align-items:center;gap:4px;margin:10px 12px 5px}
.ai-meta svg{width:13px;height:13px;fill:none;stroke:#818cf8;stroke-width:1.5}
.ai-meta-label{font-size:10px;color:#818cf8;font-weight:600}
.ai-meta-time{font-size:9px;color:#4b5563}

/* ── AI BUBBLE ── */
.ai-bubble{max-width:92%;margin:0 12px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:14px 14px 14px 3px;padding:10px 12px}
.ai-bubble-text{font-size:11px;color:#d1d5db;line-height:1.4}
.ai-bubble-text.with-signals{margin-bottom:7px}

/* ── SIGNAL CARDS (inside bubble) ── */
.signal-card{padding:7px 10px;margin:5px 0 3px;border-left:3px solid;border-radius:8px}
.sig-critical{border-color:#ef4444;background:rgba(239,68,68,.04)}
.sig-warning{border-color:#fbbf24;background:rgba(251,191,36,.03)}
.sig-info{border-color:#4ade80;background:rgba(34,197,94,.03)}
.signal-title{font-size:11px;font-weight:600;line-height:1.3}
.sig-critical .signal-title{color:#fca5a5}
.sig-warning .signal-title{color:#fcd34d}
.sig-info .signal-title{color:#86efac}
.signal-body{font-size:10px;color:#6b7280;line-height:1.3;margin-top:1px}

/* ── ACTION BUTTONS ── */
.action-buttons{display:flex;gap:5px;margin-top:8px;flex-wrap:wrap}
.action-button{padding:5px 11px;border-radius:8px;font-size:9px;font-weight:600;
    color:#a5b4fc;border:1px solid rgba(99,102,241,.15);background:transparent;
    cursor:pointer;font-family:inherit;transition:background .15s}
.action-button:active{background:rgba(99,102,241,.08)}
.action-button-more{color:#4b5563;border-color:rgba(255,255,255,.06)}

/* ── GHOST PILL ── */
.ghost-pill{padding:5px 12px;border-radius:8px;font-size:10px;font-weight:600;
    color:rgba(168,85,247,.4);border:1px dashed rgba(168,85,247,.2);background:transparent;
    cursor:pointer;font-family:inherit;margin-top:6px}

/* ── USER BUBBLE (dashboard history) ── */
.user-row{display:flex;justify-content:flex-end;margin:10px 12px 4px;flex-direction:column;align-items:flex-end}
.user-time{font-size:8px;color:#4b5563;margin-bottom:3px}
.user-bubble{background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.1);
    border-radius:14px 14px 3px 14px;padding:8px 12px;font-size:11px;color:#e2e8f0;
    max-width:75%;line-height:1.4;word-break:break-word}

/* ── AI RESPONSE BUBBLE (dashboard history) ── */
.ai-response{max-width:88%;margin:0 12px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:14px 14px 14px 3px;
    padding:8px 12px;font-size:11px;color:#d1d5db;line-height:1.5;word-break:break-word;
    white-space:pre-wrap}

/* ── INPUT BAR (dashboard bottom) ── */
.input-bar{padding:8px 12px 6px;position:relative;z-index:5;cursor:pointer}
.input-bar-inner{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:16px;padding:7px 8px 7px 10px;
    transition:border-color .2s}
.input-bar-inner:active{border-color:rgba(99,102,241,.2)}
.input-waves{display:flex;align-items:flex-end;gap:2px;height:14px;flex-shrink:0}
.input-wave-bar{width:2px;border-radius:1px}
.input-placeholder{flex:1;font-size:11px;color:#374151}
.mic-button{width:32px;height:32px;border-radius:50%;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mic-button svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2}
.send-button{width:30px;height:30px;border-radius:50%;
    background:linear-gradient(135deg,#059669,#10b981);
    display:flex;align-items:center;justify-content:center;flex-shrink:0}
.send-button svg{width:13px;height:13px;fill:#fff}

/* ── BOTTOM NAV ── */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:var(--nav);
    background:rgba(3,7,18,.97);border-top:1px solid rgba(255,255,255,.04);
    display:flex;z-index:100}
.bottom-nav-tab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:2px;font-size:8px;font-weight:600;text-decoration:none;transition:all .2s}
.bottom-nav-tab svg{width:18px;height:18px;stroke-width:1.5;fill:none}
.bottom-nav-tab.active{color:#a5b4fc}
.bottom-nav-tab.active svg{stroke:#a5b4fc}
.bottom-nav-tab.inactive{color:rgba(255,255,255,.15)}
.bottom-nav-tab.inactive svg{stroke:rgba(255,255,255,.15)}

/* ── CHAT OVERLAY ── */
.chat-overlay-bg{position:fixed;inset:0;background:rgba(3,7,18,.65);backdrop-filter:blur(8px);
    z-index:200;opacity:0;pointer-events:none;transition:opacity .25s}
.chat-overlay-bg.open{opacity:1;pointer-events:auto}
.chat-overlay-panel{position:fixed;bottom:-100%;left:0;right:0;height:72vh;
    background:rgba(8,10,24,.98);border-radius:20px 20px 0 0;z-index:210;
    display:flex;flex-direction:column;
    box-shadow:0 -8px 40px rgba(99,102,241,.12);transition:bottom .3s cubic-bezier(.32,0,.67,0)}
.chat-overlay-panel.open{bottom:0}
.chat-overlay-panel::before{content:'';position:absolute;inset:0;border-radius:20px 20px 0 0;
    background:radial-gradient(ellipse at 20% 10%,rgba(99,102,241,.04)0%,transparent 55%),
               radial-gradient(ellipse at 80% 90%,rgba(139,92,246,.03)0%,transparent 50%);
    pointer-events:none}

/* ── OVERLAY HEADER ── */
.overlay-header{display:flex;align-items:center;justify-content:space-between;
    padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.05);position:relative;z-index:1}
.overlay-header-left{display:flex;align-items:center;gap:8px}
.overlay-avatar{width:30px;height:30px;border-radius:50%;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 12px rgba(99,102,241,.3)}
.overlay-avatar svg{width:14px;height:14px;fill:#fff}
.overlay-title{font-size:13px;font-weight:600;color:#e2e8f0}
.overlay-close{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.04);
    display:flex;align-items:center;justify-content:center;cursor:pointer;border:none}
.overlay-close svg{width:14px;height:14px;stroke:rgba(255,255,255,.35);fill:none;stroke-width:2}

/* ── OVERLAY CHAT MESSAGES ── */
.overlay-messages{flex:1;overflow-y:auto;padding:10px 12px;position:relative;z-index:1;
    display:flex;flex-direction:column;gap:8px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.overlay-messages::-webkit-scrollbar{display:none}
.chat-message-group{display:flex;flex-direction:column;gap:4px}
.chat-meta-line{font-size:9px;color:#4b5563;display:flex;align-items:center;gap:4px}
.chat-meta-line.right{justify-content:flex-end}
.chat-ai-msg{max-width:85%;padding:8px 12px;font-size:12px;line-height:1.5;word-break:break-word;
    background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);color:#e2e8f0;
    border-radius:4px 14px 14px 14px;white-space:pre-wrap}
.chat-user-msg{max-width:75%;padding:8px 12px;font-size:12px;line-height:1.5;word-break:break-word;
    background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.15);color:#e2e8f0;
    border-radius:14px 14px 4px 14px;margin-left:auto}
.chat-typing-indicator{display:none;padding:8px 12px;background:rgba(15,20,40,.8);
    border:.5px solid rgba(99,102,241,.15);border-radius:4px 14px 14px 14px;width:fit-content}
.typing-dots{display:flex;gap:4px;align-items:center}
.typing-dot{width:5px;height:5px;border-radius:50%;background:#818cf8;animation:dotbounce 1.2s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}

/* ── OVERLAY REC BAR ── */
.recording-bar{display:none;align-items:center;gap:8px;padding:6px 12px;
    background:rgba(239,68,68,.06);border-top:1px solid rgba(239,68,68,.15);position:relative;z-index:1}
.recording-bar.on{display:flex}
.recording-dot{width:8px;height:8px;border-radius:50%;background:#ef4444;
    animation:recpulse 1s infinite;box-shadow:0 0 8px rgba(239,68,68,.6)}
.recording-label{font-size:9px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:.5px}
.recording-transcript{font-size:10px;color:#e2e8f0;flex:1}

/* ── OVERLAY INPUT ── */
.overlay-input{padding:6px 10px 8px;position:relative;z-index:1}
.overlay-input-inner{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.05);border-radius:16px;padding:6px 8px 6px 12px}
.overlay-textarea{flex:1;background:transparent;border:none;color:#f1f5f9;font-size:12px;
    padding:6px 0;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4}
.overlay-textarea::placeholder{color:#374151}
.overlay-mic{width:34px;height:34px;border-radius:50%;flex-shrink:0;position:relative;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    box-shadow:0 0 12px rgba(99,102,241,.3);transition:all .2s}
.overlay-mic.recording{background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 0 18px rgba(239,68,68,.5)}
.overlay-mic svg{width:16px;height:16px;color:#fff;z-index:1;stroke:#fff;fill:none;stroke-width:2}
.voice-ring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.3);opacity:0}
.overlay-mic.recording .voice-ring{border-color:rgba(255,255,255,.5)}
.vr1{width:20px;height:20px;animation:vrpulse 2s 0s ease-in-out infinite}
.vr2{width:32px;height:32px;animation:vrpulse 2s .3s ease-in-out infinite}
.vr3{width:44px;height:44px;animation:vrpulse 2s .6s ease-in-out infinite}
.overlay-send{width:34px;height:34px;border-radius:50%;
    background:linear-gradient(135deg,#059669,#10b981);
    border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;transition:opacity .2s}
.overlay-send:disabled{opacity:.2}
.overlay-send svg{width:14px;height:14px;fill:#fff}

/* ── TOAST ── */
.toast{position:fixed;bottom:60px;left:50%;transform:translateX(-50%);
    background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
    padding:7px 16px;border-radius:20px;font-size:11px;font-weight:600;z-index:500;
    opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

/* ── KEYFRAMES ── */
@keyframes dotbounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
@keyframes recpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}
@keyframes vrpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
@keyframes cardin{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════ -->
<!-- HEADER                                        -->
<!-- ══════════════════════════════════════════════ -->
<div class="header">
  <div class="header-top">
    <span class="header-brand">RUNMYSTORE.AI</span>
    <div class="header-right">
      <span class="plan-badge"><?= htmlspecialchars($plan_label) ?></span>
      <a href="simple.php" class="simple-toggle">&larr; Опростен</a>
      <div class="header-icon" onclick="location.href='settings.php'">
        <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3c.4-1.8 2.9-1.8 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.6c1.8.4 1.8 2.9 0 3.3a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-2.9 1.8-3.3 0a1.7 1.7 0 00-2.6-1c-1.5.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1-2.6c-1.8-.4-1.8-2.9 0-3.3a1.7 1.7 0 001-2.6c-.9-1.5.9-3.3 2.4-2.4 1 .6 2.3.1 2.6-1.1z"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <div class="header-icon" id="logoutWrap" onclick="toggleLogout()" style="position:relative">
        <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <a href="logout.php" class="logout-dropdown" id="logoutDrop">Изход &rarr;</a>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- MAIN SCROLL AREA                              -->
<!-- ══════════════════════════════════════════════ -->
<div class="main-scroll" id="mainScroll">

  <!-- REVENUE CARD -->
  <div class="revenue-card" style="animation:cardin .5s ease both">
    <div class="revenue-top">
      <span class="revenue-period-label" id="revLabel">ДНЕС</span>
      <?php if (count($all_stores) > 1): ?>
      <span class="revenue-store-name">
        <select onchange="location.href='?store='+this.value">
          <?php foreach ($all_stores as $st): ?>
          <option value="<?= $st['id'] ?>" <?= $st['id']==$store_id?'selected':'' ?>><?= htmlspecialchars($st['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </span>
      <?php else: ?>
      <span class="revenue-store-name"><?= htmlspecialchars($store_name) ?></span>
      <?php endif; ?>
    </div>
    <div class="revenue-number-row">
      <span class="revenue-number" id="revNum">0</span>
      <span class="revenue-currency"><?= $cs ?></span>
      <span class="revenue-change up" id="revPct">+0%</span>
    </div>
    <div class="revenue-comparison" id="revCmp"></div>
    <div class="revenue-meta" id="revMeta">0 продажби</div>
    <?php if ($role === 'owner' && $confidence_pct < 100): ?>
    <div class="confidence-warning" id="confWarn" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
      Данни за <?= $confidence_pct ?>% от артикулите (<?= $with_cost ?>/<?= $total_products ?>)
    </div>
    <?php endif; ?>
    <div class="revenue-pills">
      <div class="revenue-pill-group">
        <span class="revenue-pill active" onclick="setPeriod('today',this)">Днес</span>
        <span class="revenue-pill inactive" onclick="setPeriod('7d',this)">7 дни</span>
        <span class="revenue-pill inactive" onclick="setPeriod('30d',this)">30 дни</span>
        <span class="revenue-pill inactive" onclick="setPeriod('365d',this)">365 дни</span>
      </div>
      <?php if ($role === 'owner'): ?>
      <div class="revenue-divider"></div>
      <div class="revenue-pill-group">
        <span class="revenue-pill active" id="modeRev" onclick="setMode('rev')">Оборот</span>
        <span class="revenue-pill inactive" id="modeProfit" onclick="setMode('profit')">Печалба</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- STORE HEALTH BAR -->
  <div class="health-bar">
    <span class="health-label">Точност</span>
    <div class="health-track"><div class="health-fill" style="width:<?= $health ?>%"></div></div>
    <span class="health-percent" style="color:<?= $health >= 80 ? '#4ade80' : ($health >= 50 ? '#fbbf24' : '#f87171') ?>"><?= $health ?>%</span>
    <span class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй &rarr;</span>
  </div>

  <div class="separator"></div>

  <!-- AI BRIEFING BUBBLE -->
  <div class="ai-meta">
    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    <span class="ai-meta-label">AI</span>
    <span class="ai-meta-time">&middot; <?= date('H:i') ?></span>
  </div>

  <?php if (!empty($briefing)): ?>
  <!-- PRO: Real insights -->
  <div class="ai-bubble" style="animation:cardin .4s .1s ease both">
    <div class="ai-bubble-text with-signals"><?= htmlspecialchars($greeting) ?> Ето какво е важно:</div>
    <?php foreach ($briefing as $ins): ?>
    <div class="signal-card <?= urgencyClass($ins['urgency']) ?>">
      <div class="signal-title"><?= htmlspecialchars($ins['title']) ?></div>
      <?php if (!empty($ins['body'])): ?>
      <div class="signal-body"><?= htmlspecialchars($ins['body']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!empty($action_btns) || $remaining > 0): ?>
    <div class="action-buttons">
      <?php foreach ($action_btns as $ab): ?>
      <button class="action-button" onclick="openChatQ(<?= htmlspecialchars(json_encode($ab['q']), ENT_QUOTES) ?>)"><?= htmlspecialchars($ab['t']) ?></button>
      <?php endforeach; ?>
      <?php if ($remaining > 0): ?>
      <button class="action-button action-button-more" onclick="openChat()">Още <?= $remaining ?> сигнала</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif (!empty($ghost_pills)): ?>
  <!-- START/FREE: Ghost pill teaser -->
  <div class="ai-bubble" style="animation:cardin .4s .1s ease both">
    <div class="ai-bubble-text with-signals"><?= htmlspecialchars($greeting) ?> AI има съвет за теб...</div>
    <button class="ghost-pill" onclick="showToast('Включи PRO за AI съвети')">PRO</button>
  </div>

  <?php else: ?>
  <!-- No insights / 1/4 silence -->
  <div class="ai-bubble" style="animation:cardin .4s .1s ease both">
    <div class="ai-bubble-text"><?= htmlspecialchars($greeting) ?> Попитай каквото искаш — говори или пиши.</div>
  </div>
  <?php endif; ?>

  <!-- CHAT HISTORY (on dashboard) -->
  <?php foreach ($chat_messages as $m): ?>
    <?php if ($m['role'] === 'user'): ?>
    <div class="user-row">
      <span class="user-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
      <div class="user-bubble"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
    </div>
    <?php else: ?>
    <div class="ai-meta">
      <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      <span class="ai-meta-label">AI</span>
      <span class="ai-meta-time">&middot; <?= date('H:i', strtotime($m['created_at'])) ?></span>
    </div>
    <div class="ai-response"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
    <?php endif; ?>
  <?php endforeach; ?>

  <div style="height:14px"></div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- INPUT BAR (tap → opens overlay)               -->
<!-- ══════════════════════════════════════════════ -->
<div class="input-bar" id="dashboardInput" onclick="openChat()">
  <div class="input-bar-inner">
    <div class="input-waves">
      <div class="input-wave-bar" style="height:5px;background:#4f46e5"></div>
      <div class="input-wave-bar" style="height:9px;background:#6366f1"></div>
      <div class="input-wave-bar" style="height:14px;background:#818cf8"></div>
      <div class="input-wave-bar" style="height:9px;background:#6366f1"></div>
      <div class="input-wave-bar" style="height:5px;background:#4f46e5"></div>
    </div>
    <span class="input-placeholder">Кажи или напиши...</span>
    <div class="mic-button"><svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg></div>
    <div class="send-button"><svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- CHAT OVERLAY (72%, blur bg, clean chat)       -->
<!-- ══════════════════════════════════════════════ -->
<div class="chat-overlay-bg" id="chatOverlayBg" onclick="closeChat()"></div>
<div class="chat-overlay-panel" id="chatOverlayPanel">
  <div class="overlay-header">
    <div class="overlay-header-left">
      <div class="overlay-avatar">
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <span class="overlay-title">AI Асистент</span>
    </div>
    <button class="overlay-close" onclick="closeChat()">
      <svg viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <div class="overlay-messages" id="chatMessages">
    <?php if (empty($chat_messages)): ?>
    <div style="text-align:center;padding:30px 10px;color:#4b5563;font-size:12px">
      <div style="font-size:14px;font-weight:700;margin-bottom:6px;color:#a5b4fc">
        Здравей<?= $user_name ? ', '.htmlspecialchars($user_name) : '' ?>!
      </div>
      Попитай каквото искаш — говори или пиши.
    </div>
    <?php else: ?>
    <?php foreach ($chat_messages as $m): ?>
    <div class="chat-message-group">
      <?php if ($m['role'] === 'assistant'): ?>
      <div class="chat-meta-line">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        AI &middot; <?= date('H:i', strtotime($m['created_at'])) ?>
      </div>
      <div class="chat-ai-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
      <?php else: ?>
      <div class="chat-meta-line right"><?= date('H:i', strtotime($m['created_at'])) ?></div>
      <div class="chat-user-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <div class="chat-typing-indicator" id="chatTyping">
      <div class="typing-dots">
        <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
      </div>
    </div>
  </div>

  <div class="recording-bar" id="recBar">
    <div class="recording-dot"></div>
    <span class="recording-label">ЗАПИСВА</span>
    <span class="recording-transcript" id="recTranscript">Слушам...</span>
  </div>

  <div class="overlay-input">
    <div class="overlay-input-inner">
      <textarea class="overlay-textarea" id="chatInput" placeholder="Кажи или пиши..." rows="1"
        oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatSendBtn').disabled=!this.value.trim()"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
      <div class="overlay-mic" id="voiceBtn" onclick="toggleVoice()">
        <div class="voice-ring vr1"></div><div class="voice-ring vr2"></div><div class="voice-ring vr3"></div>
        <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
      </div>
      <button class="overlay-send" id="chatSendBtn" onclick="sendMsg()" disabled>
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- BOTTOM NAV                                    -->
<!-- ══════════════════════════════════════════════ -->
<nav class="bottom-nav">
  <a href="chat.php" class="bottom-nav-tab active">
    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI
  </a>
  <a href="warehouse.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>Склад
  </a>
  <a href="stats.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round">
      <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
      <line x1="6" y1="20" x2="6" y2="14"/></svg>Справки
  </a>
  <a href="sale.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Продажба
  </a>
</nav>

<div class="toast" id="toast"></div>

<!-- ══════════════════════════════════════════════ -->
<!-- JAVASCRIPT                                    -->
<!-- ══════════════════════════════════════════════ -->
<script>
const P = <?= $periods_json ?>;
const CS = <?= json_encode($cs) ?>;
const IS_OWNER = <?= $role === 'owner' ? 'true' : 'false' ?>;

function $(id) { return document.getElementById(id); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function showToast(m) { const t=$('toast'); t.textContent=m; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),3000); }

// ══════════════════════════════════════════════
// REVENUE — Period & Mode switching
// ══════════════════════════════════════════════
let curPeriod = 'today';
let curMode = 'rev';

function updateRevenue() {
    const d = P[curPeriod];
    const val = curMode === 'rev' ? d.rev : d.profit;
    const pct = curMode === 'rev' ? d.cmp_rev : d.cmp_prof;
    const sub = curMode === 'rev' ? d.sub_rev : d.sub_prof;

    $('revNum').textContent = fmt(val);
    $('revPct').textContent = (pct >= 0 ? '+' : '') + pct + '%';
    $('revPct').className = 'revenue-change ' + (pct > 0 ? 'up' : pct < 0 ? 'down' : 'zero');
    $('revCmp').textContent = sub + ' ' + CS;

    const labels = { today: 'ДНЕС', '7d': '7 ДНИ', '30d': '30 ДНИ', '365d': '365 ДНИ' };
    $('revLabel').textContent = labels[curPeriod];

    let meta = d.cnt + ' продажби';
    if (IS_OWNER && d.cnt > 0) meta += ' \u00b7 ' + d.margin + '% марж';
    $('revMeta').textContent = meta;

    const cw = $('confWarn');
    if (cw) cw.style.display = curMode === 'profit' ? 'flex' : 'none';
}

function setPeriod(period, el) {
    curPeriod = period;
    document.querySelectorAll('.revenue-pill-group:first-child .revenue-pill').forEach(p => {
        p.className = 'revenue-pill ' + (p === el ? 'active' : 'inactive');
    });
    updateRevenue();
}

function setMode(mode) {
    curMode = mode;
    const mr = $('modeRev'), mp = $('modeProfit');
    if (mr) { mr.className = 'revenue-pill ' + (mode === 'rev' ? 'active' : 'inactive'); }
    if (mp) { mp.className = 'revenue-pill ' + (mode === 'profit' ? 'active' : 'inactive'); }
    updateRevenue();
}

// ══════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════
function toggleLogout() { $('logoutDrop').classList.toggle('show'); }
document.addEventListener('click', e => {
    if (!$('logoutWrap').contains(e.target)) $('logoutDrop').classList.remove('show');
});

// ══════════════════════════════════════════════
// CHAT OVERLAY
// ══════════════════════════════════════════════
let chatOpen = false;

function openChat() {
    if (chatOpen) return;
    chatOpen = true;
    $('chatOverlayBg').classList.add('open');
    $('chatOverlayPanel').classList.add('open');
    $('mainScroll').style.opacity = '.4';
    $('dashboardInput').style.opacity = '0';
    $('dashboardInput').style.pointerEvents = 'none';
    document.querySelector('.header').style.opacity = '.4';
    history.pushState({ chat: true }, '');
    scrollChatBottom();
    setTimeout(() => $('chatInput').focus(), 300);
}

function closeChat() {
    chatOpen = false;
    $('chatOverlayBg').classList.remove('open');
    $('chatOverlayPanel').classList.remove('open');
    $('mainScroll').style.opacity = '1';
    $('dashboardInput').style.opacity = '1';
    $('dashboardInput').style.pointerEvents = 'auto';
    document.querySelector('.header').style.opacity = '1';
    stopVoice();
}

function openChatQ(question) {
    openChat();
    setTimeout(() => {
        $('chatInput').value = question;
        $('chatSendBtn').disabled = false;
        sendMsg();
    }, 350);
}

function scrollChatBottom() {
    const el = $('chatMessages');
    el.scrollTop = el.scrollHeight;
}

// ══════════════════════════════════════════════
// SEND MESSAGE
// ══════════════════════════════════════════════
async function sendMsg() {
    const inp = $('chatInput');
    const txt = inp.value.trim();
    if (!txt) return;

    addUserBubble(txt);
    inp.value = '';
    inp.style.height = '';
    $('chatSendBtn').disabled = true;
    $('chatTyping').style.display = 'block';
    scrollChatBottom();

    try {
        const r = await fetch('chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: txt })
        });
        const raw = await r.text();
        let d;
        try { d = JSON.parse(raw); } catch(e) {
            $('chatTyping').style.display = 'none';
            addAIBubble('Грешка: ' + raw.substring(0, 200));
            return;
        }
        $('chatTyping').style.display = 'none';
        addAIBubble(d.reply || d.error || 'Грешка при обработка.');
    } catch (e) {
        $('chatTyping').style.display = 'none';
        addAIBubble('Грешка при свързване. Опитай пак.');
    }
}

function addUserBubble(txt) {
    const g = document.createElement('div');
    g.className = 'chat-message-group';
    const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    g.innerHTML = '<div class="chat-meta-line right">' + t + '</div><div class="chat-user-msg">' + esc(txt) + '</div>';
    $('chatMessages').insertBefore(g, $('chatTyping'));
    scrollChatBottom();
}

function addAIBubble(txt) {
    const g = document.createElement('div');
    g.className = 'chat-message-group';
    const t = new Date().toLocaleTimeString('bg-BG', { hour: '2-digit', minute: '2-digit' });
    g.innerHTML = '<div class="chat-meta-line"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI \u00b7 ' + t + '</div><div class="chat-ai-msg">' + esc(txt) + '</div>';
    $('chatMessages').insertBefore(g, $('chatTyping'));
    scrollChatBottom();
}

// ══════════════════════════════════════════════
// VOICE
// ══════════════════════════════════════════════
let voiceRec = null, isRecording = false, voiceText = '';

function toggleVoice() {
    if (isRecording) { stopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласов вход'); return; }
    isRecording = true;
    voiceText = '';
    $('voiceBtn').classList.add('recording');
    $('recBar').classList.add('on');
    $('recTranscript').innerText = 'Слушам...';
    voiceRec = new SR();
    voiceRec.lang = 'bg-BG';
    voiceRec.continuous = false;
    voiceRec.interimResults = true;
    voiceRec.onresult = e => {
        let fin = '', interim = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            if (e.results[i].isFinal) fin += e.results[i][0].transcript;
            else interim += e.results[i][0].transcript;
        }
        if (fin) voiceText = fin;
        $('recTranscript').innerText = voiceText || interim || 'Слушам...';
    };
    voiceRec.onend = () => {
        isRecording = false;
        $('voiceBtn').classList.remove('recording');
        $('recBar').classList.remove('on');
        if (voiceText) {
            $('chatInput').value = voiceText;
            $('chatSendBtn').disabled = false;
            sendMsg();
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

function stopVoice() {
    isRecording = false;
    voiceText = '';
    $('voiceBtn').classList.remove('recording');
    $('recBar').classList.remove('on');
    if (voiceRec) { try { voiceRec.stop(); } catch (e) {} voiceRec = null; }
}

// ══════════════════════════════════════════════
// BACK BUTTON + INIT
// ══════════════════════════════════════════════
window.addEventListener('popstate', () => {
    if (chatOpen) closeChat();
});

window.addEventListener('DOMContentLoaded', () => {
    updateRevenue();
    scrollChatBottom();
});
</script>
</body>
</html>
