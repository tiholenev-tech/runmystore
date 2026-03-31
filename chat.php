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

$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id]
)->fetchAll();

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';
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
    }

    .chat-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .75rem 1rem;
      padding-top: calc(.75rem + env(safe-area-inset-top));
      background: var(--color-gray-900);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
      position: relative;
    }
    .chat-header::after {
      content: '';
      position: absolute;
      inset: 0;
      border-bottom: 1px solid transparent;
      background: linear-gradient(to right, var(--color-gray-800), var(--color-gray-700), var(--color-gray-800)) border-box;
      mask: linear-gradient(white 0 0) padding-box, linear-gradient(white 0 0);
      mask-composite: exclude;
      pointer-events: none;
    }

    .chat-header-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      overflow: hidden;
      flex-shrink: 0;
      border: 1.5px solid rgba(99,102,241,0.4);
      background: #fff;
    }
    .chat-header-avatar img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      object-position: center center;
    }

    .chat-header-info { flex: 1; min-width: 0; }
    .chat-header-name {
      font-family: var(--font-nacelle, sans-serif);
      font-size: .9375rem;
      font-weight: 600;
      color: var(--color-gray-200);
    }
    .chat-header-status {
      font-size: .6875rem;
      color: #4ade80;
      font-weight: 500;
    }

    .chat-header-store {
      font-size: .75rem;
      color: rgba(165,180,252,.65);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100px;
    }

    .chat-header-btn {
      background: none;
      border: none;
      color: var(--color-gray-500);
      cursor: pointer;
      padding: .375rem;
      border-radius: .5rem;
      transition: color .15s;
      line-height: 0;
    }
    .chat-header-btn:hover { color: var(--color-gray-200); }

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
      font-size: 1.25rem;
      font-weight: 600;
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

    .chat-input-wrap {
      padding: .75rem 1rem;
      padding-bottom: calc(.75rem + env(safe-area-inset-bottom));
      background: var(--color-gray-900);
      border-top: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
    }
    .chat-input-row {
      display: flex;
      align-items: flex-end;
      gap: .5rem;
    }
    .chat-input {
      flex: 1;
      background: var(--color-gray-950, #030712);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 1.25rem;
      padding: .625rem 1rem;
      color: var(--color-gray-200);
      font-size: .9375rem;
      outline: none;
      resize: none;
      max-height: 120px;
      line-height: 1.4;
      font-family: inherit;
      transition: border-color .15s;
    }
    .chat-input:focus { border-color: var(--color-indigo-500, #6366f1); }
    .chat-input::placeholder { color: var(--color-gray-600); }

    .btn-send {
      width: 42px; height: 42px;
      background: linear-gradient(to bottom, var(--color-indigo-500), var(--color-indigo-600));
      border: none;
      border-radius: 50%;
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
      display: flex;
      gap: .5rem;
      overflow-x: auto;
      padding-bottom: .5rem;
      scrollbar-width: none;
      margin-bottom: .5rem;
    }
    .suggestions::-webkit-scrollbar { display: none; }
    .suggestion {
      flex-shrink: 0;
      background: linear-gradient(to bottom, var(--color-gray-800), rgba(17,24,39,.6));
      border-radius: 1rem;
      padding: .375rem .875rem;
      font-size: .8125rem;
      color: var(--color-gray-300);
      cursor: pointer;
      white-space: nowrap;
      position: relative;
      border: none;
      transition: color .15s;
    }
    .suggestion::before {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: inherit;
      border: 1px solid transparent;
      background: linear-gradient(to right, var(--color-gray-800), var(--color-gray-700), var(--color-gray-800)) border-box;
      mask: linear-gradient(white 0 0) padding-box, linear-gradient(white 0 0);
      mask-composite: exclude;
      pointer-events: none;
    }
    .suggestion:hover { color: var(--color-indigo-400, #818cf8); }
  </style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">

<div class="chat-header">
  <div class="chat-header-avatar">
    <svg viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg" width="42" height="42">
      <circle cx="21" cy="21" r="21" fill="#1e1b4b"/>
      <rect x="11" y="26" width="20" height="12" rx="6" fill="#4f46e5"/>
      <rect x="14" y="27" width="14" height="11" rx="3" fill="#3730a3"/>
      <rect x="18" y="22" width="6" height="6" rx="2" fill="#fbbf24"/>
      <ellipse cx="21" cy="18" rx="8" ry="8" fill="#fbbf24"/>
      <ellipse cx="21" cy="11" rx="8" ry="4" fill="#92400e"/>
      <rect x="13" y="11" width="16" height="6" rx="3" fill="#92400e"/>
      <circle cx="18" cy="17" r="1.5" fill="#1e1b4b"/>
      <circle cx="24" cy="17" r="1.5" fill="#1e1b4b"/>
      <circle cx="18.5" cy="16.5" r=".5" fill="white"/>
      <circle cx="24.5" cy="16.5" r=".5" fill="white"/>
      <path d="M18 21 Q21 24 24 21" fill="none" stroke="#1e1b4b" stroke-width="1.2" stroke-linecap="round"/>
      <rect x="24" y="28" width="6" height="8" rx="1.5" fill="#6366f1"/>
      <rect x="25" y="29" width="4" height="5" rx=".5" fill="#a5b4fc"/>
    </svg>
  </div>
  <div class="chat-header-info">
    <div class="chat-header-name">AI Асистент</div>
    <div class="chat-header-status">● онлайн</div>
  </div>
  <div class="chat-header-store"><?= htmlspecialchars($store_name) ?></div>
  <button class="chat-header-btn" onclick="location.href='dashboard.php'" title="Табло">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
      <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
  </button>
</div>

<div class="chat-messages" id="chatMessages">
  <?php if (empty($messages)): ?>
    <div class="welcome">
      <strong>👋 Здравей!</strong>
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
  <div class="suggestions" id="suggestions">
    <button class="suggestion" onclick="fillSuggestion(this)">Колко стока ми остана?</button>
    <button class="suggestion" onclick="fillSuggestion(this)">Какво се продава най-много?</button>
    <button class="suggestion" onclick="fillSuggestion(this)">Каква е печалбата ми днес?</button>
    <button class="suggestion" onclick="fillSuggestion(this)">Какво трябва да поръчам?</button>
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
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
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

function scrollToBottom() {
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

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
    const res = await fetch('chat-send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    });

    const rawText = await res.text();
    let data;
    try {
      data = JSON.parse(rawText);
    } catch(e) {
      typing.style.display = 'none';
      addMessage('assistant', '⚠️ PHP грешка: ' + rawText.substring(0, 300));
      btnSend.disabled = false;
      return;
    }

    typing.style.display = 'none';

    if (data.reply) {
      addMessage('assistant', data.reply);
    } else {
      addMessage('assistant', '⚠️ ' + (data.error || 'Неизвестна грешка'));
    }
  } catch (err) {
    typing.style.display = 'none';
    addMessage('assistant', '⚠️ Fetch грешка: ' + err.message);
  }

  btnSend.disabled = false;
  chatInput.focus();
}

scrollToBottom();
</script>
</body>
</html>
