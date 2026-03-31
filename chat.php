<?php
// chat.php
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

// Брой непрочетени съобщения между обекти
$unread = DB::run(
    'SELECT COUNT(*) as cnt FROM store_messages WHERE tenant_id = ? AND to_store_id = ? AND is_read = 0',
    [$tenant_id, $store_id]
)->fetch();
$unread_count = $unread ? (int)$unread['cnt'] : 0;
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>RunMyStore.ai</title>
  <link href="./css/vendors/aos.css" rel="stylesheet">
  <link href="./style.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      height: 100dvh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding-bottom: 70px;
    }

    /* HEADER */
    .chat-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      padding-top: calc(.75rem + env(safe-area-inset-top));
      background: var(--color-gray-900);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
    }
    .chat-header-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      overflow: hidden;
      flex-shrink: 0;
      border: 1.5px solid rgba(99,102,241,0.4);
    }
    .chat-header-avatar img { width: 100%; height: 100%; object-fit: contain; }
    .chat-header-info { flex: 1; min-width: 0; }
    .chat-header-name {
      font-family: var(--font-nacelle, sans-serif);
      font-size: .9375rem; font-weight: 600;
      color: var(--color-gray-200);
    }
    .chat-header-status { font-size: .6875rem; color: #4ade80; font-weight: 500; }
    .chat-header-store {
      font-size: .75rem;
      color: rgba(165,180,252,.65);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      max-width: 100px;
    }

    /* TABS */
    .chat-tabs {
      display: flex;
      background: var(--color-gray-900);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
    }
    .chat-tab {
      flex: 1;
      padding: .625rem 1rem;
      font-size: .875rem;
      font-weight: 500;
      color: var(--color-gray-500);
      text-align: center;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: color .15s, border-color .15s;
      position: relative;
      text-decoration: none;
      display: block;
    }
    .chat-tab.active {
      color: var(--color-indigo-400, #818cf8);
      border-bottom-color: var(--color-indigo-500, #6366f1);
    }
    .unread-badge {
      position: absolute;
      top: 6px; right: calc(50% - 40px);
      background: #ef4444;
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      border-radius: 10px;
      padding: 1px 5px;
      line-height: 1.4;
    }

    /* MESSAGES */
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: .625rem;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: var(--color-gray-700) transparent;
    }
    .msg {
      max-width: 82%;
      padding: .625rem .9375rem;
      border-radius: 1.125rem;
      font-size: .9375rem;
      line-height: 1.5;
      word-break: break-word;
    }
    .msg-user {
      align-self: flex-end;
      background: linear-gradient(to bottom, var(--color-indigo-500), var(--color-indigo-600));
      color: #fff;
      border-bottom-right-radius: .3rem;
      box-shadow: inset 0px 1px 0px 0px rgba(255,255,255,.16);
    }
    .msg-assistant {
      align-self: flex-start;
      background: var(--color-gray-900);
      color: var(--color-gray-200);
      border: 1px solid rgba(255,255,255,0.06);
      border-bottom-left-radius: .3rem;
    }
    .typing {
      align-self: flex-start;
      background: var(--color-gray-900);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 1.125rem;
      border-bottom-left-radius: .3rem;
      padding: .75rem 1rem;
      display: none;
    }
    .typing span {
      display: inline-block;
      width: 7px; height: 7px;
      background: var(--color-indigo-400, #818cf8);
      border-radius: 50%;
      animation: bounce 1.2s infinite;
      margin: 0 2px;
    }
    .typing span:nth-child(2) { animation-delay: .2s; }
    .typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-6px); }
    }
    .welcome {
      align-self: center;
      text-align: center;
      color: var(--color-gray-600);
      font-size: .875rem;
      padding: 2.5rem 1rem;
    }
    .welcome strong {
      display: block;
      font-family: var(--font-nacelle, sans-serif);
      font-size: 1.25rem; font-weight: 600;
      background: linear-gradient(to right, var(--color-gray-200), var(--color-indigo-200), var(--color-gray-50), var(--color-indigo-300), var(--color-gray-200));
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: gradient 6s linear infinite;
      margin-bottom: .5rem;
    }
    @keyframes gradient {
      0% { background-position: 0% center; }
      100% { background-position: 200% center; }
    }

    /* INPUT */
    .chat-input-wrap {
      padding: .75rem 1rem .75rem;
      background: var(--color-gray-900);
      border-top: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
    }
    .chat-input-row { display: flex; align-items: flex-end; gap: .5rem; }
    .chat-input {
      flex: 1;
      background: var(--color-gray-950, #030712);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 1.25rem;
      padding: .625rem 1rem;
      color: var(--color-gray-200);
      font-size: .9375rem;
      outline: none; resize: none;
      max-height: 120px; line-height: 1.4;
      font-family: inherit;
      transition: border-color .15s;
    }
    .chat-input:focus { border-color: var(--color-indigo-500, #6366f1); }
    .chat-input::placeholder { color: var(--color-gray-600); }
    .btn-send {
      width: 42px; height: 42px;
      background: linear-gradient(to bottom, var(--color-indigo-500), var(--color-indigo-600));
      border: none; border-radius: 50%;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      box-shadow: inset 0px 1px 0px 0px rgba(255,255,255,.16);
      transition: opacity .15s, transform .1s;
    }
    .btn-send:active { opacity: .85; transform: scale(.95); }
    .btn-send:disabled { opacity: .4; cursor: default; }
    .btn-send svg { color: #fff; }

    .suggestions {
      display: flex; gap: .5rem;
      overflow-x: auto; padding-bottom: .5rem;
      scrollbar-width: none; margin-bottom: .5rem;
    }
    .suggestions::-webkit-scrollbar { display: none; }
    .suggestion {
      flex-shrink: 0;
      background: linear-gradient(to bottom, var(--color-gray-800), rgba(17,24,39,.6));
      border-radius: 1rem;
      padding: .375rem .875rem;
      font-size: .8125rem;
      color: var(--color-gray-300);
      cursor: pointer; white-space: nowrap;
      border: 1px solid rgba(255,255,255,0.08);
      transition: color .15s;
    }
    .suggestion:hover { color: var(--color-indigo-400, #818cf8); }

    /* BOTTOM NAV */
    .bottom-nav {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: 70px;
      background: #111118;
      border-top: 1px solid rgba(255,255,255,0.07);
      display: flex;
      align-items: center;
      padding-bottom: env(safe-area-inset-bottom);
      z-index: 200;
    }
    .nav-item {
      flex: 1;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 4px;
      text-decoration: none;
      color: #3f3f5a;
      font-size: 10px;
      font-weight: 500;
      transition: color .15s;
      padding: 6px 0;
    }
    .nav-item.active { color: #6366f1; }
    .nav-item svg { display: block; }
  </style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">

<div class="chat-header">
  <div class="chat-header-avatar">
    <img src="images/ai-assistant-avatar.png" alt="AI Асистент">
  </div>
  <div class="chat-header-info">
    <div class="chat-header-name">AI Асистент</div>
    <div class="chat-header-status">● онлайн</div>
  </div>
  <div class="chat-header-store"><?= htmlspecialchars($store_name) ?></div>
</div>

<div class="chat-tabs">
  <a href="chat.php" class="chat-tab active">AI Асистент</a>
  <a href="store-chat.php" class="chat-tab">
    Чат Обекти
    <?php if ($unread_count > 0): ?>
      <span class="unread-badge"><?= $unread_count ?></span>
    <?php endif; ?>
  </a>
</div>

<div class="chat-messages" id="chatMessages">
  <?php if (empty($messages)): ?>
    <div class="welcome">
      <strong>Здравей!</strong>
      Аз съм твоят AI асистент за <?= htmlspecialchars($store_name) ?>.<br>
      Кажи ми какво искаш да направя.
    </div>
  <?php else: ?>
    <?php foreach ($messages as $msg): ?>
      <div class="msg msg-<?= $msg['role'] ?>">
        <?= nl2br(htmlspecialchars($msg['content'])) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="typing" id="typing">
    <span></span><span></span><span></span>
  </div>
</div>

<div class="chat-input-wrap">
  <?php
  // Suggestions филтрирани по роля
  $suggestions = ['Колко стока ми остана?', 'Стока без движение?', 'Какво се продава най-много?'];
  if ($role === 'owner' || $role === 'manager') {
      $suggestions[] = 'Какво трябва да поръчам?';
  }
  if ($role === 'owner') {
      $suggestions[] = 'Каква е печалбата ми днес?';
  }
  ?>
  <div class="suggestions" id="suggestions">
    <?php foreach ($suggestions as $s): ?>
      <button class="suggestion" onclick="fillSuggestion(this)"><?= htmlspecialchars($s) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="chat-input-row">
    <textarea class="chat-input" id="chatInput" placeholder="Напиши съобщение..." rows="1"></textarea>
    <button class="btn-send" id="btnSend" onclick="sendMessage()">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="22" y1="2" x2="11" y2="13"/>
        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a href="chat.php" class="nav-item active">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">
      <rect x="2" y="2" width="44" height="34" rx="8"/>
      <path d="M8 40 L16 36"/>
      <line x1="12" y1="14" x2="36" y2="14"/>
      <line x1="12" y1="22" x2="28" y2="22"/>
    </svg>
    Чат
  </a>
  <a href="warehouse.php" class="nav-item">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">
      <path d="M4 38 L4 16 L24 4 L44 16 L44 38 Z"/>
      <rect x="16" y="24" width="16" height="14" rx="2"/>
      <line x1="4" y1="38" x2="44" y2="38"/>
    </svg>
    Склад
  </a>
  <a href="stats.php" class="nav-item">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">
      <polyline points="4,36 16,20 28,26 44,8"/>
      <circle cx="4" cy="36" r="2.5" fill="currentColor" stroke="none"/>
      <circle cx="16" cy="20" r="2.5" fill="currentColor" stroke="none"/>
      <circle cx="28" cy="26" r="2.5" fill="currentColor" stroke="none"/>
      <circle cx="44" cy="8" r="2.5" fill="currentColor" stroke="none"/>
      <line x1="0" y1="42" x2="48" y2="42"/>
    </svg>
    Статистики
  </a>
  <a href="actions.php" class="nav-item">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 48 48">
      <rect x="2" y="2" width="44" height="38" rx="4"/>
      <line x1="24" y1="12" x2="24" y2="30"/>
      <line x1="14" y1="21" x2="34" y2="21"/>
    </svg>
    Въвеждане
  </a>
</nav>

<script src="./js/vendors/alpinejs-focus.min.js"></script>
<script src="./js/vendors/alpinejs.min.js" defer></script>
<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput    = document.getElementById('chatInput');
const btnSend      = document.getElementById('btnSend');
const typing       = document.getElementById('typing');
const suggestions  = document.getElementById('suggestions');

chatInput.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
chatInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

function fillSuggestion(btn) {
  chatInput.value = btn.textContent;
  chatInput.focus();
  suggestions.style.display = 'none';
}
function addMessage(role, content) {
  const div = document.createElement('div');
  div.className = 'msg msg-' + role;
  div.textContent = content;
  chatMessages.insertBefore(div, typing);
  scrollToBottom();
}
function scrollToBottom() { chatMessages.scrollTop = chatMessages.scrollHeight; }

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;
  addMessage('user', text);
  chatInput.value = '';
  chatInput.style.height = 'auto';
  btnSend.disabled = true;
  suggestions.style.display = 'none';
  typing.style.display = 'block';
  scrollToBottom();
  try {
    const res  = await fetch('chat-send.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message: text }) });
    const raw  = await res.text();
    let data;
    try { data = JSON.parse(raw); } catch(e) {
      typing.style.display = 'none';
      addMessage('assistant', 'PHP грешка: ' + raw.substring(0, 300));
      btnSend.disabled = false; return;
    }
    typing.style.display = 'none';
    addMessage('assistant', data.reply || data.error || 'Неизвестна грешка');
  } catch(err) {
    typing.style.display = 'none';
    addMessage('assistant', 'Грешка: ' + err.message);
  }
  btnSend.disabled = false;
  chatInput.focus();
}
scrollToBottom();
</script>
</body>
</html>
