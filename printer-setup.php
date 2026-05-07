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
<script src="js/capacitor-printer.js?v=<?= @filemtime(__DIR__.'/js/capacitor-printer.js') ?>"></script>
<style>
:root{--bg:#030712;--card:rgba(15,15,40,.75);--indigo:#818cf8;--text:#fff;--muted:#8b92b0;--ok:#10b981;--err:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:-apple-system,sans-serif;min-height:100vh;padding:20px;padding-bottom:calc(80px + env(safe-area-inset-bottom))}
.h{font-size:22px;font-weight:800;margin-bottom:6px;background:linear-gradient(135deg,hsl(240 70% 65%),hsl(280 70% 60%));-webkit-background-clip:text;background-clip:text;color:transparent}
.sub{color:var(--muted);font-size:14px;margin-bottom:24px}
.back{display:inline-block;color:var(--indigo);text-decoration:none;margin-bottom:16px;font-size:14px}
.card{background:var(--card);border:1px solid rgba(99,102,241,0.25);border-radius:var(--radius);padding:20px;margin-bottom:16px;backdrop-filter:blur(8px);box-shadow:0 0 40px rgba(99,102,241,0.1)}
.card h3{font-size:16px;margin-bottom:8px;font-weight:700}
.card p{color:var(--muted);font-size:13px;line-height:1.5;margin-bottom:12px}
.st{display:flex;align-items:center;gap:10px;padding:12px;background:rgba(0,0,0,0.25);border-radius:var(--radius);margin-bottom:8px}
.dot{width:10px;height:10px;border-radius:50%;background:var(--muted);flex-shrink:0}
.dot.ok{background:var(--ok);box-shadow:0 0 8px var(--ok)}
.dot.err{background:var(--err)}
.st .l{font-size:13px;line-height:1.3}
.st .l b{font-weight:700}
.st .l span{color:var(--muted);font-size:12px;display:block}
.btn{width:100%;padding:14px;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#6366f1,hsl(258 91% 76%));color:#fff;margin-bottom:10px;transition:opacity 0.2s}
.btn:active{opacity:0.7}
.btn.sec{background:rgba(99,102,241,0.15);color:var(--indigo);border:1px solid rgba(99,102,241,0.3)}
.btn.danger{background:rgba(239,68,68,0.15);color:var(--err);border:1px solid rgba(239,68,68,0.3)}
.btn:disabled{opacity:0.4;cursor:not-allowed}
.active-toggle{display:flex;gap:8px;margin-top:8px}
.active-toggle .btn{margin:0;padding:10px;font-size:13px}
.active-toggle .btn.on{background:linear-gradient(135deg,#10b981,hsl(160 84% 52%))}
#log{font-size:12px;color:var(--muted);padding:10px;background:rgba(0,0,0,0.25);border-radius:var(--radius);margin-top:10px;min-height:40px;font-family:monospace;white-space:pre-wrap;max-height:200px;overflow-y:auto}
.warn{background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);padding:12px;border-radius:var(--radius);font-size:13px;color:hsl(43 96% 56%);margin-bottom:16px}
.hint{font-size:12px;color:var(--muted);margin-top:8px;padding:8px;background:rgba(99,102,241,0.08);border-left:3px solid rgba(99,102,241,0.4);border-radius:var(--radius-sm);line-height:1.4}


/* ── S106: BICHROMATIC theme support (auto-injected) ── */
[data-theme="light"] body{background:var(--bg);color:var(--text)}
[data-theme="light"] .glass{background:var(--surface,rgba(255,255,255,.6));border-color:var(--border-color,rgba(0,0,0,.06))}
[data-theme="light"] h1,[data-theme="light"] h2,[data-theme="light"] h3{color:var(--text)}
[data-theme="dark"] body{background:var(--bg);color:var(--text)}
[data-theme="dark"] .glass{background:var(--surface,rgba(20,22,30,.55))}

@media (prefers-reduced-motion: reduce){
  *{transition:none!important;animation:none!important}
}

/* glass content stays above shine/glow spans */
.glass > *:not(.shine):not(.glow){position:relative;z-index:5}
</style>
</head>
<body>

<a href="chat.php" class="back">← Назад</a>

<div class="h">🖨 Настройка на принтер</div>
<div class="sub">DTM-5811 (BLE) и D520BT (Bluetooth Classic) · 50×30mm етикети</div>

<div id="notMobile" class="warn" style="display:none">
⚠️ Тази страница работи само в мобилното приложение RunMyStore. В браузър на компютър печатът е чрез обикновен принтер.
</div>

<div class="card">
  <h3>Статус</h3>
  <div class="st"><span class="dot" id="dotEnv"></span><div class="l" id="txtEnv">Проверка...</div></div>
  <div class="st"><span class="dot" id="dotDtm"></span><div class="l"><b>DTM-5811</b> · BLE<span id="txtDtm">Проверка...</span></div></div>
  <div class="st"><span class="dot" id="dotD520"></span><div class="l"><b>D520BT</b> · Bluetooth Classic<span id="txtD520">Проверка...</span></div></div>
  <div id="activeRow" style="display:none">
    <p style="font-size:13px;margin:8px 0 4px">Активен принтер за печат:</p>
    <div class="active-toggle">
      <button class="btn sec" id="btnActiveDtm">DTM-5811</button>
      <button class="btn sec" id="btnActiveD520">D520BT</button>
    </div>
  </div>
</div>

<div class="card">
  <h3>1a. DTM-5811 (BLE)</h3>
  <p>Първият принтер. Натисни "Сдвои DTM" и избери DTM-5811 от списъка. Прави се само веднъж.</p>
  <button class="btn" id="btnPairDtm">🔗 Сдвои DTM-5811</button>
  <button class="btn danger" id="btnForgetDtm" style="display:none">❌ Забрави DTM</button>
</div>

<div class="card">
  <h3>1b. D520BT (Bluetooth Classic)</h3>
  <p>Вторият принтер. <b>Първо го сдвои от Android Settings → Bluetooth</b> (PIN 0000 ако пита). Чак след това натисни "Сдвои D520BT" тук.</p>
  <button class="btn" id="btnPairD520">🔗 Сдвои D520BT</button>
  <button class="btn danger" id="btnForgetD520" style="display:none">❌ Забрави D520BT</button>
  <div class="hint">D520BT работи през Bluetooth Classic (RFCOMM/SPP), не през BLE. Не може да се намери директно в това приложение, докато не е сдвоен системно.</div>
</div>

<div class="card">
  <h3>2. Тест</h3>
  <p>Включи активния принтер. Натисни "Тестов печат" — ще излезе етикет "Тестова етикетка 12.34 EUR".</p>
  <button class="btn sec" id="btnTest">📄 Тестов печат</button>
</div>

<div id="log">Log:</div>

<div class="card" style="margin-top:24px;border-color:rgba(245,158,11,0.3);background:rgba(245,158,11,0.05)">
  <h3 style="color:hsl(43 96% 56%)">DEBUG</h3>
  <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);cursor:pointer;margin-bottom:8px">
    <input type="checkbox" id="dbgToggle" style="width:18px;height:18px"> Показвай debug
  </label>
  <div id="dbgPanel" style="display:none">
    <p style="font-size:12px">Резултатите се показват директно на екрана (fullscreen overlay) с бутон "Копирай всичко".</p>
    <button class="btn sec" id="btnScanAll">🔍 BLE scan (10s)</button>
    <button class="btn sec" id="btnPairDebug">🔗 BLE pair и анализирай</button>
    <button class="btn sec" id="btnSppList">📋 SPP paired list</button>
    <button class="btn sec" id="btnInfo">ℹ️ Info</button>
  </div>
</div>

<script>
var $ = function(id){return document.getElementById(id)};
var logEl = $('log');
function log(msg){logEl.textContent += '\n' + msg; logEl.scrollTop = logEl.scrollHeight; console.log('[printer]', msg)}

function refreshStatus(){
  var isMobile = window.CapPrinter && window.CapPrinter.isAvailable();
  $('dotEnv').className = 'dot ' + (isMobile ? 'ok' : 'err');
  $('txtEnv').innerHTML = isMobile
    ? '<b>Мобилно приложение ✓</b>'
    : '<b>Не си в мобилно приложение</b>';

  if (!isMobile) {
    $('notMobile').style.display = 'block';
    ['btnPairDtm','btnPairD520','btnTest','btnForgetDtm','btnForgetD520'].forEach(function(id){ $(id).disabled = true; });
    $('txtDtm').textContent = '';
    $('txtD520').textContent = '';
    return;
  }

  var dtm  = window.CapPrinter.hasPairedDTM();
  var d520 = window.CapPrinter.hasPairedD520();
  var active = window.CapPrinter.getActiveType();

  $('dotDtm').className = 'dot ' + (dtm ? 'ok' : '');
  $('txtDtm').innerHTML = dtm ? '<span style="color:var(--ok)">сдвоен ✓</span>' : '<span>не сдвоен</span>';
  $('btnForgetDtm').style.display = dtm ? 'block' : 'none';

  $('dotD520').className = 'dot ' + (d520 ? 'ok' : '');
  $('txtD520').innerHTML = d520 ? '<span style="color:var(--ok)">сдвоен ✓</span>' : '<span>не сдвоен</span>';
  $('btnForgetD520').style.display = d520 ? 'block' : 'none';

  // Active selector — show only when both paired.
  if (dtm && d520) {
    $('activeRow').style.display = 'block';
    $('btnActiveDtm').className  = 'btn ' + (active === 'DTM'  ? 'on' : 'sec');
    $('btnActiveD520').className = 'btn ' + (active === 'D520' ? 'on' : 'sec');
  } else {
    $('activeRow').style.display = 'none';
  }

  $('btnTest').disabled = !(dtm || d520);
}

$('btnPairDtm').addEventListener('click', async function(){
  try {
    log('Стартирам сдвояване DTM-5811...');
    $('btnPairDtm').disabled = true;
    var r = await window.CapPrinter.pairDTM();
    log('✓ DTM сдвоен: ' + (r.name || r.deviceId));
    refreshStatus();
  } catch(e) {
    log('✗ DTM грешка: ' + (e.message || e));
  } finally {
    $('btnPairDtm').disabled = false;
  }
});

$('btnForgetDtm').addEventListener('click', function(){
  window.CapPrinter.forgetDTM();
  log('DTM забравен');
  refreshStatus();
});

$('btnPairD520').addEventListener('click', async function(){
  try {
    log('Търся D520BT в системните Bluetooth Classic...');
    $('btnPairD520').disabled = true;
    var r = await window.CapPrinter.pairD520();
    log('✓ D520 сдвоен: ' + (r.name || r.address));
    refreshStatus();
  } catch(e) {
    log('✗ D520 грешка: ' + (e.message || e));
  } finally {
    $('btnPairD520').disabled = false;
  }
});

$('btnForgetD520').addEventListener('click', function(){
  window.CapPrinter.forgetD520();
  log('D520 забравен');
  refreshStatus();
});

$('btnActiveDtm').addEventListener('click', function(){
  window.CapPrinter.setActiveType('DTM');
  log('Активен: DTM-5811');
  refreshStatus();
});

$('btnActiveD520').addEventListener('click', function(){
  window.CapPrinter.setActiveType('D520');
  log('Активен: D520BT');
  refreshStatus();
});

$('btnTest').addEventListener('click', async function(){
  try {
    log('Тестов печат на ' + (window.CapPrinter.getActiveType() || '?') + '...');
    $('btnTest').disabled = true;
    var r = await window.CapPrinter.test();
    log('✓ Готово: ' + r.bytes + ' байта на ' + r.type);
  } catch(e) {
    log('✗ Грешка: ' + (e.message || e));
  } finally {
    refreshStatus();
  }
});

$('dbgToggle').addEventListener('change', function(){
  $('dbgPanel').style.display = this.checked ? 'block' : 'none';
});

$('btnScanAll').addEventListener('click', async function(){
  try {
    log('BLE scan (10s)...');
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
    log('BLE pair+analyze...');
    $('btnPairDebug').disabled = true;
    var r = await window.CapPrinter.pairDebug();
    log('Done: ' + (r.name || r.deviceId));
  } catch(e) {
    log('PairDebug error: ' + (e.message || e));
  } finally {
    $('btnPairDebug').disabled = false;
  }
});

$('btnSppList').addEventListener('click', async function(){
  try {
    log('SPP paired list...');
    $('btnSppList').disabled = true;
    var devs = await window.CapPrinter.sppListDebug();
    log('SPP devices: ' + devs.length);
  } catch(e) {
    log('SPP error: ' + (e.message || e));
  } finally {
    $('btnSppList').disabled = false;
  }
});

$('btnInfo').addEventListener('click', function(){
  try {
    var info = window.CapPrinter._diagnostics.info();
    log('Info: ' + JSON.stringify(info));
  } catch(e) {
    log('Info error: ' + (e.message || e));
  }
});

refreshStatus();
window.addEventListener('capacitor-ready', refreshStatus);
setTimeout(refreshStatus, 800);
</script>

</body>
</html>
