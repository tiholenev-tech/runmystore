<?php
// store-chat.php
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';

// Маркираме като прочетени
DB::run('UPDATE store_messages SET is_read = 1 WHERE tenant_id = ? AND to_store_id = ?', [$tenant_id, $store_id]);

// Всички обекти на tenant-а (без текущия)
$stores = DB::run(
    'SELECT id, name FROM stores WHERE tenant_id = ? AND is_active = 1 AND id != ? ORDER BY name',
    [$tenant_id, $store_id]
)->fetchAll();

// Активен обект за чат
$active_store_id = (int)($_GET['with'] ?? ($stores[0]['id'] ?? 0));

// Съобщения с активния обект
$messages = [];
if ($active_store_id) {
    $messages = DB::run(
        'SELECT m.*, u.name as user_name, s.name as store_name
         FROM store_messages m
         JOIN users u ON m.from_user_id = u.id
         JOIN stores s ON m.from_store_id = s.id
         WHERE m.tenant_id = ?
           AND ((m.from_store_id = ? AND m.to_store_id = ?) OR (m.from_store_id = ? AND m.to_store_id = ?))
         ORDER BY m.created_at ASC LIMIT 100',
        [$tenant_id, $store_id, $active_store_id, $active_store_id, $store_id]
    )->fetchAll();
}

$my_store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$my_store_name = $my_store ? $my_store['name'] : 'Магазин';

$active_store = null;
foreach ($stores as $s) {
    if ($s['id'] == $active_store_id) { $active_store = $s; break; }
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>Чат Обекти — RunMyStore.ai</title>
  <link href="./css/vendors/aos.css" rel="stylesheet">
  <link href="./style.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      height: 100dvh;
      display: flex; flex-direction: column;
      overflow: hidden;
      padding-bottom: 70px;
    }

    .chat-header {
      display: flex; align-items: center; gap: .75rem;
      padding: .75rem 1rem;
      padding-top: calc(.75rem + env(safe-area-inset-top));
      background: var(--color-gray-900);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
    }
    .chat-header-info { flex: 1; min-width: 0; }
    .chat-header-name {
      font-family: var(--font-nacelle, sans-serif);
      font-size: .9375rem; font-weight: 600;
      color: var(--color-gray-200);
    }
    .chat-header-sub { font-size: .75rem; color: rgba(165,180,252,.65); }

    .chat-tabs {
      display: flex;
      background: var(--color-gray-900);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
    }
    .chat-tab {
      flex: 1; padding: .625rem 1rem;
      font-size: .875rem; font-weight: 500;
      color: var(--color-gray-500);
      text-align: center; cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: color .15s, border-color .15s;
      text-decoration: none; display: block;
    }
    .chat-tab.active {
      color: var(--color-indigo-400, #818cf8);
      border-bottom-color: var(--color-indigo-500, #6366f1);
    }

    /* Store selector */
    .store-selector {
      display: flex; gap: .5rem;
      padding: .75rem 1rem;
      overflow-x: auto;
      background: var(--color-gray-900);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      flex-shrink: 0;
      scrollbar-width: none;
    }
    .store-selector::-webkit-scrollbar { display: none; }
    .store-btn {
      flex-shrink: 0;
      padding: .375rem .875rem;
      border-radius: 1rem;
      font-size: .8125rem; font-weight: 500;
      border: 1px solid rgba(255,255,255,0.08);
      background: transparent;
      color: var(--color-gray-400);
      cursor: pointer;
      text-decoration: none;
      transition: all .15s;
    }
    .store-btn.active {
      background: rgba(99,102,241,0.15);
      border-color: rgba(99,102,241,0.4);
      color: var(--color-indigo-400, #818cf8);
    }

    /* Messages */
    .chat-messages {
      flex: 1; overflow-y: auto;
      padding: 1rem;
      display: flex; flex-direction: column; gap: .625rem;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: var(--color-gray-700) transparent;
    }
    .msg-wrap { display: flex; flex-direction: column; max-width: 82%; }
    .msg-wrap.mine { align-self: flex-end; align-items: flex-end; }
    .msg-wrap.theirs { align-self: flex-start; align-items: flex-start; }
    .msg-meta { font-size: 11px; color: var(--color-gray-600); margin-bottom: 3px; }
    .msg {
      padding: .625rem .9375rem;
      border-radius: 1.125rem;
      font-size: .9375rem; line-height: 1.5;
      word-break: break-word;
    }
    .msg.mine {
      background: linear-gradient(to bottom, var(--color-indigo-500), var(--color-indigo-600));
      color: #fff;
      border-bottom-right-radius: .3rem;
      box-shadow: inset 0px 1px 0px 0px rgba(255,255,255,.16);
    }
    .msg.theirs {
      background: var(--color-gray-900);
      color: var(--color-gray-200);
      border: 1px solid rgba(255,255,255,0.06);
      border-bottom-left-radius: .3rem;
    }

    .empty {
      text-align: center; padding: 3rem 1rem;
      color: var(--color-gray-600); font-size: .875rem;
    }

    /* Input */
    .chat-input-wrap {
      padding: .75rem 1rem;
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

    /* BOTTOM NAV */
    .bottom-nav {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: 70px;
      background: #111118;
      border-top: 1px solid rgba(255,255,255,0.07);
      display: flex; align-items: center;
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
      font-size: 10px; font-weight: 500;
      transition: color .15s;
      padding: 6px 0;
    }
    .nav-item.active { color: #6366f1; }
    .nav-item svg { display: block; }
  </style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">

<div class="chat-header">
  <div class="chat-header-info">
    <div class="chat-header-name">Чат Обекти</div>
    <div class="chat-header-sub"><?= htmlspecialchars($my_store_name) ?></div>
  </div>
</div>

<div class="chat-tabs">
  <a href="chat.php" class="chat-tab">AI Асистент</a>
  <a href="store-chat.php" class="chat-tab active">Чат Обекти</a>
</div>

<?php if (empty($stores)): ?>
  <div class="empty">Нямаш други обекти.</div>
<?php else: ?>

<div class="store-selector">
  <?php foreach ($stores as $s): ?>
    <a href="store-chat.php?with=<?= $s['id'] ?>"
       class="store-btn <?= $s['id'] == $active_store_id ? 'active' : '' ?>">
      <?= htmlspecialchars($s['name']) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="chat-messages" id="chatMessages">
  <?php if (empty($messages)): ?>
    <div class="empty">
      Няма съобщения с <?= htmlspecialchars($active_store['name'] ?? '') ?> още.<br>
      Напиши първото съобщение.
    </div>
  <?php else: ?>
    <?php foreach ($messages as $msg): ?>
      <?php $is_mine = ($msg['from_store_id'] == $store_id); ?>
      <div class="msg-wrap <?= $is_mine ? 'mine' : 'theirs' ?>">
        <?php if (!$is_mine): ?>
          <div class="msg-meta"><?= htmlspecialchars($msg['store_name']) ?> · <?= htmlspecialchars($msg['user_name']) ?></div>
        <?php endif; ?>
        <div class="msg <?= $is_mine ? 'mine' : 'theirs' ?>">
          <?= nl2br(htmlspecialchars($msg['message'])) ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="chat-input-wrap">
  <div class="chat-input-row">
    <textarea class="chat-input" id="chatInput" placeholder="Съобщение до <?= htmlspecialchars($active_store['name'] ?? '') ?>..." rows="1"></textarea>
    <button class="btn-send" id="btnSend" onclick="sendMessage()">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="22" y1="2" x2="11" y2="13"/>
        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>
</div>

<?php endif; ?>

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

<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput    = document.getElementById('chatInput');
const btnSend      = document.getElementById('btnSend');

if (chatInput) {
  chatInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
  });
  chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });
}

function scrollToBottom() {
  if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
}

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;
  btnSend.disabled = true;

  try {
    const res = await fetch('store-chat-send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, to_store_id: <?= $active_store_id ?> })
    });
    const data = await res.json();
    if (data.ok) {
      chatInput.value = '';
      chatInput.style.height = 'auto';
      // Добавяме съобщението веднага
      const wrap = document.createElement('div');
      wrap.className = 'msg-wrap mine';
      const msg = document.createElement('div');
      msg.className = 'msg mine';
      msg.textContent = text;
      wrap.appendChild(msg);
      const empty = chatMessages.querySelector('.empty');
      if (empty) empty.remove();
      chatMessages.appendChild(wrap);
      scrollToBottom();
    }
  } catch(err) { console.error(err); }
  btnSend.disabled = false;
}

scrollToBottom();
</script>
</body>
</html>
