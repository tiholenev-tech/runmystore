<?php
// chat.php — AI First, Champagne Edition
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';

$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id]
)->fetchAll();

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';

$unread = DB::run(
    'SELECT COUNT(*) as cnt FROM store_messages WHERE tenant_id = ? AND to_store_id = ? AND is_read = 0',
    [$tenant_id, $store_id]
)->fetch();
$unread_count = $unread ? (int)$unread['cnt'] : 0;

$notifications = DB::run(
    'SELECT type, title, message FROM notifications WHERE tenant_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 3',
    [$tenant_id]
)->fetchAll();

$quick_cmds = [
    ['icon' => '📦', 'label' => 'Склад',      'msg' => 'Покажи склада'],
    ['icon' => '💰', 'label' => 'Продажби',   'msg' => 'Колко продадох днес?'],
    ['icon' => '⚠️', 'label' => 'Ниска нал.', 'msg' => 'Кои артикули са под минимума?'],
];
if (in_array($role, ['owner','manager'])) {
    $quick_cmds[] = ['icon' => '🚚', 'label' => 'Доставка', 'msg' => 'Нова доставка'];
    $quick_cmds[] = ['icon' => '🔄', 'label' => 'Трансфер', 'msg' => 'Направи трансфер'];
}
if ($role === 'owner') {
    $quick_cmds[] = ['icon' => '📊', 'label' => 'Печалба', 'msg' => 'Каква е печалбата ми днес?'];
}
$quick_cmds[] = ['icon' => '🎁', 'label' => 'Лоялна', 'msg' => 'Лоялна програма'];

function notif_icon($type) {
    $map = ['low_stock'=>'⚠️','out_stock'=>'🔴','delivery'=>'📦','transfer'=>'🔄','sale_spike'=>'🎉','debt'=>'💳'];
    return $map[$type] ?? '🔔';
}
function notif_class($type) {
    if (in_array($type, ['out_stock','debt'])) return 'danger';
    if (in_array($type, ['low_stock','transfer'])) return 'warning';
    return 'success';
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>RunMyStore.ai</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
:root{--nav-h:64px}
*,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
body{background:#EDE8DC;color:#292524;font-family:'Montserrat',sans-serif;height:100dvh;display:flex;flex-direction:column;overflow:hidden;padding-bottom:var(--nav-h)}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 50%,rgba(218,165,32,.05) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(197,160,89,.06) 0%,transparent 45%),radial-gradient(circle at 50% 85%,rgba(230,194,122,.07) 0%,transparent 40%);pointer-events:none;z-index:0}

/* HEADER */
.hdr{position:relative;z-index:50;background:rgba(237,232,220,.92);backdrop-filter:blur(24px);border-bottom:1px solid rgba(210,193,164,.7);padding:12px 16px 0;flex-shrink:0}
.hdr-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px}
.brand{font-size:18px;font-weight:900;flex:1;background:linear-gradient(to right,#A67C00,#E6C27A,#A67C00);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.store-pill{font-size:11px;font-weight:700;color:#A67C00;background:rgba(212,175,55,.1);border:1px solid rgba(212,175,55,.25);border-radius:20px;padding:4px 10px;max-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hdr-icon{width:34px;height:34px;border-radius:10px;background:rgba(212,175,55,.1);border:1px solid rgba(212,175,55,.2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#A67C00;position:relative;flex-shrink:0}
.hdr-badge{position:absolute;top:-4px;right:-4px;width:16px;height:16px;border-radius:50%;background:#ef4444;font-size:9px;font-weight:800;color:#fff;display:flex;align-items:center;justify-content:center}
.tabs{display:flex}
.tab{flex:1;padding:8px 4px;font-size:13px;font-weight:700;color:#A8A29E;text-align:center;border-bottom:2px solid transparent;cursor:pointer;text-decoration:none;transition:all .2s;display:block}
.tab.active{color:#A67C00;border-bottom-color:#D4AF37}
.tab-badge{display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;border-radius:8px;background:#ef4444;font-size:9px;font-weight:800;color:#fff;margin-left:5px;padding:0 3px}

/* CHAT AREA */
.chat-area{flex:1;overflow-y:auto;overflow-x:hidden;padding:14px 12px 8px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.chat-area::-webkit-scrollbar{display:none}

/* NOTIFICATION CARDS */
.pro-wrap{margin-bottom:14px}
.pro-label{font-size:10px;font-weight:700;color:#A67C00;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.pro-card{border-radius:14px;padding:11px 13px;margin-bottom:7px;display:flex;align-items:flex-start;gap:10px;animation:slideIn .35s ease both;position:relative;overflow:hidden}
.pro-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px}
.pro-card.danger{background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15)}
.pro-card.danger::before{background:#ef4444}
.pro-card.warning{background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15)}
.pro-card.warning::before{background:#f59e0b}
.pro-card.success{background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.15)}
.pro-card.success::before{background:#22c55e}
.pro-icon{font-size:18px;flex-shrink:0;margin-top:1px}
.pro-body{flex:1;min-width:0}
.pro-title{font-size:12px;font-weight:700;color:#292524;margin-bottom:2px}
.pro-sub{font-size:11px;color:#78716C;line-height:1.4;margin-bottom:7px}
.pro-action{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:700;background:rgba(212,175,55,.1);border:1px solid rgba(212,175,55,.25);color:#A67C00;cursor:pointer;font-family:inherit}
.pro-close{position:absolute;top:7px;right:8px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,.04);border:none;color:#A8A29E;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center}

/* MESSAGES */
.msg-group{margin-bottom:12px;animation:fadeUp .3s ease both}
.msg-meta{font-size:10px;color:#A8A29E;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.msg-meta.right{justify-content:flex-end}
.ai-ava{width:26px;height:26px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#D4AF37,#F3E5AB);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(212,175,55,.3)}
.ai-ava-bars{display:flex;gap:2px;align-items:center;height:11px}
.ai-ava-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.ai-ava-bar:nth-child(1){height:4px}.ai-ava-bar:nth-child(2){height:8px;animation-delay:.15s}.ai-ava-bar:nth-child(3){height:11px;animation-delay:.3s}.ai-ava-bar:nth-child(4){height:6px;animation-delay:.45s}
.msg{max-width:85%;padding:10px 13px;font-size:13px;line-height:1.55;word-break:break-word}
.msg.ai{background:#FAF7F0;border:1px solid #E6D5B8;color:#292524;border-radius:4px 16px 16px 16px}
.msg.user{background:linear-gradient(135deg,#C5A059,#E6C27A);color:#fff;border-radius:16px 16px 4px 16px;margin-left:auto;box-shadow:0 4px 12px rgba(197,160,89,.2)}

.typing-wrap{display:none;padding:10px 13px;background:#FAF7F0;border:1px solid #E6D5B8;border-radius:4px 16px 16px 16px;width:fit-content;margin-bottom:12px}
.typing-dots{display:flex;gap:4px;align-items:center}
.dot{width:7px;height:7px;border-radius:50%;background:#D4AF37;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}

.welcome{text-align:center;padding:30px 20px 10px;color:#78716C;font-size:13px}
.welcome-title{font-size:20px;font-weight:900;margin-bottom:6px;background:linear-gradient(to right,#A67C00,#E6C27A,#A67C00);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}

/* QUICK COMMANDS */
.quick-wrap{padding:0 12px 8px;flex-shrink:0;position:relative;z-index:1}
.quick-row{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none}
.quick-row::-webkit-scrollbar{display:none}
.quick-btn{flex-shrink:0;padding:7px 13px;border-radius:20px;font-size:12px;font-weight:700;border:1px solid #E6D5B8;color:#78716C;background:#ffffff;cursor:pointer;font-family:inherit;transition:all .2s;white-space:nowrap;display:flex;align-items:center;gap:5px}
.quick-btn:active{background:#FAF7F0;color:#A67C00;border-color:#D4AF37}

/* INPUT */
.input-area{background:rgba(237,232,220,.92);backdrop-filter:blur(24px);border-top:1px solid rgba(210,193,164,.7);padding:10px 12px 14px;flex-shrink:0;position:relative;z-index:1}
.input-row{display:flex;gap:8px;align-items:center}
.text-input{flex:1;background:#FAF7F0;border:1.5px solid #E6D5B8;border-radius:22px;color:#292524;font-size:14px;padding:11px 16px;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4;transition:all .2s}
.text-input:focus{border-color:#C5A059;background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.1)}
.text-input::placeholder{color:#A8A29E}

/* VOICE */
.voice-wrap{position:relative;flex-shrink:0;width:56px;height:56px;cursor:pointer}
.voice-ring{position:absolute;border-radius:50%;border:1px solid rgba(212,175,55,.35);animation:waveOut 2s ease-out infinite;pointer-events:none}
.voice-ring:nth-child(1){inset:-5px}.voice-ring:nth-child(2){inset:-11px;animation-delay:.55s}.voice-ring:nth-child(3){inset:-17px;animation-delay:1.1s}
.voice-inner{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#D4AF37,#E6C27A);display:flex;align-items:center;justify-content:center;position:relative;z-index:1;box-shadow:0 4px 18px rgba(212,175,55,.45);transition:all .2s}
.voice-bars{display:flex;gap:3px;align-items:center;height:20px}
.voice-bar{width:3px;border-radius:2px;background:#fff;animation:barDance 1s ease-in-out infinite}
.voice-bar:nth-child(1){height:8px}.voice-bar:nth-child(2){height:16px;animation-delay:.15s}.voice-bar:nth-child(3){height:20px;animation-delay:.3s}.voice-bar:nth-child(4){height:12px;animation-delay:.45s}.voice-bar:nth-child(5){height:8px;animation-delay:.6s}
.voice-wrap.recording .voice-inner{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 22px rgba(239,68,68,.55)}
.voice-wrap.recording .voice-ring{border-color:rgba(239,68,68,.35)}

.send-btn{width:42px;height:42px;border-radius:50%;background:#FAF7F0;border:1px solid #E6D5B8;color:#A67C00;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-btn:active{background:#F3E5AB;transform:scale(.92)}
.send-btn:disabled{opacity:.3;cursor:default}

/* REC OVERLAY */
.rec-overlay{position:fixed;inset:0;background:rgba(237,232,220,.93);z-index:400;display:none;flex-direction:column;align-items:center;justify-content:center;backdrop-filter:blur(14px)}
.rec-overlay.show{display:flex}
.rec-circle{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;margin-bottom:24px;animation:recPulse 1s ease-out infinite;box-shadow:0 10px 30px rgba(239,68,68,.4)}
.rec-wave-bars{display:flex;gap:5px;align-items:center;height:32px}
.rec-bar{width:5px;border-radius:3px;background:#fff;animation:barDance .7s ease-in-out infinite}
.rec-bar:nth-child(1){height:12px}.rec-bar:nth-child(2){height:24px;animation-delay:.1s}.rec-bar:nth-child(3){height:32px;animation-delay:.2s}.rec-bar:nth-child(4){height:20px;animation-delay:.3s}.rec-bar:nth-child(5){height:12px;animation-delay:.4s}
.rec-title{font-size:18px;font-weight:800;color:#292524;margin-bottom:6px}
.rec-sub{font-size:13px;color:#78716C;margin-bottom:28px}
.rec-stop{padding:11px 30px;background:#fee2e2;border:1px solid #fca5a5;border-radius:24px;color:#ef4444;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}

/* BOTTOM NAV */
.bnav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:rgba(237,232,220,.95);backdrop-filter:blur(24px);border-top:1px solid rgba(210,193,164,.7);display:flex;align-items:center;z-index:100}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;text-decoration:none;border:none;background:transparent;cursor:pointer}
.ni svg{width:22px;height:22px;color:#C4B89A}
.ni span{font-size:10px;font-weight:600;color:#C4B89A}
.ni.active svg,.ni.active span{color:#A67C00}
.chat-nav-icon{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#D4AF37,#E6C27A);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(212,175,55,.4)}
.chat-nav-bars{display:flex;gap:2px;align-items:center;height:10px}
.chat-nav-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.chat-nav-bar:nth-child(1){height:4px}.chat-nav-bar:nth-child(2){height:7px;animation-delay:.15s}.chat-nav-bar:nth-child(3){height:10px;animation-delay:.3s}.chat-nav-bar:nth-child(4){height:6px;animation-delay:.45s}

@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes slideIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes waveOut{0%{transform:scale(1);opacity:.6}100%{transform:scale(1.9);opacity:0}}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.5)}70%{box-shadow:0 0 0 20px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
</style>
</head>
<body>

<div class="hdr">
  <div class="hdr-top">
    <div class="brand">RunMyStore.ai</div>
    <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
    <div class="hdr-icon" onclick="openNotifications()">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php if (!empty($notifications)): ?><div class="hdr-badge"><?= count($notifications) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="tabs">
    <div class="tab active">✦ AI Асистент</div>
    <a class="tab" href="store-chat.php">Чат Обекти<?php if ($unread_count > 0): ?><span class="tab-badge"><?= $unread_count ?></span><?php endif; ?></a>
  </div>
</div>

<div class="chat-area" id="chatArea">
  <?php if (!empty($notifications)): ?>
  <div class="pro-wrap">
    <div class="pro-label">✦ Важно днес</div>
    <?php foreach ($notifications as $i => $n): ?>
    <div class="pro-card <?= notif_class($n['type']) ?>" id="pc<?= $i ?>">
      <div class="pro-icon"><?= notif_icon($n['type']) ?></div>
      <div class="pro-body">
        <div class="pro-title"><?= htmlspecialchars($n['title']) ?></div>
        <div class="pro-sub"><?= htmlspecialchars($n['message']) ?></div>
        <button class="pro-action" onclick="fillAndSend(<?= htmlspecialchars(json_encode($n['title']), ENT_QUOTES) ?>)">Виж →</button>
      </div>
      <button class="pro-close" onclick="closeCard('pc<?= $i ?>')">✕</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($messages)): ?>
  <div class="welcome">
    <div class="welcome-title">Здравей! 👋</div>
    Аз съм твоят AI асистент за <?= htmlspecialchars($store_name) ?>.<br>
    Натисни микрофона и кажи какво да направя.
  </div>
  <?php else: ?>
  <?php foreach ($messages as $msg): ?>
  <div class="msg-group">
    <?php if ($msg['role'] === 'assistant'): ?>
      <div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div> AI Асистент</div>
      <div class="msg ai"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
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

<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1" oninput="autoResize(this)" onkeydown="handleKey(event)"></textarea>
    <div class="voice-wrap" id="voiceWrap" onclick="toggleVoice()">
      <div class="voice-ring"></div><div class="voice-ring"></div><div class="voice-ring"></div>
      <div class="voice-inner"><div class="voice-bars"><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div></div></div>
    </div>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>

<nav class="bnav">
  <a href="chat.php" class="ni active">
    <div class="chat-nav-icon"><div class="chat-nav-bars"><div class="chat-nav-bar"></div><div class="chat-nav-bar"></div><div class="chat-nav-bar"></div><div class="chat-nav-bar"></div></div></div>
    <span>Чат</span>
  </a>
  <a href="warehouse.php" class="ni"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg><span>Склад</span></a>
  <a href="stats.php"     class="ni"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистики</span></a>
  <a href="actions.php"   class="ni"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg><span>Въвеждане</span></a>
</nav>

<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle"><div class="rec-wave-bars"><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div></div></div>
  <div class="rec-title">Слушам...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<script>
const chatArea=document.getElementById('chatArea');
const chatInput=document.getElementById('chatInput');
const btnSend=document.getElementById('btnSend');
const typing=document.getElementById('typing');
const voiceWrap=document.getElementById('voiceWrap');
const recOverlay=document.getElementById('recOverlay');
let voiceRec=null,isRecording=false;

function scrollBottom(){chatArea.scrollTop=chatArea.scrollHeight}
scrollBottom();

chatInput.addEventListener('input',function(){btnSend.disabled=!this.value.trim();});
function autoResize(el){el.style.height='';el.style.height=Math.min(el.scrollHeight,80)+'px'}
function handleKey(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}}
function fillAndSend(text){chatInput.value=text;btnSend.disabled=false;sendMessage();}

async function sendMessage(){
  const text=chatInput.value.trim();if(!text)return;
  addUserMsg(text);
  chatInput.value='';chatInput.style.height='';btnSend.disabled=true;
  typing.style.display='block';scrollBottom();
  try{
    const res=await fetch('chat-send.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:text})});
    const data=await res.json();
    typing.style.display='none';
    addAIMsg(data.reply||data.error||'Грешка');
  }catch(e){typing.style.display='none';addAIMsg('Грешка при свързване с AI.');}
}

function addUserMsg(text){
  const g=document.createElement('div');g.className='msg-group';
  g.innerHTML=`<div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div><div style="display:flex;justify-content:flex-end"><div class="msg user">${esc(text)}</div></div>`;
  chatArea.insertBefore(g,typing);scrollBottom();
}
function addAIMsg(text){
  const g=document.createElement('div');g.className='msg-group';
  g.innerHTML=`<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div> AI Асистент</div><div class="msg ai">${esc(text).replace(/\n/g,'<br>')}</div>`;
  chatArea.insertBefore(g,typing);scrollBottom();
}
function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

async function toggleVoice(){
  if(isRecording){stopVoice();return;}
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){showToast('Браузърът не поддържа гласово въвеждане');return;}
  isRecording=true;voiceWrap.classList.add('recording');recOverlay.classList.add('show');
  voiceRec=new SR();voiceRec.lang='bg-BG';voiceRec.interimResults=false;voiceRec.maxAlternatives=1;voiceRec.continuous=false;
  voiceRec.onresult=(e)=>{const text=e.results[0][0].transcript;stopVoice();chatInput.value=text;btnSend.disabled=false;sendMessage();};
  voiceRec.onerror=(e)=>{stopVoice();if(e.error==='no-speech')showToast('Не чух нищо — опитай пак');else if(e.error==='not-allowed')showToast('Разреши достъп до микрофона');else showToast('Грешка: '+e.error);};
  voiceRec.onend=()=>{if(isRecording)stopVoice();};
  try{voiceRec.start();}catch(e){stopVoice();showToast('Грешка при стартиране');}
}
function stopVoice(){
  isRecording=false;voiceWrap.classList.remove('recording');recOverlay.classList.remove('show');
  if(voiceRec){try{voiceRec.stop();}catch(e){}voiceRec=null;}
}

function closeCard(id){
  const el=document.getElementById(id);if(!el)return;
  el.style.transition='all .3s';el.style.opacity='0';el.style.transform='translateX(-16px)';
  el.style.maxHeight=el.offsetHeight+'px';
  setTimeout(()=>{el.style.maxHeight='0';el.style.marginBottom='0';el.style.padding='0';},300);
  setTimeout(()=>el.remove(),600);
}
function openNotifications(){fillAndSend('Покажи всички нотификации');}
function showToast(msg){
  let t=document.getElementById('_toast');
  if(!t){t=document.createElement('div');t.id='_toast';t.style.cssText='position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#D4AF37,#C5A059);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;font-family:Montserrat,sans-serif';document.body.appendChild(t);}
  t.textContent=msg;t.style.opacity='1';
  setTimeout(()=>t.style.opacity='0',2800);
}
</script>
</body>
</html>
