<?php
/**
 * onboarding.php — AI Conversational Onboarding
 * 
 * ЛОГИКА: Потребителят минава през 6 стъпки чрез чат:
 *   1. Име → 2. Тип бизнес → 3. Брой обекти → 4. WOW анализ на загуби →
 *   5. Какво правим (4 суперсили) → 6. Лоялна програма (AI генерира 3 варианта)
 * 
 * ДИЗАЙН: Cruip Open Pro Dark (bg-gray-950, indigo градиенти)
 * П0: AI First — микрофон екран при старт
 * П8: Cruip Dark тема с page-illustration.svg + blurred-shape.svg
 * П12: style.css + aos.css
 */
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
<title>Настройка — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
/* ── FULLSCREEN CHAT LAYOUT ── */
html,body{height:100dvh;overflow:hidden}
body{display:flex;flex-direction:column}
.ob-wrap{flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;position:relative}

/* ── HEADER ── */
.ob-hdr{flex-shrink:0;padding:14px 16px;display:flex;align-items:center;justify-content:center;position:relative;z-index:50;
  background:rgba(17,24,39,.85);backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(99,102,241,.15)}
.ob-brand{font-size:18px;font-weight:800;
  background:linear-gradient(to right,var(--color-gray-200),var(--color-indigo-200),var(--color-gray-50),var(--color-indigo-300),var(--color-gray-200));
  background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;
  animation:gradient 6s linear infinite;font-family:var(--font-nacelle,ui-sans-serif,system-ui,sans-serif)}

/* ── CHAT AREA ── */
.ob-chat{flex:1;overflow-y:auto;overflow-x:hidden;padding:20px 16px 8px;display:flex;flex-direction:column;
  -webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.ob-chat::-webkit-scrollbar{display:none}

/* ── MESSAGES ── */
.msg-g{margin-bottom:14px;animation:fadeUp .3s ease both}
.msg-meta{font-size:11px;color:rgba(165,180,252,.5);margin-bottom:5px;display:flex;align-items:center;gap:6px;font-weight:500}
.msg-meta.right{justify-content:flex-end}
.ai-ava{width:26px;height:26px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--color-indigo-500),var(--color-indigo-400));
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 8px rgba(99,102,241,.3)}
.ai-bars{display:flex;gap:2px;align-items:center;height:11px}
.ai-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.ai-bar:nth-child(1){height:4px}.ai-bar:nth-child(2){height:8px;animation-delay:.15s}
.ai-bar:nth-child(3){height:11px;animation-delay:.3s}.ai-bar:nth-child(4){height:6px;animation-delay:.45s}

.msg{max-width:88%;padding:12px 16px;font-size:14px;line-height:1.55;word-break:break-word}
.msg.ai{background:rgba(30,27,75,.4);border:1px solid rgba(99,102,241,.2);color:var(--color-gray-200);border-radius:4px 16px 16px 16px}
.msg.user{background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));
  color:#fff;border-radius:16px 16px 4px 16px;margin-left:auto;box-shadow:0 4px 12px rgba(99,102,241,.25)}
.msg.wow{border-color:rgba(99,102,241,.4);background:rgba(30,27,75,.6)}
.msg b,.msg strong{color:var(--color-indigo-300);font-weight:700}

/* ── TYPING ── */
.typing-w{display:none;padding:10px 16px;background:rgba(30,27,75,.4);border:1px solid rgba(99,102,241,.15);
  border-radius:4px 16px 16px 16px;width:fit-content;margin-bottom:12px}
.dots{display:flex;gap:5px;align-items:center}
.dot{width:6px;height:6px;border-radius:50%;background:var(--color-indigo-400);animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}

/* ── SEARCH INDICATOR ── */
.search-ind{display:none;padding:10px 16px;margin-bottom:10px;background:rgba(30,27,75,.5);
  border:1px solid rgba(99,102,241,.2);border-radius:12px;font-size:12px;color:var(--color-indigo-300);font-weight:600}
.search-ind.show{display:flex;align-items:center;gap:8px}
.s-dot{width:6px;height:6px;border-radius:50%;background:var(--color-indigo-400);animation:bounce 1s infinite}

/* ── ACTION BUTTONS ── */
.act-wrap{padding:8px 12px 12px;flex-shrink:0;position:relative;z-index:1}
.act-row{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.act-btn{flex:1;min-width:110px;max-width:250px;padding:11px 14px;border-radius:14px;font-size:13px;font-weight:700;
  border:1px solid rgba(99,102,241,.25);color:var(--color-indigo-200);
  background:rgba(30,27,75,.4);cursor:pointer;font-family:inherit;transition:all .2s;text-align:center}
.act-btn:active{transform:scale(.96)}
.act-btn.primary{background:linear-gradient(to top,var(--color-indigo-600),var(--color-indigo-500));
  border-color:transparent;color:#fff;box-shadow:0 4px 14px rgba(99,102,241,.35)}

/* ── LOYALTY CARDS ── */
.loy-cards{display:flex;flex-direction:column;gap:10px;margin-top:6px;width:100%;max-width:88%}
.loy-card{background:rgba(30,27,75,.35);border:1.5px solid rgba(99,102,241,.2);border-radius:14px;
  padding:13px 16px;cursor:pointer;transition:all .2s;text-align:left;font-family:inherit;width:100%;color:var(--color-gray-200)}
.loy-card.selected{border-color:var(--color-indigo-400);background:rgba(30,27,75,.6);box-shadow:0 4px 14px rgba(99,102,241,.2)}
.loy-emoji{font-size:20px;margin-bottom:4px;display:block}
.loy-title{font-size:13px;font-weight:800;color:#fff;margin-bottom:3px}
.loy-desc{font-size:12px;color:rgba(165,180,252,.6);line-height:1.5}
.loy-custom{margin-top:4px;padding:11px;border-radius:14px;font-size:13px;font-weight:700;
  border:1.5px dashed rgba(99,102,241,.3);color:var(--color-indigo-300);
  background:transparent;cursor:pointer;font-family:inherit;width:100%;text-align:center}

/* ── INPUT AREA ── */
.inp-area{background:rgba(17,24,39,.85);backdrop-filter:blur(16px);padding:12px 16px 20px;
  flex-shrink:0;position:relative;z-index:1;border-top:1px solid rgba(99,102,241,.1)}
.inp-row{display:flex;gap:10px;align-items:center}
.txt-inp{flex:1;background:rgba(17,24,39,.6);border:1px solid rgba(99,102,241,.2);border-radius:24px;
  color:var(--color-gray-200);font-size:14px;padding:12px 18px;font-family:inherit;outline:none;
  resize:none;max-height:80px;line-height:1.4;transition:all .2s}
.txt-inp:focus{border-color:var(--color-indigo-500);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.txt-inp::placeholder{color:rgba(165,180,252,.35)}

/* ── VOICE BUTTON ── */
.vc-wrap{position:relative;flex-shrink:0;width:48px;height:48px;cursor:pointer}
.vc-ring{position:absolute;border-radius:50%;border:1px solid rgba(99,102,241,.2);
  animation:waveOut 2s ease-out infinite;pointer-events:none}
.vc-ring:nth-child(1){inset:-4px}.vc-ring:nth-child(2){inset:-9px;animation-delay:.55s}
.vc-inner{width:48px;height:48px;border-radius:50%;
  background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));
  display:flex;align-items:center;justify-content:center;position:relative;z-index:1;
  box-shadow:0 4px 14px rgba(99,102,241,.4)}
.vc-vbars{display:flex;gap:3px;align-items:center;height:18px}
.vc-vbar{width:3px;border-radius:2px;background:#fff;animation:barDance 1s ease-in-out infinite}
.vc-vbar:nth-child(1){height:7px}.vc-vbar:nth-child(2){height:14px;animation-delay:.15s}
.vc-vbar:nth-child(3){height:18px;animation-delay:.3s}.vc-vbar:nth-child(4){height:10px;animation-delay:.45s}
.vc-wrap.rec .vc-inner{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 18px rgba(239,68,68,.5)}
.send-b{width:44px;height:44px;border-radius:50%;background:rgba(30,27,75,.4);
  border:1px solid rgba(99,102,241,.25);color:var(--color-indigo-300);cursor:pointer;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-b:active{background:var(--color-indigo-600);transform:scale(.92)}
.send-b:disabled{opacity:.3;cursor:default}

/* ── MIC SCREEN ── */
.mic-scr{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;
  padding:32px 24px;text-align:center;position:relative;z-index:1}
.mic-scr.show{display:flex}
.mic-ico{width:96px;height:96px;border-radius:50%;
  background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));
  display:flex;align-items:center;justify-content:center;margin-bottom:28px;
  box-shadow:0 10px 30px rgba(99,102,241,.3);animation:recPulse 2s ease-out infinite}
.mic-t{font-size:22px;font-weight:800;color:#fff;margin-bottom:12px;font-family:var(--font-nacelle,ui-sans-serif,system-ui,sans-serif)}
.mic-s{font-size:15px;color:var(--color-indigo-200);line-height:1.65;margin-bottom:32px;max-width:300px;opacity:.7}
.mic-go{padding:16px 36px;border:none;border-radius:20px;color:#fff;font-size:16px;font-weight:700;
  cursor:pointer;font-family:inherit;
  background:linear-gradient(to top,var(--color-indigo-600),var(--color-indigo-500));
  box-shadow:0 8px 24px rgba(99,102,241,.35)}
.mic-skip{margin-top:20px;font-size:14px;color:rgba(165,180,252,.5);cursor:pointer;
  text-decoration:underline;padding:8px 16px;display:inline-block}

/* ── RECORD OVERLAY ── */
.rec-ov{position:fixed;inset:0;background:rgba(3,7,18,.92);z-index:400;display:none;
  flex-direction:column;align-items:center;justify-content:center;backdrop-filter:blur(14px)}
.rec-ov.show{display:flex}
.rec-circle{width:110px;height:110px;border-radius:50%;
  background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;
  margin-bottom:24px;animation:recPulse 1s ease-out infinite;box-shadow:0 10px 30px rgba(239,68,68,.4)}
.rec-bars{display:flex;gap:5px;align-items:center;height:32px}
.rec-bar{width:5px;border-radius:3px;background:#fff;animation:barDance .7s ease-in-out infinite}
.rec-bar:nth-child(1){height:12px}.rec-bar:nth-child(2){height:24px;animation-delay:.1s}
.rec-bar:nth-child(3){height:32px;animation-delay:.2s}.rec-bar:nth-child(4){height:20px;animation-delay:.3s}
.rec-bar:nth-child(5){height:12px;animation-delay:.4s}
.rec-t{font-size:22px;font-weight:800;color:#fff;margin-bottom:6px}
.rec-s{font-size:15px;color:rgba(165,180,252,.6);margin-bottom:32px}
.rec-stop{padding:14px 34px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);
  border-radius:24px;color:#ef4444;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}

/* ── ANIMATIONS ── */
@keyframes gradient{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
@keyframes waveOut{0%{transform:scale(1);opacity:.5}100%{transform:scale(1.9);opacity:0}}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(99,102,241,.3)}70%{box-shadow:0 0 0 20px rgba(99,102,241,0)}100%{box-shadow:0 0 0 0 rgba(99,102,241,0)}}
</style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">

<!-- Background decorations (Cruip pattern) -->
<div class="pointer-events-none absolute left-1/2 top-0 -z-10 -translate-x-1/4" aria-hidden="true">
  <img class="max-w-none" src="./images/page-illustration.svg" width="846" height="594" alt="">
</div>
<div class="pointer-events-none absolute left-1/2 top-[400px] -z-10 -mt-20 -translate-x-full opacity-50" aria-hidden="true">
  <img class="max-w-none" src="./images/blurred-shape-gray.svg" width="760" height="668" alt="">
</div>
<div class="pointer-events-none absolute left-1/2 top-[440px] -z-10 -translate-x-1/3" aria-hidden="true">
  <img class="max-w-none" src="./images/blurred-shape.svg" width="760" height="668" alt="">
</div>

<div class="ob-wrap">
  <!-- Header -->
  <div class="ob-hdr">
    <div class="ob-brand">RunMyStore.ai</div>
  </div>

  <!-- Mic Permission Screen -->
  <div class="mic-scr show" id="micScreen">
    <div class="mic-ico">
      <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
      </svg>
    </div>
    <div class="mic-t">Здравей! Аз съм AI асистентът ти</div>
    <div class="mic-s">Ще работим заедно всеки ден.<br>Разреши ми да те чувам, за да си говорим вместо да пишеш.</div>
    <button class="mic-go" id="micBtn">Разреши микрофона</button>
    <div class="mic-skip" id="micSkip">Ще пиша засега</div>
  </div>

  <!-- Chat Interface (hidden until mic screen dismissed) -->
  <div id="chatUI" style="display:none;flex:1 1 0;min-height:0;flex-direction:column;overflow:hidden">
    <div class="ob-chat" id="chatArea">
      <div class="typing-w" id="typing"><div class="dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
    </div>
    <div class="search-ind" id="searchInd"><div class="s-dot"></div><span>Анализирам данните...</span></div>
    <div class="act-wrap" id="actWrap" style="display:none"><div class="act-row" id="actRow"></div></div>
    <div class="inp-area">
      <div class="inp-row">
        <textarea class="txt-inp" id="chatInput" placeholder="Пиши тук..." rows="1"
          oninput="autoResize(this);document.getElementById('btnSend').disabled=!this.value.trim()"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendText()}"></textarea>
        <div class="vc-wrap" id="voiceWrap" onclick="toggleVoice()">
          <div class="vc-ring"></div><div class="vc-ring"></div>
          <div class="vc-inner"><div class="vc-vbars">
            <div class="vc-vbar"></div><div class="vc-vbar"></div><div class="vc-vbar"></div><div class="vc-vbar"></div>
          </div></div>
        </div>
        <button class="send-b" id="btnSend" onclick="sendText()" disabled>
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Recording Overlay -->
<div class="rec-ov" id="recOverlay">
  <div class="rec-circle"><div class="rec-bars">
    <div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div>
  </div></div>
  <div class="rec-t">Слушам те...</div>
  <div class="rec-s">Говори свободно</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<script>
/* ── HELPERS ── */
function wait(ms){return new Promise(r=>setTimeout(r,ms))}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function autoResize(el){el.style.height='';el.style.height=Math.min(el.scrollHeight,80)+'px'}
function capitalize(s){return s.split(' ').map(w=>w.charAt(0).toUpperCase()+w.slice(1).toLowerCase()).join(' ')}
function bold(s){return s.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')}

/* ── STATE ── */
var S={step:'name',name:'',biz:'',stores:'',micGranted:false};
var voiceRec=null,isRec=false;
var chatArea=document.getElementById('chatArea');
var typing=document.getElementById('typing');
var actWrap=document.getElementById('actWrap');
var actRow=document.getElementById('actRow');
var searchInd=document.getElementById('searchInd');

/* ── MIC PERMISSION ── */
document.getElementById('micBtn').addEventListener('click',function(){
  var btn=this;btn.disabled=true;btn.textContent='Изчакай...';
  if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){
    navigator.mediaDevices.getUserMedia({audio:true})
      .then(function(s){s.getTracks().forEach(t=>t.stop());S.micGranted=true;startChat()})
      .catch(function(){S.micGranted=false;startChat()});
  }else{S.micGranted=false;startChat()}
});
document.getElementById('micSkip').addEventListener('click',function(){S.micGranted=false;startChat()});

function startChat(){
  document.getElementById('micScreen').classList.remove('show');
  document.getElementById('micScreen').style.display='none';
  var ui=document.getElementById('chatUI');ui.style.display='flex';
  setTimeout(function(){aiSay('Привет! Аз съм твоят AI бизнес асистент.\nЩе настроим всичко за под 3 минути. Как се казваш?')},400);
}

/* ── CHAT FUNCTIONS ── */
function scrollBot(){chatArea.scrollTop=chatArea.scrollHeight}
function showTyping(){typing.style.display='block';scrollBot()}
function hideTyping(){typing.style.display='none'}

function aiSay(text,isWow){
  hideActs();
  var g=document.createElement('div');g.className='msg-g';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-bars"><div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div></div></div>AI Асистент</div>'
    +'<div class="msg ai'+(isWow?' wow':'')+'">'+bold(esc(text)).replace(/\n/g,'<br>')+'</div>';
  chatArea.insertBefore(g,typing);scrollBot();
}

function aiWidget(html){
  hideActs();
  var g=document.createElement('div');g.className='msg-g';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-bars"><div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div></div></div>AI Асистент</div>'+html;
  chatArea.insertBefore(g,typing);scrollBot();
}

function userSay(text){
  var g=document.createElement('div');g.className='msg-g';
  g.innerHTML='<div class="msg-meta right">'+new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})+'</div>'
    +'<div style="display:flex;justify-content:flex-end"><div class="msg user">'+esc(text)+'</div></div>';
  chatArea.insertBefore(g,typing);scrollBot();
}

function showActs(btns){
  actRow.innerHTML=btns.map(b=>
    '<button class="act-btn'+(b.p?' primary':'')+'" onclick="handleAct(\''+b.v.replace(/'/g,"\\'")+'\')">'
    +b.l+'</button>').join('');
  actWrap.style.display='block';scrollBot();
}
function hideActs(){actWrap.style.display='none';actRow.innerHTML=''}

/* ── VOICE ── */
function toggleVoice(){
  if(isRec){stopVoice();return}
  var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){return}
  isRec=true;document.getElementById('voiceWrap').classList.add('rec');
  document.getElementById('recOverlay').classList.add('show');
  voiceRec=new SR();voiceRec.lang='bg-BG';voiceRec.interimResults=false;voiceRec.maxAlternatives=1;
  voiceRec.onresult=function(e){var t=e.results[0][0].transcript;stopVoice();processInput(t)};
  voiceRec.onerror=function(){stopVoice()};
  voiceRec.onend=function(){if(isRec)stopVoice()};
  try{voiceRec.start()}catch(e){stopVoice()}
}
function stopVoice(){
  isRec=false;document.getElementById('voiceWrap').classList.remove('rec');
  document.getElementById('recOverlay').classList.remove('show');
  if(voiceRec){try{voiceRec.stop()}catch(e){}voiceRec=null}
}

function sendText(){
  var inp=document.getElementById('chatInput');var t=inp.value.trim();if(!t)return;
  inp.value='';inp.style.height='';document.getElementById('btnSend').disabled=true;processInput(t);
}
function handleAct(v){processInput(v)}

/* ── MAIN FLOW ── */
async function processInput(text){
  userSay(text);hideActs();showTyping();await wait(600);hideTyping();
  try{
    switch(S.step){
      case 'name':
        S.name=capitalize(text.trim());S.step='biz';
        aiSay(S.name+', приятно ми е.\nКакъв е профилът на твоя обект?');
        showActs([{l:'Дрехи / Обувки',v:'Дрехи и обувки'},{l:'Хранителни стоки',v:'Хранителни стоки'},{l:'Друго',v:'Друго'}]);
        break;
      case 'biz':
        S.biz=text.trim();S.step='stores';
        aiSay('Колко обекта управляваш в момента?');
        showActs([{l:'1 обект',v:'1'},{l:'2–5 обекта',v:'2-5'},{l:'6+ верига',v:'6+'}]);
        break;
      case 'stores':
        S.stores=text.trim();S.step='losses';
        await showLossAnalysis();break;
      case 'losses':
        S.step='powers';await showPowers();break;
      case 'powers':
        S.step='loyalty';await showLoyalty();break;
      case 'loyalty_chosen':
        S.loyaltyChoice=text;S.step='done';
        aiSay('Готово, '+S.name+'! Активирах "'+text+'".\n\nДа започваме ли работа?');
        showActs([{l:'Старт!',v:'старт',p:true}]);break;
      case 'done':
        await finishOnboarding();break;
    }
  }catch(err){console.error(err)}
}

/* ── STEP: LOSS ANALYSIS ── */
async function showLossAnalysis(){
  showTyping();searchInd.classList.add('show');await wait(1400);searchInd.classList.remove('show');hideTyping();
  var t='Внимание! В сектор "'+S.biz+'" бизнесите губят средно:\n\n'
    +'**Стока на нула** — клиенти идват, стоката я няма, парите отиват при конкуренцията.\n'
    +'**Залежали размери** — крайните размери стоят месеци и блокират капитал.\n'
    +'**Мъртва стока** — артикули без движение 90+ дни = замразени пари.\n'
    +'**Неконтролирани отстъпки** — продавачи дават отстъпки без контрол.\n\n'
    +'RunMyStore.ai засича всичко това автоматично.';
  aiSay(t,true);
  showActs([{l:'Как точно работи?',v:'как',p:true}]);
}

/* ── STEP: 4 SUPERPOWERS ── */
async function showPowers(){
  showTyping();await wait(800);hideTyping();
  var t='Ето твоите 4 суперсили:\n\n'
    +'**1. Скенер** — Снимаш разписка, складът се обновява за секунди.\n'
    +'**2. AI Мозък** — Следи размерите, мъртвата стока и ти казва какво да купиш.\n'
    +'**3. Глас** — Продаваш с говорене, AI смята рестото.\n'
    +'**4. Контрол 24/7** — Push известия за аномалии, отстъпки, нулеви наличности.';
  aiSay(t);
  showActs([{l:'Към Лоялната програма',v:'напред',p:true}]);
}

/* ── STEP: LOYALTY (AI-POWERED) ── */
async function showLoyalty(){
  aiSay('Генерирам персонализирани варианти за твоята лоялна програма...');
  showTyping();
  var options=[];
  try{
    var resp=await fetch('ai-helper.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'loyalty_options',biz_type:S.biz,stores:S.stores,name:S.name})});
    var data=await resp.json();
    if(data.options&&data.options.length)options=data.options;
  }catch(e){console.error('AI loyalty error:',e)}
  hideTyping();
  if(!options.length){
    options=[
      {emoji:'⭐',title:'Точки за покупка',desc:'1 EUR = 1 точка. На 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки.'},
      {emoji:'🎯',title:'VIP нива',desc:'Бронз → Сребро → Злато с растящи отстъпки според оборота.'},
      {emoji:'🤝',title:'Cashback',desc:'Фиксиран % връщане по клиентската сметка за всяка покупка.'}
    ];
  }
  aiSay('Лоялната програма е **БЕЗПЛАТНА ЗАВИНАГИ** — дори без абонамент.\nЕто 3 варианта специално за теб:');
  var html='<div class="loy-cards">';
  options.forEach(function(o){
    var safe=esc(o.title).replace(/'/g,"\\'");
    html+='<button class="loy-card" onclick="chooseLoy(this,\''+safe+'\')">'
      +'<span class="loy-emoji">'+(o.emoji||'⭐')+'</span>'
      +'<div class="loy-title">'+esc(o.title)+'</div>'
      +'<div class="loy-desc">'+esc(o.desc)+'</div></button>';
  });
  html+='<button class="loy-custom" onclick="chooseLoy(this,\'custom\')">Ще си я настроя сам по-късно</button>';
  html+='</div>';
  aiWidget(html);
  S.step='loyalty_chosen';
}
function chooseLoy(el,title){
  document.querySelectorAll('.loy-card').forEach(c=>c.classList.remove('selected'));
  if(el.classList.contains('loy-card'))el.classList.add('selected');
  setTimeout(()=>processInput(title==='custom'?'Ще настроя сам':title),400);
}

/* ── FINISH ── */
async function finishOnboarding(){
  showTyping();
  try{
    await fetch('onboarding-save.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({name:S.name,biz:S.biz,stores:S.stores,loyalty:S.loyaltyChoice||''})});
  }catch(e){}
  hideTyping();await wait(400);window.location.href='chat.php';
}
</script>
</body>
</html>
