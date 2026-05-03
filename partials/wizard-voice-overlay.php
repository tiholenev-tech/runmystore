<?php
/**
 * partials/wizard-voice-overlay.php — S93.WIZARD.V4.SESSION_2
 *
 * Wizard-specific voice overlay. Различен от партials/voice-overlay.php
 * (общия AIBRAIN overlay) — този е data-field-type aware:
 *   - numeric (price/qty/barcode/code) → MediaRecorder + Whisper през voice-router.php
 *   - text (name/supplier/category)    → Web Speech API директно в браузъра
 *   - hybrid                            → fallback към Web Speech (S2 lite mode)
 *
 * Magic words се detect-ват в browser-а с регекс mirror на server-side
 * MAGIC_WORDS const (services/parse-hybrid-voice.php). Server-side parser
 * се ползва само за content (number extraction), не за magic.
 *
 * Auto-advance: 2 sec text, 3 sec numeric, 0 sec за magic words. Countdown
 * bar показва remaining до auto-confirm. Каже Тихол нещо ново → reset.
 *
 * Mounted в products.php при wizard_version='v4' (feature flag, S3).
 * Public API:
 *   wizVoiceOpen(fieldType, fieldKey, prompt, onConfirm, onMagic, onCancel)
 *   wizVoiceCancel()
 *   wizVoiceConfirmNow()
 */
?>
<div id="wizVoiceOverlay" class="wiz-voice-overlay" hidden>
  <div class="wvo-backdrop" onclick="wizVoiceCancel()"></div>
  <div class="wvo-card glass q-magic">
    <span class="shine"></span>
    <span class="shine shine-bottom"></span>
    <span class="glow"></span>
    <span class="glow glow-bottom"></span>

    <div class="wvo-state-pill" id="wvoStatePill">
      <span class="wvo-dot"></span>
      <span class="wvo-state-text" id="wvoStateText">Готов</span>
    </div>

    <div class="wvo-prompt" id="wvoPrompt">Слушам…</div>
    <div class="wvo-transcript" id="wvoTranscript">—</div>

    <div class="wvo-countdown">
      <div class="wvo-countdown-bar" id="wvoCountdownBar"></div>
    </div>

    <div class="wvo-actions">
      <button type="button" class="wvo-btn wvo-btn-cancel" onclick="wizVoiceCancel()">✕ Отказ</button>
      <button type="button" class="wvo-btn wvo-btn-confirm" onclick="wizVoiceConfirmNow()">✓ Потвърди</button>
    </div>
  </div>
</div>

<style>
  /* S93.WIZARD.V4: voice overlay styles. Design-kit compliant — uses glass/shine/glow + q-magic hue. */
  .wiz-voice-overlay { position:fixed; inset:0; z-index:9999; display:flex; align-items:flex-end; justify-content:center; padding:24px 16px; pointer-events:none; }
  .wiz-voice-overlay[hidden] { display:none; }
  .wvo-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.45); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); pointer-events:auto; }
  .wvo-card { position:relative; width:100%; max-width:440px; padding:22px 20px 18px; border-radius:24px; pointer-events:auto; }
  .wvo-card > *:not(.shine):not(.glow) { position:relative; z-index:5; }

  .wvo-state-pill { display:flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px;
    background:rgba(0,0,0,0.32); border:1px solid rgba(255,255,255,0.08); width:fit-content; margin-bottom:14px; }
  .wvo-dot { width:9px; height:9px; border-radius:50%; background:#9ca3af; transition:background 200ms ease; }
  .wvo-card.recording .wvo-dot { background:#ef4444; animation:wvo-pulse 0.95s infinite ease-in-out; }
  .wvo-card.confirming .wvo-dot { background:#22c55e; }
  .wvo-card.low-confidence .wvo-dot { background:#f59e0b; }
  .wvo-card.error .wvo-dot { background:#ef4444; animation:none; }
  @keyframes wvo-pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:0.3; transform:scale(1.6); } }

  .wvo-state-text { font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#cbd5e1; }
  .wvo-prompt { font-size:13px; color:rgba(255,255,255,0.62); margin-bottom:6px; font-weight:500; }
  .wvo-transcript { font-size:18px; font-weight:600; color:#fff; min-height:54px; padding:14px 12px; border-radius:14px;
    background:rgba(0,0,0,0.28); border:1px solid rgba(255,255,255,0.08); margin-bottom:12px; word-break:break-word; line-height:1.4; }

  .wvo-countdown { height:3px; background:rgba(255,255,255,0.06); border-radius:2px; overflow:hidden; margin-bottom:14px; }
  .wvo-countdown-bar { height:100%; width:0%;
    background:linear-gradient(90deg, hsl(var(--hue1, 280), 75%, 65%), hsl(var(--hue2, 222), 80%, 65%));
    transition:width 100ms linear; }

  .wvo-actions { display:flex; gap:8px; }
  .wvo-btn { flex:1; height:44px; border-radius:14px; font-size:12px; font-weight:700; cursor:pointer;
    font-family:inherit; border:1px solid; letter-spacing:0.02em; }
  .wvo-btn-cancel { background:rgba(255,255,255,0.04); border-color:rgba(255,255,255,0.1); color:#cbd5e1; }
  .wvo-btn-confirm { flex:1.4; background:linear-gradient(135deg, hsl(var(--hue1, 280), 70%, 55%), hsl(var(--hue2, 222), 70%, 45%));
    border-color:hsl(var(--hue1, 280), 70%, 60%); color:#fff;
    box-shadow:0 4px 14px hsl(var(--hue1, 280), 70%, 50%, 0.4); }
</style>

<script>
(function(){
  // S93.WIZARD.V4.SESSION_2 — wizard-voice-overlay client.
  // Зависи от: window.CFG (за CFG.lang), services/voice-router.php endpoint.
  // НЕ зависи от window.S — caller подава callbacks за всички side effects.

  // Mirror на server-side const VOICE_NUMERIC_FIELDS / VOICE_TEXT_FIELDS.
  // Frontend-side decision (без roundtrip) кой engine да активира.
  var NUMERIC_FIELDS = [
    'price_retail','price_wholesale','price_cost','cost_price','retail_price','wholesale_price',
    'quantity','qty','discount_percent','markup_percent','barcode','code','code_sku'
  ];
  var TEXT_FIELDS = [
    'name','description','material','composition','origin','origin_country','notes',
    'supplier_name','supplier','customer_name','category','subcategory','color','size','zone','location'
  ];

  // Mirror на MAGIC_WORDS от services/parse-hybrid-voice.php. Поддържай в sync.
  var MAGIC_WORDS = {
    next:   ['следващ','напред','по-нататък','по нататък','пропусни'],
    back:   ['назад','предишен','върни'],
    save:   ['запази','запиши','готово'],
    print:  ['печатай','печат','отпечатай'],
    cancel: ['отказ','затвори','спри се'],
    copy:   ['като предния','като предишния','копирай предния'],
    search: ['търси','намери'],
    stop:   ['стоп','спри'],
    undo:   ['не','поправи','грешка']
  };

  var CONFIRM_DELAY_MS = { text: 2000, numeric: 3000, magic: 50 };

  var state = {
    fieldType: null,
    fieldKey: null,
    onConfirm: null,
    onMagic: null,
    onCancel: null,
    recognition: null,
    mediaRecorder: null,
    audioChunks: [],
    countdownTimer: null,
    confirmTimer: null,
    transcriptText: '',
    transcriptSource: '',
    confidence: 0
  };

  function $(id) { return document.getElementById(id); }

  function setStateUI(stateClass, label) {
    var card = document.querySelector('.wvo-card');
    if (!card) return;
    card.classList.remove('recording','confirming','low-confidence','error');
    if (stateClass) card.classList.add(stateClass);
    if ($('wvoStateText')) $('wvoStateText').textContent = label;
  }

  function fieldEngine(fieldType) {
    var f = (fieldType || '').toLowerCase();
    if (NUMERIC_FIELDS.indexOf(f) >= 0) return 'whisper';
    if (TEXT_FIELDS.indexOf(f) >= 0) return 'web_speech';
    return 'hybrid';
  }

  function detectMagic(transcript) {
    var t = (transcript || '').toLowerCase().trim();
    if (!t) return null;
    for (var action in MAGIC_WORDS) {
      var variants = MAGIC_WORDS[action];
      for (var i = 0; i < variants.length; i++) {
        var v = variants[i];
        if (t === v || t === v + '.' || t === v + ',') return action;
      }
    }
    return null;
  }

  function startCountdown(durationMs) {
    resetCountdown();
    if (!durationMs || durationMs <= 0) return;
    var bar = $('wvoCountdownBar');
    if (!bar) return;
    var startT = performance.now();
    state.countdownTimer = setInterval(function() {
      var elapsed = performance.now() - startT;
      var pct = Math.min(100, (elapsed / durationMs) * 100);
      bar.style.width = pct + '%';
      if (pct >= 100) { clearInterval(state.countdownTimer); state.countdownTimer = null; }
    }, 50);
    state.confirmTimer = setTimeout(window.wizVoiceConfirmNow, durationMs);
  }

  function resetCountdown() {
    if (state.countdownTimer) { clearInterval(state.countdownTimer); state.countdownTimer = null; }
    if (state.confirmTimer)   { clearTimeout(state.confirmTimer);   state.confirmTimer = null; }
    var bar = $('wvoCountdownBar');
    if (bar) bar.style.width = '0%';
  }

  function stopAll() {
    resetCountdown();
    if (state.recognition) {
      try { state.recognition.stop(); } catch(_e) {}
      state.recognition = null;
    }
    if (state.mediaRecorder && state.mediaRecorder.state !== 'inactive') {
      try { state.mediaRecorder.stop(); } catch(_e) {}
    }
    state.mediaRecorder = null;
    state.audioChunks = [];
  }

  function onTranscriptUpdate(text, source, confidence) {
    state.transcriptText = (text || '').trim();
    state.transcriptSource = source;
    state.confidence = confidence || 0;
    if ($('wvoTranscript')) $('wvoTranscript').textContent = state.transcriptText || '—';

    var magic = detectMagic(state.transcriptText);
    if (magic) {
      setStateUI('confirming', 'Команда');
      // 0-ish sec delay за magic — instant action
      resetCountdown();
      state.confirmTimer = setTimeout(window.wizVoiceConfirmNow, CONFIRM_DELAY_MS.magic);
      return;
    }

    if (!state.transcriptText) {
      setStateUI('low-confidence', 'Не разбрах');
      return;
    }

    setStateUI('confirming', 'Готово');
    var isNumeric = NUMERIC_FIELDS.indexOf((state.fieldType || '').toLowerCase()) >= 0;
    var delay = isNumeric ? CONFIRM_DELAY_MS.numeric : CONFIRM_DELAY_MS.text;
    startCountdown(delay);
  }

  // ── Public API ──
  window.wizVoiceOpen = function(fieldType, fieldKey, prompt, onConfirm, onMagic, onCancel) {
    state.fieldType = fieldType;
    state.fieldKey = fieldKey;
    state.onConfirm = onConfirm || null;
    state.onMagic = onMagic || null;
    state.onCancel = onCancel || null;
    state.transcriptText = '';
    state.transcriptSource = '';
    state.confidence = 0;

    var overlay = $('wizVoiceOverlay');
    if (overlay) overlay.hidden = false;
    if ($('wvoPrompt')) $('wvoPrompt').textContent = prompt || 'Слушам…';
    if ($('wvoTranscript')) $('wvoTranscript').textContent = '—';
    setStateUI('recording', 'Записва');

    var engine = fieldEngine(fieldType);
    if (engine === 'web_speech')      { startWebSpeech(); }
    else if (engine === 'whisper')    { startWhisper(); }
    else                              { startHybrid(); }
  };

  window.wizVoiceCancel = function() {
    stopAll();
    var overlay = $('wizVoiceOverlay');
    if (overlay) overlay.hidden = true;
    if (state.onCancel) state.onCancel();
  };

  window.wizVoiceConfirmNow = function() {
    resetCountdown();
    var t = state.transcriptText;
    var overlay = $('wizVoiceOverlay');

    if (!t) {
      stopAll();
      if (overlay) overlay.hidden = true;
      return;
    }

    var magic = detectMagic(t);
    if (magic && state.onMagic) {
      stopAll();
      if (overlay) overlay.hidden = true;
      state.onMagic(magic);
      return;
    }

    if (state.onConfirm) {
      stopAll();
      if (overlay) overlay.hidden = true;
      state.onConfirm(t, { source: state.transcriptSource, confidence: state.confidence });
    }
  };

  // ── Web Speech API ──
  function startWebSpeech() {
    var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) {
      setStateUI('error', 'Не работи');
      if ($('wvoTranscript')) $('wvoTranscript').textContent = 'Speech API недостъпно';
      return;
    }
    var r = new Recognition();
    r.lang = (window.CFG && CFG.lang === 'bg') ? 'bg-BG' : ((window.CFG && CFG.lang) || 'bg-BG');
    r.continuous = false;
    r.interimResults = true;
    r.maxAlternatives = 1;

    r.onresult = function(ev) {
      var text = '';
      var conf = 0;
      for (var i = ev.resultIndex; i < ev.results.length; i++) {
        text += ev.results[i][0].transcript;
        if (ev.results[i].isFinal) conf = ev.results[i][0].confidence || 0.85;
      }
      onTranscriptUpdate(text, 'web_speech', conf);
    };
    r.onerror = function(ev) {
      setStateUI('error', 'Грешка');
      if ($('wvoTranscript')) $('wvoTranscript').textContent = 'Speech: ' + (ev.error || '?');
    };
    r.onend = function() {
      if (!state.transcriptText) setStateUI('low-confidence', 'Не разбрах');
    };

    state.recognition = r;
    try { r.start(); } catch(_e) { setStateUI('error', 'Грешка'); }
  }

  // ── MediaRecorder + Whisper ──
  function startWhisper() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStateUI('error', 'Без микрофон'); return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
      var rec;
      try { rec = new MediaRecorder(stream, { mimeType: 'audio/webm' }); }
      catch(_e) { rec = new MediaRecorder(stream); }
      state.mediaRecorder = rec;
      state.audioChunks = [];

      rec.ondataavailable = function(e) { if (e.data.size > 0) state.audioChunks.push(e.data); };
      rec.onstop = function() {
        stream.getTracks().forEach(function(t){ t.stop(); });
        var blob = new Blob(state.audioChunks, { type: 'audio/webm' });
        if (blob.size === 0) { setStateUI('error', 'Тихо'); return; }
        blobToBase64(blob).then(function(b64) {
          return postVoiceRouter(state.fieldType, b64, null);
        }).then(function(result) {
          if (result && result.ok) {
            onTranscriptUpdate(result.transcript, result.engine || 'whisper', result.confidence);
          } else {
            setStateUI('error', 'Грешка');
            if ($('wvoTranscript')) $('wvoTranscript').textContent = (result && result.error) || 'Whisper error';
          }
        }).catch(function(){ setStateUI('error', 'Грешка'); });
      };
      rec.start();
      // Auto-stop след 5 сек тишина (или ръчно "Потвърди" по-рано).
      setTimeout(function(){ if (rec.state === 'recording') rec.stop(); }, 5000);
    }).catch(function(){ setStateUI('error', 'Без достъп'); });
  }

  function startHybrid() {
    // S2 lite: hybrid → fallback към Web Speech. Full parallel run се отлага за S3.
    startWebSpeech();
  }

  function blobToBase64(blob) {
    return new Promise(function(resolve, reject) {
      var r = new FileReader();
      r.onloadend = function() {
        var s = String(r.result || '');
        var i = s.indexOf(',');
        resolve(i >= 0 ? s.slice(i + 1) : s);
      };
      r.onerror = reject;
      r.readAsDataURL(blob);
    });
  }

  function postVoiceRouter(fieldType, audioB64, webSpeech) {
    var body = new URLSearchParams();
    body.append('field_type', fieldType);
    body.append('lang', (window.CFG && CFG.lang) || 'bg');
    if (audioB64) body.append('audio_b64', audioB64);
    if (webSpeech && webSpeech.transcript) {
      body.append('web_speech_transcript', webSpeech.transcript);
      body.append('web_speech_confidence', String(webSpeech.confidence || 0));
    }
    return fetch('services/voice-router.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function(resp) { return resp.json(); })
      .catch(function(){ return { ok:false, error:'Network error' }; });
  }
})();
</script>
