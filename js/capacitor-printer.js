/**
 * S82.CAPACITOR / S96.D520 — Dual Printer Bridge
 *
 * Two transports, two printers, may be paired in parallel:
 *   • DTM-5811  — BLE GATT  (TSPL @ service 18f0 / char 2af1)
 *                 Plugin: @capacitor-community/bluetooth-le
 *   • D520BT    — Bluetooth Classic SPP/RFCOMM (TSPL @ SDP 1101)
 *                 Plugin: @e-is/capacitor-bluetooth-serial
 *
 * pair()       — DTM BLE flow (legacy alias, backwards-compatible).
 * pairD520()   — Classic SPP flow (new in S96).
 * print()      — routes by active printer type. opts.type='DTM'|'D520' overrides.
 *
 * S95 history (do not revisit):
 *   D520BT *advertises* BLE service ff00 but its print engine is hardwired to
 *   RFCOMM SDP 1101 (Wireshark btsnoop confirms 0 btatt packets during print).
 *   Five BLE approaches failed (Phomemo D-family, LuckPrinter SDK, exact replay,
 *   writeWithoutResponse, frame wrapping over GATT). All stripped in S96.D520.2.
 *
 * Binary-byte caveat for D520 SPP:
 *   @e-is plugin's write() encodes the JS string with getBytes(UTF_8) on the
 *   Java side. ASCII (0x00-0x7F) passes through; 0x80-0xFF become 2-byte UTF-8
 *   sequences (corrupting the wire). For Phase 3 raw TSPL we stay ASCII-only.
 *   If Phase 4 frame-wrapping is needed, the plugin must be forked to replace
 *   toByteArray() with ISO-8859-1 1:1 mapping (or accept base64).
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

  // ─── DTM-5811 BLE constants (working, do not touch) ────────────────────
  const DTM_SERVICE_UUID    = '000018f0-0000-1000-8000-00805f9b34fb';
  const DTM_WRITE_CHAR_UUID = '00002af1-0000-1000-8000-00805f9b34fb';

  // BLE scan filter — DTM only. D520BT is Classic SPP, not BLE.
  const SERVICE_UUIDS = [DTM_SERVICE_UUID];

  // ─── Printer type constants ────────────────────────────────────────────
  const TYPE_DTM  = 'DTM';
  const TYPE_D520 = 'D520';

  // ─── Storage keys ──────────────────────────────────────────────────────
  // DTM (legacy keys, behaviour preserved across S96 refactor).
  const STORAGE_KEY            = 'rms_printer_device_id';
  const STORAGE_KEY_SERVICE    = 'rms_printer_service_uuid';
  const STORAGE_KEY_WRITE      = 'rms_printer_write_char_uuid';
  // Active selector — TYPE_DTM | TYPE_D520. Last paired wins by default.
  const STORAGE_KEY_TYPE       = 'rms_printer_type';
  // D520 (S96 new keys).
  const STORAGE_KEY_D520_ADDR  = 'rms_printer_d520_address';
  const STORAGE_KEY_D520_NAME  = 'rms_printer_d520_name';

  // BLE chunk size (DTM default MTU 20B, but 100B works in practice).
  const CHUNK_SIZE = 100;

  // ─── S97 label-spec constants ──────────────────────────────────────────
  // Matches products.php wizPrintLabels (50×30mm @ 8 dots/mm = 400×240 dots).
  const BGN_RATE = 1.95583;        // BNB fixed EUR→BGN rate
  const LBL_W    = 400;            // 50mm × 8 dots/mm
  const LBL_H    = 240;            // 30mm × 8 dots/mm
  const LBL_PX   = 16;             // padding x (2mm)
  const LBL_PY   = 4;              // padding y (~0.5mm)
  // Vertical zones (pixels). Sum ≤ LBL_H − 2*LBL_PY = 232.
  const Z_TOP_Y    = LBL_PY;        // 4
  const Z_TOP_H    = 96;            // 12mm — barcode height + name/code area
  const Z_SEP1_Y   = Z_TOP_Y + Z_TOP_H + 2;   // 102
  const Z_MID_Y    = Z_SEP1_Y + 4;            // 106
  const Z_MID_H    = 32;
  const Z_SEP2_Y   = Z_MID_Y + Z_MID_H + 2;   // 140
  const Z_PRICE_Y  = Z_SEP2_Y + 4;            // 144
  const Z_PRICE_H  = 44;
  const Z_SEP3_Y   = Z_PRICE_Y + Z_PRICE_H + 2;   // 190
  const Z_BOT_Y    = Z_SEP3_Y + 4;            // 194
  const Z_BOT_H    = 18;

  // ─── Capacitor plugin accessors ────────────────────────────────────────

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

  // @e-is/capacitor-bluetooth-serial — plugin name 'BluetoothSerial'.
  function getSPP() {
    try {
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.BluetoothSerial) {
        return window.Capacitor.Plugins.BluetoothSerial;
      }
    } catch (e) {}
    throw new Error('BluetoothSerial плъгин не е зареден (S96.D520.1 build трябва да е инсталиран)');
  }

  // ─── Storage getters/setters ───────────────────────────────────────────

  // DTM
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
  function clearDTM() {
    try {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(STORAGE_KEY_SERVICE);
      localStorage.removeItem(STORAGE_KEY_WRITE);
    } catch (e) {}
  }

  // D520
  function getD520Address() {
    try { return localStorage.getItem(STORAGE_KEY_D520_ADDR); } catch (e) { return null; }
  }
  function saveD520Address(addr) {
    try { localStorage.setItem(STORAGE_KEY_D520_ADDR, addr); } catch (e) {}
  }
  function getD520Name() {
    try { return localStorage.getItem(STORAGE_KEY_D520_NAME); } catch (e) { return null; }
  }
  function saveD520Name(name) {
    try { localStorage.setItem(STORAGE_KEY_D520_NAME, name || ''); } catch (e) {}
  }
  function clearD520() {
    try {
      localStorage.removeItem(STORAGE_KEY_D520_ADDR);
      localStorage.removeItem(STORAGE_KEY_D520_NAME);
    } catch (e) {}
  }

  // Active type
  function getActiveType() {
    try { return localStorage.getItem(STORAGE_KEY_TYPE); } catch (e) { return null; }
  }
  function setActiveType(t) {
    try { localStorage.setItem(STORAGE_KEY_TYPE, t); } catch (e) {}
  }
  function clearActiveType() {
    try { localStorage.removeItem(STORAGE_KEY_TYPE); } catch (e) {}
  }

  function uuidEq(a, b) {
    return String(a || '').toLowerCase() === String(b || '').toLowerCase();
  }

  // ─── DTM BLE: discover write endpoint after connect ────────────────────
  // Simplified post-S96 — only DTM_SERVICE_UUID is in the scan filter, so
  // this just confirms the service exists and resolves to its known write char.
  async function discoverDtmEndpoint(ble, deviceId, deviceName) {
    const services = await ble.getServices(deviceId);
    const present = (services || []).map(function(s){ return String(s.uuid).toLowerCase(); });
    dbgLog('[printer] Device ' + (deviceName || '(no name)')
      + ' exposes ' + present.length + ' services: ' + present.join(', '));

    const dtmSvc = (services || []).find(function(s){ return uuidEq(s.uuid, DTM_SERVICE_UUID); });
    if (!dtmSvc) {
      throw new Error('DTM-5811 service (' + DTM_SERVICE_UUID
        + ') not exposed. Picked device exposes: ' + present.join(', ')
        + '. За D520BT използвай "Pair D520BT" (Classic SPP, не BLE).');
    }
    dbgLog('[printer] DTM service confirmed, write char ' + DTM_WRITE_CHAR_UUID);
    return { serviceUuid: dtmSvc.uuid, writeCharUuid: DTM_WRITE_CHAR_UUID };
  }

  // ─── Encoders ──────────────────────────────────────────────────────────

  function asciiToBytes(s) {
    const out = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) {
      const c = s.charCodeAt(i);
      out[i] = c < 128 ? c : 0x3F;
    }
    return out;
  }

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

  // For D520 SPP write: bytes → ASCII-only string (verifies + maps).
  // The @e-is plugin getBytes(UTF_8)s the string; non-ASCII would be corrupted.
  function asciiBytesToString(bytes) {
    let s = '';
    for (let i = 0; i < bytes.length; i++) {
      const b = bytes[i] & 0xFF;
      if (b > 0x7F) {
        // Defensive — caller should not feed non-ASCII to this writer.
        s += '?';
      } else {
        s += String.fromCharCode(b);
      }
    }
    return s;
  }

  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

  // ─── Debug overlay (Tihol's on-device console) ─────────────────────────
  // Full-screen overlay with Copy / Clear / Close. Used by dbgLog() so Tihol
  // can see what happened during pair / connect without USB debugging.
  // S96: D520-experiment buttons (Wakeup/Mini/Label/Luck/Replay) removed —
  // those approaches were BLE-side and confirmed dead by Wireshark.

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
    return /[Ѐ-ӿ]/.test(String(s || ''));
  }

  // ─── S97 price + barcode helpers ───────────────────────────────────────
  // Mirrors products.php wizPrintLabels formatting rules.

  function priceFmt(amt, suffix) {
    return Number(amt || 0).toFixed(2).replace('.', ',') + (suffix ? ' ' + suffix : '');
  }
  function priceEur(amt) { return priceFmt(amt, '€'); }   // for DTM (BITMAP, unicode safe)
  function priceEurAscii(amt) { return priceFmt(amt, 'EUR'); }   // for D520 (ASCII)
  function priceBgn(amt) { return priceFmt(amt * BGN_RATE, 'лв'); }       // DTM
  function priceBgnAscii(amt) { return priceFmt(amt * BGN_RATE, 'lv'); }  // D520

  // EAN13 check-digit calc + auto-fill if barcode missing (matches wizPrintLabels):
  //   barcode = product.barcode || ('200' + zero-pad(product_id, 9))
  //   if 12 digits → append check
  function ensureEan13(input, productId) {
    let s = String(input || '').replace(/[^0-9]/g, '');
    if (!s && productId != null) {
      s = '200' + String(productId).padStart(9, '0');
    }
    if (s.length === 12) {
      let sum = 0;
      for (let i = 0; i < 12; i++) {
        sum += parseInt(s[i], 10) * (i % 2 === 0 ? 1 : 3);
      }
      const check = (10 - sum % 10) % 10;
      s = s + check;
    }
    return s.length === 13 ? s : '';
  }

  // BG cyrillic → latin transliteration (S96.D520).
  // D520BT TSPL TEXT command supports only ASCII fonts (no CP1251 codepage
  // by default, no cyrillic font ROM). Transliterating preserves readability;
  // the alternative is `?` placeholders from asciiToBytes.
  const BG_TRANSLIT = {
    'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ж':'zh','з':'z','и':'i',
    'й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s',
    'т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sht',
    'ъ':'a','ь':'y','ю':'yu','я':'ya',
    'А':'A','Б':'B','В':'V','Г':'G','Д':'D','Е':'E','Ж':'Zh','З':'Z','И':'I',
    'Й':'Y','К':'K','Л':'L','М':'M','Н':'N','О':'O','П':'P','Р':'R','С':'S',
    'Т':'T','У':'U','Ф':'F','Х':'H','Ц':'Ts','Ч':'Ch','Ш':'Sh','Щ':'Sht',
    'Ъ':'A','Ь':'Y','Ю':'Yu','Я':'Ya'
  };
  function transliterateBG(s) {
    s = String(s || '');
    let out = '';
    for (let i = 0; i < s.length; i++) {
      const ch = s[i];
      out += (BG_TRANSLIT[ch] !== undefined) ? BG_TRANSLIT[ch] : ch;
    }
    return out;
  }
  // Sanitize for TSPL TEXT command: transliterate cyrillic, drop non-ASCII,
  // strip control chars and TSPL-quote-conflicting double-quotes.
  function tsplSafe(s, maxLen) {
    let v = transliterateBG(s).replace(/[\r\n\t"]/g, ' ');
    // Drop any remaining non-printable / non-ASCII (defensive).
    v = v.replace(/[^\x20-\x7E]/g, '');
    if (typeof maxLen === 'number' && v.length > maxLen) v = v.substring(0, maxLen);
    return v;
  }

  // ─── Canvas → TSPL BITMAP (cyrillic via raster) ────────────────────────

  // Draw a Canvas, then pack to TSPL BITMAP raw bytes (MSB first, 1=white).
  function _canvasToTsplBitmap(canvas) {
    const W = canvas.width;
    const H = canvas.height;
    const widthBytes = W / 8;
    const ctx = canvas.getContext('2d');
    const px = ctx.getImageData(0, 0, W, H).data;
    const data = new Uint8Array(widthBytes * H);
    for (let y = 0; y < H; y++) {
      for (let bx = 0; bx < widthBytes; bx++) {
        let byte = 0;
        for (let bit = 0; bit < 8; bit++) {
          const x = bx * 8 + bit;
          const i = (y * W + x) * 4;
          const dark = (px[i] + px[i + 1] + px[i + 2]) < 384;
          const bitVal = dark ? 0 : 1;
          byte |= (bitVal << (7 - bit));
        }
        data[y * widthBytes + bx] = byte;
      }
    }
    return { widthBytes, height: H, data };
  }

  /**
   * S97 — Boxed text BITMAP: white text on black background (mimics CSS .l-sz).
   * Used by DTM for size-pill rendering.
   */
  function renderBoxedTextBitmap(text, fontSize, padX, padY) {
    text = String(text || '');
    if (!text) return null;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.font = 'bold ' + fontSize + 'px Arial, sans-serif';
    const tw = Math.ceil(ctx.measureText(text).width);
    const w = tw + padX * 2;
    const widthBytes = Math.ceil(w / 8);
    canvas.width  = widthBytes * 8;
    canvas.height = fontSize + padY * 2 + 2;
    const ctx2 = canvas.getContext('2d');
    // Black background
    ctx2.fillStyle = '#000';
    ctx2.fillRect(0, 0, canvas.width, canvas.height);
    // White text
    ctx2.fillStyle = '#fff';
    ctx2.font = 'bold ' + fontSize + 'px Arial, sans-serif';
    ctx2.textBaseline = 'top';
    ctx2.textAlign = 'left';
    ctx2.fillText(text, padX, padY);
    return _canvasToTsplBitmap(canvas);
  }

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

    ctx.font = 'bold ' + fontSize + 'px Arial, sans-serif';
    const metrics = ctx.measureText(text);
    let w = Math.ceil(metrics.width) + 4;
    if (w > maxWidthPx) w = maxWidthPx;
    if (w < 8) w = 8;

    const widthBytes = Math.ceil(w / 8);
    const canvasWidth = widthBytes * 8;
    const canvasHeight = fontSize + 8;

    canvas.width = canvasWidth;
    canvas.height = canvasHeight;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasWidth, canvasHeight);

    ctx.fillStyle = '#000000';
    ctx.font = 'bold ' + fontSize + 'px Arial, sans-serif';
    ctx.textBaseline = 'top';
    ctx.textAlign = 'left';

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

    const data = new Uint8Array(widthBytes * canvasHeight);
    for (let y = 0; y < canvasHeight; y++) {
      for (let bx = 0; bx < widthBytes; bx++) {
        let byte = 0;
        for (let bit = 0; bit < 8; bit++) {
          const x = bx * 8 + bit;
          const i = (y * canvasWidth + x) * 4;
          const dark = (px[i] + px[i + 1] + px[i + 2]) < 384;
          const bitVal = dark ? 0 : 1;
          byte |= (bitVal << (7 - bit));
        }
        data[y * widthBytes + bx] = byte;
      }
    }

    return { widthBytes, height: canvasHeight, data };
  }

  // ═══════════════════════════════════════════════════════════════════════
  // DTM-5811 TSPL generator — S97 layout matching products.php wizPrintLabels.
  //
  // 50×30mm @ 8 dots/mm = 400×240 dots. 4 sections:
  //   TOP    EAN13 barcode (left) + name + (supplier) + code · EAN (right)
  //   MID    [black size pill] color
  //   PRICE  mode-dependent (eur | dual | noprice)
  //   BOT    origin material · country     |     Внос: importer, city
  //
  // Renders all text as BITMAP (Canvas-rasterized) for perfect cyrillic
  // and unicode (€/лв) support. BLE writes binary cleanly.
  // Backwards compat: missing optional fields → that section is omitted.
  // ═══════════════════════════════════════════════════════════════════════
  function generateTSPL_DTM(product, store, copies, opts) {
    product = product || {};
    store   = store   || {};
    opts    = opts    || {};

    const mode = opts.mode || product.mode || store.label_mode || 'eur';
    const name      = String(product.name      || '').replace(/[\r\n\t"]/g, ' ').substring(0, 48);
    const supplier  = String(product.supplier  || '').replace(/[\r\n\t"]/g, ' ').substring(0, 16);
    const code      = String(product.code      || '').replace(/[\r\n\t"]/g, ' ').substring(0, 20);
    const size      = String(product.size      || '').replace(/[\r\n\t"]/g, ' ').substring(0, 8);
    const color     = String(product.color     || '').replace(/[\r\n\t"]/g, ' ').substring(0, 16);
    const matOrigin = String(product.origin_material || '').replace(/[\r\n\t"]/g, ' ').substring(0, 24);
    const country   = String(product.origin_country  || '').replace(/[\r\n\t"]/g, ' ').substring(0, 16);
    const importer  = String(product.importer        || '').replace(/[\r\n\t"]/g, ' ').substring(0, 16);
    const impCity   = String(product.importer_city   || '').replace(/[\r\n\t"]/g, ' ').substring(0, 20);
    const barcode   = ensureEan13(product.barcode, product.id);
    const amt       = parseFloat(product.retail_price) || 0;
    const n         = Math.max(1, Math.min(parseInt(copies) || 1, 50));

    const parts = [];
    const push = (s) => parts.push(asciiToBytes(s));
    const pushRaw = (b) => parts.push(b);
    const placeBitmap = (bmp, x, y) => {
      if (!bmp) return;
      push('BITMAP ' + x + ',' + y + ',' + bmp.widthBytes + ',' + bmp.height + ',0,');
      pushRaw(bmp.data);
      push('\r\n');
    };

    // Header
    push('SIZE 50 mm,30 mm\r\n');
    push('GAP 2 mm,0\r\n');
    push('DIRECTION 1\r\n');
    push('DENSITY 10\r\n');
    push('SPEED 3\r\n');
    push('CLS\r\n');

    // ── TOP: barcode (left) + name/code (right) ───────────────────────
    // Barcode area target: 28mm × 12mm = 224 × 96 dots.
    // EAN13 with narrow=2 wide=4 is roughly 220 dots wide, height=80.
    if (barcode) {
      push('BARCODE ' + LBL_PX + ',' + Z_TOP_Y + ',"EAN13",80,1,0,2,4,"' + barcode + '"\r\n');
    }

    const txX = LBL_PX + 224 + 8;     // x=248 (right of barcode)
    const txMaxW = LBL_W - txX - LBL_PX;  // ~136 dots
    let txY = Z_TOP_Y + 2;

    if (name) {
      const nameTxt = supplier ? (name + ' (' + supplier + ')') : name;
      const bmp = renderTextBitmap(nameTxt, 18, txMaxW);
      placeBitmap(bmp, txX, txY);
      txY += (bmp ? bmp.height : 0) + 2;
    }
    if (code || barcode) {
      const codeLine = (code ? code : '') + (code && barcode ? ' · ' : '') + (barcode || '');
      const bmp = renderTextBitmap(codeLine, 12, txMaxW);
      placeBitmap(bmp, txX, txY);
    }

    // ── Separator 1 ───────────────────────────────────────────────────
    push('BAR ' + LBL_PX + ',' + Z_SEP1_Y + ',' + (LBL_W - 2 * LBL_PX) + ',1\r\n');

    // ── MID: [size pill] color ────────────────────────────────────────
    let midX = LBL_PX;
    if (mode === 'noprice') {
      // Big centered size + color (per spec)
      if (size) {
        const bmp = renderBoxedTextBitmap(size, 36, 12, 4);
        if (bmp) {
          const x = Math.max(LBL_PX, Math.floor((LBL_W - bmp.widthBytes * 8) / 2));
          placeBitmap(bmp, x, Z_MID_Y);
        }
      }
      if (color) {
        const bmp = renderTextBitmap(color, 22, LBL_W - 2 * LBL_PX);
        if (bmp) {
          const x = Math.max(LBL_PX, Math.floor((LBL_W - bmp.widthBytes * 8) / 2));
          placeBitmap(bmp, x, Z_PRICE_Y);
        }
      }
    } else {
      if (size) {
        const bmp = renderBoxedTextBitmap(size, 22, 8, 3);
        placeBitmap(bmp, midX, Z_MID_Y);
        midX += (bmp ? bmp.widthBytes * 8 : 0) + 8;
      }
      if (color) {
        const bmp = renderTextBitmap(color, 16, LBL_W - midX - LBL_PX);
        placeBitmap(bmp, midX, Z_MID_Y + 6);
      }
    }

    // ── Separator 2 ───────────────────────────────────────────────────
    if (mode !== 'noprice') {
      push('BAR ' + LBL_PX + ',' + Z_SEP2_Y + ',' + (LBL_W - 2 * LBL_PX) + ',1\r\n');
    }

    // ── PRICE row ─────────────────────────────────────────────────────
    if (mode === 'eur') {
      const bmp = renderTextBitmap(priceEur(amt), 30, LBL_W - 2 * LBL_PX);
      if (bmp) {
        const x = Math.max(LBL_PX, Math.floor((LBL_W - bmp.widthBytes * 8) / 2));
        placeBitmap(bmp, x, Z_PRICE_Y);
      }
    } else if (mode === 'dual') {
      // EUR (big) + " | " + BGN (smaller) on one line
      const eurBmp = renderTextBitmap(priceEur(amt), 26, 200);
      const sepBmp = renderTextBitmap('|', 18, 12);
      const bgnBmp = renderTextBitmap(priceBgn(amt), 18, 160);
      let cx = LBL_PX;
      placeBitmap(eurBmp, cx, Z_PRICE_Y + 2);
      cx += (eurBmp ? eurBmp.widthBytes * 8 : 0) + 6;
      placeBitmap(sepBmp, cx, Z_PRICE_Y + 8);
      cx += (sepBmp ? sepBmp.widthBytes * 8 : 0) + 6;
      placeBitmap(bgnBmp, cx, Z_PRICE_Y + 8);
    }

    // ── BOT: origin · country | importer city ─────────────────────────
    const originStr = matOrigin
      ? (country ? matOrigin + ' · ' + country : matOrigin)
      : (country || '');
    const importStr = importer
      ? ('Внос: ' + importer + (impCity ? ', ' + impCity : ''))
      : '';

    if (originStr || importStr) {
      push('BAR ' + LBL_PX + ',' + Z_SEP3_Y + ',' + (LBL_W - 2 * LBL_PX) + ',1\r\n');
      if (originStr) {
        const bmp = renderTextBitmap(originStr, 11, 200);
        placeBitmap(bmp, LBL_PX, Z_BOT_Y);
      }
      if (importStr) {
        const bmp = renderTextBitmap(importStr, 11, 200);
        if (bmp) {
          const x = LBL_W - LBL_PX - bmp.widthBytes * 8;
          placeBitmap(bmp, Math.max(LBL_PX, x), Z_BOT_Y);
        }
      }
    }

    push('PRINT ' + n + '\r\n');
    return concatBytes(parts);
  }

  // ═══════════════════════════════════════════════════════════════════════
  // D520BT TSPL generator — S97 layout matching products.php wizPrintLabels.
  //
  // ASCII-only TSPL (no BITMAP). All cyrillic gets transliterated via
  // tsplSafe(). Currency renders as "EUR"/"lv" (€/лв are non-ASCII, plugin's
  // UTF-8 encoder would corrupt them). Size pill rendered via BAR + TEXT +
  // REVERSE (TSPL inverts a region's pixels — the text drawn black on white
  // becomes white text inside a black pill).
  //
  // Tunables vs. DTM (from Labelife capture, S95):
  //   DENSITY 11, SPEED 4, GAP 3mm.
  // ═══════════════════════════════════════════════════════════════════════
  function generateTSPL_D520(product, store, copies, opts) {
    product = product || {};
    store   = store   || {};
    opts    = opts    || {};

    const mode = opts.mode || product.mode || store.label_mode || 'eur';
    const name      = tsplSafe(product.name      || '', 48);
    const supplier  = tsplSafe(product.supplier  || '', 16);
    const code      = tsplSafe(product.code      || '', 20);
    const size      = tsplSafe(product.size      || '', 8);
    const color     = tsplSafe(product.color     || '', 16);
    const matOrigin = tsplSafe(product.origin_material || '', 24);
    const country   = tsplSafe(product.origin_country  || '', 16);
    const importer  = tsplSafe(product.importer        || '', 16);
    const impCity   = tsplSafe(product.importer_city   || '', 20);
    const barcode   = ensureEan13(product.barcode, product.id);
    const amt       = parseFloat(product.retail_price) || 0;
    const n         = Math.max(1, Math.min(parseInt(copies) || 1, 50));

    const parts = [];
    const push = (s) => parts.push(asciiToBytes(s));

    // Header
    push('SIZE 50 mm,30 mm\r\n');
    push('GAP 3 mm,0\r\n');
    push('DIRECTION 1\r\n');
    push('DENSITY 11\r\n');
    push('SPEED 4\r\n');
    push('CLS\r\n');

    // ── TOP: barcode (left) + name/code (right) ───────────────────────
    if (barcode) {
      push('BARCODE ' + LBL_PX + ',' + Z_TOP_Y + ',"EAN13",80,1,0,2,4,"' + barcode + '"\r\n');
    }
    const txX = LBL_PX + 224 + 8;
    if (name) {
      const nameTxt = supplier ? (name + ' (' + supplier + ')') : name;
      // Truncate to fit ~12 chars in font 2 (12px wide × 12 = 144 dots)
      const truncated = nameTxt.substring(0, 14);
      push('TEXT ' + txX + ',' + (Z_TOP_Y + 2) + ',"2",0,1,1,"' + truncated + '"\r\n');
    }
    if (code || barcode) {
      const codeLine = (code ? code : '') + (code && barcode ? ' ' : '') + (barcode || '');
      push('TEXT ' + txX + ',' + (Z_TOP_Y + 26) + ',"1",0,1,1,"' + codeLine.substring(0, 18) + '"\r\n');
    }

    // ── Separator 1 ───────────────────────────────────────────────────
    push('BAR ' + LBL_PX + ',' + Z_SEP1_Y + ',' + (LBL_W - 2 * LBL_PX) + ',1\r\n');

    // ── MID: size pill + color ────────────────────────────────────────
    if (mode === 'noprice') {
      // Big centered size; color centered below at PRICE zone
      if (size) {
        // Approximate width: each font-5 char (32×48) × scale 1 = 32 dots
        const pillW = size.length * 32 + 24;
        const pillH = 50;
        const pillX = Math.max(LBL_PX, Math.floor((LBL_W - pillW) / 2));
        const pillY = Z_MID_Y;
        push('TEXT ' + (pillX + 12) + ',' + (pillY + 4) + ',"5",0,1,1,"' + size + '"\r\n');
        push('REVERSE ' + pillX + ',' + pillY + ',' + pillW + ',' + pillH + '\r\n');
      }
      if (color) {
        // Color in PRICE zone, centered
        const cw = color.length * 16 + 8;
        const cx = Math.max(LBL_PX, Math.floor((LBL_W - cw) / 2));
        push('TEXT ' + cx + ',' + (Z_PRICE_Y + 8) + ',"3",0,1,1,"' + color + '"\r\n');
      }
    } else {
      let cursorX = LBL_PX;
      if (size) {
        // Pill: BAR for blackbox, TEXT inside, REVERSE inverts to white-on-black
        const pillW = size.length * 16 + 16;
        const pillH = 32;
        const pillX = cursorX;
        const pillY = Z_MID_Y - 2;
        push('TEXT ' + (pillX + 8) + ',' + (pillY + 4) + ',"3",0,1,1,"' + size + '"\r\n');
        push('REVERSE ' + pillX + ',' + pillY + ',' + pillW + ',' + pillH + '\r\n');
        cursorX = pillX + pillW + 8;
      }
      if (color) {
        push('TEXT ' + cursorX + ',' + (Z_MID_Y + 6) + ',"2",0,1,1,"' + color + '"\r\n');
      }
    }

    // ── Separator 2 ───────────────────────────────────────────────────
    if (mode !== 'noprice') {
      push('BAR ' + LBL_PX + ',' + Z_SEP2_Y + ',' + (LBL_W - 2 * LBL_PX) + ',1\r\n');
    }

    // ── PRICE row ─────────────────────────────────────────────────────
    if (mode === 'eur') {
      const txt = priceEurAscii(amt);
      // font 4 scale 2 = 48 dots tall; centered horizontally
      const txtW = txt.length * 24 * 2;
      const cx = Math.max(LBL_PX, Math.floor((LBL_W - txtW) / 2));
      push('TEXT ' + cx + ',' + Z_PRICE_Y + ',"4",0,2,1,"' + txt + '"\r\n');
    } else if (mode === 'dual') {
      const eurT = priceEurAscii(amt);
      const bgnT = priceBgnAscii(amt);
      // EUR font 4 scale 1, BGN font 2 scale 1
      push('TEXT ' + LBL_PX + ',' + Z_PRICE_Y + ',"4",0,1,1,"' + eurT + '"\r\n');
      const eurW = eurT.length * 24;
      const sepX = LBL_PX + eurW + 8;
      push('TEXT ' + sepX + ',' + (Z_PRICE_Y + 8) + ',"2",0,1,1,"|"\r\n');
      push('TEXT ' + (sepX + 16) + ',' + (Z_PRICE_Y + 12) + ',"2",0,1,1,"' + bgnT + '"\r\n');
    }

    // ── BOT: origin - country | importer ─────────────────────────────
    // Use ASCII '-' instead of '·' (U+00B7) — non-ASCII is stripped by tsplSafe.
    const originStr = matOrigin
      ? (country ? matOrigin + ' - ' + country : matOrigin)
      : (country || '');
    const importStr = importer
      ? ('Vnos: ' + importer + (impCity ? ', ' + impCity : ''))
      : '';

    if (originStr || importStr) {
      push('BAR ' + LBL_PX + ',' + Z_SEP3_Y + ',' + (LBL_W - 2 * LBL_PX) + ',1\r\n');
      if (originStr) {
        push('TEXT ' + LBL_PX + ',' + Z_BOT_Y + ',"1",0,1,1,"' + originStr.substring(0, 22) + '"\r\n');
      }
      if (importStr) {
        // Right-aligned approx: each font-1 char = 8 dots
        const w = importStr.length * 8;
        const x = Math.max(LBL_PX, LBL_W - LBL_PX - w);
        push('TEXT ' + x + ',' + Z_BOT_Y + ',"1",0,1,1,"' + importStr.substring(0, 22) + '"\r\n');
      }
    }

    push('PRINT ' + n + '\r\n');
    return concatBytes(parts);
  }

  // ─── DTM BLE write (chunked) ──────────────────────────────────────────
  async function writeChunked_DTM(ble, deviceId, bytes) {
    const serviceUuid   = getSavedServiceUuid()   || DTM_SERVICE_UUID;
    const writeCharUuid = getSavedWriteCharUuid() || DTM_WRITE_CHAR_UUID;
    for (let i = 0; i < bytes.length; i += CHUNK_SIZE) {
      const chunk = bytes.slice(i, i + CHUNK_SIZE);
      await ble.write(deviceId, serviceUuid, writeCharUuid, bytesToDataView(chunk));
      await sleep(5);
    }
  }

  // ─── D520 SPP write (S96.D520.3) ───────────────────────────────────────
  // Connects RFCOMM, writes the ASCII payload, keeps connection alive for
  // subsequent prints. Retries once with full reconnect on first-write fail.
  async function writeSPP_D520(address, bytes) {
    if (!address) throw new Error('D520 address missing');
    const spp = getSPP();

    const state = await spp.isEnabled();
    if (!state || !state.enabled) {
      try { await spp.enable(); } catch (_) {}
    }

    if (!window.__sppConnected || window.__sppConnectedAddr !== address) {
      try {
        await spp.connect({ address: address });
      } catch (e) {
        // Some firmware needs insecure (no PIN re-prompt) for already-bonded.
        try { await spp.connectInsecure({ address: address }); }
        catch (e2) { throw e; }
      }
      window.__sppConnected = true;
      window.__sppConnectedAddr = address;
    }

    const value = asciiBytesToString(bytes);
    try {
      await spp.write({ address: address, value: value });
    } catch (e) {
      window.__sppConnected = false;
      try { await spp.disconnect({ address: address }); } catch (_) {}
      await sleep(300);
      await spp.connect({ address: address });
      window.__sppConnected = true;
      window.__sppConnectedAddr = address;
      await spp.write({ address: address, value: value });
    }
    await sleep(150);
  }

  // ─── Public API ────────────────────────────────────────────────────────

  const CapPrinter = {

    isAvailable() {
      return isCapacitor();
    },

    hasPairedPrinter() {
      return !!getSavedDeviceId() || !!getD520Address();
    },

    hasPairedDTM()  { return !!getSavedDeviceId(); },
    hasPairedD520() { return !!getD520Address(); },

    /**
     * Legacy alias — preserves pre-S96 call sites in products.php / printer-setup.php.
     * Calls pairDTM() (BLE flow). For D520 use pairD520().
     */
    async pair() {
      return await this.pairDTM();
    },

    async pairDTM() {
      if (!isCapacitor()) {
        throw new Error('Не си в мобилно приложение');
      }
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });

      const device = await ble.requestDevice({
        services: SERVICE_UUIDS,
        optionalServices: SERVICE_UUIDS
      }).catch(async () => {
        // Fallback: някои Android BLE stack-ове не advertise-ват service UUID
        // в scan packet-а → service-filtered scan връща празен списък.
        return await ble.requestDevice({});
      });

      if (!device || !device.deviceId) {
        throw new Error('Не е избран принтер');
      }
      await ble.connect(device.deviceId, () => clearDTM());

      try {
        const ep = await discoverDtmEndpoint(ble, device.deviceId, device.name);
        saveDeviceId(device.deviceId);
        saveServiceUuid(ep.serviceUuid);
        saveWriteCharUuid(ep.writeCharUuid);
        setActiveType(TYPE_DTM);
        dbgLog('[printer] Paired DTM: ' + (device.name || device.deviceId));
      } catch (e) {
        try { await ble.disconnect(device.deviceId); } catch (_) {}
        clearDTM();
        throw e;
      }
      return { deviceId: device.deviceId, name: device.name, type: TYPE_DTM };
    },

    /**
     * Pair a D520BT via Bluetooth Classic SPP.
     * Uses @e-is/capacitor-bluetooth-serial scan() which returns the system's
     * already-paired Classic device list. User must first bond the printer in
     * Android Settings → Bluetooth (PIN 0000 if prompted) — this is a one-time
     * OS-level pairing the app cannot bypass for SPP.
     */
    async pairD520() {
      if (!isCapacitor()) {
        throw new Error('Не си в мобилно приложение');
      }
      const spp = getSPP();
      const state = await spp.isEnabled();
      if (!state || !state.enabled) {
        try { await spp.enable(); } catch (_) {}
      }
      const result = await spp.scan();
      const devices = (result && result.devices) || [];
      dbgLog('[printer] SPP paired devices: ' + devices.length);
      devices.forEach(function(d, i) {
        dbgLog('  [' + i + '] ' + (d.name || '(no name)') + '  ' + d.address);
      });

      // Auto-pick D520BT-Z by name; if not found, throw with a helpful list.
      let picked = devices.find(function(d) {
        const n = String(d.name || '').toUpperCase();
        return n.includes('D520') || n.includes('D520BT');
      });
      if (!picked) {
        // Fallback: any name containing common AIMO/Phomemo/241BT markers.
        picked = devices.find(function(d) {
          const n = String(d.name || '').toUpperCase();
          return n.includes('AIMO') || n.includes('PHOMEMO') || n.includes('241BT');
        });
      }
      if (!picked) {
        const names = devices.map(function(d){ return d.name || d.address; }).join(', ');
        throw new Error('D520BT не е сдвоен с телефона. '
          + 'Първо го сдвои от Android Settings → Bluetooth (PIN 0000), '
          + 'после натисни пак "Pair D520BT". Сдвоени Classic устройства: '
          + (names || '(няма)'));
      }

      saveD520Address(picked.address);
      saveD520Name(picked.name || '');
      setActiveType(TYPE_D520);
      dbgLog('[printer] Paired D520: ' + (picked.name || '') + ' @ ' + picked.address);
      return { address: picked.address, name: picked.name, type: TYPE_D520 };
    },

    async print(product, store, copies, opts) {
      if (!isCapacitor()) throw new Error('Мобилен печат не е достъпен тук');

      // Resolve which printer to use:
      //   1. Explicit opts.type wins.
      //   2. Active type if its printer is paired.
      //   3. Whichever is paired (DTM preferred for legacy products.php behavior).
      let type = opts && opts.type;
      if (!type) {
        const active = getActiveType();
        if (active === TYPE_DTM  && this.hasPairedDTM())  type = TYPE_DTM;
        else if (active === TYPE_D520 && this.hasPairedD520()) type = TYPE_D520;
        else if (this.hasPairedDTM())  type = TYPE_DTM;
        else if (this.hasPairedD520()) type = TYPE_D520;
      }

      if (type === TYPE_DTM)  return await this._printDTM(product, store, copies);
      if (type === TYPE_D520) return await this._printD520(product, store, copies);
      throw new Error('Няма сдвоен принтер');
    },

    async _printDTM(product, store, copies) {
      const ble = getBle();
      const bytes = generateTSPL_DTM(product, store, copies || 1);

      let id = getSavedDeviceId();
      if (!id) throw new Error('Няма сдвоен DTM принтер');

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

      try {
        await writeChunked_DTM(ble, id, bytes);
      } catch (e) {
        window.__blePrinterConnected = false;
        try { await ble.disconnect(id); } catch (_) {}
        await sleep(200);
        await ble.connect(id, () => { window.__blePrinterConnected = false; });
        window.__blePrinterConnected = true;
        await writeChunked_DTM(ble, id, bytes);
      }
      await sleep(200);
      return { ok: true, type: TYPE_DTM, bytes: bytes.length, copies: copies || 1 };
    },

    async _printD520(product, store, copies) {
      const addr = getD520Address();
      if (!addr) throw new Error('Няма сдвоен D520BT принтер');
      const bytes = generateTSPL_D520(product, store, copies || 1);
      await writeSPP_D520(addr, bytes);
      return { ok: true, type: TYPE_D520, bytes: bytes.length, copies: copies || 1 };
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

    /**
     * Forget the active printer. Switches active to the other if it's paired.
     * Use forgetAll() to clear both.
     */
    forget() {
      const active = getActiveType();
      if (active === TYPE_D520) {
        clearD520();
        if (getSavedDeviceId()) setActiveType(TYPE_DTM);
        else clearActiveType();
      } else {
        clearDTM();
        if (getD520Address()) setActiveType(TYPE_D520);
        else clearActiveType();
      }
    },

    forgetAll() {
      clearDTM();
      clearD520();
      clearActiveType();
    },

    forgetDTM()  { clearDTM();  if (getActiveType() === TYPE_DTM)  clearActiveType(); },
    forgetD520() { clearD520(); if (getActiveType() === TYPE_D520) clearActiveType(); },

    getType()       { return getActiveType(); },
    getActiveType() { return getActiveType(); },
    setActiveType(t) {
      if (t !== TYPE_DTM && t !== TYPE_D520) throw new Error('Invalid type');
      setActiveType(t);
    },

    async connect() {
      if (!isCapacitor()) throw new Error('Мобилен печат не е достъпен тук');
      const id = getSavedDeviceId();
      if (!id) throw new Error('Няма сдвоен DTM принтер');
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });
      try { await ble.connect(id, () => {}); } catch (e) {}
      return id;
    },

    async disconnect() {
      if (!isCapacitor()) return;
      const id = getSavedDeviceId();
      if (id) {
        try { await getBle().disconnect(id); } catch (e) {}
      }
      window.__blePrinterConnected = false;
      const d520 = getD520Address();
      if (d520) {
        try { await getSPP().disconnect({ address: d520 }); } catch (e) {}
      }
    },

    // ─── Debug helpers (BLE GATT introspection) ──────────────────────────
    // Useful when adding a new BLE printer model. Not used for D520 SPP path.

    async pairDebug() {
      if (!isCapacitor()) throw new Error('Не си в мобилно приложение');
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });
      dbgLog('[printer-debug] Opening UNFILTERED picker...');
      const device = await ble.requestDevice({});
      if (!device || !device.deviceId) throw new Error('No device picked');
      dbgLog('[printer-debug] Picked: ' + JSON.stringify({
        name: device.name || '(no name)',
        deviceId: device.deviceId
      }));
      await ble.connect(device.deviceId, function() {
        dbgLog('[printer-debug] Disconnected (callback)');
      });
      try {
        const services = await ble.getServices(device.deviceId);
        dbgLog('[printer-debug] Services count: ' + services.length);
        services.forEach(function(svc, si) {
          dbgLog('[printer-debug] [' + si + '] service UUID: ' + svc.uuid);
          (svc.characteristics || []).forEach(function(ch, ci) {
            const props = ch.properties || {};
            const flags = [];
            if (props.read) flags.push('READ');
            if (props.write) flags.push('WRITE');
            if (props.writeWithoutResponse) flags.push('WRITE_NO_RESP');
            if (props.notify) flags.push('NOTIFY');
            if (props.indicate) flags.push('INDICATE');
            dbgLog('[printer-debug]   [' + si + '.' + ci + '] char UUID: '
              + ch.uuid + '  [' + flags.join(',') + ']');
          });
        });
      } catch (e) {
        dbgLog('[printer-debug] getServices error: ' + (e && e.message ? e.message : e));
        throw e;
      } finally {
        try { await ble.disconnect(device.deviceId); } catch (_) {}
      }
      return { name: device.name, deviceId: device.deviceId };
    },

    async scanDebug(durationMs) {
      durationMs = durationMs || 8000;
      if (!isCapacitor()) throw new Error('Не си в мобилно приложение');
      const ble = getBle();
      await ble.initialize({ androidNeverForLocation: false });
      dbgLog('[printer-debug] LE scan ' + durationMs + 'ms...');
      const seen = {};
      let seenCount = 0;
      try {
        await ble.requestLEScan({}, function(result) {
          const key = (result.device && result.device.deviceId) || '';
          if (!key || seen[key]) return;
          seen[key] = true;
          seenCount++;
          dbgLog('[printer-debug] Found: ' + JSON.stringify({
            name: (result.device && result.device.name) || result.localName || '(unnamed)',
            deviceId: key,
            rssi: result.rssi,
            uuids: result.uuids || []
          }));
        });
      } catch (e) {
        dbgLog('[printer-debug] requestLEScan unavailable: '
          + (e && e.message ? e.message : e));
        throw e;
      }
      await sleep(durationMs);
      try { await ble.stopLEScan(); } catch (_) {}
      dbgLog('[printer-debug] Scan complete. Unique: ' + seenCount);
      return seenCount;
    },

    async sppListDebug() {
      if (!isCapacitor()) throw new Error('Не си в мобилно приложение');
      const spp = getSPP();
      const state = await spp.isEnabled();
      dbgLog('[printer-debug] SPP enabled: ' + (state && state.enabled));
      const r = await spp.scan();
      const devs = (r && r.devices) || [];
      dbgLog('[printer-debug] SPP paired devices: ' + devs.length);
      devs.forEach(function(d, i) {
        dbgLog('[printer-debug]   [' + i + '] '
          + (d.name || '(no name)') + '  ' + d.address
          + '  class=' + d.class + '  uuid=' + d.uuid);
      });
      return devs;
    },

    showDebugOverlay: showDebugOverlay,

    // ─── _diagnostics — generic protocol probes (BLE only) ───────────────
    // For experimenting with new BLE printer models. D520-specific BLE
    // probes were stripped in S96 (BLE channel proven empty for D520 print).
    _diagnostics: {
      async sendRaw(bytes, label) {
        if (!isCapacitor()) throw new Error('Мобилен печат не е достъпен тук');
        const ble = getBle();
        const id = getSavedDeviceId();
        if (!id) throw new Error('Няма сдвоен DTM принтер');

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

        dbgLog('[printer] ' + label + ' — ' + bytes.length + ' bytes');
        await writeChunked_DTM(ble, id, bytes);
        return { ok: true, bytes: bytes.length, protocol: label };
      },

      async tspl() {
        const cmd =
          'SIZE 50 mm,30 mm\r\n' +
          'GAP 2 mm,0\r\n' +
          'DIRECTION 1\r\n' +
          'DENSITY 8\r\n' +
          'SPEED 4\r\n' +
          'CLS\r\n' +
          'TEXT 50,50,"3",0,1,1,"TEST"\r\n' +
          'PRINT 1,1\r\n';
        return await this.sendRaw(asciiToBytes(cmd), 'TSPL raw');
      },

      info() {
        const info = {
          dtmDeviceId:    getSavedDeviceId(),
          dtmServiceUuid: getSavedServiceUuid(),
          dtmWriteChar:   getSavedWriteCharUuid(),
          d520Address:    getD520Address(),
          d520Name:       getD520Name(),
          activeType:     getActiveType() || '(none)',
          isCapacitor:    isCapacitor()
        };
        dbgLog('[printer] Info: ' + JSON.stringify(info, null, 2));
        return info;
      }
    },

    // Internal exports (for tools/d520_classic_test.php and unit tests).
    _generateTSPL_DTM:  generateTSPL_DTM,
    _generateTSPL_D520: generateTSPL_D520,
    _writeSPP_D520:     writeSPP_D520,
    _transliterateBG:   transliterateBG,
    _tsplSafe:          tsplSafe,
    _isCapacitor:       isCapacitor,
    _getDeviceId:       getSavedDeviceId,
    _getD520Address:    getD520Address,
    _renderTextBitmap:  renderTextBitmap,
    _asciiToBytes:      asciiToBytes,
    _asciiBytesToString: asciiBytesToString,
    _dbgLog:            dbgLog
  };

  window.CapPrinter = CapPrinter;

})(window);
