<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>D520BT Test — RunMyStore</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, system-ui, "Segoe UI", Arial, sans-serif;
    background: #0a0a0f; color: #e5e7eb;
    padding: 16px; padding-bottom: 80px;
    min-height: 100vh;
  }
  h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; color: #818cf8; }
  .sub { font-size: 13px; color: #94a3b8; margin-bottom: 20px; }
  .step {
    background: #1e1e2e; border: 1px solid #2d2d44; border-radius: 12px;
    padding: 14px; margin-bottom: 12px;
  }
  .step-num {
    display: inline-block; background: #6366f1; color: #fff;
    width: 28px; height: 28px; border-radius: 50%;
    text-align: center; line-height: 28px; font-weight: 700; margin-right: 8px;
  }
  .step h3 { font-size: 16px; font-weight: 700; display: inline-block; }
  .step p { font-size: 13px; color: #cbd5e1; margin: 8px 0 12px; line-height: 1.5; }
  .step button {
    width: 100%; padding: 14px; border: 0; border-radius: 8px;
    font-size: 15px; font-weight: 700; cursor: pointer;
    background: #6366f1; color: #fff;
  }
  .step button.danger { background: #ef4444; }
  .step button.warn { background: #f59e0b; }
  .step button:active { opacity: 0.7; }
  .out {
    margin-top: 10px; padding: 8px; border-radius: 6px;
    background: #0f0f18; font-size: 11px; color: #86efac;
    font-family: Menlo, Consolas, monospace; white-space: pre-wrap;
    max-height: 180px; overflow: auto;
    display: none;
  }
  .out.show { display: block; }
  .out.err { color: #fca5a5; }
  .footer {
    position: fixed; bottom: 0; left: 0; right: 0;
    padding: 12px; background: #0a0a0f; border-top: 1px solid #2d2d44;
    text-align: center; font-size: 11px; color: #64748b;
  }
</style>
</head>
<body>
<h1>D520BT Тест Панел</h1>
<div class="sub">S95 — Phomemo raster driver. Натискай бутоните по ред 1 → 5.</div>

<div class="step">
  <span class="step-num">1</span><h3>Forget + Pair наново</h3>
  <p>Изтрива стария paired принтер и pair-ва наново.</p>
  <button onclick="run('forgetAndPair', this)">Forget + Pair</button>
  <div class="out" id="out1"></div>
</div>

<div class="step">
  <span class="step-num">2</span><h3>Info — какво е paired</h3>
  <p>Показва type, service, writeChar.</p>
  <button onclick="run('info', this)">Покажи info</button>
  <div class="out" id="out2"></div>
</div>

<div class="step">
  <span class="step-num">3</span><h3>Wakeup test (само headers)</h3>
  <p>7 wakeup пакета. НЕ печата — само LED/звук.</p>
  <button onclick="run('wakeup', this)" class="warn">Wakeup</button>
  <div class="out" id="out3"></div>
</div>

<div class="step">
  <span class="step-num">4</span><h3>Minimal black block</h3>
  <p>8x64px черен блок. Ако излезе нещо черно — protocol работи.</p>
  <button onclick="run('minimal', this)" class="warn">Minimal black</button>
  <div class="out" id="out4"></div>
</div>

<div class="step">
  <span class="step-num">5</span><h3>Пълен label тест</h3>
  <p>50x30mm етикет с store/product/price/code/barcode.</p>
  <button onclick="run('fullTest', this)">Пълен label</button>
  <div class="out" id="out5"></div>
</div>

<div class="step">
  <span class="step-num">!</span><h3>Show debug overlay</h3>
  <p>Извиква on-screen overlay-а с пълните logs.</p>
  <button onclick="run('showOverlay', this)">Покажи overlay</button>
</div>

<div class="footer">D520BT Test • CapPrinter._diagnostics</div>

<script src="/js/capacitor-printer.js"></script>
<script>
function show(id, text, isErr) {
  var el = document.getElementById('out' + id);
  if (!el) return;
  el.textContent = text;
  el.className = 'out show' + (isErr ? ' err' : '');
}

async function run(action, btn) {
  if (!window.CapPrinter) {
    alert('CapPrinter не е зареден — отвори страницата вътре в RunMyStore APK!');
    return;
  }
  var origText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Чакай...';

  try {
    var result;
    if (action === 'forgetAndPair') {
      window.CapPrinter.forget();
      result = await window.CapPrinter.pair();
      show('1', 'OK paired deviceId: ' + result + '\n(виж overlay-а за type)');
    }
    else if (action === 'info') {
      result = window.CapPrinter._diagnostics.info();
      show('2', JSON.stringify(result, null, 2));
    }
    else if (action === 'wakeup') {
      result = await window.CapPrinter._diagnostics.d520Wakeup();
      show('3', 'OK bytes: ' + result.bytes + '\nГледай LED/звук на принтера');
    }
    else if (action === 'minimal') {
      result = await window.CapPrinter._diagnostics.d520Minimal();
      show('4', 'OK bytes: ' + result.bytes + '\nГледай хартията');
    }
    else if (action === 'fullTest') {
      result = await window.CapPrinter._diagnostics.d520Test();
      show('5', 'OK bytes: ' + result.bytes + '\nГледай хартията');
    }
    else if (action === 'showOverlay') {
      window.CapPrinter.showDebugOverlay('--- Manual overlay open ---');
    }
  } catch (e) {
    var msg = (e && e.message) ? e.message : String(e);
    var btnOut = btn.parentElement.querySelector('.out');
    if (btnOut) {
      btnOut.textContent = 'ГРЕШКА: ' + msg;
      btnOut.className = 'out show err';
    } else {
      alert('Грешка: ' + msg);
    }
  } finally {
    btn.disabled = false;
    btn.textContent = origText;
  }
}
</script>
</body>
</html>
