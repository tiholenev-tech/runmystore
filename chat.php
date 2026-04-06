<?php
/**
 * chat.php — AI First Dashboard v2
 * Pulse Radar + 5 Role Cards + Expandable Questions + Chat
 * RunMyStore.ai С26
 */
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }


$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)$_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

// ── STORE ──
$store = DB::run('SELECT name FROM stores WHERE id=? LIMIT 1', [$store_id])->fetch();
$store_name = $store['name'] ?? 'Магазин';

// ── UNREAD MESSAGES ──
$unread = DB::run(
    'SELECT COUNT(*) FROM store_messages WHERE tenant_id=? AND to_store_id=? AND is_read=0',
    [$tenant_id, $store_id]
)->fetchColumn();

// ── PULSE RADAR DATA ──
$rev_today = DB::run(
    'SELECT COALESCE(SUM(total_amount),0) AS rev, COUNT(*) AS cnt
     FROM sales WHERE store_id=? AND tenant_id=? AND DATE(created_at)=CURDATE() AND status!="cancelled"',
    [$store_id, $tenant_id]
)->fetch();

$rev_yesterday = DB::run(
    'SELECT COALESCE(SUM(total_amount),0) AS rev
     FROM sales WHERE store_id=? AND tenant_id=? AND DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status!="cancelled"',
    [$store_id, $tenant_id]
)->fetch();

$low_count = (int)DB::run(
    'SELECT COUNT(*) FROM inventory i JOIN products p ON p.id=i.product_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.qty<=p.min_stock AND p.min_stock>0 AND p.is_active=1',
    [$store_id, $tenant_id]
)->fetchColumn();

$zombie_data = DB::run(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(i.qty * COALESCE(p.cost_price, p.retail_price*0.6)),0) AS val
     FROM inventory i JOIN products p ON p.id=i.product_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.qty>0 AND p.is_active=1 AND p.parent_id IS NULL
     AND DATEDIFF(NOW(), COALESCE(
         (SELECT MAX(s2.created_at) FROM sales s2 JOIN sale_items si2 ON si2.sale_id=s2.id WHERE si2.product_id=p.id AND s2.store_id=i.store_id),
         p.created_at)) >= 45',
    [$store_id, $tenant_id]
)->fetch();

$rv   = (float)$rev_today['rev'];
$ry   = (float)$rev_yesterday['rev'];
$diff = $ry > 0 ? round(($rv - $ry) / $ry * 100, 1) : 0;
$zombie_val = round((float)$zombie_data['val']);
$zombie_cnt = (int)$zombie_data['cnt'];

// Pulse logic
if ($low_count >= 5) {
    $pulse = ['color'=>'red', 'text'=>"{$low_count} артикула под минимума — спешна поръчка нужна"];
} elseif ($zombie_val > 1000) {
    $pulse = ['color'=>'yellow', 'text'=>"{$zombie_cnt} артикула стоят 45+ дни — " . number_format($zombie_val,0,'.',' ') . " лв замразени"];
} elseif ($diff >= 15) {
    $pulse = ['color'=>'green', 'text'=>"Отличен ден! +" . $diff . "% спрямо вчера — " . number_format($rv,0,'.',' ') . " лв"];
} elseif ($diff <= -20 && (int)date('H') >= 14) {
    $pulse = ['color'=>'red', 'text'=>"Днес е слабо — " . abs($diff) . "% под вчера. Да предприемем нещо?"];
} else {
    $pulse = ['color'=>'green', 'text'=>number_format($rv,0,'.',' ') . " лв от " . (int)$rev_today['cnt'] . " продажби днес"];
}

// ── CARD DATA BY ROLE ──
$cards = [];

if (in_array($role, ['owner','manager','seller'])) {
    // Свършва — всички роли
    $low_rows = DB::run(
        'SELECT p.name, p.code, i.qty, p.min_stock
         FROM inventory i JOIN products p ON p.id=i.product_id
         WHERE i.store_id=? AND p.tenant_id=? AND i.qty<=p.min_stock AND p.min_stock>0 AND p.is_active=1
         ORDER BY i.qty ASC LIMIT 3',
        [$store_id, $tenant_id]
    )->fetchAll();
    $low_sub = $low_count > 0 ? implode(', ', array_column($low_rows, 'name')) : 'Всичко е наред';
    $low_sub = mb_strlen($low_sub) > 35 ? mb_substr($low_sub,0,32).'...' : $low_sub;
    $cards[] = [
        'icon'=>'⚠️', 'label'=>'Свършва', 'val'=>$low_count.' артикула',
        'sub'=>$low_sub,
        'bg'=>'rgba(239,68,68,.12)', 'br'=>'rgba(239,68,68,.3)', 'vc'=>'#fca5a5', 'sc'=>'rgba(252,165,165,.6)',
        'ring'=>30, 'ring_color'=>'#ef4444',
        'drawer_title'=>'Свършваща наличност',
        'drawer_val'=>$low_count.' артикула под минимума',
        'drawer_ai'=>'Зареди приоритетно. Всеки ден без наличност = директно загубени продажби.',
        'drawer_btn'=>'Виж в склада', 'drawer_url'=>'products.php?filter=low'
    ];

    // Топ продавани — всички роли
    $top = DB::run(
        'SELECT p.name, SUM(si.quantity) AS qty
         FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
         WHERE s.store_id=? AND s.tenant_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND s.status!="cancelled"
         GROUP BY si.product_id ORDER BY qty DESC LIMIT 1',
        [$store_id, $tenant_id]
    )->fetch();
    $top_name = $top ? $top['name'] : 'Няма данни';
    $top_qty  = $top ? (int)$top['qty'] : 0;
    $cards[] = [
        'icon'=>'🔥', 'label'=>'Топ продавани', 'val'=>$top_name,
        'sub'=>$top_qty.' бр. / 7 дни',
        'bg'=>'rgba(34,197,94,.08)', 'br'=>'rgba(34,197,94,.25)', 'vc'=>'#86efac', 'sc'=>'rgba(134,239,172,.55)',
        'ring'=>85, 'ring_color'=>'#22c55e',
        'drawer_title'=>'Топ продавани', 'drawer_val'=>$top_name.' — '.$top_qty.' бр.',
        'drawer_ai'=>'Увери се че топ артикулите никога не са на нула. Поръчай преди да свършат.',
        'drawer_btn'=>'Виж в склада', 'drawer_url'=>'products.php'
    ];
}

if (in_array($role, ['owner','manager'])) {
    // Липсващи размери
    $size_gaps = DB::run(
        'SELECT COUNT(DISTINCT p.parent_id) AS cnt FROM products p
         JOIN inventory i ON i.product_id=p.id
         WHERE p.tenant_id=? AND i.store_id=? AND i.qty=0 AND p.parent_id IS NOT NULL AND p.is_active=1
         AND p.parent_id IN (SELECT DISTINCT parent_id FROM products WHERE tenant_id=? AND parent_id IS NOT NULL)',
        [$tenant_id, $store_id, $tenant_id]
    )->fetch();
    $gap_cnt = (int)($size_gaps['cnt'] ?? 0);
    $cards[] = [
        'icon'=>'📏', 'label'=>'Липсващи размери', 'val'=>$gap_cnt.' продукта',
        'sub'=>'с дупки в размерите',
        'bg'=>'rgba(245,158,11,.08)', 'br'=>'rgba(245,158,11,.25)', 'vc'=>'#fcd34d', 'sc'=>'rgba(252,211,77,.55)',
        'ring'=>0, 'ring_color'=>'',
        'drawer_title'=>'Липсващи размери', 'drawer_val'=>$gap_cnt.' продукта с нулеви варианти',
        'drawer_ai'=>'Допълни преди уикенда — липсващите размери са директно загубени продажби.',
        'drawer_btn'=>'Виж в склада', 'drawer_url'=>'products.php?filter=zero'
    ];
}

if ($role === 'owner') {
    // Печалба
    $profit_data = DB::run(
        'SELECT COALESCE(SUM(si.quantity*(si.unit_price-COALESCE(si.cost_price,0))),0) AS profit,
                COALESCE(SUM(si.quantity*si.unit_price),0) AS revenue
         FROM sale_items si JOIN sales s ON s.id=si.sale_id
         WHERE s.store_id=? AND s.tenant_id=? AND DATE(s.created_at)=CURDATE() AND s.status!="cancelled"',
        [$store_id, $tenant_id]
    )->fetch();
    $profit = round((float)$profit_data['profit']);
    $margin = $profit_data['revenue'] > 0 ? round($profit/(float)$profit_data['revenue']*100,1) : 0;
    $diff_str = $diff >= 0 ? '+'.abs($diff).'%' : '-'.abs($diff).'%';
    $cards[] = [
        'icon'=>'💰', 'label'=>'Печалба днес', 'val'=>number_format($profit,0,'.',' ').' лв',
        'sub'=>$margin.'% марж · '.$diff_str.' vs вчера',
        'bg'=>'rgba(99,102,241,.12)', 'br'=>'rgba(99,102,241,.3)', 'vc'=>'#c7d2fe', 'sc'=>'rgba(199,210,254,.55)',
        'ring'=>min(100,max(0,$margin*2)), 'ring_color'=>'#818cf8',
        'drawer_title'=>'Печалба днес', 'drawer_val'=>number_format($profit,0,'.',' ').' лв (марж '.$margin.'%)',
        'drawer_ai'=>($margin < 20 ? 'Маржът е под 20% — провери цените и отстъпките.' : 'Добър марж! Фокусирай се върху оборота.'),
        'drawer_btn'=>'Виж справките', 'drawer_url'=>'stats.php?tab=finance'
    ];

    // Zombie
    $cards[] = [
        'icon'=>'🧟', 'label'=>'Zombie стока', 'val'=>$zombie_cnt.' артикула',
        'sub'=>'~'.number_format($zombie_val,0,'.',' ').' лв замразени',
        'bg'=>'rgba(245,158,11,.08)', 'br'=>'rgba(245,158,11,.25)', 'vc'=>'#fcd34d', 'sc'=>'rgba(252,211,77,.55)',
        'ring'=>0, 'ring_color'=>'',
        'drawer_title'=>'Zombie стока (45+ дни)', 'drawer_val'=>$zombie_cnt.' артикула / '.number_format($zombie_val,0,'.',' ').' лв',
        'drawer_ai'=>'Пусни -20% промоция. По-добре 80% от парите сега, отколкото да чакаш.',
        'drawer_btn'=>'Виж zombie', 'drawer_url'=>'products.php?filter=zombie'
    ];
}

// ── CHAT MESSAGES ──
$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 30',
    [$tenant_id, $store_id]
)->fetchAll();

// ── QUESTIONS BY ROLE ──
$chips = match($role) {
    'owner'   => ['Печалба тази седмица', 'Zombie стока', 'Кое губи пари?', 'Топ доставчик'],
    'manager' => ['Какво да поръчам?', 'Чакащи доставки', 'Дневен оборот', 'Трансфери'],
    default   => ['Има ли размер?', 'Препоръчай артикул', 'Продадох днес', 'Промоции']
};

$cats = [
    'finance'   => ['icon'=>'💰','label'=>'Финанси',    'qs'=>['Оборот тази седмица?','Сравни с миналата','Коя категория печели?','Колко дължим?','Средна печалба?']],
    'stock'     => ['icon'=>'📦','label'=>'Склад',      'qs'=>['Колко артикула имам?','Zombie стока','Кои размери липсват?','Стойност на склада?','Без баркод?']],
    'suppliers' => ['icon'=>'🚚','label'=>'Доставчици', 'qs'=>['Кога идва доставка?','Поръчай от X','Чакащи поръчки?','Кой не е доставял 60дни?','Колко дължим на X?']],
    'clients'   => ['icon'=>'👥','label'=>'Клиенти',    'qs'=>['Топ клиент?','Нови този месец?','Купуват заедно?','Давно не са идвали?','Едрови клиенти?']],
    'ops'       => ['icon'=>'⚙️','label'=>'Операции',   'qs'=>['Пикови часове?','Продажби днес?','Кой продава най-добре?','Затвори смяната','Грешки в касата?']],
];

function parseDeeplinks(string $html): string {
    $map = ['📦'=>'products.php','⚠️'=>'products.php?filter=low','📊'=>'stats.php','💰'=>'sale.php','🔄'=>'transfers.php','🛒'=>'purchase-orders.php'];
    return preg_replace_callback('/\[([^\]]+?)→\]/u', function($m) use ($map) {
        $text = trim($m[1]); $href = '#';
        foreach ($map as $emoji => $url) { if (mb_strpos($text,$emoji)!==false){$href=$url;break;} }
        return '<a class="deeplink" href="'.$href.'">'.htmlspecialchars($text).' →</a>';
    }, $html);
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#030712">
<title>RunMyStore.ai</title>
<style>
:root{--bg:#030712;--bg-card:rgba(15,15,40,0.75);--indigo:rgba(99,102,241,1);--border:rgba(99,102,241,0.15);--border-glow:rgba(99,102,241,0.4);--nav-h:64px}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg)}
body{background:var(--bg);color:#f1f5f9;font-family:'Montserrat',Inter,system-ui,sans-serif;height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav-h)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);width:600px;height:400px;background:radial-gradient(ellipse,rgba(99,102,241,.08) 0%,transparent 70%);pointer-events:none;z-index:0}

/* HEADER */
.hdr{flex-shrink:0;padding:16px 16px 8px;display:flex;align-items:center;justify-content:space-between;position:relative;z-index:10}
.brand{font-size:19px;font-weight:700;background:linear-gradient(135deg,#f1f5f9,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-.5px}
.store-pill{font-size:11px;font-weight:600;color:#818cf8;background:rgba(99,102,241,.15);border:.5px solid rgba(99,102,241,.3);border-radius:20px;padding:4px 10px;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hdr-btns{display:flex;gap:6px}
.hdr-btn{width:30px;height:30px;border-radius:10px;background:rgba(255,255,255,.05);border:.5px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#a5b4fc;position:relative;flex-shrink:0}
.hdr-btn:active{background:rgba(99,102,241,.3)}
.hdr-badge{position:absolute;top:-4px;right:-4px;min-width:16px;height:16px;border-radius:8px;background:#ef4444;font-size:9px;font-weight:800;color:#fff;display:flex;align-items:center;justify-content:center;padding:0 3px}

/* PULSE RADAR */
.pulse-wrap{margin:0 14px 10px;padding:10px 12px;border-radius:13px;display:flex;align-items:center;gap:10px;cursor:pointer;position:relative;overflow:hidden;flex-shrink:0}
.pulse-wrap.red{background:rgba(239,68,68,.1);border:.5px solid rgba(239,68,68,.3)}
.pulse-wrap.yellow{background:rgba(245,158,11,.1);border:.5px solid rgba(245,158,11,.3)}
.pulse-wrap.green{background:rgba(34,197,94,.08);border:.5px solid rgba(34,197,94,.25)}
.pulse-orbit{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;animation:orbitPulse 2s ease-in-out infinite}
.pulse-wrap.red .pulse-orbit{border:1.5px solid rgba(239,68,68,.6)}
.pulse-wrap.yellow .pulse-orbit{border:1.5px solid rgba(245,158,11,.6)}
.pulse-wrap.green .pulse-orbit{border:1.5px solid rgba(34,197,94,.5)}
.pulse-dot{width:8px;height:8px;border-radius:50%}
.pulse-wrap.red .pulse-dot{background:#ef4444}
.pulse-wrap.yellow .pulse-dot{background:#f59e0b}
.pulse-wrap.green .pulse-dot{background:#22c55e}
.pulse-text{font-size:12px;flex:1;line-height:1.4}
.pulse-wrap.red .pulse-text{color:#fca5a5}
.pulse-wrap.yellow .pulse-text{color:#fcd34d}
.pulse-wrap.green .pulse-text{color:#86efac}
.pulse-arrow{font-size:14px;opacity:.5}

/* CARDS */
.cards-grid{padding:0 12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;flex-shrink:0}
.card{border-radius:13px;padding:10px 11px;cursor:pointer;position:relative;overflow:hidden;opacity:0;animation:cardIn .35s ease forwards}
.card:active{transform:scale(.97)}
.card-icon{font-size:15px;margin-bottom:3px}
.card-label{font-size:10px;color:rgba(255,255,255,.38);margin-bottom:2px;letter-spacing:.3px}
.card-val{font-size:15px;font-weight:500;line-height:1.2}
.card-sub{font-size:10px;margin-top:2px;opacity:.65}
.card-ring{position:absolute;top:7px;right:7px;width:20px;height:20px}
.ring-svg{transform:rotate(-90deg)}
.ring-bg{fill:none;stroke:rgba(255,255,255,.07);stroke-width:2.5}
.ring-fill{fill:none;stroke-width:2.5;stroke-linecap:round}
.zombie-bar{margin-top:5px;display:flex;gap:2px;height:3px;border-radius:2px;overflow:hidden}
.zb-seg{height:100%;border-radius:2px}

/* CHIPS */
.chips-wrap{padding:0 14px 6px;flex-shrink:0}
.chips-row{display:flex;gap:6px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding-bottom:2px}
.chips-row::-webkit-scrollbar{display:none}
.chip{font-size:11px;padding:5px 11px;border-radius:20px;border:.5px solid rgba(99,102,241,.3);color:#a5b4fc;background:rgba(99,102,241,.1);white-space:nowrap;cursor:pointer;flex-shrink:0;opacity:0;animation:chipIn .3s ease forwards;font-family:inherit}
.chip:active{background:rgba(99,102,241,.3)}
.chip-more{border-color:rgba(99,102,241,.2);color:#6b7280;background:transparent}

/* EXPANDED QUESTIONS */
.exp-wrap{padding:0 14px 6px;flex-shrink:0;display:none}
.exp-grid{background:rgba(255,255,255,.03);border:.5px solid rgba(99,102,241,.18);border-radius:13px;overflow:hidden}
.exp-cats{display:grid;grid-template-columns:repeat(5,1fr);border-bottom:.5px solid rgba(99,102,241,.12)}
.exp-cat{padding:8px 4px;text-align:center;cursor:pointer;transition:background .15s}
.exp-cat.act{background:rgba(99,102,241,.2)}
.exp-cat-icon{font-size:13px}
.exp-cat-label{font-size:9px;color:#6b7280;margin-top:1px}
.exp-qs{padding:6px 10px}
.exp-q{padding:5px 0;border-bottom:.5px solid rgba(99,102,241,.08);font-size:11px;color:#c7d2fe;cursor:pointer;display:flex;justify-content:space-between;align-items:center}
.exp-q:last-child{border-bottom:none}
.exp-q:active{color:#fff}

/* CHAT */
.chat-area{flex:1;overflow-y:auto;overflow-x:hidden;padding:8px 14px 0;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1;min-height:0}
.chat-area::-webkit-scrollbar{display:none}
.msg-group{margin-bottom:12px;animation:cardIn .3s ease both}
.msg-meta{font-size:10px;color:#4b5563;margin-bottom:3px;display:flex;align-items:center;gap:5px}
.msg-meta.right{justify-content:flex-end}
.ai-ava{width:20px;height:20px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ai-bars{display:flex;gap:2px;align-items:center;height:9px}
.ai-bar{width:2px;border-radius:1px;background:#fff}
.msg{max-width:88%;padding:10px 13px;font-size:13px;line-height:1.5;word-break:break-word}
.msg.ai{background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.2);color:#f1f5f9;border-radius:4px 16px 16px 16px}
.msg.user{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-radius:16px 16px 4px 16px;margin-left:auto;border:.5px solid rgba(255,255,255,.1)}
.msg a.deeplink{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;background:rgba(0,0,0,.2);border:.5px solid rgba(165,180,252,.3);border-radius:10px;color:#c7d2fe;font-size:11px;font-weight:600;text-decoration:none;margin:4px 2px 0}
.msg a.deeplink:active{background:rgba(99,102,241,.4)}
.typing-wrap{display:none;padding:10px 13px;background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.2);border-radius:4px 16px 16px 16px;width:fit-content;margin-bottom:12px}
.typing-dots{display:flex;gap:4px;align-items:center}
.dot{width:5px;height:5px;border-radius:50%;background:#818cf8;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}
.dot:nth-child(3){animation-delay:.4s}
.welcome{text-align:center;padding:24px 20px 16px;color:#6b7280;font-size:13px;line-height:1.6}
.welcome-title{font-size:22px;font-weight:700;margin-bottom:8px;background:linear-gradient(135deg,#e5e7eb,#c7d2fe,#f9fafb);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:shimmer 6s linear infinite}

/* INPUT */
.input-area{padding:6px 14px 12px;flex-shrink:0;position:relative;z-index:10}
.input-row{display:flex;gap:8px;align-items:center;background:rgba(10,14,28,.9);border-radius:28px;padding:5px 5px 5px 13px;animation:breathe 3s ease-in-out infinite}
.text-input{flex:1;background:transparent;border:none;color:#f1f5f9;font-size:14px;padding:9px 0;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4}
.text-input::placeholder{color:#374151}
.voice-btn{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);display:flex;align-items:center;justify-content:center;flex-shrink:0;cursor:pointer;position:relative}
.voice-btn.rec{background:linear-gradient(135deg,#ef4444,#dc2626)}
.send-btn{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.08);border:.5px solid rgba(255,255,255,.1);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.send-btn:disabled{opacity:.2}

/* VOICE OVERLAY */
.rec-ov{position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(8px);z-index:400;display:none;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:60px}
.rec-ov.show{display:flex}
.rec-box{background:rgba(15,15,40,.95);border:.5px solid rgba(99,102,241,.4);border-radius:20px;padding:24px 20px;width:calc(100% - 32px);max-width:360px;box-shadow:0 0 30px rgba(99,102,241,.2)}
.rec-indicator{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.rec-dot{width:10px;height:10px;border-radius:50%;background:#ef4444;animation:recPulse 1s ease-in-out infinite;flex-shrink:0}
.rec-dot.done{background:#22c55e;animation:none}
.rec-status{font-size:14px;font-weight:600;color:#fff}
.rec-transcript{background:rgba(255,255,255,.05);border:.5px solid rgba(99,102,241,.2);border-radius:12px;padding:10px 12px;font-size:13px;color:#c7d2fe;min-height:44px;margin-bottom:12px;line-height:1.5;word-break:break-word}
.rec-actions{display:flex;gap:8px}
.rec-send{flex:1;padding:11px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:13px;color:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.rec-cancel{padding:11px 16px;border:.5px solid rgba(99,102,241,.3);border-radius:13px;background:transparent;color:#6b7280;font-size:13px;cursor:pointer;font-family:inherit}

/* DRAWER */
.drawer-ovl{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:200;display:none;align-items:flex-end}
.drawer-ovl.show{display:flex}
.drawer-box{width:100%;background:#080818;border-top:.5px solid rgba(99,102,241,.4);border-radius:22px 22px 0 0;padding:0 16px 36px;transform:translateY(100%);transition:transform .3s cubic-bezier(.32,0,.67,0);max-height:75vh;overflow-y:auto}
.drawer-ovl.show .drawer-box{transform:translateY(0)}
.drawer-handle{width:32px;height:3px;background:rgba(99,102,241,.3);border-radius:2px;margin:12px auto 16px}
.drawer-title{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.drawer-val{font-size:26px;font-weight:500;background:linear-gradient(135deg,#a5b4fc,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.2;margin-bottom:12px}
.ai-box{background:rgba(99,102,241,.08);border:.5px solid rgba(99,102,241,.2);border-radius:12px;padding:11px;margin-bottom:12px}
.ai-box-label{font-size:10px;color:#6366f1;letter-spacing:.8px;margin-bottom:5px;text-transform:uppercase}
.ai-box-text{font-size:13px;color:#e2e8f0;line-height:1.55}
.drawer-btn{display:block;width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:13px;color:#fff;font-size:13px;font-weight:600;text-align:center;cursor:pointer;text-decoration:none}

/* BOTTOM NAV */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.95);backdrop-filter:blur(15px);border-top:.5px solid rgba(99,102,241,.2);display:flex;z-index:100;box-shadow:0 -4px 20px rgba(99,102,241,.1)}
.btab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:9px;font-weight:600;color:rgba(165,180,252,.4);text-decoration:none;transition:all .3s}
.btab.active{color:#c7d2fe;text-shadow:0 0 10px rgba(129,140,248,.8)}
.btab-icon{font-size:18px;transition:all .3s}
.btab.active .btab-icon{transform:translateY(-2px);filter:drop-shadow(0 0 7px rgba(129,140,248,.8))}

/* TOAST */
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:10px 20px;border-radius:24px;font-size:13px;font-weight:600;z-index:500;opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-8px)}

/* ANIMATIONS */
@keyframes orbitPulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.4)}50%{box-shadow:0 0 0 5px rgba(99,102,241,0)}}
@keyframes cardIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes chipIn{to{opacity:1;transform:translateY(0)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes recPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}
@keyframes breathe{0%,100%{border:.5px solid rgba(99,102,241,.18)}50%{border:.5px solid rgba(99,102,241,.45);box-shadow:0 0 10px rgba(99,102,241,.1)}}
</style>
</head>
<body>

<!-- HEADER -->
<div class="hdr">
  <div class="brand">RunMyStore.ai</div>
  <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
  <div class="hdr-btns">
    <div class="hdr-btn" onclick="fillAndSend('Покажи всички нотификации')">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php if ($unread > 0): ?><div class="hdr-badge"><?= $unread ?></div><?php endif; ?>
    </div>
  </div>
</div>

<!-- PULSE RADAR -->
<div class="pulse-wrap <?= $pulse['color'] ?>" onclick="openDrawer('pulse')" id="pulseWrap">
  <div class="pulse-orbit"><div class="pulse-dot"></div></div>
  <div class="pulse-text" id="pulseText"></div>
  <span class="pulse-arrow">›</span>
</div>

<!-- CARDS GRID -->
<div class="cards-grid" id="cardsGrid">
<?php
$col_count = min(count($cards), 2);
$total = count($cards);
foreach ($cards as $i => $c):
    $delay = number_format($i * 0.07, 2);
    $span  = ($total === 5 && $i === 4) ? 'grid-column:1/-1;' : '';
    $circ  = 2 * M_PI * 9;
    $offset = $c['ring'] > 0 ? $circ - ($c['ring']/100)*$circ : $circ;
    $ring_html = '';
    if ($c['ring'] > 0 && $c['ring_color']) {
        $ring_html = '<div class="card-ring"><svg class="ring-svg" viewBox="0 0 24 24" width="20" height="20">
            <circle class="ring-bg" cx="12" cy="12" r="9"/>
            <circle class="ring-fill" cx="12" cy="12" r="9" stroke="'.$c['ring_color'].'" stroke-dasharray="'.$circ.'" stroke-dashoffset="'.$circ.'" data-target="'.round($offset,2).'"/>
        </svg></div>';
    }
    $zombie_html = '';
    if ($c['icon'] === '🧟') {
        $zombie_html = '<div class="zombie-bar"><div class="zb-seg" style="width:0;background:#ef4444;transition:width 1.3s .3s cubic-bezier(.34,1.56,.64,1)" data-w="35"></div><div class="zb-seg" style="width:0;background:#f59e0b;transition:width 1.3s .5s cubic-bezier(.34,1.56,.64,1)" data-w="25"></div><div class="zb-seg" style="flex:1;background:rgba(255,255,255,.06)"></div></div>';
    }
?>
<div class="card" style="<?= $span ?>background:<?= $c['bg'] ?>;border:.5px solid <?= $c['br'] ?>;animation-delay:<?= $delay ?>s"
     data-drawer='<?= htmlspecialchars(json_encode(['title'=>$c['drawer_title'],'val'=>$c['drawer_val'],'ai'=>$c['drawer_ai'],'btn'=>$c['drawer_btn'],'url'=>$c['drawer_url']]), ENT_QUOTES) ?>'
     onclick="openCardDrawer(this)">
  <?= $ring_html ?>
  <div class="card-icon"><?= $c['icon'] ?></div>
  <div class="card-label"><?= htmlspecialchars($c['label']) ?></div>
  <div class="card-val" style="color:<?= $c['vc'] ?>"><?= htmlspecialchars($c['val']) ?></div>
  <div class="card-sub" style="color:<?= $c['sc'] ?>"><?= htmlspecialchars($c['sub']) ?></div>
  <?= $zombie_html ?>
</div>
<?php endforeach; ?>
</div>

<!-- CHIPS -->
<div class="chips-wrap">
  <div class="chips-row" id="chipsRow">
    <?php foreach ($chips as $i => $ch): ?>
    <div class="chip" style="animation-delay:<?= number_format($i*0.06,2) ?>s" onclick="fillAndSend('<?= htmlspecialchars($ch, ENT_QUOTES) ?>')"><?= htmlspecialchars($ch) ?></div>
    <?php endforeach; ?>
    <div class="chip chip-more" style="animation-delay:<?= number_format(count($chips)*0.06,2) ?>s" onclick="toggleExp()" id="moreChip">Още ▾</div>
  </div>
</div>

<!-- EXPANDED QUESTIONS -->
<div class="exp-wrap" id="expWrap">
  <div class="exp-grid">
    <div class="exp-cats">
      <?php foreach ($cats as $k => $cat): ?>
      <div class="exp-cat" id="ecat-<?= $k ?>" onclick="showCat('<?= $k ?>')">
        <div class="exp-cat-icon"><?= $cat['icon'] ?></div>
        <div class="exp-cat-label"><?= $cat['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="exp-qs" id="expQs"></div>
  </div>
</div>

<!-- CHAT -->
<div class="chat-area" id="chatArea">
  <?php if (empty($messages)): ?>
  <div class="welcome">
    <div class="welcome-title">Здравей<?= $user_name ? ', '.htmlspecialchars($user_name) : '' ?>!</div>
    Натисни микрофона или пиши.
  </div>
  <?php else: ?>
  <?php foreach ($messages as $msg): ?>
  <div class="msg-group">
    <?php if ($msg['role'] === 'assistant'): ?>
      <div class="msg-meta">
        <div class="ai-ava"><div class="ai-bars">
          <?php for ($b=0;$b<4;$b++): $h=[4,8,10,6][$b]; ?>
          <div class="ai-bar" style="height:<?=$h?>px;animation:barDance <?=.7+$b*.1?>s <?=$b*.15?>s ease-in-out infinite"></div>
          <?php endfor; ?>
        </div></div> AI
      </div>
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

<!-- INPUT -->
<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1"
      oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';btnSend.disabled=!this.value.trim()"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}"></textarea>
    <div class="voice-btn" id="voiceBtn" onclick="toggleVoice()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
    </div>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>

<!-- VOICE OVERLAY -->
<div class="rec-ov" id="recOv">
  <div class="rec-box">
    <div class="rec-indicator">
      <div class="rec-dot" id="recDot"></div>
      <div class="rec-status" id="recStatus">● ЗАПИСВА</div>
    </div>
    <div class="rec-transcript" id="recTranscript">Говори свободно...</div>
    <div class="rec-actions">
      <button class="rec-send" id="recSendBtn" onclick="sendVoice()" style="display:none">Изпрати →</button>
      <button class="rec-cancel" onclick="stopVoice()">Отказ</button>
    </div>
  </div>
</div>

<!-- DRAWER -->
<div class="drawer-ovl" id="drawerOvl" onclick="closeDrawer()">
  <div class="drawer-box" onclick="event.stopPropagation()">
    <div class="drawer-handle"></div>
    <div id="drawerBody"></div>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <a href="chat.php"      class="btab active"><span class="btab-icon">✦</span>AI</a>
  <a href="warehouse.php" class="btab"><span class="btab-icon">📦</span>Склад</a>
  <a href="stats.php"     class="btab"><span class="btab-icon">📊</span>Справки</a>
  <a href="actions.php"   class="btab"><span class="btab-icon">⚡</span>Въвеждане</a>
</nav>

<div class="toast" id="toast"></div>

<script>
const chatArea  = document.getElementById('chatArea');
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');

// ── PULSE TYPEWRITER ──────────────────────────────────────────
const pulseText = "<?= addslashes($pulse['text']) ?>";
let ptIdx = 0;
const ptEl = document.getElementById('pulseText');
function typewrite() {
    if (ptIdx < pulseText.length) {
        ptEl.textContent += pulseText[ptIdx++];
        setTimeout(typewrite, 28);
    }
}
typewrite();

// ── CARDS COUNTER ANIMATION ───────────────────────────────────
function animCount(el, target, dur) {
    const start = performance.now();
    const run = (now) => {
        const p = Math.min((now - start) / dur, 1);
        const e = 1 - Math.pow(1 - p, 3);
        const cur = Math.round(target * e);
        const orig = el.textContent;
        const numMatch = orig.match(/\d[\d\s]*/);
        if (numMatch) el.textContent = orig.replace(numMatch[0], cur.toLocaleString('bg-BG'));
        if (p < 1) requestAnimationFrame(run);
    };
    requestAnimationFrame(run);
}

window.addEventListener('DOMContentLoaded', () => {
    // Animate card values
    document.querySelectorAll('.card-val').forEach(el => {
        const numMatch = el.textContent.match(/\d+/);
        if (numMatch) {
            const target = parseInt(numMatch[0]);
            if (target > 0) setTimeout(() => animCount(el, target, 900), 300);
        }
    });

    // Animate rings
    document.querySelectorAll('.ring-fill[data-target]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(.34,1.56,.64,1)';
            el.style.strokeDashoffset = el.dataset.target;
        }, 500);
    });

    // Animate zombie bars
    document.querySelectorAll('.zb-seg[data-w]').forEach(el => {
        setTimeout(() => { el.style.width = el.dataset.w + '%'; }, 400);
    });

    scrollBottom();
});

// ── SCROLL ────────────────────────────────────────────────────
function scrollBottom() { chatArea.scrollTop = chatArea.scrollHeight; }

// ── CHIPS EXPANDED ────────────────────────────────────────────
let expOpen = false;
let activeCat = null;

const CATS = <?= json_encode($cats, JSON_UNESCAPED_UNICODE) ?>;

function toggleExp() {
    expOpen = !expOpen;
    document.getElementById('expWrap').style.display = expOpen ? 'block' : 'none';
    document.getElementById('moreChip').textContent = expOpen ? 'По-малко ▴' : 'Още ▾';
    if (expOpen && !activeCat) showCat('finance');
}

function showCat(cat) {
    activeCat = cat;
    Object.keys(CATS).forEach(k => {
        const el = document.getElementById('ecat-' + k);
        if (el) el.className = 'exp-cat' + (k === cat ? ' act' : '');
    });
    const qs = CATS[cat]?.qs || [];
    document.getElementById('expQs').innerHTML = qs.map(q =>
        `<div class="exp-q" onclick="fillAndSend('${q.replace(/'/g,"\\'")}')"><span>${q}</span><span style="color:#374151;font-size:10px">›</span></div>`
    ).join('');
}

// ── DRAWER ────────────────────────────────────────────────────
const PULSE_DRAWER = {
    title: 'Pulse AI Radar',
    val: "<?= addslashes($pulse['text']) ?>",
    ai: 'AI анализира магазина в реално време и показва само най-важното за деня.',
    btn: 'Виж всички данни', url: 'stats.php'
};

function openDrawer(type) {
    const d = type === 'pulse' ? PULSE_DRAWER : null;
    if (!d) return;
    renderDrawer(d);
}

function openCardDrawer(el) {
    const d = JSON.parse(el.dataset.drawer);
    renderDrawer(d);
}

function renderDrawer(d) {
    document.getElementById('drawerBody').innerHTML =
        `<div class="drawer-title">${esc(d.title)}</div>
         <div class="drawer-val">${esc(d.val)}</div>
         <div class="ai-box">
           <div class="ai-box-label">✦ AI ПРЕПОРЪКА</div>
           <div class="ai-box-text">${esc(d.ai)}</div>
         </div>
         ${d.btn ? `<a href="${d.url}" class="drawer-btn">${esc(d.btn)} →</a>` : ''}`;
    const ovl = document.getElementById('drawerOvl');
    ovl.style.display = 'flex';
    setTimeout(() => ovl.classList.add('show'), 10);
}

function closeDrawer() {
    const ovl = document.getElementById('drawerOvl');
    ovl.classList.remove('show');
    setTimeout(() => { ovl.style.display = 'none'; }, 320);
}

// Swipe down to close drawer
let touchY = 0;
document.querySelector('.drawer-box').addEventListener('touchstart', e => { touchY = e.touches[0].clientY; });
document.querySelector('.drawer-box').addEventListener('touchend', e => { if (e.changedTouches[0].clientY - touchY > 60) closeDrawer(); });

// ── CHAT ──────────────────────────────────────────────────────
const dlMap = {'📦':'products.php','⚠️':'products.php?filter=low','📊':'stats.php','💰':'sale.php','🔄':'transfers.php','🛒':'purchase-orders.php'};

function parseDeeplinksJS(text) {
    return text.replace(/\[([^\]]+?)→\]/gu, (m, inner) => {
        let href = '#';
        for (const [emoji, url] of Object.entries(dlMap)) {
            if (inner.includes(emoji)) { href = url; break; }
        }
        return `<a class="deeplink" href="${href}">${esc(inner.trim())} →</a>`;
    });
}

function fillAndSend(text) {
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
        if (data.action) showActionConfirm(data.action);
    } catch(e) {
        typing.style.display = 'none';
        addAIMsg('Грешка при свързване. Опитай пак.');
    }
}

function addUserMsg(text) {
    const g = document.createElement('div'); g.className = 'msg-group';
    g.innerHTML = `<div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div><div style="display:flex;justify-content:flex-end"><div class="msg user">${esc(text)}</div></div>`;
    chatArea.insertBefore(g, typing); scrollBottom();
}

function addAIMsg(text) {
    const g = document.createElement('div'); g.className = 'msg-group';
    const bars = [4,8,10,6].map((h,i) => `<div class="ai-bar" style="height:${h}px;animation:barDance ${.7+i*.1}s ${i*.15}s ease-in-out infinite"></div>`).join('');
    const parsed = parseDeeplinksJS(esc(text).replace(/\n/g,'<br>'));
    g.innerHTML = `<div class="msg-meta"><div class="ai-ava"><div class="ai-bars">${bars}</div></div> AI</div><div class="msg ai">${parsed}</div>`;
    chatArea.insertBefore(g, typing); scrollBottom();
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── VOICE ─────────────────────────────────────────────────────
let voiceRec = null, isRec = false, voiceText = '';

function toggleVoice() {
    if (isRec) { stopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { showToast('Браузърът не поддържа гласово въвеждане'); return; }
    isRec = true;
    voiceText = '';
    document.getElementById('voiceBtn').classList.add('rec');
    document.getElementById('recOv').classList.add('show');
    document.getElementById('recDot').classList.remove('done');
    document.getElementById('recStatus').textContent = '● ЗАПИСВА';
    document.getElementById('recTranscript').textContent = 'Говори свободно...';
    document.getElementById('recSendBtn').style.display = 'none';

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
        document.getElementById('recTranscript').textContent = voiceText || interim || 'Слушам...';
    };
    voiceRec.onend = () => {
        if (voiceText) {
            document.getElementById('recDot').classList.add('done');
            document.getElementById('recStatus').textContent = '✓ ГОТОВО';
            document.getElementById('recSendBtn').style.display = 'block';
        } else {
            stopVoice();
        }
        isRec = false;
        document.getElementById('voiceBtn').classList.remove('rec');
    };
    voiceRec.onerror = e => {
        stopVoice();
        if (e.error === 'no-speech') showToast('Не чух — опитай пак');
        else if (e.error === 'not-allowed') showToast('Разреши микрофона');
        else showToast('Грешка: ' + e.error);
    };
    try { voiceRec.start(); } catch(e) { stopVoice(); }
}

function sendVoice() {
    if (!voiceText) return;
    stopVoice();
    chatInput.value = voiceText;
    btnSend.disabled = false;
    sendMessage();
}

function stopVoice() {
    isRec = false;
    voiceText = '';
    document.getElementById('voiceBtn').classList.remove('rec');
    document.getElementById('recOv').classList.remove('show');
    if (voiceRec) { try { voiceRec.stop(); } catch(e){} voiceRec = null; }
}

// ── ACTION CONFIRM ────────────────────────────────────────────
function showActionConfirm(action) {
    if (!action) return;
    showToast('Действие: ' + (action.details || action.action));
}

// ── TOAST ─────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>
