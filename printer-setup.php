<?php
session_start();
require_once __DIR__ . '/config/database.php';
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>Принтер · RunMyStore</title>
<?php require __DIR__ . '/includes/capacitor-head.php'; ?>
<script src="js/capacitor-printer.js"></script>
<style>
:root{--bg:#030712;--card:rgba(15,15,40,.75);--indigo:#818cf8;--text:#fff;--muted:#8b92b0;--ok:#10b981;--err:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,sans-serif;min-height:100vh;padding:20px;padding-bottom:calc(80px + env(safe-area-inset-bottom))}
.h{font-size:22px;font-weight:800;margin-bottom:6px;background:linear-gradient(135deg,hsl(240 70% 65%),hsl(280 70% 60%));-webkit-background-clip:text;background-clip:text;color:transparent}
.sub{color:var(--muted);font-size:14px;margin-bottom:24px}
.back{display:inline-block;color:var(--indigo);text-decoration:none;margin-bottom:16px;font-size:14px}
.card{background:var(--card);border:1px solid rgba(99,102,241,0.25);border-radius:20px;padding:20px;margin-bottom:16px;backdrop-filter:blur(8px);box-shadow:0 0 40px rgba(99,102,241,0.1)}
.card h3{font-size:16px;margin-bottom:8px;font-weight:700}
.card p{color:var(--muted);font-size:13px;line-height:1.5;margin-bottom:12px}
.st{display:flex;align-items:center;gap:10px;padding:12px;background:rgba(0,0,0,0.25);border-radius:12px;margin-bottom:12px}
.dot{width:10px;height:10px;border-radius:50%;background:var(--muted)}
.dot.ok{background:var(--ok);box-shadow:0 0 8px var(--ok)}
.dot.err{background:var(--err)}
.btn{width:100%;padding:14px;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#6366f1,#a78bfa);color:#fff;margin-bottom:10px;transition:opacity 0.2s}
.btn:active{opacity:0.7}
.btn.sec{background:rgba(99,102,241,0.15);color:var(--indigo);border:1px solid rgba(99,102,241,0.3)}
.btn.danger{background:rgba(239,68,68,0.15);color:var(--err);border:1px solid rgba(239,68,68,0.3)}
.btn:disabled{opacity:0.4;cursor:not-allowed}
#log{font-size:12px;color:var(--muted);padding:10px;background:rgba(0,0,0,0.25);border-radius:10px;margin-top:10px;min-height:40px;font-family:monospace;white-space:pre-wrap;max-height:200px;overflow-y:auto}
.warn{background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);padding:12px;border-radius:12px;font-size:13px;color:#fbbf24;margin-bottom:16px}
</style>
</head>
<body>

<a href="chat.php" class="back">← Назад</a>

<div class="h">🖨 Настройка на принтер</div>
<div class="sub">DTM-5811 · Bluetooth · термо етикети 50×30mm</div>

<div id="notMobile" class="warn" style="display:none">
⚠️ Тази страница работи само в мобилното приложение RunMyStore. В браузър на компютър печатът е чрез обикновен принтер.
</div>

<div class="card">
  <h3>Статус</h3>
  <div class="st"><span class="dot" id="dotEnv"></span><span id="txtEnv">Проверка...</span></div>
  <div class="st"><span class="dot" id="dotPair"></span><span id="txtPair">Проверка...</span></div>
</div>

<div class="card">
  <h3>1. Сдвояване</h3>
  <p>Натисни "Сдвои" и избери DTM-5811 от списъка. Прави се само веднъж. След това принтерът се разпознава автоматично.</p>
  <p style="font-size:12px;color:var(--muted);margin-top:-4px">Подкрепяни принтери: DTM-5811 и съвместими TSPL модели.</p>
  <button class="btn" id="btnPair">🔗 Сдвои DTM-5811</button>
  <button class="btn danger" id="btnForget" style="display:none">❌ Забрави принтера</button>
</div>

<div class="card">
  <h3>2. Тест</h3>
  <p>Включи принтера. Натисни "Тестов печат" — ще излезе етикет "RunMyStore TEST 12.34 lv".</p>
  <button class="btn sec" id="btnTest">📄 Тестов печат</button>
</div>

<div id="log">Log:</div>

<!-- ═══ S87.D520BT.HUNT — DEBUG секция (премахни след като намерим UUID) ═══ -->
<div class="card" style="margin-top:24px;border-color:rgba(245,158,11,0.3);background:rgba(245,158,11,0.05)">
  <h3 style="color:#fbbf24">DEBUG (S87)</h3>
  <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);cursor:pointer;margin-bottom:8px">
    <input type="checkbox" id="dbgToggle" style="width:18px;height:18px"> Показвай debug
  </label>
  <div id="dbgPanel" style="display:none">
    <p style="font-size:12px">Резултатите се показват директно на екрана (fullscreen overlay) с бутон "Копирай всичко".</p>
    <button class="btn sec" id="btnScanAll">🔍 Сканирай всички BT (10s)</button>
    <button class="btn sec" id="btnPairDebug">🔗 Pair и анализирай</button>
  </div>
</div>

<script>
var $ = function(id){return document.getElementById(id)};
var logEl = $('log');
function log(msg){logEl.textContent += '\n' + msg; logEl.scrollTop = logEl.scrollHeight; console.log('[printer]', msg)}

function refreshStatus(){
  var isMobile = window.CapPrinter && window.CapPrinter.isAvailable();
  $('dotEnv').className = 'dot ' + (isMobile ? 'ok' : 'err');
  $('txtEnv').textContent = isMobile ? 'Мобилно приложение ✓' : 'Не си в мобилно приложение';
  
  if (!isMobile) {
    $('notMobile').style.display = 'block';
    $('btnPair').disabled = true;
    $('btnTest').disabled = true;
    return;
  }
  
  var paired = window.CapPrinter.hasPairedPrinter();
  $('dotPair').className = 'dot ' + (paired ? 'ok' : '');
  $('txtPair').textContent = paired ? 'Принтерът е сдвоен ✓' : 'Принтерът не е сдвоен';
  $('btnForget').style.display = paired ? 'block' : 'none';
  $('btnTest').disabled = !paired;
}

$('btnPair').addEventListener('click', async function(){
  try {
    log('Стартиране сдвояване...');
    $('btnPair').disabled = true;
    var r = await window.CapPrinter.pair();
    log('✓ Сдвоен: ' + (r.name || r.deviceId));
    refreshStatus();
  } catch(e) {
    log('✗ Грешка: ' + (e.message || e));
  } finally {
    $('btnPair').disabled = false;
  }
});

$('btnForget').addEventListener('click', function(){
  window.CapPrinter.forget();
  log('Принтерът е забравен');
  refreshStatus();
});

$('btnTest').addEventListener('click', async function(){
  try {
    log('Тестов печат...');
    $('btnTest').disabled = true;
    var r = await window.CapPrinter.test();
    log('✓ Готово: ' + r.bytes + ' байта изпратени');
  } catch(e) {
    log('✗ Грешка: ' + (e.message || e));
  } finally {
    refreshStatus();
  }
});

// ═══ S87.D520BT.HUNT — DEBUG handlers ═══
$('dbgToggle').addEventListener('change', function(){
  $('dbgPanel').style.display = this.checked ? 'block' : 'none';
});

$('btnScanAll').addEventListener('click', async function(){
  try {
    log('Scan starting (10s)...');
    $('btnScanAll').disabled = true;
    var n = await window.CapPrinter.scanDebug(10000);
    log('Scan complete. ' + n + ' устройства видени.');
  } catch(e) {
    log('Scan error: ' + (e.message || e));
  } finally {
    $('btnScanAll').disabled = false;
  }
});

$('btnPairDebug').addEventListener('click', async function(){
  try {
    log('Pair+analyze...');
    $('btnPairDebug').disabled = true;
    var r = await window.CapPrinter.pairDebug();
    log('Done: ' + (r.name || r.deviceId));
  } catch(e) {
    log('PairDebug error: ' + (e.message || e));
  } finally {
    $('btnPairDebug').disabled = false;
  }
});


// Init: refresh now, on capacitor-ready event, and after a safety delay
refreshStatus();
window.addEventListener('capacitor-ready', refreshStatus);
setTimeout(refreshStatus, 800);
</script>

</body>
</html>
