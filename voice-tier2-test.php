<?php
/**
 * voice-tier2-test.php — Side-by-side Web Speech vs Whisper Groq comparison.
 *
 * S92.VOICE.TIER2_EXPERIMENT
 * Purpose: измерване на качество/скорост между двете STT engine-и за bg-BG.
 * Backend: POST към services/voice-tier2.php (HTTP endpoint mode).
 *
 * Design: чист consume на /design-kit/, без дублиране на класове.
 */

session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];
$lang      = $_SESSION['language'] ?? 'bg';

$groq_configured = is_readable('/etc/runmystore/api.env');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Voice Tier 2 Test — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">

<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

<style>
.vt-hint{font-size:11px;font-weight:600;color:var(--text-secondary);text-align:center;line-height:1.5;padding:0 8px;margin:6px 0 14px}
.vt-hint.warn{color:var(--warning)}
.vt-hint.err{color:var(--danger)}

.vt-rec-wrap{display:flex;flex-direction:column;align-items:center;gap:10px;padding:18px 0 22px}
.vt-rec{
    width:128px;height:128px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,hsl(280 60% 48%),hsl(310 60% 42%));
    color:#fff;border:none;cursor:pointer;font-family:inherit;
    box-shadow:0 0 22px hsl(280 60% 50% / .45),inset 0 1px 0 rgba(255,255,255,.25);
    transition:transform .12s ease, box-shadow .12s ease, background .12s ease;
    -webkit-tap-highlight-color:transparent;-webkit-user-select:none;user-select:none;
    touch-action:none
}
.vt-rec svg{width:46px;height:46px;stroke:currentColor;stroke-width:2.2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.vt-rec.pressed{
    transform:scale(.94);
    background:linear-gradient(135deg,hsl(295 75% 52%),hsl(310 75% 46%));
    box-shadow:0 0 36px hsl(295 75% 55% / .75),inset 0 1px 0 rgba(255,255,255,.35)
}
.vt-rec:disabled{opacity:.4;cursor:not-allowed;box-shadow:none}
.vt-rec-label{font-size:10px;font-weight:800;letter-spacing:.10em;text-transform:uppercase;color:var(--text-secondary)}
.vt-timer{font-size:13px;font-weight:800;color:var(--text-primary);font-variant-numeric:tabular-nums;min-height:18px}

.vt-card{padding:14px 16px;margin-bottom:10px}
.vt-card h3{
    font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;
    color:hsl(255 50% 70%);margin:0 0 8px;display:flex;align-items:center;gap:8px
}
.vt-card .vt-badge{
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 8px;border-radius:100px;font-size:9px;font-weight:900;
    letter-spacing:.05em;text-transform:uppercase;
    background:rgba(99,102,241,.14);color:var(--indigo-300);
    border:1px solid rgba(99,102,241,.35)
}
.vt-card .vt-badge.t2{background:rgba(139,92,246,.14);color:#c4b5fd;border-color:rgba(139,92,246,.4)}

.vt-transcript{
    font-size:15px;font-weight:700;line-height:1.4;color:var(--text-primary);
    min-height:42px;padding:10px 12px;border-radius:10px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    word-wrap:break-word
}
.vt-transcript.empty{color:var(--text-muted);font-weight:500;font-style:italic}
.vt-transcript.error{color:#fca5a5;border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.06)}

.vt-meta{
    display:flex;justify-content:space-between;align-items:center;gap:10px;
    margin-top:8px;font-size:10px;font-weight:700;
    color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;
    font-variant-numeric:tabular-nums
}
.vt-meta strong{color:var(--text-secondary);font-weight:900}
.vt-meta .vt-conf-h{color:#86efac}
.vt-meta .vt-conf-m{color:#fbbf24}
.vt-meta .vt-conf-l{color:#fca5a5}

.vt-spinner{
    display:inline-block;width:12px;height:12px;border-radius:50%;
    border:2px solid rgba(255,255,255,.15);border-top-color:var(--indigo-300);
    animation:vtSpin .7s linear infinite;vertical-align:-2px;margin-right:6px
}
@keyframes vtSpin{to{transform:rotate(360deg)}}
</style>
</head>
<body class="has-rms-shell">

<?php include __DIR__ . '/partials/header.php'; ?>

<main class="app">

    <div class="mod-del-sec-label" style="margin:8px 4px 4px">Voice Tier Comparison</div>

    <p class="vt-hint" id="vtHint">
        Натисни и задръж бутона. Говори. Пусни — ще видиш и двата transcript-а.
    </p>

    <div class="vt-rec-wrap">
        <button class="vt-rec" id="vtRec" type="button" aria-label="Запис">
            <svg viewBox="0 0 24 24"><path d="M12 1a4 4 0 0 0-4 4v7a4 4 0 0 0 8 0V5a4 4 0 0 0-4-4z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </button>
        <div class="vt-rec-label" id="vtRecLabel">Натисни и задръж</div>
        <div class="vt-timer" id="vtTimer"></div>
    </div>

    <div class="glass vt-card">
        <span class="shine"></span><span class="glow"></span>
        <h3>Tier 1 · Web Speech API <span class="vt-badge">Native</span></h3>
        <div class="vt-transcript empty" id="vtT1">— очаква запис —</div>
        <div class="vt-meta">
            <span>Confidence: <strong id="vtConf1">—</strong></span>
            <span>Latency: <strong id="vtLat1">—</strong></span>
        </div>
    </div>

    <div class="glass vt-card">
        <span class="shine"></span><span class="glow"></span>
        <h3>Tier 2 · Whisper Groq <span class="vt-badge t2">whisper-large-v3</span></h3>
        <div class="vt-transcript empty" id="vtT2"><?= $groq_configured ? '— очаква запис —' : '⚠️ GROQ_API_KEY не е конфигуриран' ?></div>
        <div class="vt-meta">
            <span>Confidence: <strong id="vtConf2">—</strong></span>
            <span>Latency: <strong id="vtLat2">—</strong></span>
        </div>
    </div>

</main>

<script>
(function(){
    const recBtn   = document.getElementById('vtRec');
    const recLabel = document.getElementById('vtRecLabel');
    const timerEl  = document.getElementById('vtTimer');
    const hintEl   = document.getElementById('vtHint');
    const t1El     = document.getElementById('vtT1');
    const t2El     = document.getElementById('vtT2');
    const conf1El  = document.getElementById('vtConf1');
    const conf2El  = document.getElementById('vtConf2');
    const lat1El   = document.getElementById('vtLat1');
    const lat2El   = document.getElementById('vtLat2');

    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        hintEl.textContent = 'Този browser не поддържа Web Speech API — пробвай Chrome или Android.';
        hintEl.className = 'vt-hint err';
        recBtn.disabled = true;
        return;
    }

    let recognition = null;
    let mediaRec = null;
    let audioChunks = [];
    let audioStream = null;
    let pressStart = 0;
    let timerInterval = null;
    let webSpeechResult = null;
    let webSpeechFinishedAt = 0;
    let isRecording = false;

    function setConfClass(el, conf){
        el.classList.remove('vt-conf-h','vt-conf-m','vt-conf-l');
        if (conf == null) return;
        if (conf >= 0.85) el.classList.add('vt-conf-h');
        else if (conf >= 0.6) el.classList.add('vt-conf-m');
        else el.classList.add('vt-conf-l');
    }

    function fmtConf(c){ return (c == null) ? '—' : (Math.round(c * 100) + '%'); }
    function fmtMs(ms){ return (ms == null) ? '—' : (ms < 1000 ? Math.round(ms) + ' ms' : (ms/1000).toFixed(2) + ' s'); }

    function resetUi(){
        t1El.textContent = '— очаква запис —';
        t1El.className = 'vt-transcript empty';
        t2El.textContent = '<?= $groq_configured ? '— очаква запис —' : '⚠️ GROQ_API_KEY не е конфигуриран' ?>';
        t2El.className = 'vt-transcript empty';
        conf1El.textContent = '—'; setConfClass(conf1El, null);
        conf2El.textContent = '—'; setConfClass(conf2El, null);
        lat1El.textContent = '—';
        lat2El.textContent = '—';
    }

    async function startRecording(){
        if (isRecording) return;
        resetUi();
        webSpeechResult = null;
        audioChunks = [];

        try {
            audioStream = await navigator.mediaDevices.getUserMedia({audio: true});
        } catch (e) {
            hintEl.textContent = 'Достъп до микрофона е отказан. Разреши го от browser settings.';
            hintEl.className = 'vt-hint err';
            return;
        }

        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');
        try {
            mediaRec = mimeType ? new MediaRecorder(audioStream, {mimeType}) : new MediaRecorder(audioStream);
        } catch (e) {
            mediaRec = new MediaRecorder(audioStream);
        }
        mediaRec.ondataavailable = (ev) => { if (ev.data && ev.data.size > 0) audioChunks.push(ev.data); };
        mediaRec.start();

        recognition = new SR();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'bg-BG';
        recognition.maxAlternatives = 1;
        recognition.onresult = (ev) => {
            const r = ev.results[0];
            if (r && r[0]) {
                webSpeechResult = {
                    transcript: r[0].transcript || '',
                    confidence: (typeof r[0].confidence === 'number') ? r[0].confidence : null,
                };
                webSpeechFinishedAt = performance.now();
            }
        };
        recognition.onerror = (ev) => {
            if (ev.error === 'no-speech' || ev.error === 'aborted') return;
            t1El.textContent = 'Грешка: ' + ev.error;
            t1El.className = 'vt-transcript error';
        };
        try { recognition.start(); } catch(_) {}

        isRecording = true;
        pressStart = performance.now();
        recBtn.classList.add('pressed');
        recLabel.textContent = 'Записва…';
        timerEl.textContent = '0.0 s';
        timerInterval = setInterval(() => {
            const sec = (performance.now() - pressStart) / 1000;
            timerEl.textContent = sec.toFixed(1) + ' s';
        }, 100);
    }

    async function stopRecording(){
        if (!isRecording) return;
        isRecording = false;
        recBtn.classList.remove('pressed');
        recLabel.textContent = 'Натисни и задръж';
        clearInterval(timerInterval);
        const releaseAt = performance.now();
        const totalMs = releaseAt - pressStart;
        timerEl.textContent = (totalMs/1000).toFixed(2) + ' s';

        try { recognition && recognition.stop(); } catch(_) {}

        const recStopped = new Promise((resolve) => {
            if (!mediaRec) return resolve();
            mediaRec.onstop = resolve;
            try { mediaRec.stop(); } catch(_) { resolve(); }
        });
        await recStopped;
        if (audioStream) {
            audioStream.getTracks().forEach(t => t.stop());
            audioStream = null;
        }

        await new Promise(r => setTimeout(r, 250));
        if (webSpeechResult) {
            t1El.textContent = webSpeechResult.transcript || '(празно)';
            t1El.className = 'vt-transcript' + (webSpeechResult.transcript ? '' : ' empty');
            conf1El.textContent = fmtConf(webSpeechResult.confidence);
            setConfClass(conf1El, webSpeechResult.confidence);
            lat1El.textContent = fmtMs(webSpeechFinishedAt - pressStart);
        } else {
            t1El.textContent = '(не разпозна реч)';
            t1El.className = 'vt-transcript empty';
            lat1El.textContent = fmtMs(totalMs);
        }

        const blob = new Blob(audioChunks, {type: (mediaRec && mediaRec.mimeType) || 'audio/webm'});
        if (!blob.size) {
            t2El.textContent = 'Празен audio buffer';
            t2El.className = 'vt-transcript error';
            return;
        }

        t2El.innerHTML = '<span class="vt-spinner"></span>Изпращам към Whisper…';
        t2El.className = 'vt-transcript';

        const fd = new FormData();
        fd.append('audio', blob, 'recording.webm');
        fd.append('lang', 'bg');

        const t2Start = performance.now();
        try {
            const resp = await fetch('/services/voice-tier2.php', {method:'POST', body: fd, credentials:'same-origin'});
            const t2End = performance.now();
            lat2El.textContent = fmtMs(t2End - t2Start);

            let json = null;
            try { json = await resp.json(); } catch(_) {}

            if (!resp.ok) {
                t2El.textContent = 'HTTP ' + resp.status + ': ' + (json && json.error ? json.error : 'request failed');
                t2El.className = 'vt-transcript error';
                return;
            }
            if (!json || !json.ok) {
                const msg = (json && json.error) ? json.error : 'unknown error';
                if (/GROQ_API_KEY/i.test(msg)) {
                    t2El.textContent = '⚠️ Whisper не е конфигуриран (GROQ_API_KEY липсва)';
                } else {
                    t2El.textContent = msg;
                }
                t2El.className = 'vt-transcript error';
                return;
            }
            const d = json.data || {};
            const text = d.transcript_normalized || d.transcript || '';
            t2El.textContent = text || '(празно)';
            t2El.className = 'vt-transcript' + (text ? '' : ' empty');
            conf2El.textContent = fmtConf(typeof d.confidence === 'number' ? d.confidence : null);
            setConfClass(conf2El, typeof d.confidence === 'number' ? d.confidence : null);
        } catch (e) {
            lat2El.textContent = fmtMs(performance.now() - t2Start);
            t2El.textContent = 'Network error: ' + (e && e.message ? e.message : String(e));
            t2El.className = 'vt-transcript error';
        }
    }

    function down(ev){ ev.preventDefault(); startRecording(); }
    function up(ev){ ev.preventDefault(); stopRecording(); }

    if (window.PointerEvent) {
        recBtn.addEventListener('pointerdown', down);
        recBtn.addEventListener('pointerup', up);
        recBtn.addEventListener('pointercancel', up);
        recBtn.addEventListener('pointerleave', (ev) => { if (isRecording) up(ev); });
    } else {
        recBtn.addEventListener('mousedown', down);
        recBtn.addEventListener('mouseup', up);
        recBtn.addEventListener('mouseleave', (ev) => { if (isRecording) up(ev); });
        recBtn.addEventListener('touchstart', down, {passive:false});
        recBtn.addEventListener('touchend', up, {passive:false});
        recBtn.addEventListener('touchcancel', up);
    }
    recBtn.addEventListener('contextmenu', (e) => e.preventDefault());
})();
</script>

</body>
</html>
