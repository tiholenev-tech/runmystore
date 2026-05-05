<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['tenant_id'])) { header('Location: /login.php'); exit; }
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>D520BT Classic SPP Test — RunMyStore</title>
<?php require __DIR__ . '/../includes/capacitor-head.php'; ?>
<script src="/js/capacitor-printer.js"></script>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,system-ui,Arial,sans-serif;background:#0a0a0f;color:#e5e7eb;padding:16px;padding-bottom:80px;min-height:100vh}
  h1{font-size:20px;color:#818cf8;margin-bottom:4px}
  .sub{font-size:13px;color:#94a3b8;margin-bottom:20px}
  .step{background:#1e1e2e;border:1px solid #2d2d44;border-radius:12px;padding:14px;margin-bottom:12px}
  .step h3{font-size:15px;margin-bottom:6px;color:#a5b4fc}
  .step p{font-size:13px;color:#cbd5e1;margin-bottom:10px;line-height:1.4}
  button{display:block;width:100%;padding:12px;border:0;border-radius:10px;background:#6366f1;color:#fff;font-weight:700;font-size:14px;cursor:pointer;margin-bottom:8px}
  button.sec{background:#374151}
  button.warn{background:#dc2626}
  button:disabled{opacity:.4;cursor:not-allowed}
  textarea{width:100%;background:#0f0f1a;color:#e5e7eb;border:1px solid #2d2d44;border-radius:8px;padding:8px;font-family:monospace;font-size:12px;min-height:80px;margin-bottom:8px}
  .out{background:#0f0f1a;border:1px solid #2d2d44;border-radius:8px;padding:8px;font-family:monospace;font-size:11px;white-space:pre-wrap;word-break:break-all;max-height:140px;overflow:auto;display:none}
  .out.show{display:block}
  .out.err{border-color:#dc2626;color:#fca5a5}
  .out.ok{border-color:#10b981;color:#86efac}
  a.back{display:inline-block;color:#a5b4fc;text-decoration:none;margin-bottom:12px;font-size:13px}
  .footer{font-size:11px;color:#64748b;margin-top:20px;text-align:center}
</style>
</head>
<body>

<a href="/printer-setup.php" class="back">← Към настройки</a>
<h1>🔧 D520BT Classic SPP Test</h1>
<div class="sub">Standalone debug страница за raw TSPL / SPP сесия. Изисква D520 вече сдвоен от printer-setup.</div>

<div class="step">
  <h3>1. Connect / Status</h3>
  <p>Connect отваря RFCOMM socket-а към D520. Reusable connection между prints.</p>
  <button data-act="info">ℹ️ Info (paired adresses, active type)</button>
  <button data-act="sppList">📋 Bonded Classic devices</button>
  <button data-act="connect">🔗 Connect D520 SPP</button>
  <button data-act="disconnect" class="sec">🔌 Disconnect</button>
  <div class="out" id="out1"></div>
</div>

<div class="step">
  <h3>2. Send raw TSPL</h3>
  <p>Editor below. Натисни <b>"Send raw"</b> → бутонът ще пусне точно тези байтове през SPP write (UTF-8 string-encoded; non-ASCII ще бъде corruptено — стой ASCII-only).</p>
  <textarea id="tspl">SIZE 50 mm,30 mm
GAP 3 mm,0
DIRECTION 1
DENSITY 11
SPEED 4
CLS
TEXT 10,10,"3",0,1,1,"D520 RAW TSPL"
TEXT 10,40,"4",0,2,2,"99.99 EUR"
TEXT 10,110,"2",0,1,1,"S96 Phase 3"
PRINT 1
</textarea>
  <button data-act="sendRaw">📤 Send raw TSPL</button>
  <div class="out" id="out2"></div>
</div>

<div class="step">
  <h3>3. Send hex</h3>
  <p>За binary опити (frame wrapping S96.D520.4). <b>Внимание:</b> @e-is plugin прави <code>getBytes(UTF_8)</code> на string-а, така че байтове ≥0x80 се корумпират по линията. Hex полето долу е „best effort" чрез Latin-1 string mapping; за реално binary трябва patch-нат plugin.</p>
  <textarea id="hex">7E 01 00 00 41 7E</textarea>
  <button data-act="sendHex">📤 Send hex</button>
  <div class="out" id="out3"></div>
</div>

<div class="step">
  <h3>4. Print test label (full pipeline)</h3>
  <p>Минава през <code>generateTSPL_D520</code> + <code>writeSPP_D520</code> (същия flow като production print).</p>
  <button data-act="testLabel" class="warn">🏷 Print test label</button>
  <div class="out" id="out4"></div>
</div>

<div class="step">
  <h3>5. Get version (read response)</h3>
  <p>Опитва TSPL <code>~!T</code> command (returns model). Полезно ако D520BT отговаря на TSPL queries.</p>
  <button data-act="getVersion" class="sec">🆔 Get version (~!T)</button>
  <div class="out" id="out5"></div>
</div>

<div class="footer">D520BT Classic SPP Test · S96.D520.6</div>

<script>
function show(outId, text, cls){
  var o = document.getElementById(outId);
  o.textContent = text;
  o.className = 'out show ' + (cls || '');
}
function err(outId, e){
  show(outId, 'ГРЕШКА: ' + ((e && e.message) || e), 'err');
}
function ok(outId, msg){
  show(outId, msg, 'ok');
}

function spp(){
  if (!window.Capacitor || !window.Capacitor.Plugins || !window.Capacitor.Plugins.BluetoothSerial) {
    throw new Error('BluetoothSerial плъгин не е зареден (трябва нов APK build от S96.D520.1)');
  }
  return window.Capacitor.Plugins.BluetoothSerial;
}

// Hex string "7E 01 00 7E" → Uint8Array
function parseHex(s){
  var clean = String(s || '').replace(/[^0-9a-fA-F]/g, '');
  if (clean.length % 2) throw new Error('Hex must have even length');
  var out = new Uint8Array(clean.length / 2);
  for (var i = 0; i < out.length; i++) out[i] = parseInt(clean.substr(i*2, 2), 16);
  return out;
}

document.body.addEventListener('click', async function(ev){
  var btn = ev.target.closest('button[data-act]');
  if (!btn) return;
  var act = btn.dataset.act;
  btn.disabled = true;
  var orig = btn.textContent;
  btn.textContent = '...';

  try {
    if (act === 'info') {
      var info = window.CapPrinter._diagnostics.info();
      ok('out1', JSON.stringify(info, null, 2));
    }
    else if (act === 'sppList') {
      var devs = await window.CapPrinter.sppListDebug();
      ok('out1', 'Found ' + devs.length + ' bonded devices:\n' +
        devs.map(function(d){ return '• ' + (d.name || '(no name)') + '  ' + d.address; }).join('\n'));
    }
    else if (act === 'connect') {
      var addr = window.CapPrinter._getD520Address();
      if (!addr) throw new Error('Няма сдвоен D520 — отиди в printer-setup.php');
      await spp().connect({ address: addr });
      window.__sppConnected = true; window.__sppConnectedAddr = addr;
      ok('out1', 'Свързан към ' + addr);
    }
    else if (act === 'disconnect') {
      var addr2 = window.CapPrinter._getD520Address();
      if (addr2) await spp().disconnect({ address: addr2 });
      window.__sppConnected = false;
      ok('out1', 'Disconnected');
    }
    else if (act === 'sendRaw') {
      var addr3 = window.CapPrinter._getD520Address();
      if (!addr3) throw new Error('Няма сдвоен D520');
      var txt = document.getElementById('tspl').value;
      var bytes = window.CapPrinter._asciiToBytes(txt);
      await window.CapPrinter._writeSPP_D520(addr3, bytes);
      ok('out2', 'Изпратени ' + bytes.length + ' bytes (ASCII)');
    }
    else if (act === 'sendHex') {
      var addr4 = window.CapPrinter._getD520Address();
      if (!addr4) throw new Error('Няма сдвоен D520');
      var bytes2 = parseHex(document.getElementById('hex').value);
      // Latin-1 string mapping (best-effort; will be corrupted for ≥0x80 by plugin's UTF-8).
      var s = '';
      for (var i = 0; i < bytes2.length; i++) s += String.fromCharCode(bytes2[i] & 0xFF);
      await spp().connect({ address: addr4 }).catch(function(){});
      await spp().write({ address: addr4, value: s });
      ok('out3', 'Изпратени ' + bytes2.length + ' bytes (hex; ≥0x80 likely corrupted)');
    }
    else if (act === 'testLabel') {
      var p = { code: 'D520-T', name: 'Test D520', retail_price: 99.99, barcode: '0000000000017' };
      var st = { name: 'RunMyStore', currency: 'EUR' };
      var res = await window.CapPrinter._printD520
        ? await window.CapPrinter._printD520(p, st, 1)
        : await window.CapPrinter.print(p, st, 1, { type: 'D520' });
      ok('out4', JSON.stringify(res));
    }
    else if (act === 'getVersion') {
      var addr5 = window.CapPrinter._getD520Address();
      if (!addr5) throw new Error('Няма сдвоен D520');
      await spp().connect({ address: addr5 }).catch(function(){});
      await spp().write({ address: addr5, value: '~!T\r\n' });
      // Read with timeout
      var resp = await Promise.race([
        spp().read({ address: addr5 }),
        new Promise(function(_, rej){ setTimeout(function(){ rej(new Error('read timeout 3s')); }, 3000); })
      ]);
      ok('out5', 'Response: ' + JSON.stringify(resp));
    }
  } catch (e) {
    var outId = btn.closest('.step').querySelector('.out').id;
    err(outId, e);
  } finally {
    btn.disabled = false;
    btn.textContent = orig;
  }
});
</script>

</body>
</html>
