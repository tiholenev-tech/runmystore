<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = $_SESSION['tenant_id'];
$store_id  = $_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';

// Симулация на метрики (в реална среда ще идват от SQL)
$saved_today = 42.20; 
$affiliate_earned = 0.00;
$aff_text = ($affiliate_earned > 0) ? "Спечелени: €" . number_format($affiliate_earned, 2) : "Спечели обратно парите си ➔";

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';

$notifications = DB::run(
    'SELECT type, title, message FROM notifications WHERE tenant_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 3',
    [$tenant_id]
)->fetchAll();

$quick_cmds = [
    ['icon' => '📦', 'label' => 'Склад',      'msg' => 'Покажи склада'],
    ['icon' => '💰', 'label' => 'Продажби',   'msg' => 'Колко продадох днес?'],
];
if ($role === 'owner') {
    $quick_cmds[] = ['icon' => '📊', 'label' => 'Печалба', 'msg' => 'Каква е печалбата ми днес?'];
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>RunMyStore.ai — Command Center</title>
<link rel="stylesheet" href="./style.css">
<style>
:root { --gold: #D4AF37; --gold-dark: #A67C00; --bg: #030712; --glass: rgba(255, 255, 255, 0.03); }
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
body { background: var(--bg); color: #f1f5f9; font-family: 'Montserrat', sans-serif; height: 100dvh; display: flex; flex-direction: column; overflow: hidden; padding-bottom: 64px; }

/* ROI & AFFILIATE HEADER */
.roi-bar { background: linear-gradient(90deg, rgba(212,175,55,0.15), transparent); border-bottom: 1px solid rgba(212,175,55,0.2); padding: 8px 16px; display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
.roi-val { color: #22c55e; }
.aff-link { color: var(--gold); text-decoration: none; border-bottom: 1px dashed var(--gold); }

.hdr { background: rgba(3,7,18,0.8); backdrop-filter: blur(20px); padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
.brand { font-size: 18px; font-weight: 900; background: linear-gradient(to right, #fff, var(--gold)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.store-pill { font-size: 10px; background: var(--glass); border: 1px solid rgba(212,175,55,0.2); padding: 4px 10px; border-radius: 20px; color: var(--gold); }

/* ALERTS (RADAR) */
.alerts-wrap { display: flex; gap: 12px; overflow-x: auto; padding: 14px 16px; scrollbar-width: none; }
.alerts-wrap::-webkit-scrollbar { display: none; }
.alert-card { flex-shrink: 0; width: 240px; background: var(--glass); border: 1px solid rgba(212,175,55,0.15); border-left: 4px solid var(--gold); border-radius: 12px; padding: 12px; position: relative; }
.alert-card.danger { border-left-color: #ef4444; }
.alert-card h4 { font-size: 12px; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
.alert-card p { font-size: 11px; color: #9ca3af; line-height: 1.4; }

.chat-area { flex: 1; overflow-y: auto; padding: 12px 16px; display: flex; flex-direction: column; scrollbar-width: none; }
.chat-area::-webkit-scrollbar { display: none; }

.msg-group { margin-bottom: 16px; }
.msg { max-width: 85%; padding: 12px 16px; font-size: 14px; line-height: 1.5; }
.msg.ai { background: var(--glass); border: 1px solid rgba(255,255,255,0.05); border-radius: 4px 18px 18px 18px; color: #e2e8f0; }
.msg.user { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #000; font-weight: 700; border-radius: 18px 18px 4px 18px; margin-left: auto; }

/* INPUT BOX */
.input-area { background: rgba(3,7,18,0.95); backdrop-filter: blur(20px); padding: 12px 16px 20px; border-top: 1px solid rgba(255,255,255,0.05); }
.input-row { display: flex; gap: 10px; align-items: center; }

.cam-btn { width: 48px; height: 48px; border-radius: 50%; background: rgba(212,175,55,0.1); border: 1px solid var(--gold); color: var(--gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 0 15px rgba(212,175,55,0.2); }
.text-input { flex: 1; background: var(--glass); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; color: #fff; padding: 12px 18px; font-family: inherit; outline: none; transition: border-color 0.2s; }
.text-input:focus { border-color: var(--gold); }

.voice-btn { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #000; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* QUICK CMDS */
.quick-row { display: flex; gap: 8px; margin-bottom: 12px; overflow-x: auto; scrollbar-width: none; }
.quick-btn { flex-shrink: 0; padding: 8px 14px; background: var(--glass); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; color: #9ca3af; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; }

/* NAV */
.bnav { position: fixed; bottom: 0; left: 0; right: 0; height: 64px; background: #000; border-top: 1px solid rgba(212,175,55,0.2); display: flex; align-items: center; z-index: 100; }
.ni { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; text-decoration: none; color: #4b5563; font-size: 10px; font-weight: 700; }
.ni.active { color: var(--gold); }
.ni svg { width: 22px; height: 22px; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>

<div class="roi-bar">
    <div>Спестени днес: <span class="roi-val">€<?= number_format($saved_today, 2) ?></span></div>
    <a href="affiliate.php" class="aff-link"><?= $aff_text ?></a>
</div>

<div class="hdr">
    <div class="brand">RunMyStore.ai</div>
    <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
</div>

<div class="alerts-wrap">
    <div class="alert-card danger">
        <h4>🚨 Zombie Stock</h4>
        <p>5 артикула стоят над 90 дни. Блокирани: €450 кеш.</p>
    </div>
    <div class="alert-card">
        <h4>📈 Бързооборотни</h4>
        <p>Размер L (Тениска) свършва до 48ч. Поръчай сега!</p>
    </div>
</div>

<div class="chat-area" id="chatArea">
    <div class="msg-group">
        <div class="msg ai">Здравей, Пешо! Твоят склад е под контрол. Снимай нова разписка или ме питай нещо.</div>
    </div>
    <div id="typing" style="display:none; color: var(--gold); font-size: 12px; margin-left: 10px;">AI мисли...</div>
</div>

<div class="input-area">
    <div class="quick-row">
        <?php foreach ($quick_cmds as $cmd): ?>
        <button class="quick-btn" onclick="sendMsg('<?= $cmd['msg'] ?>')"><?= $cmd['icon'] ?> <?= $cmd['label'] ?></button>
        <?php endforeach; ?>
    </div>
    <div class="input-row">
        <button class="cam-btn" onclick="location.href='invoice-scan.php'">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </button>
        <input type="text" class="text-input" id="chatInput" placeholder="Пиши или снимай..." onkeydown="if(event.key==='Enter') sendMsg(this.value)">
        <div class="voice-btn">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 00 6 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
        </div>
    </div>
</div>

<nav class="bnav">
    <a href="chat.php" class="ni active"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 1.821.487 3.53 1.338 5L2.1 21.9l4.899-1.237C8.47 21.513 10.179 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/></svg><span>Чат</span></a>
    <a href="warehouse.php" class="ni"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-14v14m0-14L4 7m8 4L4 7m0 0v10l8 4"/></svg><span>Склад</span></a>
    <a href="stats.php" class="ni"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg><span>Статистика</span></a>
    <a href="actions.php" class="ni"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg><span>Ръчно</span></a>
</nav>

<script>
const chatArea = document.getElementById('chatArea');
const typing = document.getElementById('typing');

function sendMsg(txt) {
    if(!txt.trim()) return;
    addMsg(txt, 'user');
    document.getElementById('chatInput').value = '';
    typing.style.display = 'block';
    
    fetch('chat-send.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({message: txt})
    })
    .then(res => res.json())
    .then(data => {
        typing.style.display = 'none';
        addMsg(data.reply, 'ai');
    });
}

function addMsg(txt, role) {
    const g = document.createElement('div');
    g.className = 'msg-group';
    g.innerHTML = `<div class="msg ${role}">${txt}</div>`;
    chatArea.insertBefore(g, typing);
    chatArea.scrollTop = chatArea.scrollHeight;
}
</script>
</body>
</html>
