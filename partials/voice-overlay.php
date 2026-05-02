<?php
/**
 * partials/voice-overlay.php — S92.AIBRAIN.PHASE1
 *
 * Shared voice overlay used by the AI Brain pill (life-board.php) and
 * by future Simple Mode modules that include the mini-FAB. Self-contained:
 * markup + scoped styles + JS. Open via window.aibrainOpen() / close via
 * window.aibrainClose(). All strings via t_aibrain() — no hardcoded BG.
 *
 * No native keyboard: textarea is readonly so tap doesn't trigger IME.
 * Voice → Web Speech API (BG locale) → fallback toast on unsupported.
 *
 * Endpoint: POST /ai-brain-record.php (Phase 1 = passthrough to chat-send.php).
 */
require_once __DIR__ . '/../config/i18n_aibrain.php';
$__aibrain_csrf = aibrain_csrf_token();
?>
<div class="aibrain-ov" id="aibrainOv" aria-hidden="true" role="dialog" aria-label="<?= htmlspecialchars(t_aibrain('rec.title'), ENT_QUOTES) ?>">
    <div class="aibrain-box" role="document">
        <div class="aibrain-status">
            <div class="aibrain-dot" id="aibrainDot"></div>
            <span class="aibrain-label recording" id="aibrainLabel"><?= htmlspecialchars(t_aibrain('rec.recording')) ?></span>
        </div>
        <textarea
            class="aibrain-transcript empty"
            id="aibrainTranscript"
            readonly
            rows="3"
            aria-label="<?= htmlspecialchars(t_aibrain('rec.placeholder'), ENT_QUOTES) ?>"
            placeholder="<?= htmlspecialchars(t_aibrain('rec.placeholder'), ENT_QUOTES) ?>"></textarea>
        <div class="aibrain-hint" id="aibrainHint"><?= htmlspecialchars(t_aibrain('rec.hint_record')) ?></div>
        <div class="aibrain-actions">
            <button type="button" class="aibrain-btn-cancel" id="aibrainCancel"><?= htmlspecialchars(t_aibrain('rec.cancel')) ?></button>
            <button type="button" class="aibrain-btn-send" id="aibrainSend" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span><?= htmlspecialchars(t_aibrain('rec.send')) ?></span>
            </button>
        </div>
    </div>
</div>

<style>
.aibrain-ov{
    position:fixed;inset:0;z-index:340;
    background:rgba(3,7,18,0.55);
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
    display:none;align-items:flex-end;justify-content:center;
    padding:0 16px calc(24px + env(safe-area-inset-bottom,0px));
}
.aibrain-ov.open{display:flex}
.aibrain-box{
    width:100%;max-width:420px;
    background:linear-gradient(180deg,hsl(280 30% 12% / .95),hsl(280 30% 8% / .95));
    border:1px solid hsl(280 50% 45% / .45);
    border-radius:22px;padding:18px;
    box-shadow:0 -12px 50px hsl(280 60% 45% / .35),0 0 40px rgba(0,0,0,0.5);
    animation:aibrainSlideUp .25s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes aibrainSlideUp{
    from{opacity:0;transform:translateY(30px)}
    to{opacity:1;transform:translateY(0)}
}
.aibrain-status{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.aibrain-dot{
    width:14px;height:14px;border-radius:50%;
    background:#ef4444;flex-shrink:0;
    box-shadow:0 0 12px #ef4444,0 0 24px rgba(239,68,68,0.4);
    animation:aibrainPulse 1s ease infinite;
}
.aibrain-dot.ready{
    background:#22c55e;
    box-shadow:0 0 12px #22c55e,0 0 24px rgba(34,197,94,0.4);
    animation:none;
}
.aibrain-dot.thinking{
    background:hsl(280 70% 60%);
    box-shadow:0 0 12px hsl(280 70% 60%),0 0 24px hsl(280 70% 60% / .4);
    animation:aibrainPulseSoft 1.4s ease infinite;
}
@keyframes aibrainPulse{
    0%,100%{opacity:1;box-shadow:0 0 8px #ef4444,0 0 16px rgba(239,68,68,0.3)}
    50%{opacity:0.5;box-shadow:0 0 20px #ef4444,0 0 40px rgba(239,68,68,0.6)}
}
@keyframes aibrainPulseSoft{
    0%,100%{opacity:.85;transform:scale(1)}
    50%{opacity:1;transform:scale(1.12)}
}
.aibrain-label{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:1.4px}
.aibrain-label.recording{color:#ef4444}
.aibrain-label.ready{color:#22c55e}
.aibrain-label.thinking{color:hsl(280 80% 78%)}
.aibrain-transcript{
    width:100%;min-height:64px;max-height:160px;resize:none;
    padding:10px 14px;margin-bottom:12px;
    background:rgba(99,102,241,0.06);
    border:1px solid hsl(280 40% 35% / .45);border-radius:12px;
    font-family:'Montserrat',Inter,system-ui,sans-serif;
    font-size:15px;font-weight:500;line-height:1.4;
    color:#f1f5f9;
    word-wrap:break-word;outline:none;
}
.aibrain-transcript:focus{border-color:hsl(280 60% 55% / .65)}
.aibrain-transcript.empty{color:rgba(255,255,255,0.55);font-style:italic}
.aibrain-hint{
    font-size:11px;color:rgba(255,255,255,0.55);margin-bottom:14px;
    text-align:center;line-height:1.4;font-weight:600;
}
.aibrain-actions{display:flex;gap:8px}
.aibrain-btn-cancel{
    flex:1;height:46px;border-radius:100px;
    border:1px solid rgba(255,255,255,0.1);
    background:rgba(255,255,255,0.04);
    color:hsl(280 70% 80%);font-size:13px;font-weight:700;
    cursor:pointer;font-family:inherit;letter-spacing:.02em;
}
.aibrain-btn-cancel:active{background:rgba(99,102,241,0.12)}
.aibrain-btn-send{
    flex:2;height:46px;border-radius:100px;
    background:linear-gradient(135deg,hsl(280 65% 45%),hsl(310 65% 38%));
    border:1px solid hsl(290 70% 60% / .55);
    color:#fff;font-size:14px;font-weight:800;letter-spacing:.02em;
    cursor:pointer;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:6px;
    box-shadow:0 4px 14px hsl(290 70% 50% / .4),inset 0 1px 0 rgba(255,255,255,0.12);
    transition:transform .15s ease;
}
.aibrain-btn-send:active{transform:scale(0.97)}
.aibrain-btn-send:disabled{opacity:0.35;pointer-events:none}
</style>

<script>
(function(){
    if (window.__aibrainOvLoaded) return;
    window.__aibrainOvLoaded = true;

    var CSRF = <?= json_encode($__aibrain_csrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var STR = {
        recording: <?= json_encode(t_aibrain('rec.recording')) ?>,
        ready:     <?= json_encode(t_aibrain('rec.ready')) ?>,
        hintIdle:  <?= json_encode(t_aibrain('rec.hint_idle')) ?>,
        hintRec:   <?= json_encode(t_aibrain('rec.hint_record')) ?>,
        hintReady: <?= json_encode(t_aibrain('rec.hint_ready')) ?>,
        hintThink: <?= json_encode(t_aibrain('rec.hint_thinking')) ?>,
        unsupported: <?= json_encode(t_aibrain('rec.unsupported')) ?>,
        micDenied:   <?= json_encode(t_aibrain('rec.mic_denied')) ?>,
        empty:       <?= json_encode(t_aibrain('rec.empty')) ?>,
        netErr:      <?= json_encode(t_aibrain('rec.network_err')) ?>,
        srvErr:      <?= json_encode(t_aibrain('rec.server_err')) ?>,
        thinking:    <?= json_encode(t_aibrain('rec.hint_thinking')) ?>,
        placeholder: <?= json_encode(t_aibrain('rec.placeholder')) ?>
    };

    var ov, dot, label, transcript, hint, sendBtn, cancelBtn;
    var recognition = null;
    var recState = 'idle'; // idle | recording | ready | thinking
    var transcriptText = '';

    function $(id){ return document.getElementById(id); }

    function ensureRefs(){
        if (ov) return true;
        ov = $('aibrainOv');
        if (!ov) return false;
        dot = $('aibrainDot');
        label = $('aibrainLabel');
        transcript = $('aibrainTranscript');
        hint = $('aibrainHint');
        sendBtn = $('aibrainSend');
        cancelBtn = $('aibrainCancel');

        cancelBtn.addEventListener('click', close);
        sendBtn.addEventListener('click', sendToAi);
        ov.addEventListener('click', function(e){ if (e.target === ov) close(); });
        // Tap-to-dictate retry: tapping the transcript zone while idle/ready
        // restarts recording (no native keyboard, textarea stays readonly).
        transcript.addEventListener('click', function(){
            if (recState === 'ready' || recState === 'idle') startRec();
        });
        return true;
    }

    function setState(s){
        recState = s;
        if (!dot) return;
        dot.classList.remove('ready','thinking');
        label.classList.remove('ready','thinking','recording');
        if (s === 'recording'){
            dot.classList.add(); // default red
            label.classList.add('recording');
            label.textContent = STR.recording;
            hint.textContent = STR.hintRec;
            sendBtn.disabled = !transcriptText.trim();
        } else if (s === 'ready'){
            dot.classList.add('ready');
            label.classList.add('ready');
            label.textContent = STR.ready;
            hint.textContent = STR.hintReady;
            sendBtn.disabled = !transcriptText.trim();
        } else if (s === 'thinking'){
            dot.classList.add('thinking');
            label.classList.add('thinking');
            label.textContent = STR.thinking;
            hint.textContent = STR.hintThink;
            sendBtn.disabled = true;
        } else {
            label.classList.add('recording');
            label.textContent = STR.recording;
            hint.textContent = STR.hintIdle;
            sendBtn.disabled = true;
        }
    }

    function setTranscript(text){
        transcriptText = (text || '').trim();
        if (!transcript) return;
        if (transcriptText){
            transcript.value = transcriptText;
            transcript.classList.remove('empty');
        } else {
            transcript.value = '';
            transcript.classList.add('empty');
        }
        if (sendBtn) sendBtn.disabled = !transcriptText || recState === 'thinking';
    }

    function open(){
        if (!ensureRefs()) return;
        setTranscript('');
        setState('recording');
        ov.classList.add('open');
        ov.setAttribute('aria-hidden','false');
        if (navigator.vibrate) try { navigator.vibrate(8); } catch(_){}
        startRec();
    }

    function close(){
        if (!ov) return;
        stopRec();
        ov.classList.remove('open');
        ov.setAttribute('aria-hidden','true');
        recState = 'idle';
    }

    function startRec(){
        if (!ensureRefs()) return;
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR){
            setState('ready');
            hint.textContent = STR.unsupported;
            return;
        }
        try {
            if (recognition){ try { recognition.abort(); } catch(_){} }
            recognition = new SR();
            recognition.lang = (document.documentElement.lang || 'bg') + '-BG';
            if (recognition.lang.indexOf('bg') !== 0) recognition.lang = 'bg-BG';
            recognition.continuous = false;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;

            recognition.onresult = function(ev){
                var full = '';
                for (var i = 0; i < ev.results.length; i++){
                    full += ev.results[i][0].transcript;
                }
                setTranscript(full);
            };
            recognition.onerror = function(ev){
                if (ev && ev.error === 'not-allowed'){
                    setState('ready');
                    hint.textContent = STR.micDenied;
                    return;
                }
                if (ev && ev.error === 'no-speech'){
                    setState('ready');
                    if (!transcriptText) hint.textContent = STR.empty;
                    return;
                }
            };
            recognition.onend = function(){
                if (recState === 'recording') setState('ready');
            };
            setState('recording');
            recognition.start();
        } catch(e){
            setState('ready');
            hint.textContent = STR.unsupported;
        }
    }

    function stopRec(){
        if (recognition){
            try { recognition.stop(); } catch(_){}
            try { recognition.abort(); } catch(_){}
            recognition = null;
        }
    }

    function sendToAi(){
        if (!transcriptText || recState === 'thinking') return;
        stopRec();
        setState('thinking');
        var payload = { csrf: CSRF, text: transcriptText, source: 'aibrain_pill' };
        fetch('/ai-brain-record.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-AI-Brain-CSRF': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function(r){
            return r.json().catch(function(){ return { error: STR.srvErr }; });
        }).then(function(data){
            if (data && data.error){
                setState('ready');
                hint.textContent = data.error || STR.srvErr;
                return;
            }
            var reply = (data && (data.reply || data.message)) || '';
            try { speakReply(reply); } catch(_){}
            // Show reply briefly inside the transcript area, then auto-close.
            transcript.value = reply || '';
            transcript.classList.toggle('empty', !reply);
            label.textContent = STR.ready;
            label.classList.remove('thinking');
            label.classList.add('ready');
            dot.classList.remove('thinking');
            dot.classList.add('ready');
            sendBtn.disabled = true;
            setTimeout(close, 2200);
        }).catch(function(){
            setState('ready');
            hint.textContent = STR.netErr;
        });
    }

    function speakReply(text){
        if (!text || !window.speechSynthesis) return;
        try {
            var u = new SpeechSynthesisUtterance(text);
            u.lang = 'bg-BG';
            u.rate = 1;
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(u);
        } catch(_){}
    }

    window.aibrainOpen = open;
    window.aibrainClose = close;
})();
</script>
