/**
 * S82.CAPACITOR — BLE Printer Bridge
 * Hardware: DTM-5811 (TSPL protocol, 50x30mm labels)
 * Plugin: @capacitor-community/bluetooth-le
 *
 * S82.CAPACITOR.10 — Canvas→BITMAP rasterize за кирилица
 * DTM-5811 не поддържа cyrillic font → render-ваме cyrillic текстове
 * като Canvas bitmap и ги изпращаме с TSPL BITMAP команда.
 * ASCII текстове (code, price, barcode) — native TSPL за компактност.
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

  // BLE Service UUIDs (TSPL-compatible printers).
  // ВНИМАНИЕ: D520BT advertise-ва af30 в scan packet, но при GATT connect
  // експозира DIFFERENT services — actual data service е ff00 (Phomemo standard).
  // Затова списъкът съдържа и двата: af30 (за scan filter в picker-а),
  // ff00 (за discoverWriteEndpoint след connect).
  const SERVICE_UUIDS = [
    '000018f0-0000-1000-8000-00805f9b34fb',  // DTM-5811 (TSPL)
    '0000ff00-0000-1000-8000-00805f9b34fb',  // D520BT data service (Phomemo standard) — priority
    '0000af30-0000-1000-8000-00805f9b34fb'   // D520BT advertisement (GT01 family) — fallback
  ];
  // DTM-5811 known write characteristic — за други принтери discover-ваме dynamically
  const DTM_SERVICE_UUID  = '000018f0-0000-1000-8000-00805f9b34fb';
  const DTM_WRITE_CHAR_UUID = '00002af1-0000-1000-8000-00805f9b34fb';

  const STORAGE_KEY         = 'rms_printer_device_id';
  const STORAGE_KEY_SERVICE = 'rms_printer_service_uuid';
  const STORAGE_KEY_WRITE   = 'rms_printer_write_char_uuid';

  // Max BLE write chunk (DTM-5811 default MTU = 20 bytes safe)
  const CHUNK_SIZE = 100;

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

  function getSavedServiceUuid() {
    try { return localStorage.getItem(STORAGE_KEY_SERVICE); } catch (e) { return null; }
  }

  function saveServiceUuid(uuid) {
    try { localStorage.setItem(STORAGE_KEY_SERVICE, uuid); } catch (e) {}
  }

  function getSavedWriteCharUuid() {
    try { return localStorage.getItem(STORAGE_KEY_WRITE); } catch (e) { return null; }
  }

  function saveWriteCharUuid(uuid) {
    try { localStorage.setItem(STORAGE_KEY_WRITE, uuid); } catch (e) {}
  }

  function clearDeviceId() {
    try {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(STORAGE_KEY_SERVICE);
      localStorage.removeItem(STORAGE_KEY_WRITE);
    } catch (e) {}
  }

  function uuidEq(a, b) {
    return String(a || '').toLowerCase() === String(b || '').toLowerCase();
  }

  // След connect — итерира SERVICE_UUIDS в priority order и за първия,
  // който device-ът експозира, намира writable characteristic.
  // 18f0 → ползва известния DTM_WRITE_CHAR_UUID (без scan на chars).
  // ff00, af30 → discover-ва char с WRITE или WRITE_NO_RESPONSE.
  // Логва всеки опит в overlay (Тихол вижда какво е пробвано).
  async function discoverWriteEndpoint(ble, deviceId, deviceName) {
    const services = await ble.getServices(deviceId);
    const presentUuids = (services || []).map(function(s){ return String(s.uuid).toLowerCase(); });
    dbgLog('[D520BT-DEBUG] Device ' + (deviceName || '(no name)')
      + ' exposes ' + presentUuids.length + ' services: ' + presentUuids.join(', '));

    let chosen = null;
    for (const known of SERVICE_UUIDS) {
      const svc = (services || []).find(function(s){ return uuidEq(s.uuid, known); });
      if (!svc) {
        dbgLog('[D520BT-DEBUG]   try ' + known + ' — not exposed');
        continue;
      }
      if (uuidEq(known, DTM_SERVICE_UUID)) {
        dbgLog('[D520BT-DEBUG]   try ' + known + ' — DTM service, use known writeChar ' + DTM_WRITE_CHAR_UUID);
        chosen = { serviceUuid: svc.uuid, writeCharUuid: DTM_WRITE_CHAR_UUID };
        break;
      }
      let writable = null;
      for (const ch of (svc.characteristics || [])) {
        const p = ch.properties || {};
        if (p.write || p.writeWithoutResponse) { writable = ch; break; }
      }
      if (writable) {
        dbgLog('[D520BT-DEBUG]   try ' + known + ' — FOUND writable char ' + writable.uuid);
        chosen = { serviceUuid: svc.uuid, writeCharUuid: writable.uuid };
        break;
      }
      dbgLog('[D520BT-DEBUG]   try ' + known + ' — present but NO writable char');
    }

    if (!chosen) {
      throw new Error('Не е намерен writable endpoint в TSPL services. Device exposes: '
        + presentUuids.join(', '));
    }
    dbgLog('[D520BT-DEBUG] Selected ' + (deviceName || '(no name)')
      + ': service=' + chosen.serviceUuid + ', writeChar=' + chosen.writeCharUuid);
    return chosen;
  }

  // ASCII-only encoder (за command strings — TSPL commands)
  function asciiToBytes(s) {
    const out = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) {
      const c = s.charCodeAt(i);
      out[i] = c < 128 ? c : 0x3F;
    }
    return out;
  }

  // Concat multiple Uint8Arrays в един
  function concatBytes(parts) {
    let total = 0;
    for (const p of parts) total += p.length;
    const out = new Uint8Array(total);
    let off = 0;
    for (const p of parts) { out.set(p, off); off += p.length; }
    return out;
  }

  function bytesToDataView(bytes) {
    return new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
  }

  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

  // ----- Debug overlay (full-screen, on-device) -----
  // Тихол няма chrome inspect setup, console.log не е видим.
  // Затова паралелно с console.log трупаме output във fullscreen overlay.

  let __dbgOverlayEl = null;
  let __dbgPreEl = null;

  function ensureDebugOverlay() {
    if (__dbgOverlayEl && document.body.contains(__dbgOverlayEl)) return;

    const ov = document.createElement('div');
    ov.id = 'capPrinterDebugOverlay';
    ov.style.cssText = [
      'position:fixed', 'inset:0', 'z-index:99999',
      'background:rgba(0,0,0,0.95)', 'color:#fff',
      'font-family:Menlo,Consolas,monospace', 'font-size:12px',
      'display:flex', 'flex-direction:column'
    ].join(';');

    const bar = document.createElement('div');
    bar.style.cssText = [
      'display:flex', 'gap:8px', 'padding:10px',
      'background:#111', 'border-bottom:1px solid #333',
      'flex-wrap:wrap'
    ].join(';');

    const btnCopy = document.createElement('button');
    btnCopy.textContent = 'Копирай всичко';
    btnCopy.style.cssText = 'padding:10px 14px;background:#6366f1;color:#fff;border:0;border-radius:8px;font-weight:700;font-size:13px';
    btnCopy.onclick = async function() {
      const txt = __dbgPreEl ? __dbgPreEl.textContent : '';
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(txt);
          btnCopy.textContent = 'OK Копирано';
        } else {
          const ta = document.createElement('textarea');
          ta.value = txt; document.body.appendChild(ta);
          ta.select(); document.execCommand('copy'); ta.remove();
          btnCopy.textContent = 'OK Копирано (fallback)';
        }
      } catch (e) {
        btnCopy.textContent = 'ERR ' + (e && e.message || e);
      }
      setTimeout(function(){ btnCopy.textContent = 'Копирай всичко'; }, 2000);
    };

    const btnClear = document.createElement('button');
    btnClear.textContent = 'Изчисти';
    btnClear.style.cssText = 'padding:10px 14px;background:#374151;color:#fff;border:0;border-radius:8px;font-weight:700;font-size:13px';
    btnClear.onclick = function() { if (__dbgPreEl) __dbgPreEl.textContent = ''; };

    const btnClose = document.createElement('button');
    btnClose.textContent = 'Затвори';
    btnClose.style.cssText = 'padding:10px 14px;background:#ef4444;color:#fff;border:0;border-radius:8px;font-weight:700;font-size:13px;margin-left:auto';
    btnClose.onclick = function() {
      if (__dbgOverlayEl && __dbgOverlayEl.parentNode) {
        __dbgOverlayEl.parentNode.removeChild(__dbgOverlayEl);
      }
      __dbgOverlayEl = null;
      __dbgPreEl = null;
    };

    bar.appendChild(btnCopy);
    bar.appendChild(btnClear);
    bar.appendChild(btnClose);

    const pre = document.createElement('pre');
    pre.style.cssText = [
      'flex:1', 'margin:0', 'padding:12px',
      'overflow:auto', 'white-space:pre-wrap', 'word-break:break-all',
      'color:#e5e7eb', 'background:#000'
    ].join(';');
    pre.textContent = '';

    ov.appendChild(bar);
    ov.appendChild(pre);
    document.body.appendChild(ov);

    __dbgOverlayEl = ov;
    __dbgPreEl = pre;
  }

  function showDebugOverlay(text) {
    ensureDebugOverlay();
    if (!__dbgPreEl) return;
    const ts = new Date().toISOString().substr(11, 12);
    __dbgPreEl.textContent += '[' + ts + '] ' + String(text) + '\n';
    __dbgPreEl.scrollTop = __dbgPreEl.scrollHeight;
  }

  // dbgLog — пише и в console.log (за adb logcat), и в on-screen overlay.
  function dbgLog() {
    const args = Array.prototype.slice.call(arguments);
    try { console.log.apply(console, args); } catch (_) {}
    const line = args.map(function(a){
      if (typeof a === 'string') return a;
      try { return JSON.stringify(a); } catch (_) { return String(a); }
    }).join(' ');
    showDebugOverlay(line);
  }

  function hasCyrillic(s) {
    return /[\u0400-\u04FF]/.test(String(s || ''));
  }

  // ----- Canvas → TSPL BITMAP -----

  /**
   * Рендерира text на Canvas и връща TSPL BITMAP raw bytes.
   * TSPL BITMAP data: MSB-first, 1=white (no dot), 0=black (dot).
   * @returns {widthBytes, height, data: Uint8Array}
   */
  function renderTextBitmap(text, fontSize, maxWidthPx) {
    text = String(text || '');
    if (!text) return null;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    // Измерваме ширината на текста
    ctx.font = 'bold ' + fontSize + 'px Arial, sans-serif';
    const metrics = ctx.measureText(text);
    let w = Math.ceil(metrics.width) + 4;
    if (w > maxWidthPx) w = maxWidthPx;
    if (w < 8) w = 8;

    // Закръгляме ширината нагоре към цял байт (8 пиксела)
    const widthBytes = Math.ceil(w / 8);
    const canvasWidth = widthBytes * 8;
    const canvasHeight = fontSize + 8; // descenders + padding

    canvas.width = canvasWidth;
    canvas.height = canvasHeight;

    // Бял фон
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasWidth, canvasHeight);

    // Черен текст
    ctx.fillStyle = '#000000';
    ctx.font = 'bold ' + fontSize + 'px Arial, sans-serif';
    ctx.textBaseline = 'top';
    ctx.textAlign = 'left';

    // Truncate ако не се вмества
    let displayText = text;
    if (ctx.measureText(displayText).width > canvasWidth - 4) {
      while (displayText.length > 1 && ctx.measureText(displayText + '…').width > canvasWidth - 4) {
        displayText = displayText.slice(0, -1);
      }
      displayText += '…';
    }
    ctx.fillText(displayText, 2, 2);

    const imgData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
    const px = imgData.data;

    // Пакетираме в TSPL BITMAP data:
    // - MSB first (bit 7 = leftmost pixel)
    // - 1 = white (no dot), 0 = black (dot)
    const data = new Uint8Array(widthBytes * canvasHeight);
    for (let y = 0; y < canvasHeight; y++) {
      for (let bx = 0; bx < widthBytes; bx++) {
        let byte = 0;
        for (let bit = 0; bit < 8; bit++) {
          const x = bx * 8 + bit;
          const i = (y * canvasWidth + x) * 4;
          const dark = (px[i] + px[i + 1] + px[i + 2]) < 384; // <128 avg на канал
          const bitVal = dark ? 0 : 1;
          byte |= (bitVal << (7 - bit));
        }
        data[y * widthBytes + bx] = byte;
      }
    }

    return { widthBytes, height: canvasHeight, data };
  }

  /**
   * Построява TSPL команди като list от byte arrays.
   * Връща финален Uint8Array готов за BLE write.
   */
  function generateTSPL(product, store, copies) {
    const name = String(product.name || '').replace(/[\r\n\t"]/g, ' ').substring(0, 48);
    const code = String(product.code || '').replace(/[\r\n\t"]/g, ' ').substring(0, 20);
    const barcode = String(product.barcode || product.code || '').replace(/[^0-9A-Za-z]/g, '');
    const storeName = String(store.name || '').replace(/[\r\n\t"]/g, ' ').substring(0, 32);
    const n = Math.max(1, Math.min(parseInt(copies) || 1, 50));

    // Price — винаги ASCII (няма cyrillic)
    const amt = parseFloat(product.retail_price) || 0;
    const cur = store.currency || 'EUR';
    let priceStr;
    if (cur === 'EUR') priceStr = amt.toFixed(2) + ' EUR';
    else if (cur === 'BGN') priceStr = amt.toFixed(2) + ' lv';
    else priceStr = amt.toFixed(2) + ' ' + cur;

    const parts = [];
    const push = (s) => parts.push(asciiToBytes(s));
    const pushRaw = (b) => parts.push(b);

    // Header
    push('SIZE 50 mm,30 mm\r\n');
    push('GAP 2 mm,0\r\n');
    push('DIRECTION 1\r\n');
    push('DENSITY 10\r\n');
    push('SPEED 3\r\n');
    push('CLS\r\n');

    // Layout @ 8 dots/mm → 50mm=400 wide, 30mm=240 tall
    // Използваме целия 240 dot height:
    //   y=4   store name (bitmap ~24px ≈ 28 dots)
    //   y=36  product name (bitmap ~28px ≈ 34 dots)
    //   y=76  price (bitmap ~34px ≈ 42 dots, bold, right-aligned feel)
    //   y=124 code (TSPL font 2, 20 dots)
    //   y=150 barcode (80 dots)

    let y = 4;

    // Store name — по-малък, горе
    if (storeName) {
      if (hasCyrillic(storeName)) {
        const bmp = renderTextBitmap(storeName, 22, 380);
        if (bmp) {
          push('BITMAP 10,' + y + ',' + bmp.widthBytes + ',' + bmp.height + ',0,');
          pushRaw(bmp.data);
          push('\r\n');
        }
      } else {
        push('TEXT 10,' + y + ',"3",0,1,1,"' + storeName + '"\r\n');
      }
    }
    y = 34;

    // Product name — font 28 (по-голям от стандартния)
    if (name) {
      const nameBmp = renderTextBitmap(name, 28, 380);
      if (nameBmp) {
        push('BITMAP 10,' + y + ',' + nameBmp.widthBytes + ',' + nameBmp.height + ',0,');
        pushRaw(nameBmp.data);
        push('\r\n');
      }
    }
    y = 76;

    // Price — ГОЛЯМ (38px), не overlap-ва
    {
      const priceBmp = renderTextBitmap(priceStr, 38, 380);
      if (priceBmp) {
        push('BITMAP 10,' + y + ',' + priceBmp.widthBytes + ',' + priceBmp.height + ',0,');
        pushRaw(priceBmp.data);
        push('\r\n');
      }
    }
    y = 128;

    // Code — малък, под цената
    if (code) {
      if (hasCyrillic(code)) {
        const bmp = renderTextBitmap(code, 14, 380);
        if (bmp) {
          push('BITMAP 10,' + y + ',' + bmp.widthBytes + ',' + bmp.height + ',0,');
          pushRaw(bmp.data);
          push('\r\n');
        }
      } else {
        push('TEXT 10,' + y + ',"2",0,1,1,"' + code + '"\r\n');
      }
    }

    // Barcode — долен край, height=70 dots
    if (barcode) {
      const barY = 160;
      // Auto-fit barcode:
      // - EAN13: 95 modules — narrow=4 → 380 dots (95% fit, лесно за сканиране)
      // - Code128: variable — narrow=3 обикновено запълва
      let fmt = '128';
      let narrow = 3;
      if (/^[0-9]{13}$/.test(barcode)) { fmt = 'EAN13'; narrow = 4; }
      else if (/^[0-9]{12}$/.test(barcode)) { fmt = 'UPCA'; narrow = 4; }
      else if (/^[0-9]{8}$/.test(barcode)) { fmt = 'EAN8'; narrow = 4; }
      push('BARCODE 10,' + barY + ',"' + fmt + '",55,1,0,' + narrow + ',2,"' + barcode + '"\r\n');
    }

    push('PRINT ' + n + '\r\n');

    return concatBytes(parts);
  }

  // ----- BLE Write (chunked) -----

  async function writeChunked(ble, deviceId, bytes) {
    // Backwards-compat: ако сме paired с DTM-5811 преди S88 (без stored UUIDs),
    // localStorage няма service/writeChar — fallback към DTM известните UUIDs.
    const serviceUuid   = getSavedServiceUuid()   || DTM_SERVICE_UUID;
    const writeCharUuid = getSavedWriteCharUuid() || DTM_WRITE_CHAR_UUID;
    for (let i = 0; i < bytes.length; i += CHUNK_SIZE) {
      const chunk = bytes.slice(i, i + CHUNK_SIZE);
      await ble.write(deviceId, serviceUuid, writeCharUuid, bytesToDataView(chunk));
      await sleep(5);
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
      await ble.initialize({ androidNeverForLocation: false });

      // S88.PRINTER.MULTI — filter по списък TSPL service UUIDs (DTM-5811, D520BT...).
      const device = await ble.requestDevice({
        services: SERVICE_UUIDS,
        optionalServices: SERVICE_UUIDS
      }).catch(async () => {
        // Fallback: някои Android BLE stack-ове не advertise-ват service UUID
        // в scan packet-а → service-filtered scan връща празен списък.
        // Last-resort: показваме всички BT устройства (потребителят избира ръчно).
        return await ble.requestDevice({});
      });

      if (!device || !device.deviceId) {
        throw new Error('Не е избран принтер');
      }
      await ble.connect(device.deviceId, () => clearDeviceId());

      // Discover service + write characteristic за този конкретен принтер.
      try {
        const ep = await discoverWriteEndpoint(ble, device.deviceId, device.name);
        saveDeviceId(device.deviceId);
        saveServiceUuid(ep.serviceUuid);
        saveWriteCharUuid(ep.writeCharUuid);
      } catch (e) {
        try { await ble.disconnect(device.deviceId); } catch (_) {}
        clearDeviceId();
        throw e;
      }
      return device.deviceId;
    },

    // ═══════════════════════════════════════════════════════════════════
    // S87.D520BT.HUNT — TEMPORARY DEBUG (REMOVE once D520BT UUID known)
    // ═══════════════════════════════════════════════════════════════════
    // Manual invocation from JS console / adb logcat:
    //   await window.CapPrinter.pairDebug()      → unfiltered picker, then
    //                                              connect + enumerate GATT
    //   await window.CapPrinter.scanDebug(8000)  → 8s programmatic LE scan,
    //                                              log every device's
    //                                              advertised service UUIDs
    // All output prefixed with [D520BT-DEBUG] for adb logcat filtering:
    //   adb logcat -s chromium:V | grep D520BT-DEBUG
    // ───────────────────────────────────────────────────────────────────

    async pairDebug() {
      if (!isCapacitor()) throw new Error('Не си в мобилно приложение');
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });
      dbgLog('[D520BT-DEBUG] Opening UNFILTERED picker — pick D520BT...');
      const device = await ble.requestDevice({});
      if (!device || !device.deviceId) throw new Error('No device picked');
      dbgLog('[D520BT-DEBUG] Picked:', JSON.stringify({
        name: device.name || '(no name)',
        deviceId: device.deviceId
      }));
      dbgLog('[D520BT-DEBUG] Connecting to enumerate GATT...');
      await ble.connect(device.deviceId, function() {
        dbgLog('[D520BT-DEBUG] Disconnected (callback)');
      });
      try {
        const services = await ble.getServices(device.deviceId);
        dbgLog('[D520BT-DEBUG] Services count:', services.length);
        services.forEach(function(svc, si) {
          dbgLog('[D520BT-DEBUG] [' + si + '] service UUID: ' + svc.uuid);
          (svc.characteristics || []).forEach(function(ch, ci) {
            const props = ch.properties || {};
            const flags = [];
            if (props.read) flags.push('READ');
            if (props.write) flags.push('WRITE');
            if (props.writeWithoutResponse) flags.push('WRITE_NO_RESP');
            if (props.notify) flags.push('NOTIFY');
            if (props.indicate) flags.push('INDICATE');
            dbgLog('[D520BT-DEBUG]   [' + si + '.' + ci + '] char UUID: '
                        + ch.uuid + '  [' + flags.join(',') + ']');
          });
        });
        dbgLog('[D520BT-DEBUG] FULL DUMP:', JSON.stringify(services, null, 2));
      } catch (e) {
        dbgLog('[D520BT-DEBUG] getServices error:', e && e.message ? e.message : e);
        throw e;
      } finally {
        try { await ble.disconnect(device.deviceId); } catch (_) {}
        dbgLog('[D520BT-DEBUG] Done. Disconnected.');
      }
      return { name: device.name, deviceId: device.deviceId };
    },

    async scanDebug(durationMs) {
      durationMs = durationMs || 8000;
      if (!isCapacitor()) throw new Error('Не си в мобилно приложение');
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });
      dbgLog('[D520BT-DEBUG] Starting LE scan for ' + durationMs + 'ms...');
      const seen = {};
      let seenCount = 0;
      try {
        await ble.requestLEScan({}, function(result) {
          const key = (result.device && result.device.deviceId) || '';
          if (!key || seen[key]) return;
          seen[key] = true;
          seenCount++;
          dbgLog('[D520BT-DEBUG] Found: ' + JSON.stringify({
            name: (result.device && result.device.name) || result.localName || '(unnamed)',
            deviceId: key,
            rssi: result.rssi,
            uuids: result.uuids || [],
            mfgData: result.manufacturerData ? Object.keys(result.manufacturerData) : []
          }));
        });
      } catch (e) {
        dbgLog('[D520BT-DEBUG] requestLEScan unavailable on this BLE plugin version:',
                    e && e.message ? e.message : e);
        throw e;
      }
      await sleep(durationMs);
      try { await ble.stopLEScan(); } catch (_) {}
      dbgLog('[D520BT-DEBUG] Scan complete. Unique devices: ' + seenCount);
      return seenCount;
    },
    // ═══════════════════════════════════════════════════════════════════
    // END S87.D520BT.HUNT
    // ═══════════════════════════════════════════════════════════════════

    async connect() {
      if (!isCapacitor()) {
        throw new Error('Мобилен печат не е достъпен тук');
      }
      const id = getSavedDeviceId();
      if (!id) {
        throw new Error('Няма сдвоен принтер — направи pair първо');
      }
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });
      try {
        await ble.connect(id, () => {});
      } catch (e) {
        // Може вече да е свързан
      }
      return id;
    },

    async disconnect() {
      if (!isCapacitor()) return;
      const id = getSavedDeviceId();
      if (!id) return;
      try { await getBle().disconnect(id); } catch (e) {}
      window.__blePrinterConnected = false;
    },

    async print(product, store, copies) {
      if (!isCapacitor()) throw new Error('Мобилен печат не е достъпен тук');

      const ble = getBle();
      const bytes = generateTSPL(product, store, copies || 1);

      let id = getSavedDeviceId();
      if (!id) throw new Error('Няма сдвоен принтер');

      // Initialize само веднъж per session
      if (!window.__bleInitialized) {
        await ble.initialize({ androidNeverForLocation: false });
        window.__bleInitialized = true;
      }

      // Connect ако още не сме свързани
      if (!window.__blePrinterConnected) {
        try {
          await ble.connect(id, () => { window.__blePrinterConnected = false; });
          window.__blePrinterConnected = true;
        } catch (e) {
          const msg = (e && e.message) ? e.message.toLowerCase() : '';
          if (msg.includes('already') || msg.includes('connected')) {
            window.__blePrinterConnected = true;
          } else {
            throw e;
          }
        }
      }

      // Write — retry веднъж ако падне
      try {
        await writeChunked(ble, id, bytes);
      } catch (e) {
        // Reconnect и пробваме пак
        window.__blePrinterConnected = false;
        try { await ble.disconnect(id); } catch (_) {}
        await sleep(200);
        await ble.connect(id, () => { window.__blePrinterConnected = false; });
        window.__blePrinterConnected = true;
        await writeChunked(ble, id, bytes);
      }
      await sleep(200);

      // Не disconnect-ваме — следващото print е мигновено
      return { ok: true, bytes: bytes.length, copies: copies || 1 };
    },

    async test() {
      const testProduct = {
        code: 'TEST-001',
        name: 'Тестова етикетка',
        retail_price: 12.34,
        barcode: '0000000000017'
      };
      const testStore = { name: 'Магазин Тест', currency: 'EUR' };
      return await this.print(testProduct, testStore, 1);
    },

    // _sendRaw — connect (ако е нужно) и пращаме arbitrary bytes през stored
    // service+writeChar. Помощник за testRaw / testCPCL / testESCPOS / testPhomemoInit.
    async _sendRaw(bytes, label) {
      if (!isCapacitor()) throw new Error('Мобилен печат не е достъпен тук');
      const ble = getBle();
      const id = getSavedDeviceId();
      if (!id) throw new Error('Няма сдвоен принтер');

      if (!window.__bleInitialized) {
        await ble.initialize({ androidNeverForLocation: false });
        window.__bleInitialized = true;
      }
      if (!window.__blePrinterConnected) {
        try {
          await ble.connect(id, () => { window.__blePrinterConnected = false; });
          window.__blePrinterConnected = true;
        } catch (e) {
          const msg = (e && e.message) ? e.message.toLowerCase() : '';
          if (msg.includes('already') || msg.includes('connected')) {
            window.__blePrinterConnected = true;
          } else {
            throw e;
          }
        }
      }

      dbgLog('[D520BT-DEBUG] Sending ' + label + ' — ' + bytes.length + ' bytes');
      await writeChunked(ble, id, bytes);
      dbgLog('[D520BT-DEBUG] ' + label + ': Готово ' + bytes.length + ' байта');
      return { ok: true, bytes: bytes.length, protocol: label };
    },

    // testRaw — минимален TSPL без bitmap, без generateTSPL.
    // За диагностика дали discovered service+writeChar реално приема писане.
    async testRaw() {
      const tspl =
        'SIZE 50 mm,30 mm\r\n' +
        'GAP 2 mm,0\r\n' +
        'DIRECTION 1\r\n' +
        'DENSITY 8\r\n' +
        'SPEED 4\r\n' +
        'CLS\r\n' +
        'TEXT 50,50,"3",0,1,1,"TEST D520"\r\n' +
        'PRINT 1,1\r\n';
      return await this._sendRaw(asciiToBytes(tspl), 'TSPL raw');
    },

    // testCPCL — Citizen / Phomemo CPCL protocol probe
    async testCPCL() {
      const cpcl =
        '! 0 200 200 240 1\r\n' +
        'TEXT 4 0 30 40 TEST D520\r\n' +
        'FORM\r\n' +
        'PRINT\r\n';
      return await this._sendRaw(asciiToBytes(cpcl), 'CPCL');
    },

    // testESCPOS — стандартен ESC/POS init + center text + cut
    async testESCPOS() {
      const bytes = new Uint8Array([
        0x1B, 0x40,                                                  // ESC @ — init
        0x1B, 0x61, 0x01,                                            // center align
        0x54, 0x45, 0x53, 0x54, 0x20, 0x44, 0x35, 0x32, 0x30, 0x0A,  // "TEST D520\n"
        0x1D, 0x56, 0x41, 0x03                                       // cut paper
      ]);
      return await this._sendRaw(bytes, 'ESC/POS');
    },

    // testPhomemoInit — хипотеза за Phomemo magic init sequence + ESC reset + text
    async testPhomemoInit() {
      const bytes = new Uint8Array([
        0x1F, 0x11, 0x02, 0x04,                       // Phomemo magic init
        0x1F, 0x11, 0x0B,                             // ?
        0x1B, 0x40,                                   // ESC @ reset
        0x54, 0x45, 0x53, 0x54, 0x0A,                 // "TEST\n"
        0x0C                                          // form feed
      ]);
      return await this._sendRaw(bytes, 'Phomemo Init');
    },

    forget() {
      clearDeviceId();
    },

    showDebugOverlay: showDebugOverlay,

    _generateTSPL: generateTSPL,
    _isCapacitor: isCapacitor,
    _getDeviceId: getSavedDeviceId,
    _renderTextBitmap: renderTextBitmap,
    _dbgLog: dbgLog
  };

  window.CapPrinter = CapPrinter;

})(window);
