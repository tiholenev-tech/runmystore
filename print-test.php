<?php
/**
 * RunMyStore.ai — Bluetooth Printer Test Page
 * 
 * Самостоятелна страница за тестване на DTM-5811 принтер.
 * НЕ е production UI — само за тестване от Тихол с реалния принтер.
 * 
 * Достъп: /print-test.php (без login)
 * След като тестваме че работи — integration в products.php (S81 етап 1, стъпка 3)
 */

// Минимална tenant data за теста (без DB зависимост)
$store_name = $_GET['store'] ?? 'RunMyStore.ai';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>Тест Bluetooth принтер — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
    --hue1: 200; --hue2: 225;
    --border: 1px;
    --border-color: hsl(var(--hue2), 12%, 20%);
    --radius: 22px; --radius-sm: 14px;
    --ease: cubic-bezier(0.5, 1, 0.89, 1);
    --bg-main: #08090d;
    --text-primary: #f1f5f9;
    --text-secondary: rgba(255, 255, 255, 0.6);
    --text-muted: rgba(255, 255, 255, 0.4);
}
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html, body {
    background: var(--bg-main);
    color: var(--text-primary);
    font-family: 'Montserrat', Inter, system-ui, sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
}
body {
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / 0.22) 0%, transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / 0.22) 0%, transparent 60%),
        linear-gradient(180deg, #0a0b14 0%, #050609 100%);
    background-attachment: fixed;
    padding-bottom: 40px;
}
input, button, textarea {
    font-family: inherit;
    -webkit-user-select: text;
    user-select: text;
}
.app { max-width: 480px; margin: 0 auto; padding: 14px 12px 20px; }

/* ═══ NEON GLASS ═══ */
.glass {
    position: relative;
    border-radius: var(--radius);
    border: var(--border) solid var(--border-color);
    background:
        linear-gradient(235deg, hsl(var(--hue1) 50% 10% / 0.8), hsl(var(--hue1) 50% 10% / 0) 33%),
        linear-gradient(45deg, hsl(var(--hue2) 50% 10% / 0.8), hsl(var(--hue2) 50% 10% / 0) 33%),
        linear-gradient(hsl(220deg 25% 4.8% / 0.78));
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    box-shadow: hsl(var(--hue2) 50% 2%) 0 10px 16px -8px;
    isolation: isolate;
    padding: 18px 16px;
    margin-bottom: 14px;
}
.glass.sm { border-radius: var(--radius-sm); padding: 14px 14px; }

/* ═══ HEADER ═══ */
.header {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 4px 16px;
}
.header h1 {
    font-size: 18px; font-weight: 800; letter-spacing: -0.01em;
    flex: 1;
}
.header-sub {
    font-size: 11px; color: var(--text-muted); margin-top: 2px;
    font-weight: 500;
}

/* ═══ STATUS PILL ═══ */
.status-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px;
    border-radius: 100px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.04em;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}
.status-pill::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: #ef4444; box-shadow: 0 0 8px #ef4444;
}
.status-pill.ok {
    background: rgba(34, 197, 94, 0.12);
    border-color: rgba(34, 197, 94, 0.3);
    color: #86efac;
}
.status-pill.ok::before { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
.status-pill.busy {
    background: rgba(251, 191, 36, 0.12);
    border-color: rgba(251, 191, 36, 0.3);
    color: #fcd34d;
}
.status-pill.busy::before { background: #fbbf24; box-shadow: 0 0 8px #fbbf24; animation: pulse 1s infinite; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* ═══ SECTION LABEL ═══ */
.sec-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
    color: var(--text-muted); text-transform: uppercase;
    padding: 8px 4px 6px;
}

/* ═══ BUTTONS ═══ */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 20px;
    border: none;
    border-radius: 14px;
    font-weight: 700; font-size: 14px; letter-spacing: 0.01em;
    cursor: pointer;
    transition: all 0.2s var(--ease);
    width: 100%;
    color: white;
    background: linear-gradient(135deg, hsl(var(--hue1) 60% 32%), hsl(var(--hue1) 65% 24%));
    box-shadow: 0 6px 18px hsl(var(--hue1) 70% 40% / 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.18);
}
.btn:active { transform: translateY(1px) scale(0.98); }
.btn:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-secondary {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: none;
    color: var(--text-primary);
}
.btn-danger {
    background: linear-gradient(135deg, hsl(0 65% 35%), hsl(0 70% 28%));
    box-shadow: 0 6px 18px hsl(0 70% 40% / 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.btn-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

/* ═══ FORM ═══ */
.field { margin-bottom: 12px; }
.field-label {
    display: block; font-size: 11px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px;
    letter-spacing: 0.02em;
}
.field input {
    width: 100%;
    padding: 11px 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 500;
    outline: none;
    transition: border 0.2s;
}
.field input:focus {
    border-color: hsl(var(--hue1) 60% 50%);
    background: rgba(255, 255, 255, 0.06);
}
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

/* ═══ FORMAT TABS ═══ */
.format-tabs {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 4px; padding: 4px; margin-bottom: 14px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 100px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.format-tab {
    padding: 10px 8px;
    border-radius: 100px;
    font-size: 11px; font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    border: none;
    background: transparent;
    transition: all 0.2s var(--ease);
}
.format-tab.active {
    background: linear-gradient(135deg, hsl(var(--hue1) 60% 32%), hsl(var(--hue1) 65% 24%));
    color: white;
    box-shadow: 0 0 14px hsl(var(--hue1) 60% 45% / 0.45);
}

/* ═══ COPIES STEPPER ═══ */
.copies-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    margin-bottom: 14px;
}
.copies-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
.copies-stepper { display: flex; align-items: center; gap: 12px; }
.copies-btn {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.06);
    color: white; font-size: 18px; font-weight: 700;
    cursor: pointer;
}
.copies-btn:active { transform: scale(0.92); }
.copies-num { min-width: 36px; text-align: center; font-size: 18px; font-weight: 800; }

/* ═══ TSPL CONSOLE ═══ */
.console {
    margin-top: 12px;
    background: #000;
    border: 1px solid rgba(0, 255, 100, 0.2);
    border-radius: 10px;
    padding: 12px 14px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: #4ade80;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 260px;
    overflow-y: auto;
    line-height: 1.5;
}
.console-header {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 10px; font-weight: 700; color: var(--text-muted);
    letter-spacing: 0.1em; text-transform: uppercase;
    padding: 0 4px 6px;
}
.copy-btn {
    background: none; border: none; color: var(--text-secondary);
    cursor: pointer; font-size: 11px; font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.2s;
}
.copy-btn:hover { background: rgba(255, 255, 255, 0.06); }

/* ═══ TOAST ═══ */
.toast {
    position: fixed; bottom: 20px; left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: rgba(0, 0, 0, 0.9);
    color: white; padding: 12px 20px;
    border-radius: 30px; font-size: 14px; font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.15);
    z-index: 1000;
    transition: transform 0.3s var(--ease);
    max-width: 360px;
    text-align: center;
}
.toast.show { transform: translateX(-50%) translateY(0); }
.toast.error { border-color: rgba(239, 68, 68, 0.5); }
.toast.success { border-color: rgba(34, 197, 94, 0.5); }
</style>
</head>
<body>

<div class="app">

    <!-- HEADER -->
    <div class="header">
        <div style="flex:1">
            <h1>🖨 Тест Bluetooth принтер</h1>
            <div class="header-sub">DTM-5811 · TSPL · 50×30mm</div>
        </div>
        <div class="status-pill" id="statusPill">Не сдвоен</div>
    </div>

    <!-- PAIRING -->
    <div class="glass sm">
        <div class="sec-label" style="padding-top:0">Сдвояване</div>
        <div class="btn-row" style="margin-top:6px">
            <button class="btn" id="btnPair">
                <svg viewBox="0 0 24 24"><path d="M6 3l12 9-12 9V3z"/></svg>
                Сдвои
            </button>
            <button class="btn btn-danger" id="btnUnpair" disabled>
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Разкачи
            </button>
        </div>
        <button class="btn btn-secondary" id="btnTestPrint" style="margin-top:8px" disabled>
            <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Тестов етикет
        </button>
    </div>

    <!-- CUSTOM LABEL -->
    <div class="sec-label">Печат на собствен етикет</div>
    <div class="glass">

        <!-- Format -->
        <div class="format-tabs" id="formatTabs">
            <button class="format-tab active" data-format="both">€ + лв</button>
            <button class="format-tab" data-format="eur">Само €</button>
            <button class="format-tab" data-format="no-price">Без цена</button>
        </div>

        <!-- Fields -->
        <div class="field">
            <label class="field-label">Магазин</label>
            <input type="text" id="fStore" value="<?= htmlspecialchars($store_name) ?>" maxlength="30">
        </div>
        <div class="field">
            <label class="field-label">Артикул (име)</label>
            <input type="text" id="fName" value="Дънки Mustang син деним" maxlength="28">
        </div>
        <div class="field">
            <label class="field-label">Вариант (опц.)</label>
            <input type="text" id="fVariant" value="M · Navy" maxlength="24">
        </div>
        <div class="field">
            <label class="field-label">Баркод (Code 128)</label>
            <input type="text" id="fBarcode" value="5901234567890">
        </div>
        <div class="field-row">
            <div class="field">
                <label class="field-label">Цена €</label>
                <input type="text" id="fPriceEur" value="€ 45.50">
            </div>
            <div class="field">
                <label class="field-label">Цена лв</label>
                <input type="text" id="fPriceBgn" value="89.00 лв">
            </div>
        </div>
        <div class="field">
            <label class="field-label">Код</label>
            <input type="text" id="fCode" value="EN-2026-0248-M-NV">
        </div>

        <!-- Copies -->
        <div class="copies-row">
            <div class="copies-label">Брой копия</div>
            <div class="copies-stepper">
                <button class="copies-btn" id="cMinus">−</button>
                <span class="copies-num" id="cNum">1</span>
                <button class="copies-btn" id="cPlus">+</button>
            </div>
        </div>

        <!-- Print -->
        <button class="btn" id="btnPrintCustom" disabled>
            <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Печатай <span id="btnCount">1</span>
        </button>

        <!-- TSPL Preview -->
        <div class="console-header" style="margin-top:14px">
            <span>TSPL генериран</span>
            <button class="copy-btn" id="btnCopy">Копирай</button>
        </div>
        <div class="console" id="tsplPreview"></div>
    </div>

</div>

<div class="toast" id="toast"></div>

<script src="/js/printer.js?v=<?= filemtime(__DIR__ . '/js/printer.js') ?: time() ?>"></script>
<script>
// ═══ STATE ═══
let copies = 1;
let format = 'both';

// ═══ DOM ═══
const $ = id => document.getElementById(id);
const statusPill = $('statusPill');
const btnPair = $('btnPair');
const btnUnpair = $('btnUnpair');
const btnTestPrint = $('btnTestPrint');
const btnPrintCustom = $('btnPrintCustom');
const tsplPreview = $('tsplPreview');
const toast = $('toast');

// ═══ CAPABILITY CHECK ═══
if (!RmsPrinter.isSupported()) {
    showToast('Принтерът работи на Android Chrome. iOS — скоро.', 'error', 8000);
    btnPair.disabled = true;
}

// ═══ STATUS DISPLAY ═══
function updateStatus() {
    const paired = RmsPrinter.isPaired();
    const status = RmsPrinter.status;

    if (status === 'connecting') {
        statusPill.textContent = 'Свързване…';
        statusPill.className = 'status-pill busy';
    } else if (status === 'printing') {
        statusPill.textContent = 'Печата…';
        statusPill.className = 'status-pill busy';
    } else if (status === 'error') {
        statusPill.textContent = 'Грешка';
        statusPill.className = 'status-pill';
    } else if (paired) {
        const info = RmsPrinter.getPairedInfo();
        statusPill.textContent = '✓ ' + (info?.name || 'Свързан');
        statusPill.className = 'status-pill ok';
    } else {
        statusPill.textContent = 'Не сдвоен';
        statusPill.className = 'status-pill';
    }

    btnUnpair.disabled = !paired;
    btnTestPrint.disabled = !paired;
    btnPrintCustom.disabled = !paired;
}
RmsPrinter.onStatus(updateStatus);
updateStatus();

// ═══ PREVIEW UPDATE ═══
function getCurrentData() {
    return {
        store: $('fStore').value,
        name: $('fName').value,
        variant: $('fVariant').value,
        barcode: $('fBarcode').value,
        priceEur: $('fPriceEur').value,
        priceBgn: $('fPriceBgn').value,
        code: $('fCode').value,
        format: format
    };
}

function updatePreview() {
    try {
        const tspl = RmsPrinter.previewTSPL(getCurrentData(), copies);
        tsplPreview.textContent = tspl;
    } catch (e) {
        tsplPreview.textContent = 'Грешка: ' + e.message;
    }
}

['fStore','fName','fVariant','fBarcode','fPriceEur','fPriceBgn','fCode'].forEach(id => {
    $(id).addEventListener('input', updatePreview);
});

// ═══ FORMAT TABS ═══
$('formatTabs').addEventListener('click', e => {
    if (!e.target.classList.contains('format-tab')) return;
    document.querySelectorAll('.format-tab').forEach(t => t.classList.remove('active'));
    e.target.classList.add('active');
    format = e.target.dataset.format;
    updatePreview();
});

// ═══ COPIES STEPPER ═══
function updateCopies() {
    $('cNum').textContent = copies;
    $('btnCount').textContent = copies;
    updatePreview();
}
$('cMinus').addEventListener('click', () => {
    if (copies > 1) { copies--; updateCopies(); if (navigator.vibrate) navigator.vibrate(5); }
});
$('cPlus').addEventListener('click', () => {
    if (copies < 99) { copies++; updateCopies(); if (navigator.vibrate) navigator.vibrate(5); }
});

// ═══ COPY TSPL ═══
$('btnCopy').addEventListener('click', () => {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(tsplPreview.textContent)
            .then(() => showToast('TSPL копиран', 'success', 1500));
    }
});

// ═══ PAIR ═══
btnPair.addEventListener('click', async () => {
    try {
        const info = await RmsPrinter.pair();
        if (info) {
            showToast('✓ Сдвоен: ' + info.name, 'success');
        }
    } catch (e) {
        showToast('Грешка: ' + e.message, 'error');
    }
    updateStatus();
});

// ═══ UNPAIR ═══
btnUnpair.addEventListener('click', () => {
    if (!confirm('Разкачи принтер?')) return;
    RmsPrinter.unpair();
    showToast('Разкачен', 'success', 1500);
    updateStatus();
});

// ═══ TEST PRINT ═══
btnTestPrint.addEventListener('click', async () => {
    try {
        await RmsPrinter.printTest();
        showToast('✓ Тестов етикет изпратен', 'success');
    } catch (e) {
        showToast('Грешка: ' + e.message, 'error');
    }
});

// ═══ CUSTOM PRINT ═══
btnPrintCustom.addEventListener('click', async () => {
    try {
        await RmsPrinter.printLabel(getCurrentData(), copies);
        showToast(`✓ Изпратени ${copies} етикета`, 'success');
    } catch (e) {
        showToast('Грешка: ' + e.message, 'error');
    }
});

// ═══ TOAST ═══
let toastTimer;
function showToast(msg, type = '', duration = 3000) {
    clearTimeout(toastTimer);
    toast.textContent = msg;
    toast.className = 'toast show' + (type ? ' ' + type : '');
    toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

// ═══ INIT PREVIEW ═══
updatePreview();
</script>
</body>
</html>
