<?php
// Debug page — показва User-Agent
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head><title>UA Debug</title></head>
<body style="font-family:monospace;padding:20px;background:#030712;color:#fff;font-size:14px;line-height:1.6">
<h2>APK User-Agent Debug</h2>
<p><b>UA:</b><br><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'none') ?></p>
<p><b>URL params:</b><br><?= htmlspecialchars(json_encode($_GET)) ?></p>
<p><b>window.Capacitor (JS):</b> <span id="cap">checking...</span></p>
<p><b>window.CapacitorBluetoothLe (JS):</b> <span id="ble">checking...</span></p>
<p><b>location.href:</b> <span id="url"></span></p>
<script>
document.getElementById('cap').textContent = typeof window.Capacitor;
document.getElementById('ble').textContent = typeof window.CapacitorBluetoothLe;
document.getElementById('url').textContent = location.href;
</script>
</body></html>
