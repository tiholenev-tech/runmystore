<?php
/**
 * includes/ai-chat-overlay.php — Shared AI Chat Overlay
 * RunMyStore.ai — S42
 * 80% екран, WhatsApp стил, вика chat-send.php
 * Include в products.php, warehouse.php, stats.php и др.
 * НЕ include-вай в chat.php — там overlay-ът е вграден.
 */
?>

<!-- ═══ AI CHAT OVERLAY ═══ -->
<div id="aiChatOverlay" class="aico">
    <div class="aico-backdrop" onclick="closeAIChatOverlay()"></div>
    <div class="aico-panel">
        <div class="aico-header">
            <div class="aico-hdr-left">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span class="aico-title">AI Асистент</span>
            </div>
            <button class="aico-close" onclick="closeAIChatOverlay()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="aico-messages" id="aicoMessages">
            <div class="aico-welcome">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(99,102,241,0.4)" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <div>Попитай каквото искаш за артикулите, продажбите или склада.</div>
            </div>
        </div>
        <div class="aico-typing" id="aicoTyping">
            <div class="aico-typing-dots"><span></span><span></span><span></span></div>
        </div>
        <div class="aico-input-area">
            <div class="aico-input-wrap">
                <textarea id="aicoInput" class="aico-input" rows="1" placeholder="Попитай AI..." oninput="aicoAutoGrow(this)" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();aicoSend()}"></textarea>
            </div>
            <button class="aico-voice-btn" id="aicoVoiceBtn" onclick="aicoToggleVoice()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="1" width="6" height="12" rx="3"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
            </button>
            <button class="aico-send-btn" id="aicoSendBtn" onclick="aicoSend()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>
</div>

<style>
/* ═══ S105 v4.1 BICHROMATIC — Light theme tokens (default) ═══ */
[data-theme="light"], :root:not([data-theme]) {
    --bg-main: #e0e5ec;
    --surface: #e0e5ec;
    --surface-2: #d1d9e6;
    --border-color: transparent;
    --text: #2d3748;
    --text-muted: #64748b;
    --text-faint: #94a3b8;
    --shadow-light: #ffffff;
    --shadow-dark: #a3b1c6;
    --neu-d: 8px; --neu-b: 16px;
    --neu-d-s: 4px; --neu-b-s: 8px;
    --shadow-card:
        var(--neu-d) var(--neu-d) var(--neu-b) var(--shadow-dark),
        calc(var(--neu-d) * -1) calc(var(--neu-d) * -1) var(--neu-b) var(--shadow-light);
    --shadow-card-sm:
        var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
        calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
    --shadow-pressed:
        inset var(--neu-d-s) var(--neu-d-s) var(--neu-b-s) var(--shadow-dark),
        inset calc(var(--neu-d-s) * -1) calc(var(--neu-d-s) * -1) var(--neu-b-s) var(--shadow-light);
    --accent: oklch(0.62 0.22 285);
    --accent-2: oklch(0.65 0.25 305);
    --q1-loss: oklch(0.65 0.22 25);
    --q2-why-loss: oklch(0.65 0.25 305);
    --q3-gain: oklch(0.68 0.18 155);
    --q4-why-gain: oklch(0.72 0.18 195);
    --q5-order: oklch(0.72 0.18 70);
    --q6-no-order: oklch(0.62 0.05 220);
    --aurora-blend: multiply;
    --aurora-opacity: 0.35;
    --radius: 22px; --radius-sm: 14px;
    --radius-pill: 999px; --radius-icon: 50%;
    --font: 'Montserrat', sans-serif;
    --font-mono: 'DM Mono', ui-monospace, monospace;
    --ease: cubic-bezier(0.5, 1, 0.89, 1);
    --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --dur: 250ms;
}
[data-theme="dark"] {
    --bg-main: #08090d;
    --surface: hsl(220, 25%, 4.8%);
    --surface-2: hsl(220, 25%, 8%);
    --border-color: hsl(222, 12%, 20%);
    --text: #f1f5f9;
    --text-muted: rgba(255, 255, 255, 0.6);
    --text-faint: rgba(255, 255, 255, 0.4);
    --shadow-card:
        hsl(222 50% 2%) 0 10px 16px -8px,
        hsl(222 50% 4%) 0 20px 36px -14px;
    --shadow-card-sm: hsl(222 50% 2%) 0 4px 8px -2px;
    --shadow-pressed: inset 0 2px 4px hsl(222 50% 2%);
    --accent: hsl(255, 80%, 65%);
    --accent-2: hsl(222, 80%, 65%);
    --q1-loss: hsl(0, 85%, 60%);
    --q2-why-loss: hsl(280, 70%, 65%);
    --q3-gain: hsl(145, 70%, 55%);
    --q4-why-gain: hsl(175, 70%, 55%);
    --q5-order: hsl(38, 90%, 60%);
    --q6-no-order: hsl(220, 10%, 60%);
    --aurora-blend: plus-lighter;
    --aurora-opacity: 0.35;
    --radius: 22px; --radius-sm: 14px;
    --radius-pill: 999px; --radius-icon: 50%;
    --font: 'Montserrat', sans-serif;
    --font-mono: 'DM Mono', ui-monospace, monospace;
    --ease: cubic-bezier(0.5, 1, 0.89, 1);
    --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --dur: 250ms;
}
/* ═══ end BICHROMATIC tokens ═══ */


/* ═══ AI CHAT OVERLAY ═══ */
.aico{position:fixed;inset:0;z-index:200;display:flex;flex-direction:column;justify-content:flex-end;pointer-events:none;opacity:0;transition:opacity .25s}
.aico.open{pointer-events:auto;opacity:1}
.aico-backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
.aico-panel{
    position:relative;z-index:1;
    height:80vh;max-height:80vh;
    display:flex;flex-direction:column;
    background:#0b0f1a;
    border-radius: var(--radius) 20px 0 0;
    border-top:1px solid rgba(99,102,241,0.25);
    box-shadow:0 -8px 40px rgba(0,0,0,0.6);
    transform:translateY(100%);transition:transform .3s cubic-bezier(.32,.72,.37,1.1);
}
.aico.open .aico-panel{transform:translateY(0)}
.aico-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 16px;flex-shrink:0;
    border-bottom:1px solid rgba(99,102,241,0.12);
}
.aico-hdr-left{display:flex;align-items:center;gap:8px}
.aico-title{font-size:14px;font-weight:700;color:#e2e8f0}
.aico-close{
    width:30px;height:30px;border-radius:50%;border:none;
    background:rgba(99,102,241,0.1);color:#94a3b8;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
}
.aico-close:active{background:rgba(99,102,241,0.25)}
.aico-messages{
    flex:1;overflow-y:auto;padding:12px 14px;
    display:flex;flex-direction:column;gap:10px;
    -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
}
.aico-welcome{
    display:flex;flex-direction:column;align-items:center;gap:8px;
    padding:30px 20px;text-align:center;
    color:rgba(148,163,184,0.6);font-size:13px;line-height:1.5;
}
/* User bubble */
.aico-msg-user{
    align-self:flex-end;max-width:82%;
    background:linear-gradient(135deg,#4f46e5,#6366f1);
    color:#fff;padding:10px 14px;border-radius: var(--radius-sm) 16px 4px 16px;
    font-size:13px;line-height:1.5;word-break:break-word;
}
/* AI bubble */
.aico-msg-ai{
    align-self:flex-start;max-width:88%;
    background:rgba(30,30,60,0.8);border:1px solid rgba(99,102,241,0.1);
    color:#e2e8f0;padding:10px 14px;border-radius: var(--radius-sm) 16px 16px 4px;
    font-size:13px;line-height:1.6;word-break:break-word;
}
.aico-msg-ai b,.aico-msg-ai strong{color:#a5b4fc}
/* Typing */
.aico-typing{display:none;padding:0 14px 6px;flex-shrink:0}
.aico-typing.show{display:block}
.aico-typing-dots{display:flex;gap:4px;padding:8px 12px;background:rgba(30,30,60,0.6);border-radius: var(--radius-sm);width:fit-content}
.aico-typing-dots span{width:6px;height:6px;border-radius:50%;background:#6366f1;animation:aicoBounce .6s infinite alternate}
.aico-typing-dots span:nth-child(2){animation-delay:.15s}
.aico-typing-dots span:nth-child(3){animation-delay:.3s}
@keyframes aicoBounce{0%{opacity:.3;transform:translateY(0)}100%{opacity:1;transform:translateY(-4px)}}
/* Input area */
.aico-input-area{
    display:flex;align-items:flex-end;gap:6px;
    padding:8px 12px;flex-shrink:0;
    border-top:1px solid rgba(99,102,241,0.12);
    background:rgba(3,7,18,0.9);
}
.aico-input-wrap{
    flex:1;background:rgba(99,102,241,0.08);
    border:1px solid rgba(99,102,241,0.15);
    border-radius: var(--radius);padding:0 14px;
    display:flex;align-items:center;
}
.aico-input{
    width:100%;border:none;outline:none;background:transparent;
    color:#e2e8f0;font-size:14px;font-family:inherit;
    padding:10px 0;resize:none;max-height:80px;line-height:1.4;
}
.aico-input::placeholder{color:rgba(148,163,184,0.4)}
.aico-voice-btn,.aico-send-btn{
    width:38px;height:38px;border-radius:50%;border:none;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;flex-shrink:0;transition:all .15s;
}
.aico-voice-btn{background:rgba(99,102,241,0.1);color:#818cf8}
.aico-voice-btn:active{background:rgba(99,102,241,0.3);transform:scale(.92)}
.aico-voice-btn.recording{background:rgba(239,68,68,0.2);color:#ef4444;animation:aicoPulseRec 1s infinite}
@keyframes aicoPulseRec{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.3)}50%{box-shadow:0 0 0 8px rgba(239,68,68,0)}}
.aico-send-btn{background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff}
.aico-send-btn:active{transform:scale(.92);filter:brightness(1.2)}


/* ═══ S105 — reduced-motion accessibility ═══ */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>

<script>
// ═══════════════════════════════════════════════════════════
// AI CHAT OVERLAY — Shared JS
// ═══════════════════════════════════════════════════════════

let _aicoRecognition = null;
let _aicoRecording = false;

function openAIChatOverlay() {
    const ov = document.getElementById('aiChatOverlay');
    if (!ov) return;
    ov.classList.add('open');
    history.pushState({ aiChat: true }, '');
    aicoScrollBottom();
}

function closeAIChatOverlay() {
    const ov = document.getElementById('aiChatOverlay');
    if (!ov) return;
    ov.classList.remove('open');
    aicoStopVoice();
}

// Back button support
window.addEventListener('popstate', function(e) {
    const ov = document.getElementById('aiChatOverlay');
    if (ov && ov.classList.contains('open')) {
        ov.classList.remove('open');
        aicoStopVoice();
    }
});

function aicoScrollBottom() {
    const area = document.getElementById('aicoMessages');
    if (area) setTimeout(() => { area.scrollTop = area.scrollHeight; }, 50);
}

function aicoAutoGrow(el) {
    el.style.height = '';
    el.style.height = Math.min(el.scrollHeight, 80) + 'px';
}

function aicoAddUserBubble(text) {
    const area = document.getElementById('aicoMessages');
    // Remove welcome if present
    const w = area.querySelector('.aico-welcome');
    if (w) w.remove();
    const div = document.createElement('div');
    div.className = 'aico-msg-user';
    div.textContent = text;
    area.appendChild(div);
    aicoScrollBottom();
}

function aicoAddAIBubble(text) {
    const area = document.getElementById('aicoMessages');
    const div = document.createElement('div');
    div.className = 'aico-msg-ai';
    // Basic markdown: **bold**, \n → <br>
    let html = text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
    html = html.replace(/\n/g, '<br>');
    div.innerHTML = html;
    area.appendChild(div);
    aicoScrollBottom();
}

async function aicoSend() {
    const input = document.getElementById('aicoInput');
    const text = input.value.trim();
    if (!text) return;

    aicoAddUserBubble(text);
    input.value = '';
    input.style.height = '';

    // Show typing
    document.getElementById('aicoTyping').classList.add('show');
    aicoScrollBottom();

    try {
        const resp = await fetch('chat-send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        });
        const data = await resp.json();
        document.getElementById('aicoTyping').classList.remove('show');
        aicoAddAIBubble(data.reply || data.error || 'Грешка при обработка.');
    } catch (err) {
        document.getElementById('aicoTyping').classList.remove('show');
        aicoAddAIBubble('Грешка при свързване. Опитай пак.');
    }
}

// Auto-send question (from signal tap, chip tap, etc.)
function sendAutoQuestion(text) {
    if (!text) return;
    openAIChatOverlay();
    // Small delay to let overlay animate open
    setTimeout(() => {
        const input = document.getElementById('aicoInput');
        if (input) input.value = text;
        aicoSend();
    }, 350);
}

// ─── VOICE ───
function aicoToggleVoice() {
    if (_aicoRecording) { aicoStopVoice(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { if(typeof showToast==='function')showToast('Гласът не се поддържа','error'); return; }
    _aicoRecognition = new SR();
    _aicoRecognition.lang = 'bg-BG';
    _aicoRecognition.continuous = false;
    _aicoRecognition.interimResults = true;
    const input = document.getElementById('aicoInput');
    const btn = document.getElementById('aicoVoiceBtn');
    _aicoRecognition.onresult = function(e) {
        let final = '', interim = '';
        for (let i = 0; i < e.results.length; i++) {
            if (e.results[i].isFinal) final += e.results[i][0].transcript;
            else interim += e.results[i][0].transcript;
        }
        input.value = final || interim;
        aicoAutoGrow(input);
    };
    _aicoRecognition.onend = function() {
        _aicoRecording = false;
        btn.classList.remove('recording');
        // Auto-send if we got text
        if (input.value.trim()) aicoSend();
    };
    _aicoRecognition.onerror = function() {
        _aicoRecording = false;
        btn.classList.remove('recording');
    };
    _aicoRecognition.start();
    _aicoRecording = true;
    btn.classList.add('recording');
}

function aicoStopVoice() {
    if (_aicoRecognition) { try { _aicoRecognition.stop(); } catch(e){} }
    _aicoRecording = false;
    const btn = document.getElementById('aicoVoiceBtn');
    if (btn) btn.classList.remove('recording');
}
</script>
