/**
 * S82.CAPACITOR — BLE Printer Bridge
 * Hardware: DTM-5811 (TSPL protocol, 50x30mm labels)
 * Plugin: @capacitor-community/bluetooth-le
 *
 * Usage:
 *   await CapPrinter.pair();           // one-time setup
 *   await CapPrinter.print(product, store, copies);
 *   await CapPrinter.test();
 *
 * S82.CAPACITOR.2 — self-loads the Capacitor runtime (native-bridge + core
 * + BLE plugin) via document.write so this one <script> is drop-in anywhere,
 * including products.php which must not be modified. Detection uses
 * Capacitor.isNativePlatform() which is reliable on all WebView versions.
 */
(function() {
  if (window.__capacitorRuntimeInjected) return;
  if (window.Capacitor || document.querySelector('script[src*="/capacitor/native-bridge.js"]')) return;
  window.__capacitorRuntimeInjected = true;
  if (document.readyState === 'loading') {
    document.write(
      '<script src="/js/capacitor/native-bridge.js"><\/script>' +
      '<script src="/js/capacitor/core.js"><\/script>' +
      '<script src="/js/capacitor/ble.js"><\/script>' +
      '<script src="/js/capacitor-bundle.js"><\/script>'
    );
  } else {
    // Page already parsed (late include) — fall back to dynamic injection
    ['native-bridge.js', 'core.js', 'ble.js'].forEach(function(f) {
      var s = document.createElement('script');
      s.src = '/js/capacitor/' + f;
      s.async = false;
      document.head.appendChild(s);
    });
    var b = document.createElement('script');
    b.src = '/js/capacitor-bundle.js';
    b.async = false;
    document.head.appendChild(b);
  }
})();

(function(window) {
  'use strict';

  // DTM-5811 BLE UUIDs (TSPL service)
  const SERVICE_UUID = '000018f0-0000-1000-8000-00805f9b34fb';
  const WRITE_CHAR_UUID = '00002af1-0000-1000-8000-00805f9b34fb';

  const STORAGE_KEY = 'rms_printer_device_id';
  const PRINTER_NAME_FILTER = 'DTM';

  // Max BLE write chunk (DTM-5811 default MTU = 20 bytes safe)
  const CHUNK_SIZE = 20;

  // ----- Helpers -----

  function isCapacitor() {
    try {
      if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function') {
        return window.Capacitor.isNativePlatform();
      }
    } catch (e) {}
    return false;
  }

  function getBle() {
    if (window.BleClient) return window.BleClient;
    if (window.capacitorCommunityBluetoothLe && window.capacitorCommunityBluetoothLe.BleClient) {
      return window.capacitorCommunityBluetoothLe.BleClient;
    }
    throw new Error('BleClient не е зареден — включи capacitor-head.php');
  }

  function getSavedDeviceId() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function saveDeviceId(id) {
    try { localStorage.setItem(STORAGE_KEY, id); } catch (e) {}
  }

  function clearDeviceId() {
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
  }

  function strToBytes(s) {
    return new TextEncoder().encode(s);
  }

  function bytesToDataView(bytes) {
    return new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
  }

  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

  // ----- TSPL Generation (50x30mm label) -----

  function escapeTsplText(s) {
    if (!s) return '';
    return String(s).replace(/[\r\n\t]/g, ' ').substring(0, 32);
  }

  function formatPrice(amount, currency) {
    const n = parseFloat(amount) || 0;
    const c = currency || 'BGN';
    if (c === 'EUR') return n.toFixed(2) + ' EUR';
    if (c === 'BGN') return n.toFixed(2) + ' lv';
    return n.toFixed(2) + ' ' + c;
  }

  function generateTSPL(product, store, copies) {
    const name = escapeTsplText(product.name || '');
    const code = escapeTsplText(product.code || '');
    const price = formatPrice(product.retail_price, store.currency);
    const barcode = product.barcode || product.code || '';
    const storeName = escapeTsplText(store.name || '');
    const n = Math.max(1, Math.min(parseInt(copies) || 1, 50));

    let cmd = '';
    cmd += 'SIZE 50 mm,30 mm\r\n';
    cmd += 'GAP 2 mm,0\r\n';
    cmd += 'DIRECTION 1\r\n';
    cmd += 'DENSITY 8\r\n';
    cmd += 'SPEED 4\r\n';
    cmd += 'CLS\r\n';
    if (storeName) {
      cmd += `TEXT 15,10,"1",0,1,1,"${storeName}"\r\n`;
    }
    cmd += `TEXT 15,35,"2",0,1,1,"${name}"\r\n`;
    cmd += `TEXT 15,75,"4",0,1,1,"${price}"\r\n`;
    if (barcode) {
      cmd += `BARCODE 15,130,"128",50,1,0,2,2,"${barcode}"\r\n`;
    }
    if (code && code !== barcode) {
      cmd += `TEXT 280,10,"1",0,1,1,"${code}"\r\n`;
    }
    cmd += `PRINT ${n}\r\n`;
    return cmd;
  }

  // ----- BLE Write (chunked) -----

  async function writeChunked(ble, deviceId, bytes) {
    for (let i = 0; i < bytes.length; i += CHUNK_SIZE) {
      const chunk = bytes.slice(i, i + CHUNK_SIZE);
      await ble.write(deviceId, SERVICE_UUID, WRITE_CHAR_UUID, bytesToDataView(chunk));
      await sleep(15);
    }
  }

  // ----- Public API -----

  const CapPrinter = {

    isAvailable() {
      return isCapacitor();
    },

    hasPairedPrinter() {
      return !!getSavedDeviceId();
    },

    async pair() {
      if (!isCapacitor()) {
        throw new Error('Не си в мобилно приложение');
      }
      const ble = getBle();
      await ble.initialize();

      const device = await ble.requestDevice({
        namePrefix: PRINTER_NAME_FILTER,
        optionalServices: [SERVICE_UUID]
      });

      if (!device || !device.deviceId) {
        throw new Error('Няма избран принтер');
      }

      saveDeviceId(device.deviceId);
      return { deviceId: device.deviceId, name: device.name || 'DTM-5811' };
    },

    async connect() {
      if (!isCapacitor()) throw new Error('Не си в мобилно приложение');
      const ble = getBle();
      await ble.initialize();

      const id = getSavedDeviceId();
      if (!id) throw new Error('Принтерът не е сдвоен. Натисни "Сдвои".');

      try {
        await ble.connect(id, null, { timeout: 10000 });
      } catch (e) {
        if (!String(e.message || e).toLowerCase().includes('already')) throw e;
      }
      return id;
    },

    async disconnect() {
      if (!isCapacitor()) return;
      const id = getSavedDeviceId();
      if (!id) return;
      try { await getBle().disconnect(id); } catch (e) {}
    },

    async print(product, store, copies) {
      if (!isCapacitor()) throw new Error('Мобилен печат не е достъпен тук');

      const id = await this.connect();
      const tspl = generateTSPL(product, store, copies || 1);
      const bytes = strToBytes(tspl);

      await writeChunked(getBle(), id, bytes);
      await sleep(500);
      return { ok: true, bytes: bytes.length, copies: copies || 1 };
    },

    async test() {
      const testProduct = {
        code: 'TEST-001',
        name: 'Тестова етикетка',
        retail_price: 12.34,
        barcode: '0000000000017'
      };
      const testStore = { name: 'RunMyStore', currency: 'BGN' };
      return await this.print(testProduct, testStore, 1);
    },

    forget() {
      clearDeviceId();
    },

    _generateTSPL: generateTSPL,
    _isCapacitor: isCapacitor,
    _getDeviceId: getSavedDeviceId
  };

  window.CapPrinter = CapPrinter;

})(window);
