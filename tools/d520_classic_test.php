<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['tenant_id'])) { header('Location: /login.php'); exit; }
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>D520BT Diagnostics — RunMyStore</title>
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
  button.ok{background:#10b981}
  button.alt{background:#f59e0b}
  button:disabled{opacity:.4;cursor:not-allowed}
  .out{background:#0f0f1a;border:1px solid #2d2d44;border-radius:8px;padding:8px;font-family:monospace;font-size:11px;white-space:pre-wrap;word-break:break-all;max-height:140px;overflow:auto;display:none}
  .out.show{display:block}
  .out.err{border-color:#dc2626;color:#fca5a5}
  .out.ok{border-color:#10b981;color:#86efac}
  a.back{display:inline-block;color:#a5b4fc;text-decoration:none;margin-bottom:12px;font-size:13px}
  .footer{font-size:11px;color:#64748b;margin-top:20px;text-align:center}
  .hint{font-size:12px;color:#fbbf24;background:rgba(245,158,11,0.1);border-left:3px solid #f59e0b;padding:8px;margin-bottom:8px;line-height:1.4}
</style>
</head>
<body>

<a href="/printer-setup.php" class="back">← Към настройки</a>
<h1>🔧 D520BT Diagnostics (S97.FIX2)</h1>
<div class="sub">Триаж на йероглифите. Изпълни в реда: 1 → 2 → 3 → 4. Първото което проработи = пътя който да ползваме.</div>

<div class="hint">
⚠️ Преди тестове: D520BT pair-нат от Settings → Bluetooth (PIN 0000), приложението force-stopped + reopened (за да зареди новия JS).
</div>

<div class="step">
  <h3>0. Connect / info</h3>
  <button data-act="info" class="sec">ℹ️ Info (paired addresses)</button>
  <button data-act="connect">🔗 Connect SPP</button>
  <button data-act="disconnect" class="sec">🔌 Disconnect</button>
  <div class="out" id="out0"></div>
</div>

<div class="step">
  <h3>1. ASCII control test (CP437 / built-in latin)</h3>
  <p>Изпраща чисто ASCII TSPL без cyrillic, без BITMAP. Ако това не печата → принтерът не приема никакви TSPL команди през SPP.</p>
  <button data-act="testAscii">📤 Print "ABC 12.34"</button>
  <div class="out" id="out1"></div>
</div>

<div class="step">
  <h3>2. Solid BITMAP control (mode 0 vs mode 4)</h3>
  <p>Изпраща 8×8 dot всички "print" pixels. Ако излезе solid black square → BITMAP е приет. Ако mode 4 работи но 0 не → текущия generator проработва.</p>
  <button data-act="solidBmp0" class="alt">⬛ Solid 8×8 mode 0 (стария)</button>
  <button data-act="solidBmp4" class="ok">⬛ Solid 8×8 mode 4 (новия — Labelife)</button>
  <div class="out" id="out2"></div>
</div>

<div class="step">
  <h3>3. CYRILLIC test — BITMAP vs CP1251 TEXT</h3>
  <p>"Памук" rendered чрез всеки от 4-те метода. Кой се вижда правилно = решението.</p>
  <button data-act="cyrBmp0" class="alt">🅰 BITMAP mode 0</button>
  <button data-act="cyrBmp4" class="ok">🅱 BITMAP mode 4 (default now)</button>
  <button data-act="cyrCp1251" class="sec">🅲 CODEPAGE 1251 + TEXT</button>
  <button data-act="cyrCp1252" class="sec">🅳 CODEPAGE 1252 + TEXT</button>
  <div class="out" id="out3"></div>
</div>

<div class="step">
  <h3>4. Full label (current generator)</h3>
  <p>Минава през production <code>generateTSPL_D520</code>. Тества целия pipeline.</p>
  <button data-act="fullLabel" class="ok">🏷 Print full test label</button>
  <button data-act="fullLabelDual" class="ok">🏷 Print + dual EUR/BGN</button>
  <div class="out" id="out4"></div>
</div>

<div class="step">
  <h3>5. Raw advanced (for debug only)</h3>
  <textarea id="raw" style="width:100%;background:#0f0f1a;color:#e5e7eb;border:1px solid #2d2d44;border-radius:8px;padding:8px;font-family:monospace;font-size:12px;min-height:60px;margin-bottom:6px">SIZE 50 mm,30 mm
GAP 3.00 mm,0.00 mm
DENSITY 11
SPEED 4
CLS
TEXT 50,50,"3",0,1,1,"D520 RAW"
PRINT 1
</textarea>
  <button data-act="sendRaw" class="sec">📤 Send raw above</button>
  <div class="out" id="out5"></div>
</div>

<div class="footer">D520BT Diagnostics · S97.LABEL.D520_FIX2</div>

<script>
function show(outId, text, cls){
  var o = document.getElementById(outId);
  o.textContent = text;
  o.className = 'out show ' + (cls || '');
}
function err(outId, e){ show(outId, 'ГРЕШКА: ' + ((e && e.message) || e), 'err'); }
function ok(outId, msg){ show(outId, msg, 'ok'); }

function getSpp(){
  if (!window.Capacitor || !window.Capacitor.Plugins || !window.Capacitor.Plugins.BluetoothSerial) {
    throw new Error('BluetoothSerial плъгин не е зареден (нов APK build трябва от S97.D520_CYRILLIC)');
  }
  return window.Capacitor.Plugins.BluetoothSerial;
}
function getAddr(){
  var a = window.CapPrinter._getD520Address();
  if (!a) throw new Error('Няма сдвоен D520 — иди в printer-setup.php');
  return a;
}
async function ensureConnect(){
  var addr = getAddr();
  var spp = getSpp();
  if (!window.__sppConnected || window.__sppConnectedAddr !== addr) {
    try { await spp.connect({ address: addr }); }
    catch (_) { await spp.connectInsecure({ address: addr }); }
    window.__sppConnected = true;
    window.__sppConnectedAddr = addr;
  }
  return { addr, spp };
}

// Build a simple TSPL header (50×30mm)
function tsplHeader(extra){
  return 'SIZE 50 mm,30 mm\r\n' +
         'GAP 3.00 mm,0.00 mm\r\n' +
         'DIRECTION 0,0\r\n' +
         'DENSITY 11\r\n' +
         'SPEED 4\r\n' +
         'CLS\r\n' + (extra || '');
}

// Send a Uint8Array to D520 via SPP
async function sendBytes(bytes){
  var c = await ensureConnect();
  var s = window.CapPrinter._bytesToLatin1String(bytes);
  await c.spp.write({ address: c.addr, value: s });
}

// Send a TSPL string (ASCII only) — wraps with header if not already present
async function sendTsplString(tspl){
  var bytes = window.CapPrinter._asciiToBytes(tspl);
  await sendBytes(bytes);
}

// Build BITMAP command bytes: header (string) + raw raster + \r\n
function bmpCmd(x, y, widthBytes, height, mode, rasterBytes){
  var hdr = window.CapPrinter._asciiToBytes('BITMAP ' + x + ',' + y + ',' + widthBytes + ',' + height + ',' + mode + ',');
  var foot = window.CapPrinter._asciiToBytes('\r\n');
  return window.CapPrinter._concatBytes([hdr, rasterBytes, foot]);
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
      ok('out0', JSON.stringify(info, null, 2));
    }
    else if (act === 'connect') {
      await ensureConnect();
      ok('out0', 'Свързан към ' + getAddr());
    }
    else if (act === 'disconnect') {
      var a = getAddr();
      try { await getSpp().disconnect({ address: a }); } catch(_){}
      window.__sppConnected = false;
      ok('out0', 'Disconnected');
    }
    else if (act === 'testAscii') {
      var t = tsplHeader(
        'TEXT 20,30,"3",0,1,1,"ABC 12.34 EUR"\r\n' +
        'TEXT 20,80,"2",0,1,1,"D520 ASCII test"\r\n' +
        'PRINT 1\r\n'
      );
      await sendTsplString(t);
      ok('out1', 'Sent ' + t.length + ' bytes ASCII TSPL');
    }
    else if (act === 'solidBmp0' || act === 'solidBmp4') {
      var mode = (act === 'solidBmp4') ? 4 : 0;
      // 8×8 raster, all black: 0x00 = "print" in TSPL → 8 bytes of 0x00
      var raster = new Uint8Array(8); // bytes already 0x00 default
      var hdr = window.CapPrinter._asciiToBytes(tsplHeader(''));
      var bmp = bmpCmd(40, 40, 1, 8, mode, raster);
      var ftr = window.CapPrinter._asciiToBytes('PRINT 1\r\n');
      await sendBytes(window.CapPrinter._concatBytes([hdr, bmp, ftr]));
      ok('out2', 'Solid 8×8 sent, mode=' + mode);
    }
    else if (act === 'cyrBmp0' || act === 'cyrBmp4') {
      var mode2 = (act === 'cyrBmp4') ? 4 : 0;
      var bmp2 = window.CapPrinter._renderTextBitmap('Памук 100% · Турция', 22, 380);
      if (!bmp2) throw new Error('renderTextBitmap returned null');
      var hdr2 = window.CapPrinter._asciiToBytes(tsplHeader(''));
      var bmp2cmd = bmpCmd(20, 60, bmp2.widthBytes, bmp2.height, mode2, bmp2.data);
      var ftr2 = window.CapPrinter._asciiToBytes('PRINT 1\r\n');
      await sendBytes(window.CapPrinter._concatBytes([hdr2, bmp2cmd, ftr2]));
      ok('out3', 'Cyrillic via BITMAP mode=' + mode2 + '. Raster=' + bmp2.widthBytes + '×' + bmp2.height);
    }
    else if (act === 'cyrCp1251' || act === 'cyrCp1252') {
      var cp = (act === 'cyrCp1251') ? '1251' : '1252';
      var cyrTxt = window.CapPrinter._utf16ToCp1251Bytes('Памук 100% Турция');
      var t3 = tsplHeader(
        'CODEPAGE ' + cp + '\r\n' +
        'TEXT 20,40,"3",0,1,1,"' + cyrTxt + '"\r\n' +
        'PRINT 1\r\n'
      );
      await sendTsplString(t3);
      ok('out3', 'CODEPAGE ' + cp + ' + cyrillic CP1251-encoded sent. ' + t3.length + ' bytes');
    }
    else if (act === 'fullLabel' || act === 'fullLabelDual') {
      var p = {
        id: 12345,
        code: 'TST-001',
        name: 'Памучна риза',
        supplier: 'ДОС',
        size: '42',
        color: 'син',
        retail_price: 12.34,
        barcode: '2000000000017',
        origin_material: 'Памук 100%',
        origin_country: 'Турция',
        importer: 'ДОС',
        importer_city: 'София'
      };
      var st = { name: 'RunMyStore', currency: 'EUR' };
      var opts = (act === 'fullLabelDual') ? { mode: 'dual' } : {};
      var bytes = window.CapPrinter._generateTSPL_D520(p, st, 1, opts);
      await sendBytes(bytes);
      ok('out4', 'Full label sent: ' + bytes.length + ' bytes, mode=' + (opts.mode || 'auto'));
    }
    else if (act === 'sendRaw') {
      var raw = document.getElementById('raw').value;
      await sendTsplString(raw);
      ok('out5', 'Sent ' + raw.length + ' chars');
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
