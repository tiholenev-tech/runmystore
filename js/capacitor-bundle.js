/**
 * S82.CAPACITOR.2 — Capacitor runtime loader (served from runmystore.ai)
 *
 * Purpose: Capacitor's automatic `window.Capacitor` injection (via
 * addDocumentStartJavaScript) proved unreliable on the Samsung Z Flip6
 * WebView — bridge stayed undefined. Hosting the runtime from the server
 * guarantees the bridge is set up on every page.
 *
 * This is a thin loader — use the PHP helper `includes/capacitor-head.php`
 * or inline the three <script> tags below in order:
 *
 *   <script src="/js/capacitor/native-bridge.js"></script>
 *   <script src="/js/capacitor/core.js"></script>
 *   <script src="/js/capacitor/ble.js"></script>
 *   <script src="/js/capacitor-bundle.js"></script>  <-- this file, finalizes
 *
 * After load: window.Capacitor, window.BleClient, and
 *             window.capacitorCommunityBluetoothLe are defined.
 * On web browsers: Capacitor.getPlatform()==='web' and BLE throws UNAVAILABLE.
 */
(function() {
  'use strict';
  try {
    if (window.capacitorCommunityBluetoothLe && window.capacitorCommunityBluetoothLe.BleClient) {
      window.BleClient = window.capacitorCommunityBluetoothLe.BleClient;
    }
  } catch(e) {}
  try { window.dispatchEvent(new Event('capacitor-ready')); } catch(e) {}
})();
