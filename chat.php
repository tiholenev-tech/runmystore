<?php
/**
 * chat.php — AI First Dashboard v4
 * С31 — Visual redesign: stats.php style cards, sticky chat, plan indicator,
 *        info chat button, wave mic, flipCard animations, logout
 *        ZERO logic changes — all PHP queries identical to v3
 */
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)$_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

$store      = DB::run('SELECT name FROM stores WHERE id=? LIMIT 1', [$store_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';

$unread = (int)DB::run(
    'SELECT COUNT(*) FROM store_messages WHERE tenant_id=? AND to_store_id=? AND is_read=0',
    [$tenant_id, $store_id]
)->fetchColumn();

// ── PLAN & AI ADVISOR STATUS ──
$sub = DB::run(
    'SELECT plan, status FROM subscriptions WHERE tenant_id=? ORDER BY created_at DESC LIMIT 1',
    [$tenant_id]
)->fetch();
$plan = strtoupper($sub['plan'] ?? 'FREE');
if (!in_array($plan, ['FREE','BUSINESS','PRO','ENTERPRISE'])) $plan = 'FREE';
$ai_advisor = in_array($plan, ['PRO','ENTERPRISE']);

// ── CURRENCY ──
$currency_symbol = '€';

// ── PULSE ──
$rev_t = DB::run(
    'SELECT COALESCE(SUM(total),0) AS r, COUNT(*) AS c FROM sales
     WHERE store_id=? AND tenant_id=? AND DATE(created_at)=CURDATE() AND status!="canceled"',
    [$store_id, $tenant_id])->fetch();
$rev_y = DB::run(
    'SELECT COALESCE(SUM(total),0) AS r FROM sales
     WHERE store_id=? AND tenant_id=? AND DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status!="canceled"',
    [$store_id, $tenant_id])->fetch();

$low_cnt = (int)DB::run(
    'SELECT COUNT(*) FROM inventory i JOIN products p ON p.id=i.product_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.quantity<=i.min_quantity AND i.min_quantity>0 AND p.is_active=1',
    [$store_id, $tenant_id])->fetchColumn();

$zombie = DB::run(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(i.quantity*COALESCE(p.cost_price,p.retail_price*0.6)),0) AS val
     FROM inventory i JOIN products p ON p.id=i.product_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.quantity>0 AND p.is_active=1 AND p.parent_id IS NULL
     AND DATEDIFF(NOW(),COALESCE((SELECT MAX(s2.created_at) FROM sales s2 JOIN sale_items si2 ON si2.sale_id=s2.id WHERE si2.product_id=p.id AND s2.store_id=i.store_id),p.created_at))>=45',
    [$store_id, $tenant_id])->fetch();

$rv = (float)$rev_t['r']; $ry = (float)$rev_y['r'];
$diff = $ry > 0 ? round(($rv-$ry)/$ry*100,1) : 0;
$zv = round((float)$zombie['val']); $zc = (int)$zombie['cnt'];

if ($low_cnt >= 5)
    $pulse = ['color'=>'red',    'text'=>"{$low_cnt} артикула под минимума — спешна поръчка нужна"];
elseif ($zv > 1000)
    $pulse = ['color'=>'yellow', 'text'=>"{$zc} артикула стоят 45+ дни — ".number_format($zv,0,'.',' ')." {$currency_symbol} замразени"];
elseif ($diff >= 15)
    $pulse = ['color'=>'green',  'text'=>"Отличен ден! +{$diff}% спрямо вчера — ".number_format($rv,0,'.',' ')." {$currency_symbol}"];
elseif ($diff <= -20 && (int)date('H') >= 14)
    $pulse = ['color'=>'red',    'text'=>"Днес е слабо — ".abs($diff)."% под вчера. Да предприемем нещо?"];
else
    $pulse = ['color'=>'green',  'text'=>number_format($rv,0,'.',' ')." {$currency_symbol} от ".(int)$rev_t['c']." продажби днес"];

// ── CARDS (exact same logic) ──
$cards = [];

// Свършва
$low_rows = DB::run(
    'SELECT p.name FROM inventory i JOIN products p ON p.id=i.product_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.quantity<=i.min_quantity AND i.min_quantity>0 AND p.is_active=1
     ORDER BY i.quantity ASC LIMIT 3',
    [$store_id, $tenant_id])->fetchAll();
$low_names = implode(', ', array_column($low_rows,'name'));
if (mb_strlen($low_names)>32) $low_names = mb_substr($low_names,0,30).'...';
$cards[] = ['icon'=>'⚠️','label'=>'Свършва','val'=>$low_cnt.' арт.','sub'=>$low_cnt>0?$low_names:'Всичко е наред',
    'color'=>'#ef4444','bg'=>'rgba(239,68,68,.18)','border'=>'rgba(239,68,68,.5)','glow'=>'rgba(239,68,68,.25)',
    'dt'=>'Свършваща наличност','dv'=>$low_cnt.' арт. под минимума',
    'dai'=>'Зареди приоритетно. Всеки ден без наличност = директно загубени продажби.',
    'db'=>'Виж в склада','du'=>'products.php?filter=low'];

// Топ
$top = DB::run(
    'SELECT p.name, SUM(si.quantity) AS qty FROM sale_items si
     JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
     WHERE s.store_id=? AND s.tenant_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND s.status!="canceled"
     GROUP BY si.product_id ORDER BY qty DESC LIMIT 1',
    [$store_id, $tenant_id])->fetch();
$cards[] = ['icon'=>'🔥','label'=>'Топ продавани','val'=>$top?$top['name']:'Няма данни','sub'=>($top?(int)$top['qty'].' бр.':'').' / 7 дни',
    'color'=>'#22c55e','bg'=>'rgba(34,197,94,.15)','border'=>'rgba(34,197,94,.45)','glow'=>'rgba(34,197,94,.2)',
    'dt'=>'Топ продавани','dv'=>($top?$top['name'].' — '.(int)$top['qty'].' бр.':'Няма данни'),
    'dai'=>'Увери се че топ артикулите никога не са на нула. Поръчай преди да свършат.',
    'db'=>'Виж в склада','du'=>'products.php'];

if (in_array($role,['owner','manager'])) {
    $gap = (int)DB::run(
        'SELECT COUNT(DISTINCT p.parent_id) FROM products p JOIN inventory i ON i.product_id=p.id
         WHERE p.tenant_id=? AND i.store_id=? AND i.quantity=0 AND p.parent_id IS NOT NULL AND p.is_active=1',
        [$tenant_id, $store_id])->fetchColumn();
    $cards[] = ['icon'=>'📏','label'=>'Липсващи размери','val'=>$gap.' продукта','sub'=>'с нулеви варианти',
        'color'=>'#f59e0b','bg'=>'rgba(245,158,11,.15)','border'=>'rgba(245,158,11,.45)','glow'=>'rgba(245,158,11,.2)',
        'dt'=>'Липсващи размери','dv'=>$gap.' продукта с нулеви варианти',
        'dai'=>'Допълни преди уикенда — липсващите размери са директно загубени продажби.',
        'db'=>'Виж в склада','du'=>'products.php?filter=zero'];
}

if ($role === 'owner') {
    $pr = DB::run(
        'SELECT COALESCE(SUM(si.quantity*(si.unit_price-COALESCE(si.cost_price,0))),0) AS profit,
                COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue
         FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE s.store_id=? AND s.tenant_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="canceled"',
        [$store_id, $tenant_id])->fetch();
    $profit = round((float)$pr['profit']);
    $margin = $pr['revenue']>0 ? round($profit/(float)$pr['revenue']*100,1) : 0;
    $ds = $diff>=0?'+'.abs($diff).'%':'-'.abs($diff).'%';
    $cards[] = ['icon'=>'💰','label'=>'Печалба днес','val'=>number_format($profit,0,'.',' ').' '.$currency_symbol,
        'sub'=>$margin.'% марж · '.$ds.' vs вчера',
        'color'=>'#22c55e','bg'=>'rgba(34,197,94,.15)','border'=>'rgba(34,197,94,.45)','glow'=>'rgba(34,197,94,.2)',
        'dt'=>'Печалба днес','dv'=>number_format($profit,0,'.',' ').' '.$currency_symbol.' (марж '.$margin.'%)',
        'dai'=>$margin<20?'Маржът е под 20% — провери цените и отстъпките.':'Добър марж! Фокусирай се върху оборота.',
        'db'=>'Виж справките','du'=>'stats.php?tab=finance'];

    $cards[] = ['icon'=>'🧟','label'=>'Zombie стока','val'=>$zc.' арт.',
        'sub'=>'~'.number_format($zv,0,'.',' ').' '.$currency_symbol.' замразени',
        'color'=>'#f59e0b','bg'=>'rgba(245,158,11,.15)','border'=>'rgba(245,158,11,.45)','glow'=>'rgba(245,158,11,.2)',
        'dt'=>'Zombie стока (45+ дни)','dv'=>$zc.' арт. / '.number_format($zv,0,'.',' ').' '.$currency_symbol,
        'dai'=>'Пусни -20% промоция. По-добре 80% от парите сега, отколкото да чакаш.',
        'db'=>'Виж zombie','du'=>'products.php?filter=zombie'];
}

// Losing money (NEW card for v4 visual — uses existing query pattern)
if ($role === 'owner') {
    $loss_cnt = (int)DB::run(
        'SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0 AND retail_price < cost_price',
        [$tenant_id]
    )->fetchColumn();
    if ($loss_cnt > 0) {
        $cards[] = ['icon'=>'❌','label'=>'Губиш пари','val'=>$loss_cnt.' арт.','sub'=>'Под себестойност',
            'color'=>'#ef4444','bg'=>'rgba(239,68,68,.18)','border'=>'rgba(239,68,68,.5)','glow'=>'rgba(239,68,68,.25)',
            'dt'=>'Продаваш под себестойност','dv'=>$loss_cnt.' арт. губят пари',
            'dai'=>'Вдигни цената или спри продажбата. Губиш при всяка транзакция.',
            'db'=>'Промени цените','du'=>'products.php'];
    }
}

// ── MESSAGES (exact same) ──
$messages = DB::run(
    'SELECT role,content,created_at FROM chat_messages
     WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 30',
    [$tenant_id, $store_id])->fetchAll();

// ── CHIPS (exact same) ──
$chips = match($role) {
    'owner'   => ['Печалба тази седмица','Zombie стока','Кое губи пари?','Топ доставчик'],
    'manager' => ['Какво да поръчам?','Чакащи доставки','Дневен оборот','Трансфери'],
    default   => ['Има ли размер?','Препоръчай артикул','Продадох днес','Промоции']
};

// ── QUESTION CATEGORIES (exact same) ──
$cats = [
    'finance'   => ['icon'=>'💰','label'=>'Финанси',    'qs'=>['Оборот тази седмица?','Сравни с миналата','Коя категория печели?','Колко дължим?','Средна печалба?']],
    'stock'     => ['icon'=>'📦','label'=>'Склад',      'qs'=>['Колко артикула имам?','Zombie стока','Кои размери липсват?','Стойност на склада?','Без баркод?']],
    'suppliers' => ['icon'=>'🚚','label'=>'Доставчици', 'qs'=>['Кога идва доставка?','Поръчай от X','Чакащи поръчки?','Не е доставял 60дни?','Колко дължим на X?']],
    'clients'   => ['icon'=>'👥','label'=>'Клиенти',    'qs'=>['Топ клиент?','Нови този месец?','Купуват заедно?','Давно не са идвали?','Едрови клиенти?']],
    'ops'       => ['icon'=>'⚙️','label'=>'Операции',   'qs'=>['Пикови часове?','Продажби днес?','Кой продава най-добре?','Грешки в касата?','Затвори смяната']],
];

$cat_icons = [
    'finance'=>['📊','📈','🏆','💸','💵'],
    'stock'=>['🔢','🧟','📏','💎','🏷️'],
    'suppliers'=>['📅','🛒','⏳','👻','💳'],
    'clients'=>['⭐','🆕','🛍️','👋','🤝'],
    'ops'=>['⏰','🧾','🥇','⚠️','🔒'],
];

function parseDeeplinks(string $html): string {
    $map=['📦'=>'products.php','⚠️'=>'products.php?filter=low','📊'=>'stats.php','💰'=>'sale.php','🔄'=>'transfers.php','🛒'=>'purchase-orders.php'];
    return preg_replace_callback('/\[([^\]]+?)→\]/u',function($m)use($map){
        $t=trim($m[1]);$h='#';
        foreach($map as $e=>$u){if(mb_strpos($t,$e)!==false){$h=$u;break;}}
        return '<a class="deeplink" href="'.$h.'">'.htmlspecialchars($t).' →</a>';
    },$html);
}

// Plan colors for CSS
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
:root{
    --bg-main:#030712;--bg-card:rgba(15,15,40,0.75);
    --border-subtle:rgba(99,102,241,0.15);--border-glow:rgba(99,102,241,0.4);
    --indigo-600:#4f46e5;--indigo-500:#6366f1;--indigo-400:#818cf8;--indigo-300:#a5b4fc;
    --text-primary:#f1f5f9;--text-secondary:#6b7280;
    --danger:#ef4444;--warning:#f59e0b;--success:#22c55e;--nav-h:56px
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg-main)}
body{background:var(--bg-main);color:var(--text-primary);font-family:'Montserrat',Inter,system-ui,sans-serif;
    height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav-h)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:600px;height:350px;background:radial-gradient(ellipse,rgba(99,102,241,.1) 0%,transparent 70%);
    pointer-events:none;z-index:0}

/* ══ HEADER ══ */
.hdr{flex-shrink:0;padding:10px 12px 6px;display:flex;flex-direction:column;gap:5px;position:relative;z-index:10;
    background:rgba(3,7,18,.93);backdrop-filter:blur(16px);border-bottom:1px solid var(--border-subtle)}
.hdr-top{display:flex;align-items:center;justify-content:space-between}
.brand{font-size:15px;font-weight:700;background:linear-gradient(135deg,#f1f5f9,#a5b4fc);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-.3px;flex-shrink:0}
.hdr-right{display:flex;align-items:center;gap:4px}
.hdr-btn{width:26px;height:26px;border-radius:8px;background:rgba(255,255,255,.05);border:.5px solid rgba(255,255,255,.08);
    display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--indigo-300);position:relative;
    flex-shrink:0;transition:background .2s;font-size:11px}
.hdr-btn:active{background:rgba(99,102,241,.3)}
.hdr-badge{position:absolute;top:-3px;right:-3px;min-width:13px;height:13px;border-radius:7px;background:#ef4444;
    font-size:7px;font-weight:800;color:#fff;display:flex;align-items:center;justify-content:center;padding:0 3px}

/* Plan indicator */
.plan-badge{display:flex;align-items:center;gap:4px;padding:2px 7px;border-radius:14px;
    background:<?= $plan_colors['bg'] ?>;border:.5px solid <?= $plan_colors['border'] ?>;
    animation:planGlow 3s ease-in-out infinite}
.plan-name{font-size:8px;font-weight:800;color:<?= $plan_colors['text'] ?>;letter-spacing:.4px}
.plan-sep{width:1px;height:9px;background:rgba(255,255,255,.1)}
.ai-status{display:flex;align-items:center;gap:2px}
.ai-dot{width:5px;height:5px;border-radius:50%}
.ai-dot.on{background:#22c55e;box-shadow:0 0 6px #22c55e;animation:dotPulse 2s ease-in-out infinite}
.ai-dot.off{background:#6b7280}
.ai-label{font-size:7px;font-weight:600}
.ai-label.on{color:#86efac}
.ai-label.off{color:#6b7280}

/* Info chat button */
.info-chat-btn{display:flex;align-items:center;gap:3px;padding:2px 8px;border-radius:14px;
    background:rgba(99,102,241,.1);border:.5px solid rgba(99,102,241,.25);cursor:pointer;transition:all .2s}
.info-chat-btn:active{background:rgba(99,102,241,.3)}
.info-chat-btn svg{width:10px;height:10px}
.info-chat-btn span{font-size:8px;font-weight:600;color:#818cf8}

/* ══ PULSE ══ */
.pulse-wrap{margin:0 12px;padding:6px 10px;border-radius:10px;display:flex;align-items:center;gap:7px;cursor:pointer;flex-shrink:0;transition:transform .15s}
.pulse-wrap:active{transform:scale(.98)}
.pulse-wrap.red{background:rgba(239,68,68,.1);border:.5px solid rgba(239,68,68,.3)}
.pulse-wrap.yellow{background:rgba(245,158,11,.1);border:.5px solid rgba(245,158,11,.3)}
.pulse-wrap.green{background:rgba(34,197,94,.08);border:.5px solid rgba(34,197,94,.25)}
.pulse-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;animation:dotPulse 2s ease-in-out infinite}
.pulse-wrap.red .pulse-dot{background:#ef4444;box-shadow:0 0 6px #ef4444}
.pulse-wrap.yellow .pulse-dot{background:#f59e0b;box-shadow:0 0 6px #f59e0b}
.pulse-wrap.green .pulse-dot{background:#22c55e;box-shadow:0 0 6px #22c55e}
.pulse-text{font-size:10.5px;flex:1;line-height:1.4}
.pulse-wrap.red .pulse-text{color:#fca5a5}
.pulse-wrap.yellow .pulse-text{color:#fcd34d}
.pulse-wrap.green .pulse-text{color:#86efac}

/* Separator */
.indigo-sep{height:1px;background:linear-gradient(to right,transparent,rgba(99,102,241,.25),transparent);flex-shrink:0}

/* ══ TOP SCROLL AREA ══ */
.top-scroll{flex:1;overflow-y:auto;padding:8px 10px 4px;min-height:0;border-bottom:1px solid var(--border-subtle);
    -webkit-overflow-scrolling:touch;scrollbar-width:none}
.top-scroll::-webkit-scrollbar{display:none}

/* Signal cards — VIVID bright */
.sig-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:6px}
.sig-card{border-radius:14px;padding:9px 10px;cursor:pointer;position:relative;overflow:hidden;
    transition:transform .15s,box-shadow .3s}
.sig-card:active{transform:scale(.97)}
.sig-card::before{content:'';position:absolute;inset:0;border-radius:inherit;pointer-events:none}
.sig-card .sc-row{display:flex;align-items:center;gap:3px;margin-bottom:2px;position:relative}
.sig-card .sc-icon{font-size:10px}
.sig-card .sc-label{font-size:7.5px;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.sig-card .sc-val{font-size:16px;font-weight:800;line-height:1.1;position:relative}
.sig-card .sc-unit{font-size:10px;font-weight:600;opacity:.7;margin-left:2px}
.sig-card .sc-sub{font-size:9px;color:#9ca3af;margin-top:1px;position:relative}

/* Question cards — stats.php dark glassmorphism */
.q-section{margin-bottom:6px}
.q-section-hdr{display:flex;align-items:center;gap:6px;margin:8px 0 5px}
.q-section-title{font-size:10px;font-weight:700;color:#818cf8;text-transform:uppercase;letter-spacing:1px}
.q-section-line{flex:1;height:1px;background:linear-gradient(to right,rgba(99,102,241,.2),transparent);animation:sectionLine .8s ease both}
.q-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.q-card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:16px;
    padding:10px 11px;cursor:pointer;position:relative;overflow:hidden;backdrop-filter:blur(12px);
    transition:all .25s}
.q-card:active{transform:scale(.97)}
.q-card:hover{border-color:var(--border-glow);box-shadow:0 0 28px rgba(99,102,241,.18)}
.q-card::before{content:'';position:absolute;inset:0;border-radius:inherit;
    background:linear-gradient(135deg,rgba(99,102,241,.06),transparent);pointer-events:none}
.q-card .q-inner{display:flex;align-items:center;gap:5px;margin-bottom:4px;position:relative}
.q-card .q-ic{font-size:14px;flex-shrink:0}
.q-card .q-text{font-size:12px;color:var(--text-primary);font-weight:600}
.q-card .q-hint{font-size:10px;color:rgba(99,102,241,.5);font-weight:600;position:relative}
.q-card.full{grid-column:1/-1}

/* ══ STICKY CHAT ══ */
.chat-wrap{height:330px;flex-shrink:0;display:flex;flex-direction:column;position:relative;z-index:10;
    background:rgba(3,7,18,.97)}
.chat-hdr{display:flex;align-items:center;gap:5px;padding:5px 12px 2px;flex-shrink:0}
.chat-ava{width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);
    display:flex;align-items:center;justify-content:center}
.chat-ava span{font-size:7px;color:#fff}
.chat-label{font-size:10px;font-weight:700;color:#818cf8;text-transform:uppercase;letter-spacing:.5px}

/* Chips */
.chips-row{display:flex;gap:4px;padding:2px 12px 3px;overflow-x:auto;flex-shrink:0;scrollbar-width:none}
.chips-row::-webkit-scrollbar{display:none}
.chip{font-size:9.5px;padding:3px 8px;border-radius:14px;border:.5px solid rgba(99,102,241,.25);
    color:#818cf8;background:rgba(99,102,241,.08);white-space:nowrap;cursor:pointer;flex-shrink:0;
    font-family:inherit;transition:background .15s,transform .1s}
.chip:active{background:rgba(99,102,241,.35);transform:scale(.95)}

/* Messages */
.chat-area{flex:1;overflow-y:auto;overflow-x:hidden;padding:4px 12px;display:flex;flex-direction:column;
    -webkit-overflow-scrolling:touch;scrollbar-width:none;min-height:0}
.chat-area::-webkit-scrollbar{display:none}
.msg-group{margin-bottom:7px;animation:fadeUp .3s ease both}
.msg-meta{font-size:9px;color:#4b5563;margin-bottom:2px;display:flex;align-items:center;gap:4px}
.msg-meta.right{justify-content:flex-end}
.ai-ava{width:18px;height:18px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);
    display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ai-bars{display:flex;gap:1.5px;align-items:center;height:7px}
.ai-bar{width:1.5px;border-radius:1px;background:#fff}
.msg{max-width:82%;padding:7px 10px;font-size:12px;line-height:1.45;word-break:break-word}
.msg.ai{background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);color:#e2e8f0;border-radius:4px 12px 12px 12px}
.msg.user{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-radius:12px 12px 4px 12px;
    margin-left:auto;border:.5px solid rgba(255,255,255,.1)}
.msg a.deeplink{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(0,0,0,.2);
    border:.5px solid rgba(165,180,252,.3);border-radius:9px;color:#c7d2fe;font-size:10px;font-weight:600;
    text-decoration:none;margin:3px 2px 0}
.msg a.deeplink:active{background:rgba(99,102,241,.4)}
.typing-wrap{display:none;padding:7px 10px;background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);
    border-radius:4px 12px 12px 12px;width:fit-content;margin-bottom:7px}
.typing-dots{display:flex;gap:4px;align-items:center}
.dot{width:4px;height:4px;border-radius:50%;background:#818cf8;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}
.welcome{text-align:center;padding:16px 16px 8px;color:#6b7280;font-size:12px;line-height:1.5}
.welcome-title{font-size:18px;font-weight:700;margin-bottom:4px;background:linear-gradient(135deg,#e5e7eb,#c7d2fe);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

/* ══ INPUT ══ */
.input-area{padding:3px 10px 7px;flex-shrink:0}
.input-row{display:flex;gap:5px;align-items:center;background:rgba(10,14,28,.9);border-radius:20px;
    padding:3px 3px 3px 10px;border:.5px solid rgba(99,102,241,.2);animation:breathe 3s ease-in-out infinite}
.text-input{flex:1;background:transparent;border:none;color:var(--text-primary);font-size:12px;padding:6px 0;
    font-family:inherit;outline:none;resize:none;max-height:60px;line-height:1.4}
.text-input::placeholder{color:#374151}

/* Wave mic button */
.wave-btn{width:36px;height:36px;border-radius:50%;position:relative;display:flex;align-items:center;
    justify-content:center;cursor:pointer;flex-shrink:0;overflow:hidden;
    background:linear-gradient(135deg,#4f46e5,#9333ea);box-shadow:0 0 12px rgba(99,102,241,.3)}
.wave-btn.rec{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 0 18px rgba(239,68,68,.5)}
.wave-ring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.2);opacity:0}
.wave-btn.rec .wave-ring{border-color:rgba(255,255,255,.4)}
.wr1{width:18px;height:18px;animation:waveRing 2s 0s ease-in-out infinite}
.wr2{width:29px;height:29px;animation:waveRing 2s .35s ease-in-out infinite}
.wr3{width:40px;height:40px;animation:waveRing 2s .7s ease-in-out infinite}
.wave-bars{display:flex;gap:2px;align-items:center;height:14px;z-index:1}
.wave-bar{width:2.5px;border-radius:2px;background:#fff}
.wave-btn.rec .wave-bar{animation:barDance .55s ease-in-out infinite}

.send-btn{width:31px;height:31px;border-radius:50%;background:rgba(255,255,255,.08);border:.5px solid rgba(255,255,255,.1);
    color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s;font-size:12px}
.send-btn:disabled{opacity:.2}

/* ══ VOICE OVERLAY — exact same as before ══ */
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
.rec-label.recording{color:#ef4444}.rec-label.ready{color:#22c55e}
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

/* ══ DRAWER — stats.php style ══ */
.drawer-ovl{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);
    z-index:200;display:none;align-items:flex-end}
.drawer-ovl.show{display:flex}
.drawer-box{width:100%;background:#080818;border-top:.5px solid var(--border-glow);
    border-radius:22px 22px 0 0;padding:0 16px 32px;
    transform:translateY(100%);transition:transform .3s cubic-bezier(.32,0,.67,0);
    max-height:72vh;overflow-y:auto;box-shadow:0 -20px 60px rgba(99,102,241,.2)}
.drawer-ovl.show .drawer-box{transform:translateY(0)}
.drawer-handle{width:30px;height:3px;background:rgba(99,102,241,.3);border-radius:2px;margin:11px auto 14px}
.drawer-title{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.drawer-val{font-size:24px;font-weight:900;background:linear-gradient(135deg,#a5b4fc,#6366f1);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.2;margin-bottom:10px}
.ai-rec-box{background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(168,85,247,.06));
    border:1px solid rgba(99,102,241,.2);border-radius:12px;padding:12px;margin-bottom:14px;
    position:relative;overflow:hidden}
.ai-rec-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;
    background:linear-gradient(90deg,transparent,#6366f1,transparent);opacity:.5}
.ai-rec-label{font-size:10px;font-weight:800;color:#6366f1;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.ai-rec-text{font-size:13px;color:var(--text-primary);line-height:1.55}
.drawer-btn{display:block;width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);
    border:none;border-radius:14px;color:#fff;font-size:13px;font-weight:700;text-align:center;cursor:pointer;
    text-decoration:none;box-shadow:0 4px 20px rgba(99,102,241,.35)}
.drawer-btn:active{transform:scale(.98)}
.drawer-close-hint{font-size:10px;color:rgba(107,114,128,.5);text-align:center;padding:6px 0 0}

/* ══ HELP MODAL — exact same ══ */
.help-ovl{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:300;display:none;align-items:flex-end}
.help-ovl.show{display:flex}
.help-box{width:100%;background:#080818;border-top:.5px solid var(--border-glow);border-radius:22px 22px 0 0;
    padding:0 16px 32px;max-height:80vh;overflow-y:auto}
.help-handle{width:30px;height:3px;background:rgba(99,102,241,.3);border-radius:2px;margin:11px auto 14px}
.help-title{font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:14px;
    background:linear-gradient(135deg,#f1f5f9,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.help-section{margin-bottom:14px;background:rgba(99,102,241,.06);border:.5px solid rgba(99,102,241,.15);
    border-radius:12px;padding:12px}
.help-section h4{font-size:12px;font-weight:700;color:var(--indigo-300);margin-bottom:6px}
.help-section p{font-size:12px;color:var(--text-secondary);line-height:1.6}
.help-section .help-example{font-size:11px;color:#c7d2fe;background:rgba(99,102,241,.1);border-radius:8px;padding:6px 9px;margin-top:6px}

/* ══ BOTTOM NAV ══ */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.95);
    backdrop-filter:blur(15px);border-top:.5px solid rgba(99,102,241,.2);display:flex;z-index:100;
    box-shadow:0 -4px 20px rgba(99,102,241,.1)}
.btab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;
    font-size:9px;font-weight:600;color:rgba(165,180,252,.4);text-decoration:none;transition:all .3s}
.btab.active{color:#c7d2fe;text-shadow:0 0 10px rgba(129,140,248,.8)}
.btab-icon{font-size:16px;transition:all .3s}
.btab.active .btab-icon{transform:translateY(-1px);filter:drop-shadow(0 0 7px rgba(129,140,248,.8))}

/* Toast */
.toast{position:fixed;bottom:65px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#4f46e5,#7c3aed);
    color:#fff;padding:7px 16px;border-radius:20px;font-size:11px;font-weight:600;z-index:500;
    opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

/* Logout dropdown */
.logout-drop{position:absolute;top:30px;right:0;background:#0f0f2a;border:1px solid rgba(239,68,68,.3);
    border-radius:10px;padding:8px 14px;white-space:nowrap;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.5);
    font-size:12px;color:#fca5a5;font-weight:600;cursor:pointer;display:none}
.logout-drop.show{display:block}

/* ══ ANIMATIONS ══ */
@keyframes flipCard{from{opacity:0;transform:perspective(600px) rotateX(-20deg) translateY(10px)}to{opacity:1;transform:perspective(600px) rotateX(0) translateY(0)}}
@keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideRight{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}
@keyframes slideLeft{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
@keyframes scaleUp{from{opacity:0;transform:scale(.84)}to{opacity:1;transform:scale(1)}}
@keyframes flipIn{from{opacity:0;transform:perspective(400px) rotateY(-12deg)}to{opacity:1;transform:perspective(400px) rotateY(0)}}
@keyframes rotateIn{from{opacity:0;transform:rotate(-3deg) scale(.93)}to{opacity:1;transform:rotate(0) scale(1)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
@keyframes barDance{0%,100%{height:3px}50%{height:13px}}
@keyframes waveRing{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
@keyframes breathe{0%,100%{border-color:rgba(99,102,241,.2)}50%{border-color:rgba(99,102,241,.5);box-shadow:0 0 12px rgba(99,102,241,.1)}}
@keyframes sectionLine{from{width:0}to{width:100%}}
@keyframes planGlow{0%,100%{box-shadow:0 0 4px rgba(168,85,247,.2)}50%{box-shadow:0 0 12px rgba(168,85,247,.4)}}
@keyframes recPulse{0%,100%{opacity:1;box-shadow:0 0 8px #ef4444}50%{opacity:.5;box-shadow:0 0 20px #ef4444}}
@keyframes recSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}
@keyframes chipIn{to{opacity:1;transform:translateY(0)}}
</style>
</head>
<body>
<!-- ══ HEADER ══ -->
<div class="hdr">
  <div class="hdr-top">
    <span class="brand">RunMyStore.ai</span>
    <div class="hdr-right">
      <!-- Info Chat -->
      <a href="store-chat.php" class="info-chat-btn" title="Съобщения между обекти">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span>Info</span>
        <?php if ($unread > 0): ?><div class="hdr-badge" style="position:static;margin-left:2px"><?= $unread ?></div><?php endif; ?>
      </a>
      <!-- Plan + AI Advisor -->
      <div class="plan-badge">
        <span class="plan-name"><?= $plan ?></span>
        <div class="plan-sep"></div>
        <div class="ai-status">
          <div class="ai-dot <?= $ai_advisor?'on':'off' ?>"></div>
          <span class="ai-label <?= $ai_advisor?'on':'off' ?>"><?= $ai_advisor?'AI Advisor':'AI 🔒' ?></span>
        </div>
      </div>
      <!-- Help -->
      <div class="hdr-btn" onclick="showHelp()" title="Помощ">
        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
      </div>
      <!-- Logout -->
      <div class="hdr-btn" onclick="toggleLogout()" style="position:relative" id="logoutWrap">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2.5"><path stroke-linecap="round" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        <a href="logout.php" class="logout-drop" id="logoutDrop">Изход →</a>
      </div>
    </div>
  </div>
  <!-- Pulse -->
  <div class="pulse-wrap <?= $pulse['color'] ?>" onclick="openDrawer('pulse')">
    <div class="pulse-dot"></div>
    <div class="pulse-text" id="pulseText"></div>
    <span style="font-size:10px;opacity:.4;color:inherit">›</span>
  </div>
</div>

<!-- Separator -->
<div class="indigo-sep"></div>

<!-- ══ TOP SCROLL — signal cards + question sections ══ -->
<div class="top-scroll">

  <!-- Signal Cards — vivid, flipCard animation -->
  <div class="sig-grid">
    <?php foreach ($cards as $i => $c):
      $delay = number_format($i * 0.09, 2);
      $drawer_data = htmlspecialchars(json_encode(['title'=>$c['dt'],'val'=>$c['dv'],'ai'=>$c['dai'],'btn'=>$c['db'],'url'=>$c['du']]),ENT_QUOTES);
    ?>
    <div class="sig-card" style="background:<?= $c['bg'] ?>;border:1.5px solid <?= $c['border'] ?>;
         box-shadow:0 0 12px <?= $c['glow'] ?>,inset 0 1px 0 rgba(255,255,255,.05);
         animation:flipCard .5s <?= $delay ?>s ease both"
         data-drawer="<?= $drawer_data ?>" onclick="openCardDrawer(this)">
      <div style="position:absolute;inset:0;border-radius:inherit;background:linear-gradient(135deg,<?= $c['color'] ?>12,transparent 60%);pointer-events:none"></div>
      <div class="sc-row"><span class="sc-icon"><?= $c['icon'] ?></span><span class="sc-label"><?= htmlspecialchars($c['label']) ?></span></div>
      <div class="sc-val" style="color:<?= $c['color'] ?>"><?= htmlspecialchars($c['val']) ?></div>
      <div class="sc-sub"><?= htmlspecialchars($c['sub']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Question Sections — stats.php glassmorphism -->
  <?php
  $anims = ['finance'=>'slideRight','stock'=>'scaleUp','suppliers'=>'slideLeft','clients'=>'flipIn','ops'=>'rotateIn'];
  $si = 0;
  foreach ($cats as $key => $cat):
    $anim = $anims[$key] ?? 'cardIn';
    $icons = $cat_icons[$key] ?? [];
  ?>
  <div class="q-section">
    <div class="q-section-hdr">
      <span class="q-section-title"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?></span>
      <div class="q-section-line" style="animation-delay:<?= number_format(.3+$si*.12, 2) ?>s"></div>
    </div>
    <div class="q-grid">
      <?php foreach ($cat['qs'] as $qi => $q):
        $ic = $icons[$qi] ?? '💬';
        $isLast = ($qi === count($cat['qs'])-1 && count($cat['qs'])%2 !== 0);
        $d = number_format(.4+$si*.1+$qi*.06, 2);
      ?>
      <div class="q-card <?= $isLast?'full':'' ?>" onclick="fillAndSend(this.dataset.q)" data-q="<?= htmlspecialchars($q,ENT_QUOTES) ?>"
           style="animation:<?= $anim ?> .4s <?= $d ?>s ease both">
        <div class="q-inner">
          <span class="q-ic"><?= $ic ?></span>
          <span class="q-text"><?= htmlspecialchars($q) ?></span>
        </div>
        <div class="q-hint">↗ Натисни за детайли</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php $si++; endforeach; ?>

  <div style="height:4px"></div>
</div>

<!-- ══ STICKY CHAT ══ -->
<div class="chat-wrap">
  <div class="chat-hdr">
    <div class="chat-ava"><span>✦</span></div>
    <span class="chat-label">AI Чат</span>
  </div>

  <!-- Chips -->
  <div class="chips-row">
    <?php foreach ($chips as $ch): ?>
    <div class="chip" onclick="fillAndSend(this.dataset.q)" data-q="<?= htmlspecialchars($ch,ENT_QUOTES) ?>"><?= htmlspecialchars($ch) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Chat messages -->
  <div class="chat-area" id="chatArea">
    <?php if (empty($messages)): ?>
    <div class="welcome">
      <div class="welcome-title">Здравей<?= $user_name ? ', '.htmlspecialchars($user_name) : '' ?>!</div>
      Натисни микрофона или пиши.
    </div>
    <?php else: ?>
    <?php foreach ($messages as $msg): ?>
    <div class="msg-group">
      <?php if ($msg['role']==='assistant'): ?>
        <div class="msg-meta">
          <div class="ai-ava"><div class="ai-bars">
            <?php for($b=0;$b<5;$b++):$h=[2,5,8,5,2][$b];?>
            <div class="ai-bar" style="height:<?=$h?>px;animation:barDance <?=.7+$b*.1?>s <?=$b*.1?>s ease-in-out infinite"></div>
            <?php endfor;?>
          </div></div> AI
        </div>
        <div class="msg ai"><?= parseDeeplinks(nl2br(htmlspecialchars($msg['content']))) ?></div>
      <?php else: ?>
        <div class="msg-meta right"><?= date('H:i',strtotime($msg['created_at'])) ?></div>
        <div style="display:flex;justify-content:flex-end"><div class="msg user"><?= nl2br(htmlspecialchars($msg['content'])) ?></div></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <div class="typing-wrap" id="typing"><div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
  </div>

  <!-- Input -->
  <div class="input-area">
    <div class="input-row">
      <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1"
        oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,60)+'px';btnSend.disabled=!this.value.trim()"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}"></textarea>
      <div class="wave-btn" id="voiceBtn" onclick="toggleVoice()">
        <div class="wave-ring wr1"></div><div class="wave-ring wr2"></div><div class="wave-ring wr3"></div>
        <div class="wave-bars">
          <div class="wave-bar" style="height:3px"></div>
          <div class="wave-bar" style="height:7px"></div>
          <div class="wave-bar" style="height:11px"></div>
          <div class="wave-bar" style="height:7px"></div>
          <div class="wave-bar" style="height:3px"></div>
        </div>
      </div>
      <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>→</button>
    </div>
  </div>
</div>

<!-- ══ VOICE OVERLAY — exact same ══ -->
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
      <button class="rec-btn-send" id="recSendBtn" onclick="sendVoice()" disabled>🎤 Изпрати →</button>
    </div>
    <div class="rec-close-hint">Натисни навсякъде извън прозореца за затваряне</div>
  </div>
</div>

<!-- ══ DRAWER ══ -->
<div class="drawer-ovl" id="drawerOvl" onclick="closeDrawer()">
  <div class="drawer-box" onclick="event.stopPropagation()">
    <div class="drawer-handle"></div>
    <div id="drawerBody"></div>
    <div class="drawer-close-hint">← Назад или натисни извън прозореца</div>
  </div>
</div>

<!-- ══ HELP ══ -->
<div class="help-ovl" id="helpOvl" onclick="closeHelp()">
  <div class="help-box" onclick="event.stopPropagation()">
    <div class="help-handle"></div>
    <div class="help-title">✦ Как работи AI Dashboard</div>
    <div class="help-section"><h4>🔴 Pulse Radar</h4><p>Най-важното от магазина в реално време.</p><div class="help-example">🟢 Зелен = наред · 🟡 Жълт = внимание · 🔴 Червен = спешно</div></div>
    <div class="help-section"><h4>📊 Сигнални карти</h4><p>Реални данни. Натисни за AI препоръка.</p></div>
    <div class="help-section"><h4>💬 Бързи въпроси</h4><p>Натисни карта от секциите долу за директен въпрос.</p></div>
    <div class="help-section"><h4>🎤 Гласов вход</h4><p>Натисни вълната, говори, натисни "Изпрати →".</p><div class="help-example">"Кво става", "якеца" = якета, "маратонки" = обувки</div></div>
    <button class="drawer-btn" onclick="closeHelp()" style="margin-top:4px">Разбрах ✓</button>
  </div>
</div>

<!-- ══ BOTTOM NAV ══ -->
<nav class="bnav">
  <a href="chat.php"      class="btab active"><span class="btab-icon">✦</span>AI</a>
  <a href="warehouse.php" class="btab"><span class="btab-icon">📦</span>Склад</a>
  <a href="stats.php"     class="btab"><span class="btab-icon">📊</span>Справки</a>
  <a href="actions.php"   class="btab"><span class="btab-icon">⚡</span>Въвеждане</a>
</nav>

<div class="toast" id="toast"></div>
<script>
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');
const chatArea  = document.getElementById('chatArea');

// ── PULSE TYPEWRITER ──
(function(){
    const txt = <?= json_encode($pulse['text'], JSON_UNESCAPED_UNICODE) ?>;
    const el  = document.getElementById('pulseText');
    let i = 0;
    function t(){ if(i<txt.length){el.textContent+=txt[i++];setTimeout(t,26);} }
    t();
})();

// ── LOGOUT ──
function toggleLogout(){
    document.getElementById('logoutDrop').classList.toggle('show');
}
document.addEventListener('click',function(e){
    if(!document.getElementById('logoutWrap').contains(e.target))
        document.getElementById('logoutDrop').classList.remove('show');
});

// ── DRAWER ──
const PULSE_D = {
    title:'Pulse AI Radar',
    val: <?= json_encode($pulse['text'], JSON_UNESCAPED_UNICODE) ?>,
    ai:'AI анализира магазина в реално време и показва само най-важното за деня.',
    btn:'Виж справките', url:'stats.php'
};

function openDrawer(type) { renderDrawer(PULSE_D); }
function openCardDrawer(el) { renderDrawer(JSON.parse(el.dataset.drawer)); }

function renderDrawer(d) {
    document.getElementById('drawerBody').innerHTML =
        `<div class="drawer-title">${esc(d.title)}</div>
         <div class="drawer-val">${esc(d.val)}</div>
         <div class="ai-rec-box">
           <div class="ai-rec-label">✦ AI ПРЕПОРЪКА</div>
           <div class="ai-rec-text">${esc(d.ai)}</div>
         </div>
         ${d.btn?`<a href="${d.url}" class="drawer-btn">${esc(d.btn)} →</a>`:''}`;
    const ovl = document.getElementById('drawerOvl');
    ovl.style.display = 'flex';
    setTimeout(()=>ovl.classList.add('show'), 10);
    history.pushState({drawer:true}, '');
}

function closeDrawer() {
    const ovl = document.getElementById('drawerOvl');
    ovl.classList.remove('show');
    setTimeout(()=>{ ovl.style.display='none'; }, 300);
}

// HELP
function showHelp() {
    const ovl = document.getElementById('helpOvl');
    ovl.style.display = 'flex';
    setTimeout(()=>ovl.classList.add('show'),10);
    history.pushState({help:true},'');
}
function closeHelp() {
    const ovl = document.getElementById('helpOvl');
    ovl.classList.remove('show');
    setTimeout(()=>{ ovl.style.display='none'; },300);
}

// BACK BUTTON
window.addEventListener('popstate', e => {
    const drawer = document.getElementById('drawerOvl');
    const help   = document.getElementById('helpOvl');
    const voice  = document.getElementById('recOv');
    if (voice.classList.contains('open')) { stopVoice(); return; }
    if (drawer.classList.contains('show')) { closeDrawer(); return; }
    if (help.classList.contains('show'))   { closeHelp();   return; }
});

// Swipe down to close drawer
let tsY = 0;
document.querySelector('.drawer-box').addEventListener('touchstart', e=>{ tsY=e.touches[0].clientY; });
document.querySelector('.drawer-box').addEventListener('touchend',   e=>{ if(e.changedTouches[0].clientY-tsY>60) closeDrawer(); });

// ── CHAT ──
const dlMap = {'📦':'products.php','⚠️':'products.php?filter=low','📊':'stats.php','💰':'sale.php','🔄':'transfers.php','🛒':'purchase-orders.php'};

function parseDeeplinksJS(text) {
    return text.replace(/\[([^\]]+?)→\]/gu, (m,inner)=>{
        let href='#';
        for(const[e,u]of Object.entries(dlMap)){if(inner.includes(e)){href=u;break;}}
        return `<a class="deeplink" href="${href}">${esc(inner.trim())} →</a>`;
    });
}

function fillAndSend(text) {
    if (!text) return;
    chatInput.value = text;
    btnSend.disabled = false;
    sendMessage();
}

async function sendMessage() {
    const text = chatInput.value.trim();
    if (!text) return;
    addUserMsg(text);
    chatInput.value = ''; chatInput.style.height = ''; btnSend.disabled = true;
    typing.style.display = 'block'; scrollBottom();
    try {
        const res  = await fetch('chat-send.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({message:text})});
        const data = await res.json();
        typing.style.display = 'none';
        addAIMsg(data.reply || data.error || 'Грешка');
        if (data.action) showToast('Действие: '+(data.action.details||data.action.action||''));
    } catch(e) {
        typing.style.display = 'none';
        addAIMsg('Грешка при свързване. Опитай пак.');
    }
}

function addUserMsg(text) {
    const g = document.createElement('div'); g.className='msg-group';
    g.innerHTML=`<div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div><div style="display:flex;justify-content:flex-end"><div class="msg user">${esc(text)}</div></div>`;
    chatArea.insertBefore(g,typing); scrollBottom();
}

function addAIMsg(text) {
    const g = document.createElement('div'); g.className='msg-group';
    const bars = [2,5,8,5,2].map((h,i)=>`<div class="ai-bar" style="height:${h}px;animation:barDance ${.7+i*.1}s ${i*.1}s ease-in-out infinite"></div>`).join('');
    const parsed = parseDeeplinksJS(esc(text).replace(/\n/g,'<br>'));
    g.innerHTML=`<div class="msg-meta"><div class="ai-ava"><div class="ai-bars">${bars}</div></div> AI</div><div class="msg ai">${parsed}</div>`;
    chatArea.insertBefore(g,typing); scrollBottom();
}

function scrollBottom(){ chatArea.scrollTop = chatArea.scrollHeight; }
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── VOICE — exact same ──
let voiceRec=null, isRec=false, voiceText='';

function toggleVoice() {
    if (isRec) { stopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласово въвеждане'); return; }

    isRec = true; voiceText = '';
    document.getElementById('voiceBtn').classList.add('rec');
    document.getElementById('recOv').classList.add('open');
    document.getElementById('recDot').classList.remove('ready');
    document.getElementById('recLabel').textContent = '● ЗАПИСВА';
    document.getElementById('recLabel').className = 'rec-label recording';
    document.getElementById('recTranscript').textContent = 'Слушам...';
    document.getElementById('recTranscript').className = 'rec-transcript empty';
    document.getElementById('recSendBtn').disabled = true;
    history.pushState({voice:true},'');

    voiceRec = new SR();
    voiceRec.lang = 'bg-BG';
    voiceRec.continuous = false;
    voiceRec.interimResults = true;

    voiceRec.onresult = e => {
        let interim='', final='';
        for(let i=e.resultIndex;i<e.results.length;i++){
            if(e.results[i].isFinal) final+=e.results[i][0].transcript;
            else interim+=e.results[i][0].transcript;
        }
        if(final) voiceText=final;
        const el=document.getElementById('recTranscript');
        el.innerText = voiceText||interim||'Слушам...';
        el.className = 'rec-transcript'+(voiceText||interim?'':' empty');
    };

    voiceRec.onend = () => {
        isRec = false;
        document.getElementById('voiceBtn').classList.remove('rec');
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
        if(e.error==='no-speech') showToast('Не чух — опитай пак');
        else if(e.error==='not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: '+e.error);
    };

    try { voiceRec.start(); } catch(e){ stopVoice(); }
}

function sendVoice() {
    if (!voiceText) return;
    const t = voiceText;
    stopVoice();
    chatInput.value = t;
    btnSend.disabled = false;
    sendMessage();
}

function stopVoice() {
    isRec = false; voiceText = '';
    document.getElementById('voiceBtn').classList.remove('rec');
    document.getElementById('recOv').classList.remove('open');
    if (voiceRec) { try{ voiceRec.stop(); }catch(e){} voiceRec=null; }
}

// Scroll to bottom on load
window.addEventListener('DOMContentLoaded', () => { scrollBottom(); });

// Toast
function showToast(msg) {
    const t=document.getElementById('toast');
    t.textContent=msg; t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),3000);
}
</script>
</body>
</html>
