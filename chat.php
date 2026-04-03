<?php
session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'owner';
$user_name = $_SESSION['name'] ?? 'Собственик';

$tenant = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch();
$currency = $tenant['currency'] ?? 'EUR';
$cs = $currency === 'EUR' ? '€' : $currency;

// Stores за switcher
$stores = DB::run("SELECT id, name FROM stores WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll();
$store_id = $_SESSION['store_id'] ?? ($stores[0]['id'] ?? 0);

// Unread store messages
$unread = DB::run("SELECT COUNT(*) FROM store_messages WHERE tenant_id=? AND is_read=0 AND from_store_id != ?", [$tenant_id, $store_id])->fetchColumn();

// Chat history (последните 40)
$chat_msgs = DB::run(
    "SELECT role, message, created_at FROM chat_messages WHERE tenant_id=? AND user_id=? ORDER BY created_at DESC LIMIT 40",
    [$tenant_id, $user_id]
)->fetchAll();
$chat_msgs = array_reverse($chat_msgs);
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>✦ AI Асистент — RunMyStore.ai</title>
<link href="./css/vendors/aos.css" rel="stylesheet">
<link href="./style.css" rel="stylesheet">
<style>
/* ── OVERRIDE / MOBILE EXTRA ───────────────────────────────────────── */
*{-webkit-tap-highlight-color:transparent;box-sizing:border-box;}
body{overflow-x:hidden;padding-bottom:0;}
/* BG illustrations */
.page-bg{pointer-events:none;position:fixed;inset:0;z-index:0;overflow:hidden;}
.page-bg img{position:absolute;}
/* HEADER */
.app-header{position:fixed;top:0;left:0;right:0;z-index:50;
  background:rgba(17,24,39,.9);backdrop-filter:blur(12px);
  border-bottom:1px solid rgba(255,255,255,.06);
  padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;}
.app-logo{display:flex;align-items:center;gap:8px;}
.app-logo img{width:28px;height:28px;}
.app-logo span{font-size:15px;font-weight:800;background:linear-gradient(to right,#e2e8f0,#a5b4fc,#f8fafc,#c7d2fe,#e2e8f0);
  background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;
  animation:gradient 6s linear infinite;}
@keyframes gradient{0%{background-position:0%}100%{background-position:200%}}
.hdr-right{display:flex;align-items:center;gap:8px;}
/* Pulse btn */
.pulse-btn{position:relative;width:36px;height:36px;border-radius:50%;
  background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.25);
  display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;}
.pulse-btn svg{width:16px;height:16px;color:#a5b4fc;}
.pulse-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:#ef4444;display:none;}
/* Store chat btn */
.storechat-btn{position:relative;width:36px;height:36px;border-radius:50%;
  background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.18);
  display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;flex-shrink:0;}
.storechat-btn svg{width:16px;height:16px;color:#a5b4fc;}
.unread-badge{position:absolute;top:-3px;right:-3px;min-width:16px;height:16px;border-radius:8px;
  background:#ef4444;color:#fff;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;padding:0 3px;}
/* BRIEFING SECTION */
.briefing-wrap{margin:0 12px 8px;display:flex;flex-direction:column;gap:6px;}
.brief-greeting{font-size:13px;font-weight:700;color:#e2e8f0;margin-bottom:2px;}
.brief-card{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:12px;border:1px solid;position:relative;cursor:pointer;text-decoration:none;}
.brief-card[data-priority="red"]{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.22);}
.brief-card[data-priority="orange"]{background:rgba(245,158,11,.07);border-color:rgba(245,158,11,.22);}
.brief-card[data-priority="yellow"]{background:rgba(234,179,8,.07);border-color:rgba(234,179,8,.2);}
.brief-card[data-priority="green"]{background:rgba(34,197,94,.06);border-color:rgba(34,197,94,.2);}
.brief-card-txt{flex:1;font-size:12px;font-weight:600;color:#e2e8f0;line-height:1.4;}
.brief-close{width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.08);
  border:none;color:#9ca3af;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.brief-loading{font-size:12px;color:#6b7280;padding:8px 12px;}
/* CHAT AREA */
.chat-area{position:fixed;top:58px;left:0;right:0;bottom:132px;overflow-y:auto;padding:12px 12px 8px;
  display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth;}
.msg{display:flex;gap:8px;max-width:88%;}
.msg.user{align-self:flex-end;flex-direction:row-reverse;}
.msg.assistant{align-self:flex-start;}
.msg-avatar{width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;}
.msg.assistant .msg-avatar{background:linear-gradient(135deg,#6366f1,#818cf8);}
.msg.user .msg-avatar{background:rgba(99,102,241,.2);border:1px solid rgba(99,102,241,.3);}
.msg-bubble{padding:9px 12px;border-radius:14px;font-size:13px;line-height:1.5;}
.msg.assistant .msg-bubble{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-bottom-left-radius:4px;}
.msg.user .msg-bubble{background:linear-gradient(135deg,rgba(99,102,241,.35),rgba(99,102,241,.25));border:1px solid rgba(99,102,241,.3);color:#e2e8f0;border-bottom-right-radius:4px;}
.msg-time{font-size:9px;color:#4b5563;margin-top:3px;text-align:right;}
/* Deeplink buttons inside AI messages */
.deep-btn{display:inline-flex;align-items:center;padding:6px 12px;
  background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.25);
  border-radius:10px;color:#a5b4fc;font-size:12px;font-weight:700;
  text-decoration:none;margin:4px 2px;}
/* Typing indicator */
.typing-dots{display:flex;gap:4px;align-items:center;padding:10px 12px;}
.typing-dots span{width:7px;height:7px;border-radius:50%;background:#6366f1;animation:td .8s ease-in-out infinite;}
.typing-dots span:nth-child(2){animation-delay:.15s;}
.typing-dots span:nth-child(3){animation-delay:.3s;}
@keyframes td{0%,80%,100%{transform:scale(1);opacity:.5;}40%{transform:scale(1.3);opacity:1;}}
/* Welcome state */
.welcome-state{display:flex;flex-direction:column;align-items:center;padding:24px 20px 12px;gap:6px;}
.welcome-icon{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);
  display:flex;align-items:center;justify-content:center;margin-bottom:4px;
  box-shadow:0 0 0 12px rgba(99,102,241,.06);}
.welcome-title{font-size:17px;font-weight:800;color:#e2e8f0;text-align:center;}
.welcome-sub{font-size:12px;color:#6b7280;text-align:center;max-width:260px;line-height:1.5;}
/* INPUT AREA */
.input-area{position:fixed;bottom:64px;left:0;right:0;z-index:40;
  background:rgba(3,7,18,.95);border-top:1px solid rgba(255,255,255,.06);
  padding:8px 12px 10px;}
/* Quick commands */
.qcmds{display:flex;gap:6px;margin-bottom:8px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;}
.qcmds::-webkit-scrollbar{display:none;}
.qcmd{flex-shrink:0;padding:5px 11px;background:rgba(99,102,241,.08);
  border:1px solid rgba(99,102,241,.18);border-radius:16px;
  font-size:11px;font-weight:700;color:#a5b4fc;cursor:pointer;white-space:nowrap;}
.qcmd:active{background:rgba(99,102,241,.18);}
/* Input row */
.input-row{display:flex;gap:8px;align-items:flex-end;}
.chat-input{flex:1;min-height:42px;max-height:120px;padding:10px 14px;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
  border-radius:14px;font-size:14px;color:#e2e8f0;font-family:inherit;
  outline:none;resize:none;line-height:1.4;}
.chat-input:focus{border-color:rgba(99,102,241,.5);background:rgba(99,102,241,.05);}
.chat-input::placeholder{color:#4b5563;}
.send-btn{width:42px;height:42px;border-radius:12px;flex-shrink:0;
  background:linear-gradient(to top,#4f46e5,#6366f1);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.16);}
.send-btn:active{opacity:.8;}
.send-btn svg{width:18px;height:18px;color:#fff;}
/* Voice btn */
.voice-btn{width:42px;height:42px;border-radius:12px;flex-shrink:0;
  background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);
  cursor:pointer;display:flex;align-items:center;justify-content:center;}
.voice-btn.recording{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.4);animation:vrec 1s ease-in-out infinite;}
.voice-btn svg{width:18px;height:18px;color:#a5b4fc;}
.voice-btn.recording svg{color:#f87171;}
@keyframes vrec{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.3);}50%{box-shadow:0 0 0 8px rgba(239,68,68,0);}}
/* Recording overlay */
.rec-overlay{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:200;
  display:none;flex-direction:column;align-items:center;justify-content:center;gap:16px;}
.rec-overlay.open{display:flex;}
.rec-circle{width:80px;height:80px;border-radius:50%;background:rgba(239,68,68,.15);
  border:2px solid rgba(239,68,68,.4);display:flex;align-items:center;justify-content:center;
  animation:vrec 1s ease-in-out infinite;}
.rec-circle svg{width:36px;height:36px;color:#f87171;}
.rec-text{font-size:16px;font-weight:700;color:#e2e8f0;}
.rec-sub{font-size:13px;color:#6b7280;}
.rec-cancel{padding:10px 28px;border-radius:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#e2e8f0;font-size:14px;font-weight:700;cursor:pointer;}
/* TOAST */
.toast{position:fixed;bottom:145px;left:50%;transform:translateX(-50%);
  background:rgba(17,24,39,.95);border:1px solid rgba(255,255,255,.1);
  color:#e2e8f0;padding:10px 20px;border-radius:20px;
  font-size:13px;font-weight:600;z-index:300;
  opacity:0;transition:opacity .25s;pointer-events:none;white-space:nowrap;backdrop-filter:blur(8px);}
.toast.show{opacity:1;}
/* ACTION CONFIRM POPUP */
.action-ovl{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);
  z-index:200;display:none;align-items:flex-end;justify-content:center;}
.action-ovl.open{display:flex;}
.action-box{background:#111827;border:1px solid rgba(255,255,255,.1);
  border-radius:24px 24px 0 0;width:100%;padding:24px 20px 40px;max-width:480px;}
.action-icon{width:52px;height:52px;border-radius:50%;background:rgba(99,102,241,.12);
  border:1.5px solid rgba(99,102,241,.3);display:flex;align-items:center;
  justify-content:center;margin:0 auto 14px;font-size:22px;}
.action-title{font-size:17px;font-weight:800;color:#e2e8f0;text-align:center;margin-bottom:8px;}
.action-text{font-size:13px;color:#9ca3af;text-align:center;line-height:1.5;margin-bottom:22px;}
.action-btns{display:flex;gap:10px;}
.action-yes{flex:1;padding:14px;border:none;border-radius:14px;
  background:linear-gradient(to top,#4f46e5,#6366f1);color:#fff;font-size:15px;font-weight:800;cursor:pointer;}
.action-no{flex:1;padding:14px;border:1.5px solid rgba(255,255,255,.1);border-radius:14px;
  background:transparent;color:#9ca3af;font-size:14px;font-weight:700;cursor:pointer;}
/* BOTTOM NAV */
.bnav{position:fixed;bottom:0;left:0;right:0;height:64px;
  background:rgba(3,7,18,.97);border-top:1px solid rgba(255,255,255,.06);
  display:flex;align-items:center;z-index:50;}
.ni{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;text-decoration:none;}
.ni-icon{width:20px;height:20px;color:#4b5563;}
.ni-lbl{font-size:10px;font-weight:600;color:#4b5563;}
.ni.active .ni-icon,.ni.active .ni-lbl{color:#818cf8;}
.ni-ai{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#818cf8);
  display:flex;align-items:center;justify-content:center;}
.ni-ai-bars{display:flex;gap:2px;align-items:center;height:10px;}
.ni-ai-bars div{width:2px;border-radius:1px;background:#fff;animation:bd .9s ease-in-out infinite;}
.ni-ai-bars div:nth-child(1){height:4px;}.ni-ai-bars div:nth-child(2){height:7px;animation-delay:.15s;}.ni-ai-bars div:nth-child(3){height:10px;animation-delay:.3s;}.ni-ai-bars div:nth-child(4){height:6px;animation-delay:.45s;}
@keyframes bd{0%,100%{transform:scaleY(1);}50%{transform:scaleY(.25);}}
</style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">

<!-- BG -->
<div class="page-bg" aria-hidden="true">
  <img src="./images/page-illustration.svg" width="846" height="594" alt="" style="left:50%;top:0;transform:translateX(-25%);">
  <img src="./images/blurred-shape-gray.svg" width="760" height="668" alt="" style="left:0;top:300px;opacity:.4;">
  <img src="./images/blurred-shape.svg" width="760" height="668" alt="" style="right:0;top:350px;opacity:.25;">
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- RECORDING OVERLAY -->
<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
    </svg>
  </div>
  <div class="rec-text">Говоря...</div>
  <div class="rec-sub">Кажи командата си</div>
  <button class="rec-cancel" onclick="stopVoice()">Отказ</button>
</div>

<!-- ACTION CONFIRM -->
<div class="action-ovl" id="actionOvl">
  <div class="action-box">
    <div class="action-icon" id="actionIcon">⚡</div>
    <div class="action-title" id="actionTitle">Потвърди действие</div>
    <div class="action-text" id="actionText"></div>
    <div class="action-btns">
      <button class="action-yes" onclick="confirmAction()">Да, изпълни</button>
      <button class="action-no" onclick="closeAction()">Не</button>
    </div>
  </div>
</div>

<!-- HEADER -->
<header class="app-header">
  <div class="app-logo">
    <img src="./images/logo.svg" alt="logo">
    <span>RunMyStore.ai</span>
  </div>
  <div class="hdr-right">
    <!-- Pulse -->
    <div class="pulse-btn" id="pulseBtn" onclick="doPulse()" title="Пулс проверка">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h3l2-7 3 14 2-6 2 3h4"/>
      </svg>
      <div class="pulse-dot" id="pulseDot"></div>
    </div>
    <!-- Store chat -->
    <a href="store-chat.php" class="storechat-btn">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-1M3 8V5a2 2 0 012-2h10a2 2 0 012 2v5a2 2 0 01-2 2H7L3 16V8z"/>
      </svg>
      <?php if($unread > 0):?>
      <div class="unread-badge"><?= $unread > 9 ? '9+' : $unread ?></div>
      <?php endif;?>
    </a>
  </div>
</header>

<!-- CHAT MESSAGES -->
<div class="chat-area" id="chatArea">

  <!-- Proactive Briefing (зарежда се с JS) -->
  <div id="briefingSection" style="display:none;">
    <div class="briefing-wrap" id="briefingWrap"></div>
  </div>

  <!-- Welcome / empty state -->
  <div id="welcomeState" <?= count($chat_msgs) > 0 ? 'style="display:none;"' : '' ?>>
    <div class="welcome-state">
      <div class="welcome-icon">
        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
      </div>
      <div class="welcome-title">Здравей, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>!</div>
      <div class="welcome-sub">Твоят AI бизнес партньор е готов. Питай за наличности, продажби, прогнози или просто кажи команда с глас.</div>
    </div>
  </div>

  <!-- Existing messages -->
  <?php foreach($chat_msgs as $msg):
    $ts = date('H:i', strtotime($msg['created_at']));
    $isUser = $msg['role'] === 'user';
  ?>
  <div class="msg <?= $isUser ? 'user' : 'assistant' ?>">
    <div class="msg-avatar"><?= $isUser ? '👤' : '✦' ?></div>
    <div>
      <div class="msg-bubble" id="mb_<?= md5($msg['message'].$msg['created_at']) ?>">
        <?= $isUser ? htmlspecialchars($msg['message']) : parseDeeplinksPhp($msg['message']) ?>
      </div>
      <div class="msg-time"><?= $ts ?></div>
    </div>
  </div>
  <?php endforeach; ?>

</div>

<!-- INPUT AREA -->
<div class="input-area">
  <!-- Quick commands -->
  <div class="qcmds" id="qcmds">
    <div class="qcmd" onclick="sendQuick('📦 Склад — покажи наличности')">📦 Склад</div>
    <div class="qcmd" onclick="sendQuick('💰 Продажби — колко продадох днес?')">💰 Продажби</div>
    <div class="qcmd" onclick="sendQuick('⚠️ Покажи артикулите с ниска наличност')">⚠️ Ниска нал.</div>
    <?php if(in_array($user_role, ['owner','manager'])):?>
    <div class="qcmd" onclick="sendQuick('🚚 Очаквани доставки')">🚚 Доставка</div>
    <?php endif;?>
    <?php if($user_role === 'owner'):?>
    <div class="qcmd" onclick="sendQuick('📊 Покажи печалбата за тази седмица')">📊 Печалба</div>
    <?php endif;?>
    <div class="qcmd" onclick="sendQuick('🎁 Лоялна програма — активни клиенти')">🎁 Лоялна</div>
  </div>
  <!-- Input row -->
  <div class="input-row">
    <button class="voice-btn" id="voiceBtn" onclick="startVoice()">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
      </svg>
    </button>
    <textarea class="chat-input" id="chatInput" placeholder="Питай нещо..." rows="1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"
      oninput="autoResize(this)"></textarea>
    <button class="send-btn" onclick="sendMsg()">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
      </svg>
    </button>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <a href="chat.php" class="ni active">
    <div class="ni-ai"><div class="ni-ai-bars"><div></div><div></div><div></div><div></div></div></div>
    <span class="ni-lbl" style="color:#818cf8;">✦ AI</span>
  </a>
  <a href="warehouse.php" class="ni">
    <svg class="ni-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
    </svg>
    <span class="ni-lbl">Склад</span>
  </a>
  <a href="stats.php" class="ni">
    <svg class="ni-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <span class="ni-lbl">Статистики</span>
  </a>
  <a href="actions.php" class="ni">
    <svg class="ni-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    <span class="ni-lbl">Въвеждане</span>
  </a>
</nav>

<?php
// PHP helper — парсва deeplinks за server-render на chat history
function parseDeeplinksPhp($text) {
    $map = [
        '📦' => 'products.php?filter=low',
        '⚠️' => 'purchase-orders.php',
        '📊' => 'stats.php',
        '💰' => 'sale.php',
        '🔄' => 'transfers.php?new=1',
    ];
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    foreach ($map as $emoji => $url) {
        $text = preg_replace_callback(
            '/\[(' . preg_quote($emoji, '/') . '[^\]]+)\s*→\]/',
            function($m) use ($url) {
                return '<a href="' . htmlspecialchars($url) . '" class="deep-btn">' . $m[1] . ' →</a>';
            },
            $text
        );
    }
    return $text;
}
?>

<script>
// ── CONFIG ──────────────────────────────────────────────────────────
const ROLE = <?= json_encode($user_role) ?>;
let isTyping = false;
let recognition = null;
let isRecording = false;
let pendingAction = null;

// ── DEEPLINKS JS (для AI-отговори в реално време) ───────────────────
const DEEPLINK_MAP = {
  '📦': 'products.php?filter=low',
  '⚠️': 'purchase-orders.php',
  '📊': 'stats.php',
  '💰': 'sale.php',
  '🔄': 'transfers.php?new=1',
};

function parseDeeplinks(text) {
  return text.replace(/\[([^\]]+)\s*→\]/g, (match, label) => {
    let url = '#';
    for (const [emoji, link] of Object.entries(DEEPLINK_MAP)) {
      if (label.startsWith(emoji)) { url = link; break; }
    }
    return `<a href="${url}" class="deep-btn">${label} →</a>`;
  });
}

// ── PROACTIVE BRIEFING ──────────────────────────────────────────────
async function loadBriefing() {
  try {
    const r = await fetch('ai-helper.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'briefing'})
    });
    const d = await r.json();
    if (!d.items || d.items.length === 0) return;

    const sec = document.getElementById('briefingSection');
    const wrap = document.getElementById('briefingWrap');
    wrap.innerHTML = '';

    if (d.greeting) {
      const g = document.createElement('div');
      g.className = 'brief-greeting';
      g.textContent = d.greeting;
      wrap.appendChild(g);
    }

    d.items.slice(0, 5).forEach(item => {
      const card = document.createElement(item.deeplink ? 'a' : 'div');
      card.className = 'brief-card';
      card.dataset.priority = item.priority || 'green';
      if (item.deeplink) card.href = item.deeplink;
      card.innerHTML = `
        <span class="brief-card-txt">${item.text}</span>
        <button class="brief-close" onclick="event.stopPropagation();event.preventDefault();this.closest('.brief-card').remove();checkBriefing()">✕</button>
      `;
      wrap.appendChild(card);
    });

    sec.style.display = 'block';
    scrollChat();
  } catch(e) { /* тихо */ }
}

function checkBriefing() {
  const wrap = document.getElementById('briefingWrap');
  if (!wrap.querySelector('.brief-card')) {
    document.getElementById('briefingSection').style.display = 'none';
  }
}

// ── PULSE BUTTON ────────────────────────────────────────────────────
async function doPulse() {
  const btn = document.getElementById('pulseBtn');
  btn.style.opacity = '.5';
  try {
    const r = await fetch('ai-helper.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'pulse'})
    });
    const d = await r.json();
    btn.style.opacity = '1';
    if (d.status === 'issues') {
      document.getElementById('pulseDot').style.display = 'block';
      toast('⚠️ ' + d.message, 4000);
    } else {
      document.getElementById('pulseDot').style.display = 'none';
      toast('✅ ' + (d.message || 'Всичко е наред!'), 2500);
    }
  } catch(e) {
    btn.style.opacity = '1';
    toast('Грешка при проверката');
  }
}

// ── SEND MESSAGE ────────────────────────────────────────────────────
function sendQuick(text) {
  document.getElementById('chatInput').value = text;
  sendMsg();
}

async function sendMsg() {
  const inp = document.getElementById('chatInput');
  const text = inp.value.trim();
  if (!text || isTyping) return;
  inp.value = '';
  autoResize(inp);

  // Скрий welcome
  document.getElementById('welcomeState').style.display = 'none';

  appendMsg('user', text);
  showTyping();
  isTyping = true;

  try {
    const r = await fetch('chat-send.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: text})
    });
    const d = await r.json();
    hideTyping();
    isTyping = false;

    const reply = d.reply || d.response || d.message || 'Няма отговор.';
    appendMsg('assistant', reply, true);

    // Action Layer
    if (d.action && d.action.type) {
      showAction(d.action);
    }
  } catch(e) {
    hideTyping();
    isTyping = false;
    appendMsg('assistant', 'Грешка при свързване. Опитай пак.');
  }
}

function appendMsg(role, text, parseLinks = false) {
  const area = document.getElementById('chatArea');
  const div = document.createElement('div');
  div.className = 'msg ' + role;
  const avatar = role === 'user' ? '👤' : '✦';
  const now = new Date();
  const ts = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

  let content = role === 'user'
    ? escHtml(text)
    : (parseLinks ? parseDeeplinks(escHtml(text)) : escHtml(text));

  div.innerHTML = `
    <div class="msg-avatar">${avatar}</div>
    <div>
      <div class="msg-bubble">${content}</div>
      <div class="msg-time">${ts}</div>
    </div>
  `;
  area.appendChild(div);
  scrollChat();
}

function escHtml(t) {
  return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── TYPING INDICATOR ────────────────────────────────────────────────
function showTyping() {
  const area = document.getElementById('chatArea');
  const d = document.createElement('div');
  d.className = 'msg assistant'; d.id = 'typingMsg';
  d.innerHTML = '<div class="msg-avatar">✦</div><div class="msg-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
  area.appendChild(d);
  scrollChat();
}
function hideTyping() {
  const t = document.getElementById('typingMsg');
  if (t) t.remove();
}

// ── ACTION LAYER ────────────────────────────────────────────────────
function showAction(action) {
  pendingAction = action;
  document.getElementById('actionTitle').textContent = action.title || 'Потвърди действие';
  document.getElementById('actionText').textContent = action.description || '';
  document.getElementById('actionIcon').textContent = action.icon || '⚡';
  document.getElementById('actionOvl').classList.add('open');
}
function confirmAction() {
  closeAction();
  toast('✅ Ще бъде изпълнено');
}
function closeAction() {
  document.getElementById('actionOvl').classList.remove('open');
  pendingAction = null;
}

// ── VOICE ───────────────────────────────────────────────────────────
function startVoice() {
  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRec) { toast('Гласово въвеждане не се поддържа'); return; }

  recognition = new SpeechRec();
  recognition.lang = 'bg-BG';
  recognition.interimResults = false;
  recognition.maxAlternatives = 1;

  recognition.onstart = () => {
    isRecording = true;
    document.getElementById('voiceBtn').classList.add('recording');
    document.getElementById('recOverlay').classList.add('open');
  };
  recognition.onresult = (e) => {
    const txt = e.results[0][0].transcript;
    document.getElementById('chatInput').value = txt;
    stopVoice();
    sendMsg();
  };
  recognition.onerror = () => { stopVoice(); toast('Не разбрах. Опитай пак.'); };
  recognition.onend = () => { stopVoice(); };
  recognition.start();
}

function stopVoice() {
  isRecording = false;
  document.getElementById('voiceBtn').classList.remove('recording');
  document.getElementById('recOverlay').classList.remove('open');
  if (recognition) { try { recognition.stop(); } catch(e){} recognition = null; }
}

// ── UTILS ───────────────────────────────────────────────────────────
function scrollChat() {
  const a = document.getElementById('chatArea');
  setTimeout(() => { a.scrollTop = a.scrollHeight; }, 60);
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function toast(msg, dur = 2500) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), dur);
}

// ── INIT ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  scrollChat();
  loadBriefing();
});
</script>

<script src="./js/vendors/alpinejs.min.js" defer></script>
<script src="./js/vendors/aos.js"></script>
<script src="./js/main.js"></script>
</body>
</html>
