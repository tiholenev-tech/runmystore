<?php
// chat.php — главен екран, изглежда като WhatsApp
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];

// Зареждаме последните 50 съобщения
$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id]
)->fetchAll();

// Зареждаме името на магазина
$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>RunMyStore.ai</title>
  <link rel="stylesheet" href="style.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg-body, #0f172a);
      color: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      height: 100dvh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* ── HEADER ── */
    .chat-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .875rem 1rem;
      background: var(--card-bg, #1e293b);
      border-bottom: 1px solid var(--border, #334155);
      flex-shrink: 0;
    }
    .chat-header-avatar {
      width: 38px; height: 38px;
      background: var(--color-primary, #6366f1);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .chat-header-info { flex: 1; min-width: 0; }
    .chat-header-name { font-size: .9375rem; font-weight: 600; color: #f1f5f9; }
    .chat-header-status { font-size: .75rem; color: #4ade80; }
    .chat-header-btn {
      background: none; border: none; color: #94a3b8;
      cursor: pointer; padding: .375rem;
      border-radius: .375rem;
      transition: color .15s;
    }
    .chat-header-btn:hover { color: #f1f5f9; }

    /* ── MESSAGES ── */
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: .5rem;
      -webkit-overflow-scrolling: touch;
    }

    .msg {
      max-width: 80%;
      padding: .625rem .875rem;
      border-radius: 1rem;
      font-size: .9375rem;
      line-height: 1.45;
      word-break: break-word;
    }
    .msg-user {
      align-self: flex-end;
      background: var(--color-primary, #6366f1);
      color: #fff;
      border-bottom-right-radius: .25rem;
    }
    .msg-assistant {
      align-self: flex-start;
      background: var(--card-bg, #1e293b);
      color: #f1f5f9;
      border: 1px solid var(--border, #334155);
      border-bottom-left-radius: .25rem;
    }
    .msg-time {
      font-size: .6875rem;
      color: #64748b;
      margin-top: .25rem;
      text-align: right;
    }
    .msg-user + .msg-time { text-align: right; align-self: flex-end; }
    .msg-assistant + .msg-time { text-align: left; align-self: flex-start; }

    /* Typing indicator */
    .typing {
      align-self: flex-start;
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border, #334155);
      border-radius: 1rem;
      border-bottom-left-radius: .25rem;
      padding: .75rem 1rem;
      display: none;
    }
    .typing span {
      display: inline-block;
      width: 7px; height: 7px;
      background: #94a3b8;
      border-radius: 50%;
      animation: bounce 1.2s infinite;
      margin: 0 1px;
    }
    .typing span:nth-child(2) { animation-delay: .2s; }
    .typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-6px); }
    }

    /* Welcome message */
    .welcome {
      align-self: center;
      text-align: center;
      color: #64748b;
      font-size: .875rem;
      padding: 2rem 1rem;
    }
    .welcome strong { display: block; font-size: 1.125rem; color: #94a3b8; margin-bottom: .5rem; }

    /* ── INPUT ── */
    .chat-input-wrap {
      padding: .75rem 1rem;
      padding-bottom: calc(.75rem + env(safe-area-inset-bottom));
      background: var(--card-bg, #1e293b);
      border-top: 1px solid var(--border, #334155);
      flex-shrink: 0;
    }
    .chat-input-row {
      display: flex;
      align-items: flex-end;
      gap: .5rem;
    }
    .chat-input {
      flex: 1;
      background: var(--input-bg, #0f172a);
      border: 1px solid var(--border, #334155);
      border-radius: 1.25rem;
      padding: .625rem 1rem;
      color: #f1f5f9;
      font-size: .9375rem;
      outline: none;
      resize: none;
      max-height: 120px;
      line-height: 1.4;
      font-family: inherit;
      transition: border-color .15s;
    }
    .chat-input:focus { border-color: var(--color-primary, #6366f1); }
    .chat-input::placeholder { color: #475569; }

    .btn-send {
      width: 42px; height: 42px;
      background: var(--color-primary, #6366f1);
      border: none;
      border-radius: 50%;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      transition: opacity .15s, transform .1s;
    }
    .btn-send:active { opacity: .85; transform: scale(.95); }
    .btn-send:disabled { opacity: .4; cursor: default; }
    .btn-send svg { color: #fff; }

    /* Quick suggestions */
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
      background: var(--input-bg, #0f172a);
      border: 1px solid var(--border, #334155);
      border-radius: 1rem;
      padding: .375rem .875rem;
      font-size: .8125rem;
      color: #94a3b8;
      cursor: pointer;
      white-space: nowrap;
      transition: border-color .15s, color .15s;
    }
    .suggestion:hover { border-color: var(--color-primary, #6366f1); color: #f1f5f9; }
  </style>
</head>
<body>

<!-- HEADER -->
<div class="chat-header">
  <div class="chat-header-avatar">🤖</div>
  <div class="chat-header-info">
    <div class="chat-header-name">AI Асистент</div>
    <div class="chat-header-status">● онлайн</div>
  </div>
  <button class="chat-header-btn" onclick="location.href='dashboard.php'" title="Табло">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
      <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
    </svg>
  </button>
</div>

<!-- MESSAGES -->
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

<!-- INPUT -->
<div class="chat-input-wrap">
  <div class="suggestions" id="suggestions">
    <button class="suggestion" onclick="fillSuggestion(this)">Колко стока ми остана?</button>
    <button class="suggestion" onclick="fillSuggestion(this)">Какво се продава най-много?</button>
    <button class="suggestion" onclick="fillSuggestion(this)">Каква е печалбата ми днес?</button>
    <button class="suggestion" onclick="fillSuggestion(this)">Какво трябва да поръчам?</button>
  </div>
  <div class="chat-input-row">
    <textarea
      class="chat-input"
      id="chatInput"
      placeholder="Напиши съобщение..."
      rows="1"
    ></textarea>
    <button class="btn-send" id="btnSend" onclick="sendMessage()">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="22" y1="2" x2="11" y2="13"/>
        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput    = document.getElementById('chatInput');
const btnSend      = document.getElementById('btnSend');
const typing       = document.getElementById('typing');
const suggestions  = document.getElementById('suggestions');

// Auto-resize textarea
chatInput.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Enter = изпрати, Shift+Enter = нов ред
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

    // DEBUG: показваме суровия отговор ако не е валиден JSON
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

// Scroll до дъното при зареждане
scrollToBottom();
</script>
</body>
</html>