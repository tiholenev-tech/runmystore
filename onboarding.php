<?php
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
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 50%,rgba(99,102,241,.09) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(168,85,247,.07) 0%,transparent 45%),radial-gradient(circle at 50% 85%,rgba(59,130,246,.05) 0%,transparent 40%);pointer-events:none;z-index:0}
.hdr{position:relative;z-index:50;background:rgba(3,7,18,.92);backdrop-filter:blur(24px);border-bottom:1px solid rgba(99,102,241,.12);padding:14px 16px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.brand{font-size:18px;font-weight:900;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
/* ТУК Е ПРОМЯНАТА НА ДИЗАЙНА ЗА ЧАТА */
.chat-area{background-color:rgba(15,23,42,0.4);border-radius:16px;margin:8px 8px 0 8px;flex:1;overflow-y:auto;overflow-x:hidden;padding:16px 14px 8px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.chat-area::-webkit-scrollbar{display:none}
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
.msg.wow{border-color:rgba(99,102,241,.4);background:rgba(6,6,20,.97)}
.typing-wrap{display:none;padding:10px 14px;background:rgba(12,12,30,.88);border:1px solid rgba(99,102,241,.14);border-radius:4px 16px 16px 16px;width:fit-content;margin-bottom:14px;backdrop-filter:blur(8px)}
.typing-dots{display:flex;gap:4px;align-items:center}
.dot{width:7px;height:7px;border-radius:50%;background:#6366f1;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}
.dot:nth-child(3){animation-delay:.4s}
.search-indicator{display:none;padding:8px 14px;margin-bottom:10px;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:12px;font-size:11px;color:#6366f1;font-weight:600}
.search-indicator.show{display:flex;align-items:center;gap:8px}
.search-dot{width:6px;height:6px;border-radius:50%;background:#6366f1;animation:bounce 1s infinite}
.action-wrap{padding:0 14px 8px;flex-shrink:0;position:relative;z-index:1}
.action-row{display:flex;gap:8px;justify-content:center}
.action-btn{flex:1;max-width:180px;padding:11px 14px;border-radius:14px;font-size:13px;font-weight:700;border:1px solid rgba(99,102,241,.25);color:#a5b4fc;background:rgba(12,12,30,.7);backdrop-filter:blur(8px);cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;text-align:center}
.action-btn:active{background:rgba(99,102,241,.2);border-color:#6366f1;transform:scale(.97)}
.action-btn.primary{background:linear-gradient(to bottom,#6366f1,#5558e8);border-color:transparent;color:#fff;box-shadow:0 4px 16px rgba(99,102,241,.4)}
.input-area{background:rgba(3,7,18,.94);backdrop-filter:blur(24px);border-top:1px solid rgba(99,102,241,.12);padding:10px 14px 16px;flex-shrink:0;position:relative;z-index:1}
.input-row{display:flex;gap:8px;align-items:center}
.text-input{flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(99,102,241,.18);border-radius:22px;color:#e2e8f0;font-size:14px;padding:11px 16px;font-family:'Montserrat',sans-serif;outline:none;resize:none;max-height:80px;line-height:1.4;transition:border-color .2s}
.text-input:focus{border-color:rgba(99,102,241,.45)}
.text-input::placeholder{color:#2d3748}
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
.send-btn{width:42px;height:42px;border-radius:50%;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.22);color:#a5b4fc;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-btn:active{background:rgba(99,102,241,.28);transform:scale(.92)}
.send-btn:disabled{opacity:.3;cursor:default}
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
.mic-screen{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:32px 24px;text-align:center;position:relative;z-index:1}
.mic-screen.show{display:flex}
.mic-icon-wrap{width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;margin-bottom:24px;box-shadow:0 0 40px rgba(99,102,241,.5);animation:recPulse 2s ease-out infinite}
.mic-title{font-size:22px;font-weight:800;color:#f1f5f9;margin-bottom:10px;background:linear-gradient(to right,#f1f5f9,#a5b4fc,#f1f5f9);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.mic-sub{font-size:14px;color:#6b7280;line-height:1.65;margin-bottom:28px;max-width:280px}
.mic-btn{padding:14px 32px;background:linear-gradient(to bottom,#6366f1,#5558e8);border:none;border-radius:16px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 20px rgba(99,102,241,.4)}
.mic-skip{margin-top:16px;font-size:13px;color:#4b5563;cursor:pointer;text-decoration:underline;padding:8px 16px;display:inline-block}
@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
@keyframes waveOut{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.9);opacity:0}}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(99,102,241,.5)}70%{box-shadow:0 0 0 20px rgba(99,102,241,0)}100%{box-shadow:0 0 0 0 rgba(99,102,241,0)}}
</style>
</head>
<body>

<div class="hdr"><div class="brand">RunMyStore.ai</div></div>

<div class="mic-screen show" id="micScreen">
  <div class="mic-icon-wrap">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
    </svg>
  </div>
  <div class="mic-title">Хей! Аз съм твоят AI асистент 🙌</div>
  <div class="mic-sub">Ще работим заедно всеки ден.<br>Разреши ми да те чувам — за да говориш с мен вместо да пишеш.</div>
  <button class="mic-btn" id="micBtn">Разреши микрофона</button>
  <div class="mic-skip" id="micSkip">Ще пиша засега</div>
</div>

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
        oninput="autoResize(this);document.getElementById('btnSend').disabled=!this.value.trim()"
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
// ═══ UTILS — ПЪРВИ В КОДА ═══
function wait(ms) { return new Promise(function(r){ setTimeout(r, ms); }); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function autoResize(el) { el.style.height=''; el.style.height=Math.min(el.scrollHeight,80)+'px'; }
function capitalize(s) { return s.split(' ').map(function(w){ return w.charAt(0).toUpperCase()+w.slice(1).toLowerCase(); }).join(' '); }
function showToast(msg) {
  var t=document.getElementById('_toast');
  if(!t){t=document.createElement('div');t.id='_toast';t.style.cssText='position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;font-family:Montserrat,sans-serif';document.body.appendChild(t);}
  t.textContent=msg; t.style.opacity='1'; setTimeout(function(){t.style.opacity='0';},2800);
}

// ═══ STATE ═══
var state = { step:'name', name:'', biz:'', segment:'', stores:'', products:'', employees:'', loyaltyFreq:'', loyaltyReward:'', loyaltyCompetition:'', micGranted:false };
var voiceRec=null, isRecording=false, searchResult='', searchDone=false;

// ═══ DOM ═══
var chatArea=document.getElementById('chatArea');
var typing=document.getElementById('typing');
var voiceWrap=document.getElementById('voiceWrap');
var recOverlay=document.getElementById('recOverlay');
var actionWrap=document.getElementById('actionWrap');
var actionRow=document.getElementById('actionRow');
var searchInd=document.getElementById('searchIndicator');

// ═══ MIC BUTTONS ═══
document.getElementById('micBtn').addEventListener('click', function(){
  var btn = this;
  btn.disabled = true;
  btn.textContent = 'Изчакай...';
  
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SR) {
    try {
      var dummy = new SR();
      dummy.onstart = function() { 
        dummy.stop(); 
        state.micGranted = true; 
        startChat(); 
      };
      dummy.onerror = function() { 
        state.micGranted = false; 
        startChat(); 
      };
      dummy.start();
    } catch(e) {
      state.micGranted = false;
      startChat();
    }
  } else if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia({audio:true})
      .then(function(stream){
        stream.getTracks().forEach(function(t){t.stop();});
        state.micGranted = true;
        startChat();
      })
      .catch(function(){
        state.micGranted = false;
        startChat();
      });
  } else {
    state.micGranted = false;
    startChat();
  }
});

document.getElementById('micSkip').addEventListener('click', function(){
  state.micGranted = false;
  startChat();
});

// ═══ START CHAT ═══
function startChat() {
  document.getElementById('micScreen').classList.remove('show');
  var ci = document.getElementById('chatInterface');
  ci.style.display = 'flex';
  ci.style.flex = '1';
  ci.style.flexDirection = 'column';
  ci.style.overflow = 'hidden';
  setTimeout(function(){
    aiSay('Хей! Аз съм твоят нов бизнес асистент 🙌\nЩе работим заедно всеки ден.\nКак да те викам?');
  }, 400);
}

// ═══ CHAT HELPERS ═══
function scrollBottom(){ chatArea.scrollTop=chatArea.scrollHeight; }

function aiSay(text, isWow){
  hideActions();
  var g=document.createElement('div'); g.className='msg-group';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div>AI Асистент</div><div class="msg ai'+(isWow?' wow':'')+'">'+esc(text).replace(/\n/g,'<br>')+'</div>';
  chatArea.insertBefore(g,typing); scrollBottom();
}

function userSay(text){
  var g=document.createElement('div'); g.className='msg-group';
  g.innerHTML='<div class="msg-meta" style="justify-content:flex-end">'+new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})+'</div><div style="display:flex;justify-content:flex-end"><div class="msg user">'+esc(text)+'</div></div>';
  chatArea.insertBefore(g,typing); scrollBottom();
}

function showTyping(){ typing.style.display='block'; scrollBottom(); }
function hideTyping(){ typing.style.display='none'; }
function showActions(buttons){
  actionRow.innerHTML=buttons.map(function(b){ return '<button class="action-btn'+(b.primary?' primary':'')+'" onclick="handleAction(\''+esc(b.val)+'\')">'+b.label+'</button>'; }).join('');
  actionWrap.style.display='block';
}
function hideActions(){ actionWrap.style.display='none'; actionRow.innerHTML=''; }

// ═══ VOICE ═══
function toggleVoice(){
  if(isRecording){stopVoice();return;}
  var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){showToast('Браузърът не поддържа гласово въвеждане');return;}
  isRecording=true; voiceWrap.classList.add('recording'); recOverlay.classList.add('show');
  voiceRec=new SR(); voiceRec.lang='bg-BG'; voiceRec.interimResults=false; voiceRec.maxAlternatives=1; voiceRec.continuous=false;
  voiceRec.onresult=function(e){var t=e.results[0][0].transcript;stopVoice();processInput(t);};
  voiceRec.onerror=function(e){stopVoice();if(e.error==='no-speech')showToast('Не чух нищо — опитай пак');else if(e.error==='not-allowed')showToast('Разреши микрофона от настройките или презареди');else showToast('Грешка: '+e.error);};
  voiceRec.onend=function(){if(isRecording)stopVoice();};
  try{voiceRec.start();}catch(e){stopVoice();}
}

function stopVoice(){
  isRecording=false; voiceWrap.classList.remove('recording'); recOverlay.classList.remove('show');
  if(voiceRec){try{voiceRec.stop();}catch(e){} voiceRec=null;}
}

function sendText(){
  var input=document.getElementById('chatInput');
  var text=input.value.trim(); if(!text)return;
  input.value=''; input.style.height=''; document.getElementById('btnSend').disabled=true;
  processInput(text);
}

function handleAction(val){ processInput(val); }

// ═══ FLOW ═══
async function processInput(text){
  userSay(text); hideActions(); showTyping();
  await wait(600); hideTyping();

  switch(state.step){
    case 'name':
      state.name=capitalize(text.trim()); state.step='biz';
      aiSay(state.name+'! Хубаво 😄\nКажи ми — какво продаваш?');
      break;

    case 'biz':
      state.biz=text.trim(); state.step='segment';
      aiSay(getSegmentQuestion(state.biz));
      break;

    case 'segment':
      state.segment=text.trim(); state.step='stores';
      searchResult=''; doWebSearch();
      aiSay('Колко магазина имаш?');
      break;

    case 'stores':
      state.stores=text.trim(); state.step='products';
      aiSay('Колко артикула приблизително —\nпод 200, около 500, или повече?');
      break;

    case 'products':
      state.products=text.trim(); state.step='employees';
      aiSay('Имаш ли служители?');
      break;

    case 'employees':
      state.employees=text.trim();
      if(/да|имам|момич|момч|човек|души/i.test(text)){
        aiSay('Те ще могат да питат мен\nвместо да те звънят на теб 😄');
        await wait(1800);
      }
      state.step='wow';
      await showWowMoment();
      break;

    case 'wow_confirm':
      state.step='features';
      await showFeatures();
      break;

    case 'loyalty1':
      state.loyaltyFreq=text.trim(); state.step='loyalty2';
      await wait(300); aiSay('Какво би дал на най-верния си клиент?');
      break;

    case 'loyalty2':
      state.loyaltyReward=text.trim(); state.step='loyalty3';
      await wait(300); aiSay('Имаш ли конкуренция наблизо?');
      break;

    case 'loyalty3':
      state.loyaltyCompetition=text.trim(); state.step='done';
      await showLoyaltyResult();
      break;

    case 'done':
      await finishOnboarding();
      break;
  }
}

// ═══ SEGMENT QUESTION ═══
function getSegmentQuestion(biz){
  if(/дрех|облекло|риз|блуз|панталон|рокл/i.test(biz)) return 'Casual ли е — тениски, дънки, или по-официално — костюми?';
  if(/обувк|маратонк|чехъл|боти/i.test(biz))           return 'Ежедневни обувки или по-скъпи — кожени, официални?';
  if(/бижу|пръстен|гривн|обец|колие/i.test(biz))        return 'Сребро и стомана, или злато и скъпоценни камъни?';
  if(/козмет|парфюм|грим|крем/i.test(biz))              return 'Масов пазар или по-скъпи марки — Chanel, Dior?';
  if(/телефон|електрон|аксесоар|кабел|калъф/i.test(biz)) return 'Аксесоари и кабели, или и телефони, лаптопи?';
  if(/хран|бакал|хлябъ|месо|плод/i.test(biz))           return 'Обикновен хранителен или bio, premium продукти?';
  if(/строит|инструмент|бои|крепеж/i.test(biz))         return 'Дребен консуматив или и едро, строителни материали?';
  if(/играч|детск/i.test(biz))                           return 'По-евтини играчки или и по-скъпи — конструктори?';
  if(/цвет|букет|сакс/i.test(biz))                      return 'Рязан цвят и букети, или и саксийни композиции?';
  if(/авто|кола|гума|масло/i.test(biz))                 return 'Консумативи — масла, филтри, или и по-скъпи части?';
  return 'Продаваш ли по-евтини артикули или по-скъпи, качествени стоки?';
}

// ═══ WEB SEARCH ═══
function doWebSearch(){
  searchDone = false;
  fetch('ai-helper.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'web_search',query:state.biz+' '+state.segment+' small retailer dead stock loss monthly EU average'})})
    .then(function(r){return r.json();})
    .then(function(d){searchResult=d.result||'няма данни'; searchDone=true;})
    .catch(function(){searchResult=''; searchDone=true;});
}

// ═══ WOW MOMENT ═══
async function showWowMoment(){
  showTyping(); searchInd.classList.add('show');
  var waited=0;
  while(!searchDone && waited<6000){ await wait(300); waited+=300; }
  searchInd.classList.remove('show'); hideTyping();

  try{
    showTyping();
    var controller = new AbortController();
    var timeoutId = setTimeout(function(){ controller.abort(); }, 12000);

    var r=await fetch('ai-helper.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'wow',prompt:buildWowPrompt()}),
      signal: controller.signal
    });
    clearTimeout(timeoutId);

    var d=await r.json(); hideTyping();
    if(d.messages && Array.isArray(d.messages)){
      for(var i=0;i<d.messages.length;i++){ await wait(i===0?300:1400); aiSay(d.messages[i],true); scrollBottom(); }
    } else {
      aiSay('Магазини като твоя губят средно €200-500 на месец.\nАз следя и те спирам навреме.',true);
    }
  }catch(e){
    hideTyping();
    aiSay(state.name+', магазини като твоя губят средно €200-500 на месец.\nАз следя всичко и те спирам преди да е станало.',true);
  }

  await wait(1600); state.step='wow_confirm';
  aiSay('Продължаваме? 🚀');
  showActions([{label:'Да, напред!',val:'да',primary:true}]);
}

function buildWowPrompt(){
  return 'Ти си AI асистент на RunMyStore.ai провеждащ онбординг.\nГовориш като топъл приятел търговец — не като робот.\nРазговорен български.\n\nДАННИ:\n- Име: '+state.name+'\n- Бизнес: '+state.biz+'\n- Сегмент: '+state.segment+'\n- Магазини: '+state.stores+'\n- Артикули: '+state.products+'\n- Служители: '+state.employees+'\n- Пазарни данни: '+(searchResult||'няма — използвай консервативни диапазони')+'\n\nГЕНЕРИРАЙ ТОЧНО 5 СЪОБЩЕНИЯ като JSON:\n{"messages":["msg1","msg2","msg3","msg4","msg5"]}\n\nСТРУКТУРА 1-4:\n[Проблем за ТОЗИ бизнес и сегмент]\n→ [Как RunMyStore.ai го решава]\nЗагуба: €X-Y на период\n\nСЪОБЩЕНИЕ 5:\n"'+state.name+', само тези 4 проблема ти струват ~€X на година. RunMyStore.ai е €588 на година. Разликата — €X — остава в джоба ти."\n\nПРАВИЛА:\n- Цифри реалистични за ТОЗИ сегмент\n- Скъп бизнес→по-големи суми\n- Макс 4 реда на съобщение\n- Никога Shopify, Facebook, Еконт\nВЪРНИ САМО JSON.';
}

// ═══ FEATURES ═══
async function showFeatures(){
  await wait(400);
  aiSay('Ето с какво ще работим заедно:\n\n📦 Следя склада — кое върви, кое стои, кое свършва\n\n🔔 Будя те навреме — преди да е свършила стоката\n\n📊 Казвам ти печалбата за деня — без да броиш нищо\n\n🎤 Управляваш всичко с глас — питаш мен, не търсиш в менюта\n\n👥 Служителите питат мен вместо да те звънят\n\n🎁 Лоялна карта за клиентите — безплатна завинаги\n\n30 дни пробваш всичко — безплатно, без карта.\nСлед това — €49 на месец.');
  await wait(1800); state.step='loyalty1';
  aiSay('Сега правим лоялната програма.\nТри бързи въпроса.\n\nКолко често идват редовните ти клиенти?');
}

// ═══ LOYALTY ═══
async function showLoyaltyResult(){
  showTyping(); await wait(1200); hideTyping();
  var isFrequent=/всеки|ден|седм|редовно/i.test(state.loyaltyFreq);
  var hasComp=/да|има|конкурент|наблизо/i.test(state.loyaltyCompetition);
  var pts=isFrequent?50:100, eur=isFrequent?3:5;
  var compLine=hasComp?'\nСрещу конкуренцията — лоялността е оръжието ти 💪':'';
  aiSay('Готово! Правя ти \''+state.name+' CLUB\':\n\n• €1 похарчен = 1 точка\n• '+pts+' точки = €'+eur+' отстъпка\n• Рожден ден = специален подарък\n• Лоялната карта е безплатна завинаги'+compLine+'\n\nХаресва ли ти?');
  showActions([{label:'Да, страхотно!',val:'готово',primary:true},{label:'Промени нещо',val:'промени'}]);
}

// ═══ FINISH ═══
async function finishOnboarding(){
  showTyping();
  try{
    await fetch('onboarding-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:state.name,biz:state.biz,segment:state.segment,stores:state.stores,products:state.products,employees:state.employees,loyalty_freq:state.loyaltyFreq,loyalty_reward:state.loyaltyReward,loyalty_competition:state.loyaltyCompetition})});
  }catch(e){}
  hideTyping();
  aiSay(state.name+', всичко е готово! 🚀\n\n30 дни пробваш безплатно — без карта.\nЛоялната карта остава безплатна завинаги.\nСлед това — свързваме складовия модул към твоя бизнес.\n\nАз съм тук всеки ден.\nПитай каквото искаш, по всяко време.');
  await wait(2000);
  window.location.href='chat.php';
}
</script>
</body>
</html>
