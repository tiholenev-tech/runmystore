<?php
// chat.php — AI First команден център
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];
$role       = $_SESSION['role'] ?? 'seller';

$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id]
)->fetchAll();

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';

$unread = DB::run(
    'SELECT COUNT(*) as cnt FROM store_messages WHERE tenant_id = ? AND to_store_id = ? AND is_read = 0',
    [$tenant_id, $store_id]
)->fetch();
$unread_count = $unread ? (int)$unread['cnt'] : 0;

// Проактивни нотификации — последните 3 непрочетени
$notifications = DB::run(
    'SELECT type, title, message FROM notifications
     WHERE tenant_id = ? AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY created_at DESC LIMIT 3',
    [$tenant_id]
)->fetchAll();

// Бързи команди по роля
$quick_cmds = [
    ['icon' => '📦', 'label' => 'Склад', 'msg' => 'Покажи склада'],
    ['icon' => '💰', 'label' => 'Продажби', 'msg' => 'Колко продадох днес?'],
    ['icon' => '⚠️', 'label' => 'Ниска нал.', 'msg' => 'Кои артикули са под минимума?'],
];
if (in_array($role, ['owner', 'manager'])) {
    $quick_cmds[] = ['icon' => '🚚', 'label' => 'Доставка', 'msg' => 'Нова доставка'];
    $quick_cmds[] = ['icon' => '🔄', 'label' => 'Трансфер', 'msg' => 'Направи трансфер'];
}
if ($role === 'owner') {
    $quick_cmds[] = ['icon' => '📊', 'label' => 'Печалба', 'msg' => 'Каква е печалбата ми днес?'];
}
$quick_cmds[] = ['icon' => '🎁', 'label' => 'Лоялна', 'msg' => 'Лоялна програма'];

// Тип иконка по нотификация
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
<title>RunMyStore.ai — Чат</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
:root { --nav-h: 64px; }
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }

body {
  background: #030712;
  color: #e2e8f0;
  font-family: 'Montserrat', sans-serif;
  height: 100dvh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── GLOW ── */
body::before {
  content: '';
  position: fixed;
  top: -200px; left: 50%;
  transform: translateX(-50%);
  width: 700px; height: 500px;
  background: radial-gradient(ellipse, rgba(99,102,241,.07) 0%, transparent 70%);
  pointer-events: none; z-index: 0;
}

/* ── HEADER ── */
.hdr {
  position: relative; z-index: 50;
  background: rgba(3,7,18,.95);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(99,102,241,.12);
  padding: 12px 16px 0;
  flex-shrink: 0;
}
.hdr-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
  gap: 10px;
}
.brand {
  font-size: 18px; font-weight: 800;
  background: linear-gradient(to right, #f1f5f9, #a5b4fc, #f1f5f9);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gShift 6s linear infinite;
  flex: 1;
}
.store-pill {
  font-size: 11px; font-weight: 700;
  color: rgba(165,180,252,.7);
  background: rgba(99,102,241,.08);
  border: 1px solid rgba(99,102,241,.15);
  border-radius: 20px;
  padding: 4px 10px;
  max-width: 110px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.hdr-icon {
  width: 34px; height: 34px;
  border-radius: 10px;
  background: rgba(99,102,241,.1);
  border: 1px solid rgba(99,102,241,.2);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: #a5b4fc;
  position: relative; flex-shrink: 0;
}
.hdr-badge {
  position: absolute; top: -4px; right: -4px;
  width: 16px; height: 16px; border-radius: 50%;
  background: #ef4444; font-size: 9px; font-weight: 800;
  color: #fff; display: flex; align-items: center; justify-content: center;
}

/* ── TABS ── */
.tabs { display: flex; gap: 4px; }
.tab {
  flex: 1; padding: 8px 4px;
  font-size: 13px; font-weight: 700;
  color: #4b5563; text-align: center;
  border-bottom: 2px solid transparent;
  cursor: pointer; text-decoration: none;
  transition: all .2s; display: block;
  position: relative;
}
.tab.active { color: #6366f1; border-bottom-color: #6366f1; }
.tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 16px; height: 16px; border-radius: 8px;
  background: #ef4444; font-size: 9px; font-weight: 800;
  color: #fff; margin-left: 5px; padding: 0 3px;
}

/* ── CHAT AREA ── */
.chat-area {
  flex: 1; overflow-y: auto; overflow-x: hidden;
  padding: 12px 12px 8px;
  display: flex; flex-direction: column; gap: 0;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  position: relative; z-index: 1;
}
.chat-area::-webkit-scrollbar { display: none; }

/* ── PROACTIVE CARDS ── */
.pro-wrap { margin-bottom: 14px; }
.pro-label {
  font-size: 10px; font-weight: 700;
  color: #6366f1; text-transform: uppercase;
  letter-spacing: 1px; margin-bottom: 8px;
}
.pro-card {
  border-radius: 14px; padding: 11px 13px;
  margin-bottom: 7px;
  display: flex; align-items: flex-start; gap: 10px;
  animation: slideIn .35s ease both;
  position: relative; overflow: hidden;
}
.pro-card::before {
  content: ''; position: absolute;
  left: 0; top: 0; bottom: 0; width: 3px;
}
.pro-card.danger { background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.18); }
.pro-card.danger::before { background: #ef4444; }
.pro-card.warning { background: rgba(245,158,11,.07); border: 1px solid rgba(245,158,11,.18); }
.pro-card.warning::before { background: #f59e0b; }
.pro-card.success { background: rgba(34,197,94,.07); border: 1px solid rgba(34,197,94,.18); }
.pro-card.success::before { background: #22c55e; }
.pro-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
.pro-body { flex: 1; min-width: 0; }
.pro-title { font-size: 12px; font-weight: 700; color: #f1f5f9; margin-bottom: 2px; }
.pro-sub { font-size: 11px; color: #9ca3af; line-height: 1.4; margin-bottom: 7px; }
.pro-action {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 8px;
  font-size: 11px; font-weight: 700;
  background: rgba(99,102,241,.12);
  border: 1px solid rgba(99,102,241,.25);
  color: #a5b4fc; cursor: pointer; font-family: inherit;
}
.pro-close {
  position: absolute; top: 7px; right: 8px;
  width: 20px; height: 20px; border-radius: 50%;
  background: rgba(255,255,255,.04); border: none;
  color: #4b5563; font-size: 11px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}

/* ── MESSAGES ── */
.msg-group { margin-bottom: 12px; animation: fadeUp .3s ease both; }
.msg-meta {
  font-size: 10px; color: #4b5563;
  margin-bottom: 4px;
  display: flex; align-items: center; gap: 5px;
}
.msg-meta.right { justify-content: flex-end; }
.ai-ava {
  width: 20px; height: 20px; border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; flex-shrink: 0;
}
.msg {
  max-width: 85%; padding: 10px 13px;
  font-size: 13px; line-height: 1.55;
  word-break: break-word;
}
.msg.ai {
  background: rgba(15,15,40,.9);
  border: 1px solid rgba(99,102,241,.14);
  color: #e2e8f0;
  border-radius: 4px 16px 16px 16px;
  align-self: flex-start;
}
.msg.user {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border-radius: 16px 16px 4px 16px;
  margin-left: auto;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.16);
}
.action-chips {
  display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;
}
.action-chip {
  padding: 5px 11px; border-radius: 20px;
  font-size: 11px; font-weight: 700;
  background: rgba(99,102,241,.1);
  border: 1px solid rgba(99,102,241,.22);
  color: #a5b4fc; cursor: pointer;
  transition: all .2s; white-space: nowrap;
  font-family: inherit;
}
.action-chip:active { background: rgba(99,102,241,.25); transform: scale(.97); }

/* ── TYPING ── */
.typing-wrap {
  display: none;
  padding: 10px 13px;
  background: rgba(15,15,40,.9);
  border: 1px solid rgba(99,102,241,.14);
  border-radius: 4px 16px 16px 16px;
  width: fit-content; margin-bottom: 12px;
}
.typing-dots { display: flex; gap: 4px; align-items: center; }
.dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: #6366f1; animation: bounce 1.2s infinite;
}
.dot:nth-child(2) { animation-delay: .2s; }
.dot:nth-child(3) { animation-delay: .4s; }

/* ── WELCOME ── */
.welcome {
  text-align: center; padding: 30px 20px 10px;
  color: #4b5563; font-size: 13px;
}
.welcome-title {
  font-size: 20px; font-weight: 800; margin-bottom: 6px;
  background: linear-gradient(to right, #f1f5f9, #a5b4fc, #f1f5f9);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gShift 6s linear infinite;
}

/* ── QUICK COMMANDS ── */
.quick-wrap {
  padding: 0 12px 8px;
  flex-shrink: 0; position: relative; z-index: 1;
}
.quick-row {
  display: flex; gap: 6px;
  overflow-x: auto; scrollbar-width: none;
}
.quick-row::-webkit-scrollbar { display: none; }
.quick-btn {
  flex-shrink: 0;
  padding: 7px 13px; border-radius: 20px;
  font-size: 12px; font-weight: 700;
  border: 1px solid rgba(99,102,241,.2);
  color: #6b7280; background: rgba(15,15,40,.8);
  cursor: pointer; font-family: inherit;
  transition: all .2s; white-space: nowrap;
  display: flex; align-items: center; gap: 5px;
}
.quick-btn:active { background: rgba(99,102,241,.2); color: #a5b4fc; border-color: #6366f1; }

/* ── INPUT AREA ── */
.input-area {
  background: rgba(3,7,18,.97);
  border-top: 1px solid rgba(99,102,241,.12);
  padding: 12px 12px 16px;
  flex-shrink: 0; position: relative; z-index: 1;
}
.input-row { display: flex; gap: 8px; align-items: flex-end; }
.text-input {
  flex: 1;
  background: rgba(15,15,40,.8);
  border: 1px solid rgba(99,102,241,.2);
  border-radius: 20px; color: #e2e8f0;
  font-size: 14px; padding: 10px 16px;
  font-family: inherit; outline: none;
  resize: none; max-height: 80px; line-height: 1.4;
  transition: border-color .2s;
}
.text-input:focus { border-color: rgba(99,102,241,.5); }
.text-input::placeholder { color: #374151; }

.voice-btn {
  width: 56px; height: 56px; border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  border: none; color: #fff;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 24px rgba(99,102,241,.6), 0 0 48px rgba(99,102,241,.2);
  flex-shrink: 0; transition: all .2s;
  font-size: 24px;
  animation: voice-pulse 3s ease-in-out infinite;
}
.voice-btn:active { transform: scale(.92); }
.voice-btn.recording {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  box-shadow: 0 0 30px rgba(239,68,68,.7);
  animation: pulse-rec 1s infinite;
}
.send-btn {
  width: 44px; height: 44px; border-radius: 50%;
  background: rgba(99,102,241,.15);
  border: 1px solid rgba(99,102,241,.25);
  color: #a5b4fc; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: all .2s;
}
.send-btn:active { background: rgba(99,102,241,.3); transform: scale(.92); }
.send-btn:disabled { opacity: .35; cursor: default; }

/* ── RECORDING OVERLAY ── */
.rec-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.85); z-index: 400;
  display: none; flex-direction: column;
  align-items: center; justify-content: center;
  backdrop-filter: blur(12px);
}
.rec-overlay.show { display: flex; }
.rec-circle {
  width: 110px; height: 110px; border-radius: 50%;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  display: flex; align-items: center; justify-content: center;
  font-size: 44px;
  animation: pulse-rec 1s infinite;
  box-shadow: 0 0 60px rgba(239,68,68,.5);
  margin-bottom: 24px;
}
.rec-title { font-size: 18px; font-weight: 800; color: #f1f5f9; margin-bottom: 6px; }
.rec-sub { font-size: 13px; color: #6b7280; margin-bottom: 32px; }
.rec-stop {
  padding: 12px 32px;
  background: rgba(239,68,68,.12);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: 24px; color: #ef4444;
  font-size: 14px; font-weight: 700;
  cursor: pointer; font-family: inherit;
}

/* ── BOTTOM NAV ── */
.bnav {
  position: fixed; bottom: 0; left: 0; right: 0;
  height: var(--nav-h);
  background: rgba(3,7,18,.97);
  backdrop-filter: blur(20px);
  border-top: 1px solid rgba(99,102,241,.1);
  display: flex; align-items: center; z-index: 100;
}
.ni {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 3px; text-decoration: none;
  border: none; background: transparent; cursor: pointer;
}
.ni svg { width: 22px; height: 22px; color: #3f3f5a; }
.ni span { font-size: 10px; font-weight: 600; color: #3f3f5a; }
.ni.active svg, .ni.active span { color: #6366f1; }

/* ── STORE CHAT TAB ── */
.store-area {
  flex: 1; overflow-y: auto; padding: 12px;
  display: none; scrollbar-width: none;
}
.store-area.active { display: block; }
.store-area::-webkit-scrollbar { display: none; }
.store-card {
  background: rgba(15,15,40,.8);
  border: 1px solid rgba(99,102,241,.12);
  border-radius: 14px; padding: 12px 14px;
  margin-bottom: 8px;
  display: flex; gap: 10px; align-items: flex-start;
  cursor: pointer; transition: border-color .2s;
}
.store-card:active { border-color: rgba(99,102,241,.35); }
.store-ava {
  width: 38px; height: 38px; border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800; color: #fff;
  flex-shrink: 0;
}
.store-info { flex: 1; min-width: 0; }
.store-name { font-size: 13px; font-weight: 700; color: #f1f5f9; margin-bottom: 2px; }
.store-preview { font-size: 11px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.store-time { font-size: 10px; color: #4b5563; flex-shrink: 0; }
.unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #6366f1; margin-top: 4px; }

/* ── ANIMATIONS ── */
@keyframes gShift { 0% { background-position: 0% center } 100% { background-position: 200% center } }
@keyframes slideIn { from { opacity: 0; transform: translateX(-8px) } to { opacity: 1; transform: translateX(0) } }
@keyframes fadeUp { from { opacity: 0; transform: translateY(8px) } to { opacity: 1; transform: translateY(0) } }
@keyframes bounce { 0%,60%,100% { transform: translateY(0) } 30% { transform: translateY(-6px) } }
@keyframes pulse-rec { 0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.4) } 50% { box-shadow: 0 0 0 14px rgba(239,68,68,0) } }
@keyframes voice-pulse { 0%,100% { box-shadow: 0 0 24px rgba(99,102,241,.6), 0 0 48px rgba(99,102,241,.2); } 50% { box-shadow: 0 0 32px rgba(99,102,241,.9), 0 0 64px rgba(99,102,241,.35); } }
</style>
</head>
<body>

<!-- HEADER -->
<div class="hdr">
  <div class="hdr-top">
    <div class="brand">RunMyStore.ai</div>
    <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
    <div class="hdr-icon" onclick="openNotifications()">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
      <?php if (!empty($notifications)): ?>
      <div class="hdr-badge"><?= count($notifications) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="tabs">
    <div class="tab active" id="tab-ai" onclick="switchTab('ai')">✦ AI Асистент</div>
    <a class="tab" id="tab-store" href="store-chat.php">
      Чат Обекти
      <?php if ($unread_count > 0): ?>
        <span class="tab-badge"><?= $unread_count ?></span>
      <?php endif; ?>
    </a>
  </div>
</div>

<!-- AI CHAT AREA -->
<div class="chat-area" id="chatArea">

  <?php if (!empty($notifications)): ?>
  <!-- PROACTIVE CARDS -->
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
      <button class="pro-close" onclick="closeCard('pc<?= $i ?>', <?= $i ?>)">✕</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($messages)): ?>
  <!-- WELCOME -->
  <div class="welcome">
    <div class="welcome-title">Здравей!</div>
    Аз съм твоят AI асистент за <?= htmlspecialchars($store_name) ?>.<br>
    Кажи ми какво да направя — с глас или текст.
  </div>
  <?php else: ?>
  <!-- HISTORY -->
  <?php foreach ($messages as $msg): ?>
  <div class="msg-group">
    <?php if ($msg['role'] === 'assistant'): ?>
      <div class="msg-meta"><div class="ai-ava">✦</div> AI Асистент</div>
      <div class="msg ai"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
    <?php else: ?>
      <div class="msg-meta right"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
      <div style="display:flex;justify-content:flex-end"><div class="msg user"><?= nl2br(htmlspecialchars($msg['content'])) ?></div></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- TYPING -->
  <div class="typing-wrap" id="typing">
    <div class="typing-dots">
      <div class="dot"></div><div class="dot"></div><div class="dot"></div>
    </div>
  </div>

</div>

<!-- QUICK COMMANDS -->
<div class="quick-wrap" id="quickWrap">
  <div class="quick-row">
    <?php foreach ($quick_cmds as $cmd): ?>
    <button class="quick-btn" onclick="fillAndSend(<?= htmlspecialchars(json_encode($cmd['msg']), ENT_QUOTES) ?>)">
      <?= $cmd['icon'] ?> <?= htmlspecialchars($cmd['label']) ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- INPUT -->
<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput"
      placeholder="Кажи или пиши..." rows="1"
      oninput="autoResize(this)"
      onkeydown="handleKey(event)"></textarea>
    <button class="voice-btn" id="voiceBtn" onclick="toggleVoice()">🎤</button>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
    </button>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <a href="chat.php" class="ni active">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    <span>Чат</span>
  </a>
  <a href="warehouse.php" class="ni">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
    <span>Склад</span>
  </a>
  <a href="stats.php" class="ni">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
    <span>Статистики</span>
  </a>
  <a href="actions.php" class="ni">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    <span>Въвеждане</span>
  </a>
</nav>

<!-- RECORDING OVERLAY -->
<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle">🎤</div>
  <div class="rec-title">Слушам...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<script>
const chatArea  = document.getElementById('chatArea');
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');
const voiceBtn  = document.getElementById('voiceBtn');
const recOverlay= document.getElementById('recOverlay');

let voiceRec = null;
let isRecording = false;

// ── SCROLL ──
function scrollBottom() { chatArea.scrollTop = chatArea.scrollHeight; }
scrollBottom();

// ── INPUT ──
chatInput.addEventListener('input', function() {
  btnSend.disabled = !this.value.trim();
});
function autoResize(el) {
  el.style.height = '';
  el.style.height = Math.min(el.scrollHeight, 80) + 'px';
}
function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

// ── FILL & SEND ──
function fillAndSend(text) {
  chatInput.value = text;
  btnSend.disabled = false;
  sendMessage();
}

// ── SEND ──
async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;

  addUserMsg(text);
  chatInput.value = '';
  chatInput.style.height = '';
  btnSend.disabled = true;

  typing.style.display = 'block';
  scrollBottom();

  try {
    const res  = await fetch('chat-send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    });
    const data = await res.json();
    typing.style.display = 'none';
    addAIMsg(data.reply || data.error || 'Грешка');
  } catch(e) {
    typing.style.display = 'none';
    addAIMsg('Грешка при свързване с AI.');
  }
}

function addUserMsg(text) {
  const g = document.createElement('div');
  g.className = 'msg-group';
  g.innerHTML = `
    <div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div>
    <div style="display:flex;justify-content:flex-end"><div class="msg user">${escHtml(text)}</div></div>
  `;
  chatArea.insertBefore(g, typing);
  scrollBottom();
}

function addAIMsg(text) {
  // Deeplink detection — [текст →](url)
  const formatted = escHtml(text).replace(/\n/g,'<br>');
  const g = document.createElement('div');
  g.className = 'msg-group';
  g.innerHTML = `
    <div class="msg-meta"><div class="ai-ava">✦</div> AI Асистент</div>
    <div class="msg ai">${formatted}</div>
  `;
  chatArea.insertBefore(g, typing);
  scrollBottom();
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── VOICE ──
function toggleVoice() {
  if (isRecording) { stopVoice(); return; }
  if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
    showToast('Гласовото въвеждане не се поддържа от браузъра');
    return;
  }
  isRecording = true;
  voiceBtn.classList.add('recording');
  recOverlay.classList.add('show');

  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  voiceRec = new SR();
  voiceRec.lang = 'bg-BG';
  voiceRec.interimResults = false;
  voiceRec.maxAlternatives = 1;

  voiceRec.onresult = e => {
    const text = e.results[0][0].transcript;
    stopVoice();
    chatInput.value = text;
    btnSend.disabled = false;
    sendMessage();
  };
  voiceRec.onerror = () => stopVoice();
  voiceRec.onend   = () => { if (isRecording) stopVoice(); };
  voiceRec.start();
}

function stopVoice() {
  isRecording = false;
  voiceBtn.classList.remove('recording');
  recOverlay.classList.remove('show');
  if (voiceRec) { voiceRec.stop(); voiceRec = null; }
}

// ── PROACTIVE CARDS ──
function closeCard(id, idx) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.transition = 'all .3s';
  el.style.opacity = '0';
  el.style.transform = 'translateX(-16px)';
  el.style.maxHeight = el.offsetHeight + 'px';
  setTimeout(() => { el.style.maxHeight = '0'; el.style.marginBottom = '0'; el.style.padding = '0'; }, 300);
  setTimeout(() => el.remove(), 600);
  // Mark as read
  fetch('chat-send.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_notif_read', idx: idx })
  }).catch(()=>{});
}

// ── NOTIFICATIONS ──
function openNotifications() {
  fillAndSend('Покажи всички нотификации');
}

// ── TOAST ──
function showToast(msg) {
  let t = document.getElementById('__toast');
  if (!t) {
    t = document.createElement('div');
    t.id = '__toast';
    t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;font-family:Montserrat,sans-serif';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1';
  setTimeout(() => t.style.opacity = '0', 2500);
}
</script>
</body>
</html>
