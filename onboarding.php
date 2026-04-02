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
/* ═══ PREMIUM CHAMPAGNE DESIGN ═══ */
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;margin:0;padding:0}
body{background:#EDE8DC;color:#3f3b36;font-family:'Montserrat',sans-serif;height:100dvh;max-height:100dvh;display:flex;flex-direction:column;overflow:hidden}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 50%,rgba(218,165,32,.04) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(197,160,89,.05) 0%,transparent 45%),radial-gradient(circle at 50% 85%,rgba(230,194,122,.06) 0%,transparent 40%);pointer-events:none;z-index:0}

.hdr{position:relative;z-index:50;background:rgba(252,249,242,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(230,213,184,.6);padding:14px 16px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.brand{font-size:18px;font-weight:900;background:linear-gradient(to right,#A67C00,#E6C27A,#A67C00);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}

.chat-area{background-color:#ffffff;border:1px solid #F5EBE0;box-shadow:0 8px 30px rgba(166,124,0,.03);border-radius:20px;margin:12px 12px 0 12px;flex:1 1 0;min-height:0;overflow-y:auto;overflow-x:hidden;padding:20px 16px 8px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.chat-area::-webkit-scrollbar{display:none}

.msg-group{margin-bottom:16px;animation:fadeUp .3s ease both}
.msg-meta{font-size:11px;color:#9ca3af;margin-bottom:6px;display:flex;align-items:center;gap:6px;font-weight:500}
.ai-ava{width:28px;height:28px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#D4AF37,#F3E5AB);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(212,175,55,.3)}
.ai-ava-bars{display:flex;gap:2px;align-items:center;height:12px}
.ai-ava-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.ai-ava-bar:nth-child(1){height:5px;animation-delay:0s}
.ai-ava-bar:nth-child(2){height:9px;animation-delay:.15s}
.ai-ava-bar:nth-child(3){height:12px;animation-delay:.3s}
.ai-ava-bar:nth-child(4){height:7px;animation-delay:.45s}

.msg{max-width:88%;padding:13px 16px;font-size:14px;line-height:1.55;word-break:break-word}
.msg.ai{background:#FCF9F2;border:1px solid #E6D5B8;color:#292524;border-radius:4px 18px 18px 18px}
.msg.user{background:linear-gradient(135deg,#C5A059,#E6C27A);color:#ffffff;border-radius:18px 18px 4px 18px;margin-left:auto;box-shadow:0 4px 12px rgba(197,160,89,.2)}
.msg.wow{border-color:#D4AF37;background:#FFFDF8;color:#574200;font-weight:500}

.typing-wrap{display:none;padding:12px 16px;background:#FCF9F2;border:1px solid #E6D5B8;border-radius:4px 18px 18px 18px;width:fit-content;margin-bottom:14px}
.typing-dots{display:flex;gap:5px;align-items:center}
.dot{width:7px;height:7px;border-radius:50%;background:#D4AF37;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}
.dot:nth-child(3){animation-delay:.4s}

.search-indicator{display:none;padding:10px 16px;margin-bottom:12px;background:#FCF9F2;border:1px solid #E6D5B8;border-radius:12px;font-size:12px;color:#A67C00;font-weight:600}
.search-indicator.show{display:flex;align-items:center;gap:8px}
.search-dot{width:7px;height:7px;border-radius:50%;background:#C5A059;animation:bounce 1s infinite}

.action-wrap{padding:0 12px 12px;flex-shrink:0;position:relative;z-index:1}
.action-row{display:flex;gap:10px;justify-content:center}
.action-btn{flex:1;max-width:180px;padding:12px 16px;border-radius:16px;font-size:13px;font-weight:700;border:1px solid #E6D5B8;color:#574200;background:#ffffff;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.02)}
.action-btn:active{background:#FCF9F2;transform:scale(.97)}
.action-btn.primary{background:linear-gradient(to bottom,#D4AF37,#C5A059);border-color:transparent;color:#ffffff;box-shadow:0 6px 16px rgba(212,175,55,.3)}

.input-area{background:rgba(255,255,255,.9);backdrop-filter:blur(20px);padding:12px 16px 20px;flex-shrink:0;position:relative;z-index:1}
.input-row{display:flex;gap:10px;align-items:center}
.text-input{flex:1;background:#FCF9F2;border:1px solid #E6D5B8;border-radius:24px;color:#292524;font-size:14px;padding:12px 18px;font-family:'Montserrat',sans-serif;outline:none;resize:none;max-height:80px;line-height:1.4;transition:all .2s;box-shadow:inset 0 2px 4px rgba(0,0,0,.01)}
.text-input:focus{border-color:#C5A059;background:#ffffff;box-shadow:0 0 0 3px rgba(197,160,89,.1)}
.text-input::placeholder{color:#A8A29E}

.voice-wrap{position:relative;flex-shrink:0;width:52px;height:52px;cursor:pointer}
.voice-ring{position:absolute;border-radius:50%;border:1px solid rgba(212,175,55,.3);animation:waveOut 2s ease-out infinite;pointer-events:none}
.voice-ring:nth-child(1){inset:-4px;animation-delay:0s}
.voice-ring:nth-child(2){inset:-9px;animation-delay:.55s}
.voice-ring:nth-child(3){inset:-14px;animation-delay:1.1s}
.voice-inner{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#D4AF37,#E6C27A);display:flex;align-items:center;justify-content:center;position:relative;z-index:1;box-shadow:0 4px 14px rgba(212,175,55,.4);transition:all .2s}
.voice-bars{display:flex;gap:3px;align-items:center;height:20px}
.voice-bar{width:3px;border-radius:2px;background:#fff;animation:barDance 1s ease-in-out infinite}
.voice-bar:nth-child(1){height:8px;animation-delay:0s}
.voice-bar:nth-child(2){height:16px;animation-delay:.15s}
.voice-bar:nth-child(3){height:20px;animation-delay:.3s}
.voice-bar:nth-child(4){height:12px;animation-delay:.45s}
.voice-bar:nth-child(5){height:8px;animation-delay:.6s}
.voice-wrap.recording .voice-inner{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 18px rgba(239,68,68,.5)}
.voice-wrap.recording .voice-ring{border-color:rgba(239,68,68,.3)}

.send-btn{width:46px;height:46px;border-radius:50%;background:#FCF9F2;border:1px solid #E6D5B8;color:#A67C00;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-btn:active{background:#F3E5AB;transform:scale(.92)}
.send-btn:disabled{opacity:.4;cursor:default;background:#F5EBE0;color:#A8A29E;border-color:transparent}

.rec-overlay{position:fixed;inset:0;background:rgba(255,255,255,.9);z-index:400;display:none;flex-direction:column;align-items:center;justify-content:center;backdrop-filter:blur(14px)}
.rec-overlay.show{display:flex}
.rec-circle{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;margin-bottom:24px;animation:recPulse 1s ease-out infinite;box-shadow:0 10px 30px rgba(239,68,68,.4)}
.rec-wave-bars{display:flex;gap:5px;align-items:center;height:32px}
.rec-bar{width:5px;border-radius:3px;background:#fff;animation:barDance .7s ease-in-out infinite}
.rec-bar:nth-child(1){height:12px;animation-delay:0s}
.rec-bar:nth-child(2){height:24px;animation-delay:.1s}
.rec-bar:nth-child(3){height:32px;animation-delay:.2s}
.rec-bar:nth-child(4){height:20px;animation-delay:.3s}
.rec-bar:nth-child(5){height:12px;animation-delay:.4s}
.rec-title{font-size:22px;font-weight:800;color:#292524;margin-bottom:6px}
.rec-sub{font-size:15px;color:#78716C;margin-bottom:32px}
.rec-stop{padding:14px 34px;background:#fee2e2;border:1px solid #fca5a5;border-radius:24px;color:#ef4444;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 4px 12px rgba(239,68,68,.15)}

.mic-screen{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:32px 24px;text-align:center;position:relative;z-index:1}
.mic-screen.show{display:flex}
.mic-icon-wrap{width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,#D4AF37,#E6C27A);display:flex;align-items:center;justify-content:center;margin-bottom:28px;box-shadow:0 10px 30px rgba(212,175,55,.3);animation:recPulse 2s ease-out infinite}
.mic-title{font-size:24px;font-weight:800;color:#292524;margin-bottom:12px}
.mic-sub{font-size:15px;color:#574200;line-height:1.65;margin-bottom:32px;max-width:300px}
.mic-btn{padding:16px 36px;background:linear-gradient(to bottom,#D4AF37,#C5A059);border:none;border-radius:20px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 8px 24px rgba(212,175,55,.35);transition:transform .2s}
.mic-btn:active{transform:scale(0.96)}
.mic-skip{margin-top:20px;font-size:14px;color:#78716C;cursor:pointer;text-decoration:underline;padding:8px 16px;display:inline-block;font-weight:500}

@keyframes gShift{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
@keyframes waveOut{0%{transform:scale(1);opacity:.5}100%{transform:scale(1.9);opacity:0}}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(212,175,55,.3)}70%{box-shadow:0 0 0 20px rgba(212,175,55,0)}100%{box-shadow:0 0 0 0 rgba(212,175,55,0)}}
</style>
</head>
<body>

<div class="hdr"><div class="brand">RunMyStore.ai</div></div>

<div class="mic-screen show" id="micScreen">
  <div class="mic-icon-wrap">
    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
    </svg>
  </div>
  <div class="mic-title">Здравей! Аз съм твоят AI асистент 🙌</div>
  <div class="mic-sub">Ще работим заедно всеки ден.<br>Разреши ми да те чувам, за да си говорим вместо да пишеш.</div>
  <button class="mic-btn" id="micBtn">Разреши микрофона</button>
  <div class="mic-skip" id="micSkip">Ще пиша засега</div>
</div>

<div id="chatInterface" style="display:none;flex:1 1 0;min-height:0;flex-direction:column;overflow:hidden">
  <div class="chat-area" id="chatArea">
    <div class="typing-wrap" id="typing">
      <div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
    </div>
  </div>
  <div class="search-indicator" id="searchIndicator">
    <div class="search-dot"></div>
    <span>Правя пазарен анализ за твоя тип бизнес...</span>
  </div>
  <div class="action-wrap" id="actionWrap" style="display:none">
    <div class="action-row" id="actionRow"></div>
  </div>
  <div class="input-area">
    <div class="input-row">
      <textarea class="text-input" id="chatInput" placeholder="Пиши тук..." rows="1"
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
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
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
  <div class="rec-title">Слушам те...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<script>
// ═══ UTILS ═══
function wait(ms) { return new Promise(function(r){ setTimeout(r, ms); }); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function autoResize(el) { el.style.height=''; el.style.height=Math.min(el.scrollHeight,80)+'px'; }
function capitalize(s) { return s.split(' ').map(function(w){ return w.charAt(0).toUpperCase()+w.slice(1).toLowerCase(); }).join(' '); }
function showToast(msg) {
  var t=document.getElementById('_toast');
  if(!t){t=document.createElement('div');t.id='_toast';t.style.cssText='position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#292524;color:#fff;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:600;z-index:500;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;font-family:Montserrat,sans-serif;box-shadow:0 10px 25px rgba(0,0,0,0.1)';document.body.appendChild(t);}
  t.textContent=msg; t.style.opacity='1'; setTimeout(function(){t.style.opacity='0';},4000);
}

// ═══ AI FETCH — без AbortController, с реален timeout ═══
async function aiFetch(body) {
  // PHP cURL таймаутът е 30сек — match-ваме го тук
  return fetch('ai-helper.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
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
  var btn = this; btn.disabled = true; btn.textContent = 'Изчакай...';
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia({audio:true})
      .then(function(stream){ stream.getTracks().forEach(function(t){t.stop();}); state.micGranted = true; startChat(); })
      .catch(function(err){ state.micGranted = false; startChat(); });
  } else { state.micGranted = false; startChat(); }
});
document.getElementById('micSkip').addEventListener('click', function(){ state.micGranted = false; startChat(); });

function startChat() {
  document.getElementById('micScreen').classList.remove('show');
  var ci = document.getElementById('chatInterface');
  ci.style.display = 'flex'; ci.style.flex = '1 1 0'; ci.style.minHeight = '0';
  setTimeout(function(){ aiSay('Хей! Аз съм твоят нов бизнес асистент 🙌\nЩе работим заедно всеки ден.\nКак да те викам?'); }, 400);
}

// ═══ CHAT HELPERS ═══
function scrollBottom(){ chatArea.scrollTop=chatArea.scrollHeight; }

function aiSay(text, isWow){
  hideActions();
  var g=document.createElement('div'); g.className='msg-group';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div>RunMyStore.ai Асистент</div><div class="msg ai'+(isWow?' wow':'')+'">'+esc(text).replace(/\n/g,'<br>')+'</div>';
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
  actionWrap.style.display='block'; scrollBottom();
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
  voiceRec.onerror=function(e){ stopVoice(); if(e.error==='no-speech') showToast('Не чух нищо — опитай пак'); else if(e.error==='not-allowed') showToast('Временно блокиран! Ще работи перфектно, когато имаме HTTPS.'); else showToast('Грешка: '+e.error); };
  voiceRec.onend=function(){if(isRecording)stopVoice();};
  try{voiceRec.start();}catch(e){stopVoice();}
}

function stopVoice(){
  isRecording=false; voiceWrap.classList.remove('recording'); recOverlay.classList.remove('show');
  if(voiceRec){try{voiceRec.stop();}catch(e){} voiceRec=null;}
}

function sendText(){
  var input=document.getElementById('chatInput'); var text=input.value.trim(); if(!text)return;
  input.value=''; input.style.height=''; document.getElementById('btnSend').disabled=true; processInput(text);
}
function handleAction(val){ processInput(val); }

// ═══ BULLETPROOF FLOW ═══
async function processInput(text){
  userSay(text); hideActions(); showTyping(); await wait(600); hideTyping();

  try {
    switch(state.step){
      case 'name':
        state.name=capitalize(text.trim()); state.step='biz';
        aiSay(state.name+', приятно ми е! 😄\nКажи ми — какво точно продаваш в твоя обект?');
        break;

      case 'biz':
        state.biz=text.trim(); state.step='segment';
        showTyping();
        try {
          // БЕЗ AbortController — чакаме колкото трябва (PHP timeout = 30сек)
          var r = await aiFetch({action:'analyze_biz_segment', biz: state.biz});
          var d = await r.json();
          hideTyping();
          var question = (d && d.question && d.question.trim()) 
            ? d.question 
            : 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?';
          aiSay(question);
        } catch(e) {
          console.error('analyze_biz_segment error:', e);
          hideTyping();
          aiSay('Разбрах! Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?');
        }
        break;

      case 'segment':
        state.segment=text.trim(); state.step='stores';
        searchResult=''; doWebSearch(); // Скрит старт на търсене
        aiSay('Разбрах те отлично. Колко физически обекта/магазина имаш в момента?');
        break;

      case 'stores':
        state.stores=text.trim(); state.step='products';
        aiSay('Колко артикула приблизително поддържаш —\nпод 200, около 500, или хиляди?');
        break;

      case 'products':
        state.products=text.trim(); state.step='employees';
        aiSay('Имаш ли служители, които работят на касата?');
        break;

      case 'employees':
        state.employees=text.trim();
        if(/да|имам|момич|момч|човек|души/i.test(text)){
          aiSay('Те ще могат да питат мен за складови наличности\nвместо да ти звънят на теб постоянно 😄');
          await wait(2200);
        }
        state.step='wow';
        await showWowMoment();
        break;

      case 'wow_confirm':
        state.step='features'; await showFeatures(); break;

      case 'loyalty1':
        state.loyaltyFreq=text.trim(); state.step='loyalty2';
        await wait(300); aiSay('А какво би дал като бонус или отстъпка на най-верния си клиент?');
        break;

      case 'loyalty2':
        state.loyaltyReward=text.trim(); state.step='loyalty3';
        await wait(300); aiSay('Имаш ли силна конкуренция наблизо до обекта ти?');
        break;

      case 'loyalty3':
        state.loyaltyCompetition=text.trim(); state.step='done';
        await showLoyaltyResult();
        break;

      case 'done':
        await finishOnboarding(); break;
    }
  } catch(err) { console.error('processInput error:', err); }
}

// ═══ WEB SEARCH ═══
function doWebSearch(){
  searchDone = false;
  // Web search може да е бавно — без abort, просто изчакваме
  aiFetch({action:'web_search', query: state.biz+' '+state.segment+' retail dead stock loss financial average EU'})
    .then(function(r){ return r.text(); })
    .then(function(t){ 
      try { var d=JSON.parse(t); searchResult = d.result || ''; } catch(e){ searchResult=''; }
      searchDone=true; 
    })
    .catch(function(){ searchResult=''; searchDone=true; });
}

// ═══ WOW MOMENT ═══
async function showWowMoment(){
  showTyping(); searchInd.classList.add('show'); scrollBottom();
  
  // Изчакваме web search — до 20 сек (web_search е бавен)
  var checks = 60;
  while(!searchDone && checks > 0){ await wait(350); checks--; }
  searchInd.classList.remove('show');

  var fallbackExecuted = false;
  var executeFallback = async function() {
    if(fallbackExecuted) return; fallbackExecuted = true;
    hideTyping(); searchInd.classList.remove('show');
    var amt = /скъп|марков|костюм|злат|техник|мебел|луксоз/i.test(state.biz+' '+state.segment) ? '€1500 - €4000' : '€200 - €500';
    aiSay(state.name+', в твоята сфера се губят средно '+amt+' на месец от залежала стока и липси.\nRunMyStore.ai следи всичко автоматично и те предупреждава преди да е станало.', true);
    await wait(3500); state.step='wow_confirm';
    aiSay('Искаш ли да ти покажа какво още правим? 🚀');
    showActions([{label:'Да, покажи ми!',val:'да',primary:true}]);
  };

  // Safety timer — 35 сек (дава достатъчно време на Claude)
  var safetyTimer = setTimeout(executeFallback, 35000);

  try {
    showTyping();
    // БЕЗ AbortController — Claude отговаря за 5-15 сек
    var r = await aiFetch({action:'wow', prompt: buildWowPrompt()});
    var txt = await r.text();

    if(fallbackExecuted) return;
    
    var d;
    try { d = JSON.parse(txt); } catch(e) { throw new Error('JSON parse failed: '+txt.substring(0,200)); }
    
    if(d && d.messages && Array.isArray(d.messages) && d.messages.length > 0){
      clearTimeout(safetyTimer); fallbackExecuted = true; hideTyping();
      for(var i=0;i<d.messages.length;i++){ 
        await wait(i===0 ? 500 : 3500); 
        aiSay(d.messages[i], true); 
      }
      await wait(3500); state.step='wow_confirm';
      aiSay('Искаш ли да ти покажа какво още правим? 🚀');
      showActions([{label:'Да, покажи ми!',val:'да',primary:true}]);
    } else {
      throw new Error('No valid messages in response');
    }
  } catch(e) { 
    console.error('wow error:', e);
    executeFallback(); 
  }
}

function buildWowPrompt(){
  return 'Ти си AI асистент на RunMyStore.ai. Говориш като умен приятел търговец — конкретно, без технически термини. Разбираш разговорен български и поправяш правописни грешки наум.\n' +
         'ДАННИ ЗА КЛИЕНТА:\nИме: '+state.name+'\nБизнес: '+state.biz+' (поправи грешките логически)\nСегмент: '+state.segment+'\nМагазини: '+state.stores+'\nПазарни данни: '+(searchResult||'няма данни')+'\n\n' +
         'ИНСТРУКЦИЯ: ГЕНЕРИРАЙ ТОЧНО 5 СЪОБЩЕНИЯ (JSON формат: {"messages":["..."]}).\n' +
         'ИЗПОЛЗВАЙ 4 МАРКЕТИНГ СТРАТЕГИИ с реалистични суми в евро (ако стоката е скъпа - хиляди, ако е евтина - стотици):\n' +
         '1. Zombie Stock (блокирани пари в залежала стока).\n' +
         '2. Size-Curve (загуби от грешно заредени размери/цветове).\n' +
         '3. Lost Revenue (загуби от изчерпване на търсена стока - кажи, че ги ПРЕДУПРЕЖДАВАШ навреме, никога не използвай думата "будя").\n' +
         '4. Basket Analysis (изпуснати ползи от непредлагане на свързани продукти - Upsell).\n' +
         '5. Обобщение: "Само тези 4 проблема ти струват ~€[СУМА] на година. RunMyStore.ai е €588 на година. Разликата остава в джоба ти."\n' +
         'Бъди кратък (макс 3 изречения на съобщение). ВЪРНИ САМО JSON.';
}

// ═══ FEATURES ═══
async function showFeatures(){
  await wait(400);
  aiSay('Ето как RunMyStore.ai ще ти помага всеки ден:\n\n📦 Следя склада — кое върви, кое стои, кое свършва\n\n🔔 Предупреждавам те навреме — преди стоката да се е изчерпала\n\n📊 Казвам ти печалбата за деня — без да събираш хартийки\n\n🎤 Управляваш всичко с глас — буквално си говориш с мен\n\n🎁 Получаваш дигитална лоялна карта за клиентите си\n\n30 дни пробваш всичко напълно безплатно.\nСлед това е едва €49 на месец.');
  await wait(4500); state.step='loyalty1'; 
  aiSay('Споменах лоялна програма. Нека я настроим за 10 секунди.\n\nКолко често идват редовните ти клиенти при теб?');
}

// ═══ LOYALTY ═══
async function showLoyaltyResult(){
  showTyping(); await wait(1200); hideTyping();
  var isFrequent=/всеки|ден|седм|редовно/i.test(state.loyaltyFreq);
  var hasComp=/да|има|конкурент|наблизо/i.test(state.loyaltyCompetition);
  var pts=isFrequent?50:100, eur=isFrequent?3:5;
  var compLine=hasComp?'\nСрещу конкуренцията около теб — това е най-силното ти оръжие 💪':'';
  aiSay('Готово! Току-що ти създадох \''+state.name+' CLUB\':\n\n• €1 похарчен = 1 точка\n• '+pts+' точки = €'+eur+' постоянна отстъпка\n• Персонален подарък за всеки рожден ден\n• Този модул е безплатен завинаги'+compLine+'\n\nДобре ли звучи?');
  showActions([{label:'Да, супер е!',val:'готово',primary:true},{label:'Искам друга',val:'промени'}]);
}

// ═══ FINISH ═══
async function finishOnboarding(){
  showTyping();
  try{
    await fetch('onboarding-save.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({name:state.name,biz:state.biz,segment:state.segment,stores:state.stores,products:state.products,employees:state.employees,loyalty_freq:state.loyaltyFreq,loyalty_reward:state.loyaltyReward,loyalty_competition:state.loyaltyCompetition})
    });
  }catch(e){ console.error('save error:', e); }
  hideTyping();
  aiSay(state.name+', всичко е готово! 🚀\n\n30 дни пробваш безплатно.\nЛоялната карта ти остава безплатна завинаги.\nСледващата стъпка е да качим ценовата ти листа.\n\nАз съм тук. Питай ме каквото искаш.');
  await wait(3500); window.location.href='chat.php';
}
</script>
</body>
</html>
