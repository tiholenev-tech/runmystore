<?php
// Debug page — показва User-Agent + Capacitor bridge статус
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head><title>UA Debug</title>
<?php require __DIR__ . '/includes/capacitor-head.php'; ?>
</head>
<body style="font-family:monospace;padding:20px;background:#030712;color:#fff;font-size:14px;line-height:1.6">
<h2>APK User-Agent Debug</h2>
<p><b>UA:</b><br><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'none') ?></p>
<p><b>URL params:</b><br><?= htmlspecialchars(json_encode($_GET)) ?></p>

<h3>Capacitor bridge</h3>
<p><b>window.Capacitor:</b> <span id="cap">checking...</span></p>
<p><b>Capacitor.getPlatform():</b> <span id="platform">checking...</span></p>
<p><b>Capacitor.isNativePlatform():</b> <span id="native">checking...</span></p>
<p><b>window.androidBridge:</b> <span id="android">checking...</span></p>
<p><b>window.BleClient:</b> <span id="ble">checking...</span></p>
<p><b>Capacitor.Plugins.BluetoothLe:</b> <span id="blplug">checking...</span></p>
<p><b>location.href:</b> <span id="url"></span></p>

<script>
(function(){
  function show(id, v){ document.getElementById(id).textContent = v; }
  function refresh(){
    show('cap', typeof window.Capacitor);
    try {
      show('platform', window.Capacitor ? window.Capacitor.getPlatform() : '—');
      show('native', window.Capacitor ? String(window.Capacitor.isNativePlatform()) : '—');
    } catch(e){ show('platform','err: '+e.message); }
    show('android', typeof window.androidBridge);
    show('ble', typeof window.BleClient);
    try {
      show('blplug', (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BluetoothLe) ? 'present' : 'missing');
    } catch(e){ show('blplug','err'); }
    show('url', location.href);
  }
  refresh();
  // Re-check after scripts settle
  setTimeout(refresh, 300);
  window.addEventListener('capacitor-ready', refresh);
})();
</script>
</body></html>
