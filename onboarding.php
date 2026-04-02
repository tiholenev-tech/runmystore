<?php
// onboarding.php — AI First Onboarding v2.0
// Voice First. Без форми. Само разговор.
// УАУ момент с web search → реални цифри.

session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$t = DB::run("SELECT onboarding_done FROM tenants WHERE id=?", [$tenant_id])->fetch();
if ($t && $t['onboarding_done']) { header('Location: chat.php'); exit; }
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Добре дошъл — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
body{background:#030712;color:#e2e8f0;font-family:'Montserrat',sans-serif;min-height:100dvh;display:flex;flex-direction:column;overflow:hidden}

/* MESH GRADIENT */
body::before{content:'';position:fixed;inset:0;background:
  radial-gradient(circle at 15% 50%,rgba(99,102,241,.09) 0%,transparent 45%),
  radial-gradient(circle at 85% 20%,rgba(168,85,247,.07) 0%,transparent 45%),
  radial-gradient(circle at 50% 85%,rgba(59,130,246,.05) 0%,transparent 40%);
  pointer-events:none;z-index:0}

/* HEADER */
.hdr{position:relative;z-index:50;background:rgba(3,7,18,.92);backdrop-filter:blur(24px);border-bottom:1px solid rgba(99,102,241,.12);padding:14px 16px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.brand{font-size:18px;font-weight:900;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}

/* CHAT AREA */
.chat-area{flex:1;overflow-y:auto;overflow-x:hidden;padding:16px 14px 8px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.chat-area::-webkit-scrollbar{display:none}

/* MESSAGES */
.msg-group{margin-bottom:14px;animation:fadeUp .3s ease both}
.msg-meta{font-size:10px;color:#4b5563;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.ai-ava{width:26px;height:26px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;box-shadow:0 0 8px rgba(99,102,241,.5)}
.ai-ava-bars{display:flex;gap:2px;align-items:center;height:11px}
.ai-ava-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.ai-ava-bar:nth-child(1){height:4px;animation-delay:0s}
.ai-ava-bar:nth-child(2){height:8px;animation-delay:.15s}
.ai-ava-bar:nth-child(3){height:11px;animation-delay:.3s}
.ai-ava-bar:nth-child(4){height:6px;animation-delay:.45s}
.msg{max-width:88%;padding:11px 14px;font-size:13px;line-height:1.6;word-break:break-word}
.msg.ai{background:rgba(12,12,30,.88);border:1px solid rgba(99,102,241,.14);color:#e2e8f0;border-radius:4px 16px 16px 16px;backdrop-filter:blur(8px)}
.msg.user{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:16px 16px 4px 16px;margin-left:auto;box-shadow:inset 0 1px 0 rgba(255,255,255,.16)}
.msg.ai.wow{border-color:rgba(99,102,241,.35);background:rgba(12,12,30,.95)}

/* TYPING */
.typing-wrap{display:none;padding:10px 14px;background:rgba(12,12,30,.88);border:1px solid rgba(99,102,241,.14);border-radius:4px 16px 16px 16px;width:fit-content;margin-bottom:14px;backdrop-filter:blur(8px)}
.typing-dots{display:flex;gap:4px;align-items:center}
.dot{width:7px;height:7px;border-radius:50%;background:#6366f1;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}
.dot:nth-child(3){animation-delay:.4s}

/* SEARCH INDICATOR */
.search-indicator{display:none;padding:8px 14px;margin-bottom:10px;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:12px;font-size:11px;color:#6366f1;font-weight:600;animation:fadeUp .3s ease}
.search-indicator.show{display:flex;align-items:center;gap:8px}
.search-dot{width:6px;height:6px;border-radius:50%;background:#6366f1;animation:bounce 1s infinite}

/* ACTION BUTTONS */
.action-wrap{padding:0 14px 8px;flex-shrink:0;position:relative;z-index:1}
.action-row{display:flex;gap:8px;justify-content:center}
.action-btn{flex:1;max-width:180px;padding:11px 14px;border-radius:14px;font-size:13px;font-weight:700;border:1px solid rgba(99,102,241,.25);color:#a5b4fc;background:rgba(12,12,30,.7);backdrop-filter:blur(8px);cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;text-align:center}
.action-btn:active{background:rgba(99,102,241,.2);border-color:#6366f1;transform:scale(.97)}
.action-btn.primary{background:linear-gradient(to bottom,#6366f1,#5558e8);border-color:transparent;color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.4)}

/* INPUT */
.input-area{background:rgba(3,7,18,.94);backdrop-filter:blur(24px);border-top:1px solid rgba(99,102,241,.12);padding:10px 14px 16px;flex-shrink:0;position:relative;z-index:1}
.input-row{display:flex;gap:8px;align-items:center}
.text-input{flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(99,102,241,.18);border-radius:22px;color:#e2e8f0;font-size:14px;padding:11px 16px;font-family:'Montserrat',sans-serif;outline:none;resize:none;max-height:80px;line-height:1.4;transition:border-color .2s}
.text-input:focus{border-color:rgba(99,102,241,.45)}
.text-input::placeholder{color:#2d3748}

/* VOICE BUTTON */
.voice-wrap{position:relative;flex-shrink:0;width:56px;height:56px;cursor:pointer}
.voice-ring{position:absolute;border-radius:50%;border:1px solid rgba(99,102,241,.45);animation:waveOut 2s ease-out infinite;pointer-events:none}
.voice-ring:nth-child(1){inset:-5px;animation-delay:0s}
.voice-ring:nth-child(2){inset:-11px;animation-delay:.55s}
.voice-ring:nth-child(3){inset:-17px;animation-delay:1.1s}
.voice-inner{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;position:relative;z-index:1;box-shadow:0 0 22px rgba(99,102,241,.55),0 0 44px rgba(99,102,241,.2);transition:all .2s}
.voice-bars{display:flex;gap:3px;align-items:center;height:20px}
.voice-bar{width:3px;border-radius:2px;background:#fff;animation:barDance 1s ease-in-out infinite}
.voice-bar:nth-child(1){height:8px;animation-delay:0s}
.voice-bar:nth-child(2){height:16px;animation-delay:.15s}
.voice-bar:nth-child(3){height:20px;animation-delay:.3s}
.voice-bar:nth-child(4){height:12px;animation-delay:.45s}
.voice-bar:nth-child(5){height:8px;animation-delay:.6s}
.voice-wrap.recording .voice-inner{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 0 28px rgba(239,68,68,.7)}
.voice-wrap.recording .voice-ring{border-color:rgba(239,68,68,.45)}

/* SEND BTN */
.send-btn{width:42px;height:42px;border-radius:50%;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.22);color:#a5b4fc;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-btn:active{background:rgba(99,102,241,.28);transform:scale(.92)}
.send-btn:disabled{opacity:.3;cursor:default}

/* REC OVERLAY */
.rec-overlay{position:fixed;inset:0;background:rgba(0,0,0,.87);z-index:400;display:none;flex-direction:column;align-items:center;justify-content:center;backdrop-filter:blur(14px)}
.rec-overlay.show{display:flex}
.rec-circle{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;margin-bottom:24px;animation:recPulse 1s ease-out infinite}
.rec-wave-bars{display:flex;gap:5px;align-items:center;height:32px}
.rec-bar{width:5px;border-radius:3px;background:#fff;animation:barDance .7s ease-in-out infinite}
.rec-bar:nth-child(1){height:12px;animation-delay:0s}
.rec-bar:nth-child(2){height:24px;animation-delay:.1s}
.rec-bar:nth-child(3){height:32px;animation-delay:.2s}
.rec-bar:nth-child(4){height:20px;animation-delay:.3s}
.rec-bar:nth-child(5){height:12px;animation-delay:.4s}
.rec-title{font-size:18px;font-weight:800;color:#f1f5f9;margin-bottom:6px}
.rec-sub{font-size:13px;color:#6b7280;margin-bottom:28px}
.rec-stop{padding:11px 30px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);border-radius:24px;color:#ef4444;font-size:14px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}

/* MIC PERMISSION SCREEN */
.mic-screen{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:32px 24px;text-align:center;position:relative;z-index:1}
.mic-screen.show{display:flex}
.mic-icon-wrap{width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;margin-bottom:24px;box-shadow:0 0 40px rgba(99,102,241,.5);animation:recPulse 2s ease-out infinite}
.mic-title{font-size:22px;font-weight:800;color:#f1f5f9;margin-bottom:10px;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.mic-sub{font-size:14px;color:#6b7280;line-height:1.65;margin-bottom:28px;max-width:280px}
.mic-btn{padding:14px 32px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:16px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.4)}
.mic-skip{margin-top:12px;font-size:12px;color:#4b5563;cursor:pointer;text-decoration:underline}

/* ANIMATIONS */
@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
@keyframes waveOut{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.9);opacity:0}}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(99,102,241,.5)}70%{box-shadow:0 0 0 20px rgba(99,102,241,0)}100%{box-shadow:0 0 0 0 rgba(99,102,241,0)}}
</style>
</head>
<body>

<div class="hdr">
  <div class="brand">RunMyStore.ai</div>
</div>

<!-- MIC PERMISSION SCREEN -->
<div class="mic-screen show" id="micScreen">
  <div class="mic-icon-wrap">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
    </svg>
  </div>
  <div class="mic-title">Хей! Аз съм твоят AI асистент 🙌</div>
  <div class="mic-sub">Ще работим заедно всеки ден.<br>Разреши ми да те чувам — за да говориш с мен вместо да пишеш.</div>
  <button class="mic-btn" onclick="requestMic()">Разреши микрофона</button>
  <div class="mic-skip" onclick="skipMic()">Ще пиша засега</div>
</div>

<!-- CHAT INTERFACE -->
<div id="chatInterface" style="display:none;flex:1;flex-direction:column;overflow:hidden">
  <div class="chat-area" id="chatArea">
    <div class="typing-wrap" id="typing">
      <div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
    </div>
  </div>

  <div class="search-indicator" id="searchIndicator">
    <div class="search-dot"></div>
    <span>Проучвам данни за твоя бизнес...</span>
  </div>

  <div class="action-wrap" id="actionWrap" style="display:none">
    <div class="action-row" id="actionRow"></div>
  </div>

  <div class="input-area">
    <div class="input-row">
      <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1"
        oninput="autoResize(this);btnSend.disabled=!this.value.trim()"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendText()}"></textarea>
      <div class="voice-wrap" id="voiceWrap" onclick="toggleVoice()">
        <div class="voice-ring"></div><div class="voice-ring"></div><div class="voice-ring"></div>
        <div class="voice-inner">
          <div class="voice-bars">
            <div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div>
            <div class="voice-bar"></div><div class="voice-bar"></div>
          </div>
        </div>
      </div>
      <button class="send-btn" id="btnSend" onclick="sendText()" disabled>
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </button>
    </div>
  </div>
</div>

<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle">
    <div class="rec-wave-bars">
      <div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div>
      <div class="rec-bar"></div><div class="rec-bar"></div>
    </div>
  </div>
  <div class="rec-title">Слушам...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<script>
// ═══════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════
const state = {
  step: 'name',        // name → biz → segment → stores → products → employees → wow → features → loyalty1 → loyalty2 → loyalty3 → done
  name: '',
  biz: '',
  segment: '',
  stores: '',
  products: '',
  employees: '',
  loyaltyFreq: '',
  loyaltyReward: '',
  loyaltyCompetition: '',
  micGranted: false,
  wowDone: false
};

const chatArea   = document.getElementById('chatArea');
const typing     = document.getElementById('typing');
const voiceWrap  = document.getElementById('voiceWrap');
const recOverlay = document.getElementById('recOverlay');
const actionWrap = document.getElementById('actionWrap');
const actionRow  = document.getElementById('actionRow');
const searchInd  = document.getElementById('searchIndicator');
let voiceRec = null, isRecording = false;

// ═══════════════════════════════════════════
// MIC PERMISSION
// ═══════════════════════════════════════════
async function requestMic() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    stream.getTracks().forEach(t => t.stop());
    state.micGranted = true;
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SR) {
      await new Promise(resolve => {
        const sr = new SR();
        sr.lang = 'bg-BG';
        sr.continuous = false;
        sr.onstart  = () => setTimeout(() => { try { sr.abort(); } catch(e){} resolve(); }, 500);
        sr.onresult = () => { try { sr.abort(); } catch(e){} resolve(); };
        sr.onerror  = () => resolve();
        sr.onend    = () => resolve();
        try { sr.start(); } catch(e) { resolve(); }
      });
    }
  } catch(e) {
    state.micGranted = false;
  }
  startChat();
}

    state.micGranted = true;
  } catch(e) {
    state.micGranted = false;
  }
  startChat();
}

function startChat() {
  document.getElementById('micScreen').classList.remove('show');
  document.getElementById('chatInterface').style.display = 'flex';
  document.getElementById('chatInterface').style.flexDirection = 'column';
  setTimeout(() => aiSay("Хей! Аз съм твоят нов бизнес асистент 🙌\nЩе работим заедно всеки ден.\nКак да те викам?"), 400);
}

// ═══════════════════════════════════════════
// CHAT HELPERS
// ═══════════════════════════════════════════
function scrollBottom() { chatArea.scrollTop = chatArea.scrollHeight; }

function aiSay(text, isWow = false) {
  hideActions();
  const g = document.createElement('div');
  g.className = 'msg-group';
  g.innerHTML = `
    <div class="msg-meta">
      <div class="ai-ava"><div class="ai-ava-bars">
        <div class="ai-ava-bar"></div><div class="ai-ava-bar"></div>
        <div class="ai-ava-bar"></div><div class="ai-ava-bar"></div>
      </div></div>
      AI Асистент
    </div>
    <div class="msg ai${isWow?' wow':''}">${esc(text).replace(/\n/g,'<br>')}</div>`;
  chatArea.insertBefore(g, typing);
  scrollBottom();
}

function userSay(text) {
  const g = document.createElement('div');
  g.className = 'msg-group';
  g.innerHTML = `
    <div class="msg-meta" style="justify-content:flex-end">
      ${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}
    </div>
    <div style="display:flex;justify-content:flex-end">
      <div class="msg user">${esc(text)}</div>
    </div>`;
  chatArea.insertBefore(g, typing);
  scrollBottom();
}

function showTyping() { typing.style.display = 'block'; scrollBottom(); }
function hideTyping() { typing.style.display = 'none'; }

function showActions(buttons) {
  actionRow.innerHTML = buttons.map(b =>
    `<button class="action-btn${b.primary?' primary':''}" onclick="handleAction('${esc(b.val)}')">${b.label}</button>`
  ).join('');
  actionWrap.style.display = 'block';
}
function hideActions() { actionWrap.style.display = 'none'; actionRow.innerHTML = ''; }

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function autoResize(el) { el.style.height = ''; el.style.height = Math.min(el.scrollHeight,80)+'px'; }

// ═══════════════════════════════════════════
// VOICE
// ═══════════════════════════════════════════
function toggleVoice() {
  if (isRecording) { stopVoice(); return; }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { showToast('Браузърът не поддържа гласово въвеждане'); return; }
  isRecording = true;
  voiceWrap.classList.add('recording');
  recOverlay.classList.add('show');
  voiceRec = new SR();
  voiceRec.lang = 'bg-BG';
  voiceRec.interimResults = false;
  voiceRec.maxAlternatives = 1;
  voiceRec.continuous = false;
  voiceRec.onresult = (e) => {
    const text = e.results[0][0].transcript;
    stopVoice();
    processInput(text);
  };
  voiceRec.onerror = (e) => {
    stopVoice();
    if (e.error === 'no-speech') showToast('Не чух нищо — опитай пак');
    else if (e.error === 'not-allowed') showToast('Разреши достъп до микрофона в настройките');
    else showToast('Грешка: ' + e.error);
  };
  voiceRec.onend = () => { if (isRecording) stopVoice(); };
  try { voiceRec.start(); } catch(e) { stopVoice(); }
}

function stopVoice() {
  isRecording = false;
  voiceWrap.classList.remove('recording');
  recOverlay.classList.remove('show');
  if (voiceRec) { try { voiceRec.stop(); } catch(e){} voiceRec = null; }
}

function sendText() {
  const input = document.getElementById('chatInput');
  const text = input.value.trim();
  if (!text) return;
  input.value = ''; input.style.height = '';
  document.getElementById('btnSend').disabled = true;
  processInput(text);
}

function handleAction(val) { processInput(val); }

// ═══════════════════════════════════════════
// FLOW LOGIC
// ═══════════════════════════════════════════
async function processInput(text) {
  userSay(text);
  hideActions();
  showTyping();

  await delay(600);
  hideTyping();

  switch(state.step) {

    case 'name':
      state.name = capitalize(text.trim());
      state.step = 'biz';
      aiSay(`${state.name}! Хубаво 😄\nКажи ми — какво продаваш?`);
      break;

    case 'biz':
      state.biz = text.trim();
      state.step = 'segment';
      const segQ = getSegmentQuestion(state.biz);
      aiSay(segQ);
      break;

    case 'segment':
      state.segment = text.trim();
      state.step = 'stores';
      // Web search started in background
      searchResult = '';
doWebSearch();
await delay(200);
aiSay('Колко магазина имаш?');
      break;

    case 'stores':
      state.stores = text.trim();
      state.step = 'products';
      aiSay('Колко артикула приблизително —\nпод 200, около 500, или повече?');
      break;

    case 'products':
      state.products = text.trim();
      state.step = 'employees';
      aiSay('Имаш ли служители?');
      break;

    case 'employees':
      state.employees = text.trim();
      const hasEmp = /да|yes|имам|работят|момич|момч|човек|души/i.test(text);
      if (hasEmp) {
        aiSay(`Те ще могат да питат мен\nвместо да те звънят на теб 😄`);
        await delay(1800);
      }
      state.step = 'wow';
      await showWowMoment();
      break;

    case 'wow_confirm':
      state.step = 'features';
      await showFeatures();
      break;

    case 'loyalty1':
      state.loyaltyFreq = text.trim();
      state.step = 'loyalty2';
      await delay(300);
      aiSay('Какво би дал на най-верния си клиент?');
      break;

    case 'loyalty2':
      state.loyaltyReward = text.trim();
      state.step = 'loyalty3';
      await delay(300);
      aiSay('Имаш ли конкуренция наблизо?');
      break;

    case 'loyalty3':
      state.loyaltyCompetition = text.trim();
      state.step = 'done';
      await showLoyaltyResult();
      break;

    case 'done':
      await finishOnboarding();
      break;
  }
}

// ═══════════════════════════════════════════
// SEGMENT QUESTION
// ═══════════════════════════════════════════
function getSegmentQuestion(biz) {
  const b = biz.toLowerCase();
  if (/дрех|облекло|риз|блуз|панталон|рокл/i.test(b))
    return 'Casual ли е — тениски, дънки, или по-официално — костюми, елегантно?';
  if (/обувк|маратонк|чехъл|боти/i.test(b))
    return 'Ежедневни обувки или по-скъпи — кожени, официални?';
  if (/бижу|пръстен|гривн|обец|колие/i.test(b))
    return 'Сребро и стомана, или злато и скъпоценни камъни?';
  if (/козмет|парфюм|грим|крем/i.test(b))
    return 'Масов пазар или по-скъпи марки — Chanel, Dior и подобни?';
  if (/телефон|електрон|аксесоар|кабел|калъф/i.test(b))
    return 'Аксесоари и кабели, или и телефони, лаптопи?';
  if (/хран|магазин|хлябъ|месо|плод/i.test(b))
    return 'Обикновен хранителен или bio, premium продукти?';
  if (/строит|инструмент|бои|крепеж/i.test(b))
    return 'Дребен консуматив или и едро, машини, строителни материали?';
  if (/играч|детск/i.test(b))
    return 'По-евтини играчки или и по-скъпи — конструктори, образователни?';
  if (/цвет|букет|сакс/i.test(b))
    return 'Рязан цвят и букети, или и саксийни, по-скъпи композиции?';
  if (/авто|кола|гума|масло/i.test(b))
    return 'Консумативи — масла, филтри, или и по-скъпи части?';
  // Default
  return `Продаваш ли по-евтини артикули или по-скъпи, качествени стоки?`;
}

// ═══════════════════════════════════════════
// WEB SEARCH → ai-helper.php
// ═══════════════════════════════════════════
let searchResult = '';
async function doWebSearch() {
  try {
    const query = `${state.biz} ${state.segment} small retailer dead stock loss monthly EU average`;
    const r = await fetch('ai-helper.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        action: 'web_search',
        query: query
      })
    });
    const d = await r.json();
    searchResult = d.result || '';
  } catch(e) {
    searchResult = '';
  }
}

// ═══════════════════════════════════════════
// WOW MOMENT — 5 съобщения от AI
// ═══════════════════════════════════════════
async function showWowMoment() {
  showTyping();

  // Показваме search indicator ако още търси
  searchInd.classList.add('show');

  // Изчакваме search (макс 4 сек)
  let waited = 0;
while (!searchResult && waited < 5000) {
  await delay(300);
  waited += 300;
}
searchInd.classList.remove('show');
hideTyping();

  const prompt = buildWowPrompt();

  try {
    showTyping();
    const r = await fetch('ai-helper.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'wow', prompt: prompt })
    });
    const d = await r.json();
    hideTyping();

    if (d.messages && Array.isArray(d.messages)) {
      // Показваме 5 съобщения едно по едно с пауза
      for (let i = 0; i < d.messages.length; i++) {
        await delay(i === 0 ? 300 : 1400);
        aiSay(d.messages[i], true);
        scrollBottom();
      }
    } else {
      // Fallback ако AI не върне масив
      aiSay(d.reply || d.text || 'Магазини като твоя губят средно €200-500 на месец от залежала стока. Аз следя и те спирам навреме.', true);
    }
  } catch(e) {
    hideTyping();
    aiSay(`${state.name}, магазини като твоя губят средно €200-500 на месец.\nАз следя всичко и те спирам преди да е станало.`, true);
  }

  await delay(1600);
  state.step = 'wow_confirm';
  aiSay('Продължаваме? 🚀');
  showActions([{label:'Да, напред!', val:'да', primary:true}]);
}

function buildWowPrompt() {
  return `Ти си AI асистент на RunMyStore.ai провеждащ онбординг разговор.
Говориш като топъл, умен приятел търговец — не като робот.
Разговорен български. Никога корпоративен.

ДАННИ ЗА ПЕШО:
- Име: ${state.name}
- Бизнес: ${state.biz}
- Ценови сегмент: ${state.segment}
- Брой магазини: ${state.stores}
- Брой артикули: ${state.products}
- Служители: ${state.employees}
- Пазарни данни: ${searchResult || 'не са налични — използвай консервативни диапазони'}

ГЕНЕРИРАЙ ТОЧНО 5 КРАТКИ СЪОБЩЕНИЯ като JSON масив:
{"messages": ["съобщение1", "съобщение2", "съобщение3", "съобщение4", "съобщение5"]}

СТРУКТУРА:
Съобщения 1-4: Всяко следва точно тази структура:
[Конкретен проблем за ТОЗИ бизнес и ценови сегмент]
→ [Точно как RunMyStore.ai го решава]
Загуба: €X-Y на [период]

Съобщение 5 ЗАДЪЛЖИТЕЛНО:
"${state.name}, само тези 4 проблема ти струват ~€X на година.
RunMyStore.ai е €588 на година.
Разликата — €X — остава в джоба ти."
(X = реалистична сума базирана на пазарните данни × 12)

ПРАВИЛА:
- Цифрите трябва да са реалистични за ТОЗИ ценови сегмент
- Скъп бизнес (костюми, злато) → по-големи суми
- Евтин бизнес (аксесоари, кабели) → по-малки суми
- Никога не измисляй — използвай пазарните данни
- Ако няма данни → консервативен диапазон €X-Y
- Максимум 4 реда на съобщение
- Тон: приятел, конкретен, без "може би"
- Никога не споменавай Shopify, Facebook, Еконт
ВЪРНИ САМО JSON БЕЗ ОБЯСНЕНИЯ.`;
}

// ═══════════════════════════════════════════
// FEATURES
// ═══════════════════════════════════════════
async function showFeatures() {
  await delay(400);
  aiSay(`Ето с какво ще работим заедно:\n\n📦 Следя склада — кое върви, кое стои, кое свършва\n\n🔔 Будя те навреме — преди да е свършила стоката\n\n📊 Казвам ти печалбата за деня — без да броиш нищо\n\n🎤 Управляваш всичко с глас — питаш мен, не търсиш в менюта\n\n👥 Служителите питат мен вместо да те звънят\n\n🎁 Лоялна карта за клиентите — безплатна завинаги\n\n30 дни пробваш всичко — безплатно, без карта.\nСлед това — €49 на месец.`);
  await delay(1800);
  state.step = 'loyalty1';
  aiSay(`Сега правим лоялната програма за клиентите ти.\nТри бързи въпроса.\n\nКолко често идват редовните ти клиенти?`);
}

// ═══════════════════════════════════════════
// LOYALTY RESULT
// ═══════════════════════════════════════════
async function showLoyaltyResult() {
  showTyping();
  await delay(1200);
  hideTyping();

  const hasCompetition = /да|yes|има|конкурент|наблизо/i.test(state.loyaltyCompetition);
  const isFrequent = /всеки|ден|седм|редовно/i.test(state.loyaltyFreq);

  // Изчисляваме точки спрямо честота
  const pointsThreshold = isFrequent ? 50 : 100;
  const rewardEur = isFrequent ? 3 : 5;

  const competitionLine = hasCompetition
    ? `\nСрещу конкуренцията — лоялността е оръжието ти 💪`
    : '';

  aiSay(`Готово! Правя ти '${state.name} CLUB':\n\n• €1 похарчен = 1 точка\n• ${pointsThreshold} точки = €${rewardEur} отстъпка\n• Рожден ден = специален подарък\n• Лоялната карта е безплатна завинаги${competitionLine}\n\nХаресва ли ти?`);

  showActions([
    {label:'Да, страхотно!', val:'готово', primary:true},
    {label:'Промени нещо', val:'промени'}
  ]);
}

// ═══════════════════════════════════════════
// FINISH
// ═══════════════════════════════════════════
async function finishOnboarding() {
  showTyping();

  try {
    await fetch('onboarding-save.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        name: state.name,
        biz: state.biz,
        segment: state.segment,
        stores: state.stores,
        products: state.products,
        employees: state.employees,
        loyalty_freq: state.loyaltyFreq,
        loyalty_reward: state.loyaltyReward,
        loyalty_competition: state.loyaltyCompetition
      })
    });
  } catch(e) {}

  hideTyping();
  aiSay(`${state.name}, всичко е готово! 🚀\n\n30 дни пробваш безплатно — без карта.\nЛоялната карта остава безплатна завинаги.\nСлед това — €49 на месец.\n\nАз съм тук всеки ден.\nПитай каквото искаш, по всяко време.`);

  await delay(2000);
  window.location.href = 'chat.php';
}

// ═══════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════
function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

function capitalize(s) {
  return s.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
}

function showToast(msg) {
  let t = document.getElementById('_toast');
  if (!t) {
    t = document.createElement('div');
    t.id = '_toast';
    t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;font-family:Montserrat,sans-serif';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1';
  setTimeout(() => t.style.opacity = '0', 2800);
}
</script>
</body>
</html>
