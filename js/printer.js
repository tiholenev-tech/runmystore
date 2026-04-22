/**
 * RunMyStore.ai — Bluetooth Printer Module
 * 
 * Поддържа: DTM-5811 (TSPL protocol)
 * Web Bluetooth API + characteristic chunked write
 * 
 * Етикет: 50×30mm
 * Service UUID: 0xffb0
 * Characteristic UUID: 0xffb2 (write)
 * MTU: ~20 bytes / chunk
 * 
 * Public API:
 *   RmsPrinter.pair()                       — Pairing dialog (Settings)
 *   RmsPrinter.unpair()                     — Forget device
 *   RmsPrinter.isPaired()                   — bool
 *   RmsPrinter.printLabel(data, copies)     — Print N copies of one label
 *   RmsPrinter.printBatch(items)            — Print array of {data, copies}
 *   RmsPrinter.printTest()                  — Тестов етикет (от Settings)
 *   RmsPrinter.status                       — 'idle' | 'connecting' | 'printing' | 'error'
 *   RmsPrinter.onStatus(callback)           — Subscribe to status changes
 * 
 * Etiqueta data shape:
 *   {
 *     store: 'RunMyStore',           // Магазин (Ред 1)
 *     name: 'Тениска Nike Air',      // Артикул (Ред 2, max 28 chars)
 *     barcode: '5901234567890',      // Code 128 (Ред 3)
 *     priceEur: '€ 45.50',           // Цена EUR (Ред 4)
 *     priceBgn: '89.00 лв',          // Цена BGN (опц., dual до 8.8.2026)
 *     code: 'EN-2026-0248',          // Вътрешен код (Ред 5)
 *     variant: 'M · Navy',           // Вариант tag (опц.)
 *     format: 'both'                 // 'both' | 'eur' | 'no-price'
 *   }
 */

(function (window) {
    'use strict';

    const SERVICE_UUID = 0xffb0;
    const WRITE_CHAR_UUID = 0xffb2;
    const CHUNK_SIZE = 20;        // BLE MTU safe limit
    const CHUNK_DELAY = 50;       // ms between chunks
    const STORAGE_KEY = 'rms_printer_id';

    let _device = null;
    let _server = null;
    let _characteristic = null;
    let _status = 'idle';
    let _statusListeners = [];

    // ───────────────────────────────────────────
    // STATUS
    // ───────────────────────────────────────────

    function setStatus(s, detail) {
        _status = s;
        _statusListeners.forEach(fn => {
            try { fn(s, detail); } catch (e) { console.warn('[printer] status cb error', e); }
        });
    }

    // ───────────────────────────────────────────
    // CAPABILITY CHECK
    // ───────────────────────────────────────────

    function isSupported() {
        return typeof navigator !== 'undefined'
            && navigator.bluetooth
            && typeof navigator.bluetooth.requestDevice === 'function';
    }

    // ───────────────────────────────────────────
    // PAIRING
    // ───────────────────────────────────────────

    async function pair() {
        if (!isSupported()) {
            const msg = 'Принтерът работи на Android. iOS — скоро.';
            setStatus('error', msg);
            throw new Error(msg);
        }

        setStatus('connecting');

        try {
            // Прозорец за Bluetooth избор — филтър по име DTM-5811
            const device = await navigator.bluetooth.requestDevice({
                filters: [
                    { namePrefix: 'DTM-5811' },
                    { namePrefix: 'DTM' },
                    { namePrefix: 'Printer' }
                ],
                optionalServices: [SERVICE_UUID]
            });

            if (!device) {
                setStatus('idle');
                return null;
            }

            _device = device;

            // Запазваме ID за бъдеща връзка
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    id: device.id,
                    name: device.name || 'DTM-5811',
                    pairedAt: Date.now()
                }));
            } catch (e) { /* localStorage блокиран — продължаваме */ }

            device.addEventListener('gattserverdisconnected', onDisconnect);

            await connect();
            setStatus('idle');
            return { id: device.id, name: device.name || 'DTM-5811' };
        } catch (err) {
            // User cancel — silent
            if (err && (err.name === 'NotFoundError' || err.message?.includes('cancelled'))) {
                setStatus('idle');
                return null;
            }
            setStatus('error', err.message || 'Pairing failed');
            throw err;
        }
    }

    function unpair() {
        if (_device && _device.gatt && _device.gatt.connected) {
            try { _device.gatt.disconnect(); } catch (e) { /* ignore */ }
        }
        _device = null;
        _server = null;
        _characteristic = null;
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
        setStatus('idle');
    }

    function isPaired() {
        try {
            return !!localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return false;
        }
    }

    function getPairedInfo() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function onDisconnect() {
        _server = null;
        _characteristic = null;
        // Не изтриваме _device — може да се reconnect-нем при следващ print
    }

    // ───────────────────────────────────────────
    // CONNECTION
    // ───────────────────────────────────────────

    async function connect() {
        if (!_device) {
            // Опит за reconnect от cache (Web Bluetooth getDevices() ако е поддържано)
            if (navigator.bluetooth.getDevices) {
                const cached = getPairedInfo();
                if (cached) {
                    const devices = await navigator.bluetooth.getDevices();
                    _device = devices.find(d => d.id === cached.id) || null;
                }
            }
            if (!_device) {
                throw new Error('Принтерът не е сдвоен. Сдвои от Настройки.');
            }
        }

        if (_device.gatt.connected && _characteristic) {
            return _characteristic;
        }

        _server = await _device.gatt.connect();
        const service = await _server.getPrimaryService(SERVICE_UUID);
        _characteristic = await service.getCharacteristic(WRITE_CHAR_UUID);
        return _characteristic;
    }

    // ───────────────────────────────────────────
    // TSPL GENERATION
    // ───────────────────────────────────────────

    /**
     * Безопасно truncate за TSPL TEXT command
     */
    function trunc(s, n) {
        s = String(s || '');
        if (s.length <= n) return s;
        return s.slice(0, n - 1) + '…';
    }

    /**
     * Escape за TSPL string (вътре в кавички)
     * TSPL: " → \"
     */
    function esc(s) {
        return String(s || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    /**
     * Build TSPL command string за един етикет.
     * 
     * Етикет 50×30mm, gap 3mm:
     *   ─────────────────────────────────  y=10  Магазин (font 2, size 1)
     *   Артикул име                        y=40  (font 2, size 1)
     *   ████████████████████ Code128       y=70  (height 60)
     *   € 45.50    89.00 лв                y=155 (font 3 за price)
     *   EN-2026-0248                       y=195 (font 1, малък)
     */
    function buildTSPL(data, copies) {
        const fmt = data.format || 'both';
        const showPriceEur = fmt !== 'no-price' && data.priceEur;
        const showPriceBgn = fmt === 'both' && data.priceBgn;

        let out = '';
        out += 'SIZE 50 mm,30 mm\r\n';
        out += 'GAP 3 mm,0\r\n';
        out += 'DIRECTION 0\r\n';
        out += 'CLS\r\n';

        // Опитваме TSS24.BF2 (built-in unicode font) — fallback "0" за safety
        // Координати в dots @ 203 dpi: 1mm ≈ 8 dots, 50mm × 30mm = 400 × 240 dots

        let y = 10;

        // Ред 1 — магазин
        if (data.store) {
            out += `TEXT 12,${y},"TSS24.BF2",0,8,8,"${esc(trunc(data.store, 30))}"\r\n`;
            y += 30;
        }

        // Ред 2 — артикул
        if (data.name) {
            out += `TEXT 12,${y},"TSS24.BF2",0,9,9,"${esc(trunc(data.name, 28))}"\r\n`;
            y += 32;
        }

        // Ред 2.5 — вариант (по желание)
        if (data.variant) {
            out += `TEXT 12,${y},"TSS24.BF2",0,7,7,"${esc(trunc(data.variant, 24))}"\r\n`;
            y += 24;
        }

        // Ред 3 — баркод
        if (data.barcode) {
            out += `BARCODE 12,${y},"128",60,1,0,2,2,"${esc(data.barcode)}"\r\n`;
            y += 70;
        } else {
            y += 5; // ако няма баркод — оставяме малко място
        }

        // Ред 4 — цена
        if (showPriceEur) {
            const eurStr = trunc(data.priceEur, 16);
            out += `TEXT 12,${y},"TSS24.BF2",0,12,12,"${esc(eurStr)}"\r\n`;
            if (showPriceBgn) {
                const bgnStr = trunc(data.priceBgn, 16);
                // Десен край — приблизително x=240 (за 50mm)
                out += `TEXT 240,${y + 4},"TSS24.BF2",0,9,9,"${esc(bgnStr)}"\r\n`;
            }
            y += 32;
        }

        // Ред 5 — код
        if (data.code) {
            out += `TEXT 12,${y},"TSS24.BF2",0,7,7,"${esc(trunc(data.code, 30))}"\r\n`;
        }

        const c = Math.max(1, Math.min(99, parseInt(copies, 10) || 1));
        out += `PRINT 1,${c}\r\n`;

        return out;
    }

    /**
     * За Тихол да види TSPL output в console (debug)
     */
    function previewTSPL(data, copies) {
        return buildTSPL(data, copies);
    }

    // ───────────────────────────────────────────
    // CHUNKED WRITE
    // ───────────────────────────────────────────

    async function writeChunked(bytes) {
        if (!_characteristic) {
            await connect();
        }

        for (let i = 0; i < bytes.length; i += CHUNK_SIZE) {
            const chunk = bytes.slice(i, i + CHUNK_SIZE);
            // writeValueWithoutResponse е по-бърз ако се поддържа
            try {
                if (_characteristic.writeValueWithoutResponse) {
                    await _characteristic.writeValueWithoutResponse(chunk);
                } else {
                    await _characteristic.writeValue(chunk);
                }
            } catch (e) {
                // Retry веднъж със стандартен write
                await new Promise(r => setTimeout(r, 100));
                await _characteristic.writeValue(chunk);
            }
            await new Promise(r => setTimeout(r, CHUNK_DELAY));
        }
    }

    // ───────────────────────────────────────────
    // PUBLIC PRINT
    // ───────────────────────────────────────────

    async function printLabel(data, copies) {
        if (!isSupported()) {
            throw new Error('Принтерът работи на Android. iOS — скоро.');
        }
        if (!isPaired() && !_device) {
            throw new Error('Принтерът не е сдвоен. Сдвои от Настройки.');
        }

        setStatus('printing');

        const tspl = buildTSPL(data, copies);
        // TSPL поддържа UTF-8 при CODEPAGE UTF-8, но за DTM-5811 разчитаме на TSS24.BF2 (китайски/multilingual font)
        const bytes = new TextEncoder().encode(tspl);

        let attempt = 0;
        const maxAttempts = 2;
        while (attempt < maxAttempts) {
            try {
                await writeChunked(bytes);
                setStatus('idle');
                return { ok: true, bytes: bytes.length, copies };
            } catch (err) {
                attempt++;
                console.warn(`[printer] print attempt ${attempt} failed:`, err);
                if (attempt >= maxAttempts) {
                    setStatus('error', 'Принтерът прекъсна. Опитай пак.');
                    throw new Error('Принтерът прекъсна. Опитай пак.');
                }
                // Force reconnect
                _server = null;
                _characteristic = null;
                await new Promise(r => setTimeout(r, 300));
            }
        }
    }

    /**
     * Print batch — масив от {data, copies}
     */
    async function printBatch(items) {
        if (!Array.isArray(items) || items.length === 0) {
            throw new Error('Празен списък за печат.');
        }

        setStatus('printing');
        const results = [];

        try {
            await connect();
        } catch (e) {
            setStatus('error', e.message);
            throw e;
        }

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            try {
                const tspl = buildTSPL(item.data, item.copies || 1);
                const bytes = new TextEncoder().encode(tspl);
                await writeChunked(bytes);
                results.push({ index: i, ok: true });
                // Кратка пауза между етикети — да не претовариме принтера
                if (i < items.length - 1) {
                    await new Promise(r => setTimeout(r, 200));
                }
            } catch (err) {
                results.push({ index: i, ok: false, error: err.message });
                console.error(`[printer] batch item ${i} failed:`, err);
            }
        }

        setStatus('idle');
        return results;
    }

    /**
     * Тестов етикет (от Settings)
     */
    async function printTest() {
        const testData = {
            store: 'RunMyStore.ai',
            name: 'Тестов етикет Ø42',
            variant: 'TEST',
            barcode: '1234567890128',
            priceEur: '€ 19.99',
            priceBgn: '39.10 лв',
            code: 'TEST-001',
            format: 'both'
        };
        return await printLabel(testData, 1);
    }

    // ───────────────────────────────────────────
    // EXPORT
    // ───────────────────────────────────────────

    window.RmsPrinter = {
        // Capability
        isSupported,

        // Pairing
        pair,
        unpair,
        isPaired,
        getPairedInfo,

        // Print
        printLabel,
        printBatch,
        printTest,

        // Debug
        previewTSPL,
        buildTSPL,

        // Status
        get status() { return _status; },
        onStatus(cb) {
            if (typeof cb === 'function') _statusListeners.push(cb);
            return () => {
                _statusListeners = _statusListeners.filter(f => f !== cb);
            };
        },

        // Constants (за external code)
        SERVICE_UUID,
        WRITE_CHAR_UUID
    };

})(window);
