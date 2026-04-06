<?php
/**
 * chat.php — AI First Dashboard v3
 * С26 — Fix: chips работят, drawer back button, flip анимации,
 *        voice overlay = sale.php стил, намалени карти, help бутон
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
    $pulse = ['color'=>'yellow', 'text'=>"{$zc} артикула стоят 45+ дни — ".number_format($zv,0,'.',' ')." лв замразени"];
elseif ($diff >= 15)
    $pulse = ['color'=>'green',  'text'=>"Отличен ден! +{$diff}% спрямо вчера — ".number_format($rv,0,'.',' ')." лв"];
elseif ($diff <= -20 && (int)date('H') >= 14)
    $pulse = ['color'=>'red',    'text'=>"Днес е слабо — ".abs($diff)."% под вчера. Да предприемем нещо?"];
else
    $pulse = ['color'=>'green',  'text'=>number_format($rv,0,'.',' ')." лв от ".(int)$rev_t['c']." продажби днес"];

// ── CARDS ──
$cards = [];

// Свършва — всички роли
$low_rows = DB::run(
    'SELECT p.name FROM inventory i JOIN products p ON p.id=i.product_id
     WHERE i.store_id=? AND p.tenant_id=? AND i.quantity<=i.min_quantity AND i.min_quantity>0 AND p.is_active=1
     ORDER BY i.quantity ASC LIMIT 3',
    [$store_id, $tenant_id])->fetchAll();
$low_names = implode(', ', array_column($low_rows,'name'));
if (mb_strlen($low_names)>32) $low_names = mb_substr($low_names,0,30).'...';
$cards[] = ['icon'=>'⚠️','label'=>'Свършва','val'=>$low_cnt.' арт.','sub'=>$low_cnt>0?$low_names:'Всичко е наред',
    'bg'=>'rgba(239,68,68,.12)','br'=>'rgba(239,68,68,.3)','vc'=>'#fca5a5','sc'=>'rgba(252,165,165,.6)',
    'ring'=>max(0,min(100,100-$low_cnt*8)),'rc'=>'#ef4444',
    'dt'=>'Свършваща наличност','dv'=>$low_cnt.' арт. под минимума',
    'dai'=>'Зареди приоритетно. Всеки ден без наличност = директно загубени продажби.',
    'db'=>'Виж в склада','du'=>'products.php?filter=low'];

// Топ — всички роли
$top = DB::run(
    'SELECT p.name, SUM(si.quantity) AS qty FROM sale_items si
     JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
     WHERE s.store_id=? AND s.tenant_id=? AND s.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND s.status!="canceled"
     GROUP BY si.product_id ORDER BY qty DESC LIMIT 1',
    [$store_id, $tenant_id])->fetch();
$cards[] = ['icon'=>'🔥','label'=>'Топ продавани','val'=>$top?$top['name']:'Няма данни','sub'=>($top?(int)$top['qty'].' бр.':'').' / 7 дни',
    'bg'=>'rgba(34,197,94,.08)','br'=>'rgba(34,197,94,.25)','vc'=>'#86efac','sc'=>'rgba(134,239,172,.6)',
    'ring'=>85,'rc'=>'#22c55e',
    'dt'=>'Топ продавани','dv'=>($top?$top['name'].' — '.(int)$top['qty'].' бр.':'Няма данни'),
    'dai'=>'Увери се че топ артикулите никога не са на нула. Поръчай преди да свършат.',
    'db'=>'Виж в склада','du'=>'products.php'];

if (in_array($role,['owner','manager'])) {
    $gap = (int)DB::run(
        'SELECT COUNT(DISTINCT p.parent_id) FROM products p JOIN inventory i ON i.product_id=p.id
         WHERE p.tenant_id=? AND i.store_id=? AND i.quantity=0 AND p.parent_id IS NOT NULL AND p.is_active=1',
        [$tenant_id, $store_id])->fetchColumn();
    $cards[] = ['icon'=>'📏','label'=>'Липсващи размери','val'=>$gap.' продукта','sub'=>'с нулеви варианти',
        'bg'=>'rgba(245,158,11,.08)','br'=>'rgba(245,158,11,.25)','vc'=>'#fcd34d','sc'=>'rgba(252,211,77,.6)',
        'ring'=>0,'rc'=>'',
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
    $cards[] = ['icon'=>'💰','label'=>'Печалба днес','val'=>number_format($profit,0,'.',' ').' лв',
        'sub'=>$margin.'% марж · '.$ds.' vs вчера',
        'bg'=>'rgba(99,102,241,.12)','br'=>'rgba(99,102,241,.3)','vc'=>'#c7d2fe','sc'=>'rgba(199,210,254,.6)',
        'ring'=>min(100,max(0,$margin*2)),'rc'=>'#818cf8',
        'dt'=>'Печалба днес','dv'=>number_format($profit,0,'.',' ').' лв (марж '.$margin.'%)',
        'dai'=>$margin<20?'Маржът е под 20% — провери цените и отстъпките.':'Добър марж! Фокусирай се върху оборота.',
        'db'=>'Виж справките','du'=>'stats.php?tab=finance'];

    $cards[] = ['icon'=>'🧟','label'=>'Zombie стока','val'=>$zc.' арт.',
        'sub'=>'~'.number_format($zv,0,'.',' ').' лв замразени',
        'bg'=>'rgba(245,158,11,.08)','br'=>'rgba(245,158,11,.25)','vc'=>'#fcd34d','sc'=>'rgba(252,211,77,.6)',
        'ring'=>0,'rc'=>'',
        'dt'=>'Zombie стока (45+ дни)','dv'=>$zc.' арт. / '.number_format($zv,0,'.',' ').' лв',
        'dai'=>'Пусни -20% промоция. По-добре 80% от парите сега, отколкото да чакаш.',
        'db'=>'Виж zombie','du'=>'products.php?filter=zombie'];
}

// ── MESSAGES ──
$messages = DB::run(
    'SELECT role,content,created_at FROM chat_messages
     WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 30',
    [$tenant_id, $store_id])->fetchAll();

// ── CHIPS ──
$chips = match($role) {
    'owner'   => ['Печалба тази седмица','Zombie стока','Кое губи пари?','Топ доставчик'],
    'manager' => ['Какво да поръчам?','Чакащи доставки','Дневен оборот','Трансфери'],
    default   => ['Има ли размер?','Препоръчай артикул','Продадох днес','Промоции']
};

$cats = [
    'finance'   => ['icon'=>'💰','label'=>'Финанси',    'qs'=>['Оборот тази седмица?','Сравни с миналата','Коя категория печели?','Колко дължим?','Средна печалба?']],
    'stock'     => ['icon'=>'📦','label'=>'Склад',      'qs'=>['Колко артикула имам?','Zombie стока','Кои размери липсват?','Стойност на склада?','Без баркод?']],
    'suppliers' => ['icon'=>'🚚','label'=>'Доставчици', 'qs'=>['Кога идва доставка?','Поръчай от X','Чакащи поръчки?','Не е доставял 60дни?','Колко дължим на X?']],
    'clients'   => ['icon'=>'👥','label'=>'Клиенти',    'qs'=>['Топ клиент?','Нови този месец?','Купуват заедно?','Давно не са идвали?','Едрови клиенти?']],
    'ops'       => ['icon'=>'⚙️','label'=>'Операции',   'qs'=>['Пикови часове?','Продажби днес?','Кой продава най-добре?','Затвори смяната','Грешки в касата?']],
];

function parseDeeplinks(string $html): string {
    $map=['📦'=>'products.php','⚠️'=>'products.php?filter=low','📊'=>'stats.php','💰'=>'sale.php','🔄'=>'transfers.php','🛒'=>'purchase-orders.php'];
    return preg_replace_callback('/\[([^\]]+?)→\]/u',function($m)use($map){
        $t=trim($m[1]);$h='#';
        foreach($map as $e=>$u){if(mb_strpos($t,$e)!==false){$h=$u;break;}}
        return '<a class="deeplink" href="'.$h.'">'.htmlspecialchars($t).' →</a>';
    },$html);
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
:root{
    --bg-main:#030712;--bg-card:rgba(15,15,40,0.75);
    --border-subtle:rgba(99,102,241,0.15);--border-glow:rgba(99,102,241,0.4);
    --indigo-600:#4f46e5;--indigo-500:#6366f1;--indigo-400:#818cf8;--indigo-300:#a5b4fc;
    --text-primary:#f1f5f9;--text-secondary:#6b7280;
    --danger:#ef4444;--warning:#f59e0b;--success:#22c55e;--nav-h:64px
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
html{background:var(--bg-main)}
body{background:var(--bg-main);color:var(--text-primary);font-family:'Montserrat',Inter,system-ui,sans-serif;
    height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav-h)}
body::before{content:'';position:fixed;top:-200px;left:50%;transform:translateX(-50%);
    width:600px;height:350px;background:radial-gradient(ellipse,rgba(99,102,241,.08) 0%,transparent 70%);
    pointer-events:none;z-index:0}

/* HEADER */
.hdr{flex-shrink:0;padding:13px 14px 7px;display:flex;align-items:center;justify-content:space-between;position:relative;z-index:10;gap:8px}
.brand{font-size:17px;font-weight:700;background:linear-gradient(135deg,#f1f5f9,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-.5px;flex-shrink:0}
.store-pill{font-size:10px;font-weight:600;color:#818cf8;background:rgba(99,102,241,.15);border:.5px solid rgba(99,102,241,.3);border-radius:20px;padding:3px 9px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hdr-btns{display:flex;gap:5px;flex-shrink:0}
.hdr-btn{width:28px;height:28px;border-radius:9px;background:rgba(255,255,255,.05);border:.5px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--indigo-300);position:relative;flex-shrink:0;transition:background .2s}
.hdr-btn:active{background:rgba(99,102,241,.3)}
.hdr-badge{position:absolute;top:-3px;right:-3px;min-width:14px;height:14px;border-radius:7px;background:#ef4444;font-size:8px;font-weight:800;color:#fff;display:flex;align-items:center;justify-content:center;padding:0 3px}

/* PULSE */
.pulse-wrap{margin:0 12px 8px;padding:8px 11px;border-radius:12px;display:flex;align-items:center;gap:9px;cursor:pointer;flex-shrink:0;transition:transform .15s}
.pulse-wrap:active{transform:scale(.98)}
.pulse-wrap.red{background:rgba(239,68,68,.1);border:.5px solid rgba(239,68,68,.3)}
.pulse-wrap.yellow{background:rgba(245,158,11,.1);border:.5px solid rgba(245,158,11,.3)}
.pulse-wrap.green{background:rgba(34,197,94,.08);border:.5px solid rgba(34,197,94,.25)}
.pulse-orbit{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.pulse-wrap.red .pulse-orbit{border:1.5px solid rgba(239,68,68,.6);animation:orbitPulse 2s ease-in-out infinite;box-shadow:0 0 8px rgba(239,68,68,.2)}
.pulse-wrap.yellow .pulse-orbit{border:1.5px solid rgba(245,158,11,.6);animation:orbitPulse 2s ease-in-out infinite;box-shadow:0 0 8px rgba(245,158,11,.2)}
.pulse-wrap.green .pulse-orbit{border:1.5px solid rgba(34,197,94,.5);animation:orbitPulse 2s ease-in-out infinite;box-shadow:0 0 8px rgba(34,197,94,.15)}
.pulse-dot{width:7px;height:7px;border-radius:50%;animation:dotPulse 2s ease-in-out infinite}
.pulse-wrap.red .pulse-dot{background:#ef4444;box-shadow:0 0 6px #ef4444}
.pulse-wrap.yellow .pulse-dot{background:#f59e0b;box-shadow:0 0 6px #f59e0b}
.pulse-wrap.green .pulse-dot{background:#22c55e;box-shadow:0 0 6px #22c55e}
.pulse-text{font-size:11.5px;flex:1;line-height:1.4}
.pulse-wrap.red .pulse-text{color:#fca5a5}
.pulse-wrap.yellow .pulse-text{color:#fcd34d}
.pulse-wrap.green .pulse-text{color:#86efac}

/* CARDS — 30% smaller */
.cards-grid{padding:0 12px;display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px;flex-shrink:0}
.card{border-radius:11px;padding:8px 10px;cursor:pointer;position:relative;overflow:hidden;
    perspective:600px;transition:box-shadow .3s}
.card-inner{position:relative;width:100%;transform-style:preserve-3d;transition:transform .5s cubic-bezier(.175,.885,.32,1.275)}
.card:hover .card-inner,.card:active .card-inner{transform:rotateY(4deg) rotateX(-2deg)}
.card-face{backface-visibility:hidden}
.card-glow{position:absolute;inset:0;border-radius:inherit;opacity:0;transition:opacity .3s;pointer-events:none}
.card:active .card-glow{opacity:1}
.card-icon{font-size:13px;margin-bottom:2px}
.card-label{font-size:9px;color:rgba(255,255,255,.35);margin-bottom:2px;letter-spacing:.3px}
.card-val{font-size:13px;font-weight:600;line-height:1.2}
.card-sub{font-size:9px;margin-top:2px;opacity:.65;line-height:1.3}
.card-ring{position:absolute;top:6px;right:6px;width:18px;height:18px}
.ring-svg{transform:rotate(-90deg)}
.ring-bg{fill:none;stroke:rgba(255,255,255,.06);stroke-width:2.5}
.ring-fill{fill:none;stroke-width:2.5;stroke-linecap:round}
.zombie-bar{margin-top:4px;display:flex;gap:2px;height:3px;border-radius:2px;overflow:hidden}
.zb-seg{height:100%;border-radius:2px}

/* CHIPS */
.chips-wrap{padding:0 12px 5px;flex-shrink:0}
.chips-row{display:flex;gap:5px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding-bottom:2px}
.chips-row::-webkit-scrollbar{display:none}
.chip{font-size:10.5px;padding:4px 10px;border-radius:18px;border:.5px solid rgba(99,102,241,.3);
    color:var(--indigo-300);background:rgba(99,102,241,.1);white-space:nowrap;cursor:pointer;flex-shrink:0;
    opacity:0;animation:chipIn .3s ease forwards;font-family:inherit;transition:background .15s,transform .1s}
.chip:active{background:rgba(99,102,241,.35);transform:scale(.95)}
.chip-more{border-color:rgba(99,102,241,.2);color:#6b7280;background:transparent}

/* EXPANDED */
.exp-wrap{padding:0 12px 5px;flex-shrink:0;display:none}
.exp-grid{background:rgba(255,255,255,.03);border:.5px solid rgba(99,102,241,.18);border-radius:12px;overflow:hidden}
.exp-cats{display:grid;grid-template-columns:repeat(5,1fr);border-bottom:.5px solid rgba(99,102,241,.12)}
.exp-cat{padding:7px 2px;text-align:center;cursor:pointer;transition:background .15s}
.exp-cat.act{background:rgba(99,102,241,.2)}
.exp-cat:active{background:rgba(99,102,241,.3)}
.exp-cat-icon{font-size:12px}
.exp-cat-label{font-size:8px;color:#6b7280;margin-top:1px}
.exp-qs{padding:4px 10px}
.exp-q{padding:5px 0;border-bottom:.5px solid rgba(99,102,241,.08);font-size:11px;color:var(--indigo-300);
    cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:color .15s}
.exp-q:last-child{border-bottom:none}
.exp-q:active{color:#fff}

/* CHAT */
.chat-area{flex:1;overflow-y:auto;overflow-x:hidden;padding:6px 14px 0;display:flex;flex-direction:column;
    -webkit-overflow-scrolling:touch;scrollbar-width:none;min-height:0;position:relative;z-index:1}
.chat-area::-webkit-scrollbar{display:none}
.msg-group{margin-bottom:10px;animation:cardIn .3s ease both}
.msg-meta{font-size:10px;color:#4b5563;margin-bottom:3px;display:flex;align-items:center;gap:5px}
.msg-meta.right{justify-content:flex-end}
.ai-ava{width:18px;height:18px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ai-bars{display:flex;gap:2px;align-items:center;height:8px}
.ai-bar{width:2px;border-radius:1px;background:#fff}
.msg{max-width:88%;padding:9px 12px;font-size:13px;line-height:1.5;word-break:break-word}
.msg.ai{background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.2);color:var(--text-primary);border-radius:4px 14px 14px 14px}
.msg.user{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-radius:14px 14px 4px 14px;margin-left:auto;border:.5px solid rgba(255,255,255,.1)}
.msg a.deeplink{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;background:rgba(0,0,0,.2);border:.5px solid rgba(165,180,252,.3);border-radius:9px;color:#c7d2fe;font-size:11px;font-weight:600;text-decoration:none;margin:4px 2px 0}
.msg a.deeplink:active{background:rgba(99,102,241,.4)}
.typing-wrap{display:none;padding:9px 12px;background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.2);border-radius:4px 14px 14px 14px;width:fit-content;margin-bottom:10px}
.typing-dots{display:flex;gap:4px;align-items:center}
.dot{width:5px;height:5px;border-radius:50%;background:#818cf8;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}
.welcome{text-align:center;padding:20px 20px 12px;color:#6b7280;font-size:13px;line-height:1.6}
.welcome-title{font-size:20px;font-weight:700;margin-bottom:6px;background:linear-gradient(135deg,#e5e7eb,#c7d2fe);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:shimmer 6s linear infinite}

/* INPUT */
.input-area{padding:5px 12px 10px;flex-shrink:0;position:relative;z-index:10}
.input-row{display:flex;gap:7px;align-items:center;background:rgba(10,14,28,.9);border-radius:26px;
    padding:4px 4px 4px 12px;border:.5px solid rgba(99,102,241,.2);animation:breathe 3s ease-in-out infinite}
.text-input{flex:1;background:transparent;border:none;color:var(--text-primary);font-size:13px;padding:8px 0;font-family:inherit;outline:none;resize:none;max-height:70px;line-height:1.4}
.text-input::placeholder{color:#374151}
.voice-btn{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;cursor:pointer;
    box-shadow:0 0 12px rgba(99,102,241,.3);transition:box-shadow .3s}
.voice-btn.rec{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 0 16px rgba(239,68,68,.5)}
.send-btn{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.08);border:.5px solid rgba(255,255,255,.1);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s}
.send-btn:disabled{opacity:.2}

/* VOICE OVERLAY — sale.php стил */
.rec-ov{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,.6);backdrop-filter:blur(8px);
    display:none;align-items:flex-end;justify-content:center;padding:0 16px 80px}
.rec-ov.open{display:flex}
.rec-box{width:100%;max-width:400px;background:rgba(15,15,40,.95);border:1px solid var(--border-glow);
    border-radius:20px;padding:18px;box-shadow:0 -12px 50px rgba(99,102,241,.25),0 0 40px rgba(0,0,0,.5);
    animation:recSlideUp .25s ease}
.rec-status{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.rec-dot{width:14px;height:14px;border-radius:50%;background:#ef4444;flex-shrink:0;
    box-shadow:0 0 10px #ef4444,0 0 20px rgba(239,68,68,.4);animation:recPulse 1s ease infinite}
.rec-dot.ready{background:#22c55e;box-shadow:0 0 10px #22c55e,0 0 20px rgba(34,197,94,.4);animation:none}
.rec-label{font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px}
.rec-label.recording{color:#ef4444}.rec-label.ready{color:#22c55e}
.rec-transcript{min-height:40px;padding:9px 12px;margin-bottom:10px;
    background:rgba(99,102,241,.06);border:1px solid var(--border-subtle);
    border-radius:11px;font-size:14px;font-weight:500;color:var(--text-primary);line-height:1.4;word-wrap:break-word}
.rec-transcript.empty{color:var(--text-secondary);font-style:italic}
.rec-hint{font-size:11px;color:var(--text-secondary);margin-bottom:12px;text-align:center;line-height:1.4}
.rec-close-hint{font-size:10px;color:rgba(107,114,128,.6);text-align:center;margin-top:8px}
.rec-actions{display:flex;gap:8px}
.rec-btn-cancel{flex:1;height:40px;border-radius:11px;border:.5px solid var(--border-subtle);
    background:var(--bg-card);color:var(--indigo-300);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.rec-btn-send{flex:2;height:40px;border-radius:11px;border:none;
    background:linear-gradient(135deg,var(--indigo-600),var(--indigo-500));
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
    box-shadow:0 4px 14px rgba(99,102,241,.35)}
.rec-btn-send:disabled{opacity:.3;pointer-events:none}

/* DRAWER */
.drawer-ovl{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);
    z-index:200;display:none;align-items:flex-end}
.drawer-ovl.show{display:flex}
.drawer-close-hint{font-size:10px;color:rgba(107,114,128,.5);text-align:center;padding:6px 0 0}
.drawer-box{width:100%;background:#080818;border-top:.5px solid var(--border-glow);
    border-radius:22px 22px 0 0;padding:0 16px 32px;
    transform:translateY(100%);transition:transform .3s cubic-bezier(.32,0,.67,0);
    max-height:72vh;overflow-y:auto}
.drawer-ovl.show .drawer-box{transform:translateY(0)}
.drawer-handle{width:30px;height:3px;background:rgba(99,102,241,.3);border-radius:2px;margin:11px auto 14px}
.drawer-title{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.drawer-val{font-size:24px;font-weight:600;background:linear-gradient(135deg,#a5b4fc,#6366f1);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.2;margin-bottom:11px}
.ai-rec-box{background:rgba(99,102,241,.08);border:.5px solid rgba(99,102,241,.2);border-radius:11px;padding:10px;margin-bottom:11px}
.ai-rec-label{font-size:10px;color:#6366f1;letter-spacing:.8px;margin-bottom:4px;text-transform:uppercase}
.ai-rec-text{font-size:12px;color:var(--text-primary);line-height:1.5}
.drawer-btn{display:block;width:100%;padding:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);
    border:none;border-radius:12px;color:#fff;font-size:13px;font-weight:600;text-align:center;cursor:pointer;text-decoration:none}

/* HELP MODAL */
.help-ovl{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:300;display:none;align-items:flex-end}
.help-ovl.show{display:flex}
.help-box{width:100%;background:#080818;border-top:.5px solid var(--border-glow);border-radius:22px 22px 0 0;padding:0 16px 32px;max-height:80vh;overflow-y:auto}
.help-handle{width:30px;height:3px;background:rgba(99,102,241,.3);border-radius:2px;margin:11px auto 14px}
.help-title{font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:14px;background:linear-gradient(135deg,#f1f5f9,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.help-section{margin-bottom:14px;background:rgba(99,102,241,.06);border:.5px solid rgba(99,102,241,.15);border-radius:12px;padding:12px}
.help-section h4{font-size:12px;font-weight:700;color:var(--indigo-300);margin-bottom:6px}
.help-section p{font-size:12px;color:var(--text-secondary);line-height:1.6}
.help-section .help-example{font-size:11px;color:#c7d2fe;background:rgba(99,102,241,.1);border-radius:8px;padding:6px 9px;margin-top:6px}

/* BOTTOM NAV */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(3,7,18,.95);
    backdrop-filter:blur(15px);border-top:.5px solid rgba(99,102,241,.2);display:flex;z-index:100;
    box-shadow:0 -4px 20px rgba(99,102,241,.1)}
.btab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
    font-size:9px;font-weight:600;color:rgba(165,180,252,.4);text-decoration:none;transition:all .3s}
.btab.active{color:#c7d2fe;text-shadow:0 0 10px rgba(129,140,248,.8)}
.btab-icon{font-size:17px;transition:all .3s}
.btab.active .btab-icon{transform:translateY(-2px);filter:drop-shadow(0 0 7px rgba(129,140,248,.8))}

/* TOAST */
.toast{position:fixed;bottom:75px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#4f46e5,#7c3aed);
    color:#fff;padding:8px 18px;border-radius:22px;font-size:12px;font-weight:600;z-index:500;
    opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}

/* ANIMATIONS */
@keyframes orbitPulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.3)}50%{box-shadow:0 0 0 4px rgba(99,102,241,0)}}
@keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.2)}}
@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes flipIn{from{opacity:0;transform:perspective(400px) rotateX(-20deg) translateY(10px)}to{opacity:1;transform:perspective(400px) rotateX(0) translateY(0)}}
@keyframes chipIn{to{opacity:1;transform:translateY(0)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes recPulse{0%,100%{opacity:1;box-shadow:0 0 8px #ef4444,0 0 16px rgba(239,68,68,.3)}50%{opacity:.5;box-shadow:0 0 20px #ef4444,0 0 40px rgba(239,68,68,.6)}}
@keyframes recSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}
@keyframes breathe{0%,100%{border-color:rgba(99,102,241,.2)}50%{border-color:rgba(99,102,241,.45);box-shadow:0 0 10px rgba(99,102,241,.08)}}
@keyframes glowPulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.0)}50%{box-shadow:0 0 12px 2px rgba(99,102,241,.15)}}
</style>
</head>
<body>

<!-- HEADER -->
<div class="hdr">
  <div class="brand">RunMyStore.ai</div>
  <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
  <div class="hdr-btns">
    <div class="hdr-btn" onclick="showHelp()" title="Помощ">
      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
    </div>
    <div class="hdr-btn" onclick="fillAndSend('Покажи всички нотификации')">
      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php if ($unread > 0): ?><div class="hdr-badge"><?= $unread ?></div><?php endif; ?>
    </div>
  </div>
</div>

<!-- PULSE -->
<div class="pulse-wrap <?= $pulse['color'] ?>" id="pulseWrap" onclick="openDrawer('pulse')">
  <div class="pulse-orbit"><div class="pulse-dot"></div></div>
  <div class="pulse-text" id="pulseText"></div>
  <span style="font-size:12px;opacity:.4;color:inherit">›</span>
</div>

<!-- CARDS -->
<div class="cards-grid">
<?php foreach ($cards as $i => $c):
    $delay = number_format($i * 0.07, 2);
    $total = count($cards);
    $span  = ($total >= 5 && $i === $total-1) ? 'grid-column:1/-1;' : '';
    $circ  = 2 * M_PI * 9;
    $off   = $c['ring'] > 0 ? $circ - ($c['ring']/100)*$circ : $circ;
    $ring_html = '';
    if ($c['ring'] > 0 && $c['rc']) {
        $ring_html = '<div class="card-ring"><svg class="ring-svg" viewBox="0 0 24 24" width="18" height="18">
            <circle class="ring-bg" cx="12" cy="12" r="9"/>
            <circle class="ring-fill" cx="12" cy="12" r="9" stroke="'.$c['rc'].'" stroke-dasharray="'.$circ.'" stroke-dashoffset="'.$circ.'" data-target="'.round($off,2).'"/>
        </svg></div>';
    }
    $z_html = '';
    if ($c['icon']==='🧟') $z_html = '<div class="zombie-bar"><div class="zb-seg" style="width:0;background:#ef4444;transition:width 1.3s .4s cubic-bezier(.34,1.56,.64,1)" data-w="35"></div><div class="zb-seg" style="width:0;background:#f59e0b;transition:width 1.5s .6s cubic-bezier(.34,1.56,.64,1)" data-w="22"></div><div class="zb-seg" style="flex:1;background:rgba(255,255,255,.05)"></div></div>';
    $drawer_data = htmlspecialchars(json_encode(['title'=>$c['dt'],'val'=>$c['dv'],'ai'=>$c['dai'],'btn'=>$c['db'],'url'=>$c['du']]),ENT_QUOTES);
?>
<div class="card" style="<?= $span ?>background:<?= $c['bg'] ?>;border:.5px solid <?= $c['br'] ?>;
     animation:flipIn .4s <?= $delay ?>s ease both;box-shadow:0 0 0 0 <?= $c['br'] ?>;animation-fill-mode:both"
     data-drawer="<?= $drawer_data ?>" onclick="openCardDrawer(this)">
  <div class="card-inner">
    <div class="card-face">
      <?= $ring_html ?>
      <div class="card-icon"><?= $c['icon'] ?></div>
      <div class="card-label"><?= htmlspecialchars($c['label']) ?></div>
      <div class="card-val" style="color:<?= $c['vc'] ?>"><?= htmlspecialchars($c['val']) ?></div>
      <div class="card-sub" style="color:<?= $c['sc'] ?>"><?= htmlspecialchars($c['sub']) ?></div>
      <?= $z_html ?>
    </div>
    <div class="card-glow" style="background:radial-gradient(circle,<?= $c['br'] ?> 0%,transparent 70%)"></div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- CHIPS -->
<div class="chips-wrap">
  <div class="chips-row">
    <?php foreach ($chips as $i => $ch): ?>
    <div class="chip" style="animation-delay:<?= number_format($i*0.06,2) ?>s"
         onclick="fillAndSend(this.dataset.q)" data-q="<?= htmlspecialchars($ch,ENT_QUOTES) ?>"><?= htmlspecialchars($ch) ?></div>
    <?php endforeach; ?>
    <div class="chip chip-more" style="animation-delay:<?= number_format(count($chips)*0.06,2) ?>s"
         onclick="toggleExp()" id="moreChip">Още ▾</div>
  </div>
</div>

<!-- EXPANDED -->
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
    <?php if ($msg['role']==='assistant'): ?>
      <div class="msg-meta">
        <div class="ai-ava"><div class="ai-bars">
          <?php for($b=0;$b<4;$b++):$h=[4,8,10,6][$b];?>
          <div class="ai-bar" style="height:<?=$h?>px;animation:barDance <?=.7+$b*.1?>s <?=$b*.15?>s ease-in-out infinite"></div>
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

<!-- INPUT -->
<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1"
      oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,70)+'px';btnSend.disabled=!this.value.trim()"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}"></textarea>
    <div class="voice-btn" id="voiceBtn" onclick="toggleVoice()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
    </div>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>

<!-- VOICE OVERLAY — sale.php стил -->
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

<!-- DRAWER -->
<div class="drawer-ovl" id="drawerOvl" onclick="closeDrawer()">
  <div class="drawer-box" onclick="event.stopPropagation()">
    <div class="drawer-handle"></div>
    <div id="drawerBody"></div>
    <div class="drawer-close-hint">← Назад или натисни извън прозореца за затваряне</div>
  </div>
</div>

<!-- HELP MODAL -->
<div class="help-ovl" id="helpOvl" onclick="closeHelp()">
  <div class="help-box" onclick="event.stopPropagation()">
    <div class="help-handle"></div>
    <div class="help-title">✦ Как работи AI Dashboard</div>
    <div class="help-section">
      <h4>🔴 Pulse Radar — горната лента</h4>
      <p>Показва най-важното от магазина в реално време. Цветът сигнализира статуса.</p>
      <div class="help-example">🟢 Зелен = всичко е наред · 🟡 Жълт = внимание · 🔴 Червен = спешно</div>
    </div>
    <div class="help-section">
      <h4>📊 Информационните карти</h4>
      <p>Показват реални данни от магазина. Натисни карта за повече детайли и AI препоръка.</p>
    </div>
    <div class="help-section">
      <h4>💬 Бързи въпроси (chips)</h4>
      <p>Натисни chip за да зададеш въпроса директно. "Още ▾" показва 5 категории с по 5 въпроса.</p>
      <div class="help-example">Примери: "Zombie стока", "Колко дължим?", "Кога идва доставка?"</div>
    </div>
    <div class="help-section">
      <h4>🎤 Гласов вход</h4>
      <p>Натисни микрофона, говори на български, след това натисни "Изпрати →". AI разбира разговорен и диалектен език.</p>
      <div class="help-example">"Кво става", "якеца" = якета, "маратонки" = обувки</div>
    </div>
    <button class="drawer-btn" onclick="closeHelp()" style="margin-top:4px">Разбрах ✓</button>
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
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');
const chatArea  = document.getElementById('chatArea');

// ── PULSE TYPEWRITER ──────────────────────────────────────────
(function(){
    const txt = <?= json_encode($pulse['text'], JSON_UNESCAPED_UNICODE) ?>;
    const el  = document.getElementById('pulseText');
    let i = 0;
    function t(){ if(i<txt.length){el.textContent+=txt[i++];setTimeout(t,26);} }
    t();
})();

// ── CARDS ANIMATION ───────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    // Counter за card-val
    document.querySelectorAll('.card-val').forEach(el => {
        const m = el.textContent.match(/(\d[\d\s]*)/);
        if (!m) return;
        const target = parseInt(m[1].replace(/\s/g,''));
        if (target < 2) return;
        const orig = el.textContent;
        const start = performance.now();
        const run = now => {
            const p = Math.min((now-start)/900,1);
            const e = 1-Math.pow(1-p,3);
            el.textContent = orig.replace(m[1], Math.round(target*e).toLocaleString('bg-BG'));
            if (p<1) requestAnimationFrame(run);
        };
        setTimeout(()=>requestAnimationFrame(run), 200);
    });

    // Rings
    document.querySelectorAll('.ring-fill[data-target]').forEach(el => {
        setTimeout(()=>{
            el.style.transition='stroke-dashoffset 1.4s cubic-bezier(.34,1.56,.64,1)';
            el.style.strokeDashoffset = el.dataset.target;
        }, 500);
    });

    // Zombie bars
    document.querySelectorAll('.zb-seg[data-w]').forEach(el => {
        setTimeout(()=>{ el.style.width = el.dataset.w + '%'; }, 400);
    });

    // Card glow on load
    document.querySelectorAll('.card').forEach((card, i) => {
        setTimeout(() => {
            card.style.animation += `,glowPulse 2s ${i*0.3}s ease-in-out`;
        }, 600 + i*100);
    });

    scrollBottom();
});

// ── EXPANDED ──────────────────────────────────────────────────
let expOpen = false, activeCat = null;
const CATS = <?= json_encode($cats, JSON_UNESCAPED_UNICODE) ?>;

function toggleExp() {
    expOpen = !expOpen;
    const el = document.getElementById('expWrap');
    el.style.display = expOpen ? 'block' : 'none';
    document.getElementById('moreChip').textContent = expOpen ? 'По-малко ▴' : 'Още ▾';
    if (expOpen && !activeCat) showCat('finance');
}

function showCat(cat) {
    activeCat = cat;
    Object.keys(CATS).forEach(k => {
        const el = document.getElementById('ecat-'+k);
        if (el) el.className = 'exp-cat'+(k===cat?' act':'');
    });
    const qs = CATS[cat]?.qs || [];
    document.getElementById('expQs').innerHTML = qs.map(q =>
        `<div class="exp-q" onclick="fillAndSend(this.dataset.q)" data-q="${esc(q)}"><span>${esc(q)}</span><span style="color:#374151;font-size:10px">›</span></div>`
    ).join('');
}

// ── DRAWER ────────────────────────────────────────────────────
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

// BACK BUTTON — затваря drawer/help вместо да излиза
window.addEventListener('popstate', e => {
    const drawer = document.getElementById('drawerOvl');
    const help   = document.getElementById('helpOvl');
    if (drawer.classList.contains('show')) { closeDrawer(); return; }
    if (help.classList.contains('show'))   { closeHelp();   return; }
});

// Swipe down to close drawer
let tsY = 0;
document.querySelector('.drawer-box').addEventListener('touchstart', e=>{ tsY=e.touches[0].clientY; });
document.querySelector('.drawer-box').addEventListener('touchend',   e=>{ if(e.changedTouches[0].clientY-tsY>60) closeDrawer(); });

// ── CHAT ──────────────────────────────────────────────────────
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
    // Затвори expanded ако е отворен
    if (expOpen) {
        expOpen = false;
        document.getElementById('expWrap').style.display = 'none';
        document.getElementById('moreChip').textContent = 'Още ▾';
    }
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
    const bars = [4,8,10,6].map((h,i)=>`<div class="ai-bar" style="height:${h}px;animation:barDance ${.7+i*.1}s ${i*.15}s ease-in-out infinite"></div>`).join('');
    const parsed = parseDeeplinksJS(esc(text).replace(/\n/g,'<br>'));
    g.innerHTML=`<div class="msg-meta"><div class="ai-ava"><div class="ai-bars">${bars}</div></div> AI</div><div class="msg ai">${parsed}</div>`;
    chatArea.insertBefore(g,typing); scrollBottom();
}

function scrollBottom(){ chatArea.scrollTop = chatArea.scrollHeight; }
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── VOICE ─────────────────────────────────────────────────────
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

// Back button затваря voice
window.addEventListener('popstate', e => {
    if (document.getElementById('recOv').classList.contains('open')) { stopVoice(); return; }
});

// Toast
function showToast(msg) {
    const t=document.getElementById('toast');
    t.textContent=msg; t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),3000);
}
</script>
</body>
</html>
