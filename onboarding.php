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
body{background:#EDE8DC;color:#3f3b36;font-family:'Montserrat',sans-serif;height:100dvh;max-height:100dvh;display:flex;flex-direction:column;overflow:hidden}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 50%,rgba(218,165,32,.05) 0%,transparent 45%),radial-gradient(circle at 85% 20%,rgba(197,160,89,.06) 0%,transparent 45%),radial-gradient(circle at 50% 85%,rgba(230,194,122,.07) 0%,transparent 40%);pointer-events:none;z-index:0}
.hdr{position:relative;z-index:50;background:rgba(237,232,220,.9);backdrop-filter:blur(20px);border-bottom:1px solid rgba(210,193,164,.7);padding:14px 16px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.brand{font-size:18px;font-weight:900;background:linear-gradient(to right,#A67C00,#E6C27A,#A67C00);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gShift 6s linear infinite}
.chat-area{background-color:#ffffff;border:1px solid #EAE0D0;box-shadow:0 8px 30px rgba(166,124,0,.04);border-radius:20px;margin:12px 12px 0 12px;flex:1 1 0;min-height:0;overflow-y:auto;overflow-x:hidden;padding:20px 16px 8px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.chat-area::-webkit-scrollbar{display:none}
.msg-group{margin-bottom:16px;animation:fadeUp .3s ease both}
.msg-meta{font-size:11px;color:#9ca3af;margin-bottom:6px;display:flex;align-items:center;gap:6px;font-weight:500}
.ai-ava{width:28px;height:28px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#D4AF37,#F3E5AB);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(212,175,55,.3)}
.ai-ava-bars{display:flex;gap:2px;align-items:center;height:12px}
.ai-ava-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.ai-ava-bar:nth-child(1){height:5px}.ai-ava-bar:nth-child(2){height:9px;animation-delay:.15s}.ai-ava-bar:nth-child(3){height:12px;animation-delay:.3s}.ai-ava-bar:nth-child(4){height:7px;animation-delay:.45s}
.msg{max-width:88%;padding:13px 16px;font-size:14px;line-height:1.55;word-break:break-word}
.msg.ai{background:#FAF7F0;border:1px solid #E6D5B8;color:#292524;border-radius:4px 18px 18px 18px}
.msg.user{background:linear-gradient(135deg,#C5A059,#E6C27A);color:#fff;border-radius:18px 18px 4px 18px;margin-left:auto;box-shadow:0 4px 12px rgba(197,160,89,.2)}
.msg.wow{border-color:#D4AF37;background:#FFFDF8;color:#574200;font-weight:500}
.typing-wrap{display:none;padding:12px 16px;background:#FAF7F0;border:1px solid #E6D5B8;border-radius:4px 18px 18px 18px;width:fit-content;margin-bottom:14px}
.typing-dots{display:flex;gap:5px;align-items:center}
.dot{width:7px;height:7px;border-radius:50%;background:#D4AF37;animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}
.search-indicator{display:none;padding:10px 16px;margin-bottom:12px;background:#FAF7F0;border:1px solid #E6D5B8;border-radius:12px;font-size:12px;color:#A67C00;font-weight:600}
.search-indicator.show{display:flex;align-items:center;gap:8px}
.search-dot{width:7px;height:7px;border-radius:50%;background:#C5A059;animation:bounce 1s infinite}
.action-wrap{padding:0 12px 12px;flex-shrink:0;position:relative;z-index:1}
.action-row{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.action-btn{flex:1;min-width:120px;max-width:200px;padding:12px 14px;border-radius:16px;font-size:13px;font-weight:700;border:1px solid #E6D5B8;color:#574200;background:#fff;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .2s;text-align:center}
.action-btn.primary{background:linear-gradient(to bottom,#D4AF37,#C5A059);border-color:transparent;color:#fff;box-shadow:0 6px 16px rgba(212,175,55,.3)}
.loyalty-cards{display:flex;flex-direction:column;gap:10px;margin-top:4px;width:100%;max-width:88%}
.loyalty-card{background:#fff;border:1.5px solid #E6D5B8;border-radius:16px;padding:14px 16px;cursor:pointer;transition:all .2s;text-align:left;font-family:'Montserrat',sans-serif;width:100%}
.loyalty-card.selected{border-color:#D4AF37;background:#FFFDF8;box-shadow:0 4px 14px rgba(212,175,55,.2)}
.loyalty-card-emoji{font-size:20px;margin-bottom:5px;display:block}
.loyalty-card-title{font-size:13px;font-weight:800;color:#292524;margin-bottom:3px}
.loyalty-card-desc{font-size:12px;color:#78716C;line-height:1.5}
.loyalty-build-btn{margin-top:2px;padding:12px;border-radius:14px;font-size:13px;font-weight:700;border:1.5px dashed #C5A059;color:#A67C00;background:transparent;cursor:pointer;font-family:'Montserrat',sans-serif;width:100%;text-align:center}
.input-area{background:rgba(237,232,220,.9);backdrop-filter:blur(20px);padding:12px 16px 20px;flex-shrink:0;position:relative;z-index:1}
.input-row{display:flex;gap:10px;align-items:center}
.text-input{flex:1;background:#FAF7F0;border:1px solid #E6D5B8;border-radius:24px;color:#292524;font-size:14px;padding:12px 18px;font-family:'Montserrat',sans-serif;outline:none;resize:none;max-height:80px;line-height:1.4;transition:all .2s}
.text-input:focus{border-color:#C5A059;background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.1)}
.text-input::placeholder{color:#A8A29E}
.voice-wrap{position:relative;flex-shrink:0;width:52px;height:52px;cursor:pointer}
.voice-ring{position:absolute;border-radius:50%;border:1px solid rgba(212,175,55,.3);animation:waveOut 2s ease-out infinite;pointer-events:none}
.voice-ring:nth-child(1){inset:-4px}.voice-ring:nth-child(2){inset:-9px;animation-delay:.55s}.voice-ring:nth-child(3){inset:-14px;animation-delay:1.1s}
.voice-inner{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#D4AF37,#E6C27A);display:flex;align-items:center;justify-content:center;position:relative;z-index:1;box-shadow:0 4px 14px rgba(212,175,55,.4)}
.voice-bars{display:flex;gap:3px;align-items:center;height:20px}
.voice-bar{width:3px;border-radius:2px;background:#fff;animation:barDance 1s ease-in-out infinite}
.voice-bar:nth-child(1){height:8px}.voice-bar:nth-child(2){height:16px;animation-delay:.15s}.voice-bar:nth-child(3){height:20px;animation-delay:.3s}.voice-bar:nth-child(4){height:12px;animation-delay:.45s}.voice-bar:nth-child(5){height:8px;animation-delay:.6s}
.voice-wrap.recording .voice-inner{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 18px rgba(239,68,68,.5)}
.send-btn{width:46px;height:46px;border-radius:50%;background:#FAF7F0;border:1px solid #E6D5B8;color:#A67C00;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-btn:active{background:#F3E5AB;transform:scale(.92)}
.send-btn:disabled{opacity:.4;cursor:default}
.rec-overlay{position:fixed;inset:0;background:rgba(237,232,220,.92);z-index:400;display:none;flex-direction:column;align-items:center;justify-content:center;backdrop-filter:blur(14px)}
.rec-overlay.show{display:flex}
.rec-circle{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;margin-bottom:24px;animation:recPulse 1s ease-out infinite;box-shadow:0 10px 30px rgba(239,68,68,.4)}
.rec-wave-bars{display:flex;gap:5px;align-items:center;height:32px}
.rec-bar{width:5px;border-radius:3px;background:#fff;animation:barDance .7s ease-in-out infinite}
.rec-bar:nth-child(1){height:12px}.rec-bar:nth-child(2){height:24px;animation-delay:.1s}.rec-bar:nth-child(3){height:32px;animation-delay:.2s}.rec-bar:nth-child(4){height:20px;animation-delay:.3s}.rec-bar:nth-child(5){height:12px;animation-delay:.4s}
.rec-title{font-size:22px;font-weight:800;color:#292524;margin-bottom:6px}
.rec-sub{font-size:15px;color:#78716C;margin-bottom:32px}
.rec-stop{padding:14px 34px;background:#fee2e2;border:1px solid #fca5a5;border-radius:24px;color:#ef4444;font-size:15px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif}
.mic-screen{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:32px 24px;text-align:center;position:relative;z-index:1}
.mic-screen.show{display:flex}
.mic-icon-wrap{width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,#D4AF37,#E6C27A);display:flex;align-items:center;justify-content:center;margin-bottom:28px;box-shadow:0 10px 30px rgba(212,175,55,.3);animation:recPulse 2s ease-out infinite}
.mic-title{font-size:24px;font-weight:800;color:#292524;margin-bottom:12px}
.mic-sub{font-size:15px;color:#574200;line-height:1.65;margin-bottom:32px;max-width:300px}
.mic-btn{padding:16px 36px;background:linear-gradient(to bottom,#D4AF37,#C5A059);border:none;border-radius:20px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;box-shadow:0 8px 24px rgba(212,175,55,.35)}
.mic-skip{margin-top:20px;font-size:14px;color:#78716C;cursor:pointer;text-decoration:underline;padding:8px 16px;display:inline-block}
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
    <div class="typing-wrap" id="typing"><div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
  </div>
  <div class="search-indicator" id="searchIndicator"><div class="search-dot"></div><span>Правя пазарен анализ за твоя тип бизнес...</span></div>
  <div class="action-wrap" id="actionWrap" style="display:none"><div class="action-row" id="actionRow"></div></div>
  <div class="input-area">
    <div class="input-row">
      <textarea class="text-input" id="chatInput" placeholder="Пиши тук..." rows="1"
        oninput="autoResize(this);document.getElementById('btnSend').disabled=!this.value.trim()"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendText()}"></textarea>
      <div class="voice-wrap" id="voiceWrap" onclick="toggleVoice()">
        <div class="voice-ring"></div><div class="voice-ring"></div><div class="voice-ring"></div>
        <div class="voice-inner"><div class="voice-bars"><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div></div></div>
      </div>
      <button class="send-btn" id="btnSend" onclick="sendText()" disabled>
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>
</div>
<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle"><div class="rec-wave-bars"><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div></div></div>
  <div class="rec-title">Слушам те...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>
<script>
function wait(ms){return new Promise(function(r){setTimeout(r,ms);})}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function autoResize(el){el.style.height='';el.style.height=Math.min(el.scrollHeight,80)+'px'}
function capitalize(s){return s.split(' ').map(function(w){return w.charAt(0).toUpperCase()+w.slice(1).toLowerCase();}).join(' ')}
async function aiFetch(body){return fetch('ai-helper.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})}

var state={step:'name',name:'',biz:'',segment:'',stores:'',products:'',employees:'',loyaltyChoice:'',micGranted:false};
var voiceRec=null,isRecording=false,searchResult='',searchDone=false;
var chatArea=document.getElementById('chatArea');
var typing=document.getElementById('typing');
var voiceWrap=document.getElementById('voiceWrap');
var recOverlay=document.getElementById('recOverlay');
var actionWrap=document.getElementById('actionWrap');
var actionRow=document.getElementById('actionRow');
var searchInd=document.getElementById('searchIndicator');

document.getElementById('micBtn').addEventListener('click',function(){
  var btn=this;btn.disabled=true;btn.textContent='Изчакай...';
  if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){
    navigator.mediaDevices.getUserMedia({audio:true})
      .then(function(s){s.getTracks().forEach(function(t){t.stop();});state.micGranted=true;startChat();})
      .catch(function(){state.micGranted=false;startChat();});
  }else{state.micGranted=false;startChat();}
});
document.getElementById('micSkip').addEventListener('click',function(){state.micGranted=false;startChat();});

function startChat(){
  document.getElementById('micScreen').classList.remove('show');
  var ci=document.getElementById('chatInterface');
  ci.style.display='flex';ci.style.flex='1 1 0';ci.style.minHeight='0';
  setTimeout(function(){aiSay('Хей! Аз съм твоят нов бизнес асистент 🙌\nЩе работим заедно всеки ден.\nКак да те викам?');},400);
}

function scrollBottom(){chatArea.scrollTop=chatArea.scrollHeight}
function showTyping(){typing.style.display='block';scrollBottom()}
function hideTyping(){typing.style.display='none'}

function aiSay(text,isWow){
  hideActions();
  var g=document.createElement('div');g.className='msg-group';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div>RunMyStore.ai Асистент</div><div class="msg ai'+(isWow?' wow':'')+'">'+esc(text).replace(/\n/g,'<br>')+'</div>';
  chatArea.insertBefore(g,typing);scrollBottom();
}

function aiSayWidget(html){
  hideActions();
  var g=document.createElement('div');g.className='msg-group';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div>RunMyStore.ai Асистент</div>'+html;
  chatArea.insertBefore(g,typing);scrollBottom();
}

function userSay(text){
  var g=document.createElement('div');g.className='msg-group';
  g.innerHTML='<div class="msg-meta" style="justify-content:flex-end">'+new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})+'</div><div style="display:flex;justify-content:flex-end"><div class="msg user">'+esc(text)+'</div></div>';
  chatArea.insertBefore(g,typing);scrollBottom();
}

function showActions(buttons){
  actionRow.innerHTML=buttons.map(function(b){
    return '<button class="action-btn'+(b.primary?' primary':'')+'" onclick="handleAction(\''+b.val.replace(/'/g,"\\'")+'\')">'+b.label+'</button>';
  }).join('');
  actionWrap.style.display='block';scrollBottom();
}
function hideActions(){actionWrap.style.display='none';actionRow.innerHTML='';}

function toggleVoice(){
  if(isRecording){stopVoice();return;}
  var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){alert('Браузърът не поддържа гласово въвеждане');return;}
  isRecording=true;voiceWrap.classList.add('recording');recOverlay.classList.add('show');
  voiceRec=new SR();voiceRec.lang='bg-BG';voiceRec.interimResults=false;voiceRec.maxAlternatives=1;
  voiceRec.onresult=function(e){var t=e.results[0][0].transcript;stopVoice();processInput(t);};
  voiceRec.onerror=function(){stopVoice();};
  voiceRec.onend=function(){if(isRecording)stopVoice();};
  try{voiceRec.start();}catch(e){stopVoice();}
}
function stopVoice(){
  isRecording=false;voiceWrap.classList.remove('recording');recOverlay.classList.remove('show');
  if(voiceRec){try{voiceRec.stop();}catch(e){}voiceRec=null;}
}
function sendText(){
  var input=document.getElementById('chatInput');var text=input.value.trim();if(!text)return;
  input.value='';input.style.height='';document.getElementById('btnSend').disabled=true;processInput(text);
}
function handleAction(val){processInput(val);}

async function processInput(text){
  userSay(text);hideActions();showTyping();await wait(600);hideTyping();
  try{
    switch(state.step){
      case 'name':
        state.name=capitalize(text.trim());state.step='biz';
        aiSay(state.name+', приятно ми е! 😄\nКажи ми — какво точно продаваш в твоя обект?');
        break;
      case 'biz':
        state.biz=text.trim();state.step='segment';showTyping();
        try{
          var r=await aiFetch({action:'analyze_biz_segment',biz:state.biz});
          var d=await r.json();hideTyping();
          aiSay((d&&d.question&&d.question.trim())?d.question:'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?');
        }catch(e){hideTyping();aiSay('Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?');}
        break;
      case 'segment':
        state.segment=text.trim();state.step='stores';
        searchResult='';doWebSearch();
        aiSay('Разбрах те. Колко физически обекта имаш в момента?');
        break;
      case 'stores':
        state.stores=text.trim();state.step='products';
        aiSay('Колко артикула поддържаш — под 200, около 500, или хиляди?');
        break;
      case 'products':
        state.products=text.trim();state.step='employees';
        aiSay('Имаш ли служители, които работят на касата?');
        break;
      case 'employees':
        state.employees=text.trim();
        if(/да|имам|момич|момч|човек|души/i.test(text)){
          aiSay('Те ще могат да питат мен вместо да ти звънят постоянно 😄');
          await wait(2200);
        }
        state.step='wow';await showWowMoment();break;
      case 'wow_confirm':
        state.step='loyalty';await showLoyaltyOptions();break;
      case 'loyalty_chosen':
        state.loyaltyChoice=text;state.step='done';await showFinalMessage();break;
      case 'done':
        await finishOnboarding();break;
    }
  }catch(err){console.error('processInput error:',err);}
}

function doWebSearch(){
  searchDone=false;
  aiFetch({action:'web_search',query:state.biz+' '+state.segment+' retail dead stock loss EU average'})
    .then(function(r){return r.text();})
    .then(function(t){try{var d=JSON.parse(t);searchResult=d.result||'';}catch(e){searchResult='';}searchDone=true;})
    .catch(function(){searchResult='';searchDone=true;});
}

async function showWowMoment(){
  showTyping();searchInd.classList.add('show');scrollBottom();
  var checks=60;
  while(!searchDone&&checks>0){await wait(350);checks--;}
  searchInd.classList.remove('show');
  var done=false;
  var fallback=async function(){
    if(done)return;done=true;hideTyping();searchInd.classList.remove('show');
    var isExp=/скъп|марков|луксоз|злат|техник|мебел|бижу|оптик/i.test(state.biz+' '+state.segment);
    var amt=isExp?'€1500–€4000':'€200–€600';
    var msgs=[
      '💀 Zombie Stock: Залежала стока = замразени пари в рафта.\nАз засичам мъртвите артикули и ти казвам точно как да освободиш кеша — преди да е станало проблем.',
      '📐 Size-Curve Protector: Анализирам историята ти и казвам ТОЧНО каква пропорция размери/цветове да заредиш.\nНикога повече "10 бройки в S, но никой не ги купува".',
      '🔔 Lost Revenue Alert: Засичам кога топ артикул ти свършва и те предупреждавам ПРЕДИ да е нула.\nИзпуснатата продажба е по-скъпа от всяка отстъпка.',
      '🛒 Basket Analysis: Знам кое с кое се купува в твоя магазин.\nКазвам на продавачите какво да предложат → касовият бон расте автоматично.',
      state.name+', само тези 4 проблема ти струват ~'+amt+' на година.\nRunMyStore.ai е €588/год.\nРазликата остава изцяло в твоя джоб. 💰'
    ];
    for(var i=0;i<msgs.length;i++){await wait(i===0?500:3200);aiSay(msgs[i],true);}
    await wait(3000);state.step='wow_confirm';
    aiSay('Искаш ли да видиш още какво правя за теб всеки ден? 🚀');
    showActions([{label:'Да, покажи!',val:'да',primary:true}]);
  };
  var safetyTimer=setTimeout(fallback,35000);
  try{
    showTyping();
    var r=await aiFetch({action:'wow',prompt:buildWowPrompt()});
    var txt=await r.text();if(done)return;
    var d=JSON.parse(txt);
    if(d&&d.messages&&Array.isArray(d.messages)&&d.messages.length>0){
      clearTimeout(safetyTimer);done=true;hideTyping();
      for(var i=0;i<d.messages.length;i++){await wait(i===0?500:3200);aiSay(d.messages[i],true);}
      await wait(3000);state.step='wow_confirm';
      aiSay('Искаш ли да видиш още какво правя за теб всеки ден? 🚀');
      showActions([{label:'Да, покажи!',val:'да',primary:true}]);
    }else{throw new Error('no messages');}
  }catch(e){fallback();}
}

function buildWowPrompt(){
  return 'Ти си AI асистент на RunMyStore.ai. Говориш като умен приятел търговец. Разбираш разговорен български и поправяш правописни грешки наум.\n'+
    'ДАННИ: Име:'+state.name+' | Бизнес:'+state.biz+' | Сегмент:'+state.segment+' | Магазини:'+state.stores+' | Пазарни данни:'+(searchResult||'няма')+'\n\n'+
    'ГЕНЕРИРАЙ ТОЧНО 5 СЪОБЩЕНИЯ {"messages":["..."]}:\n'+
    '1. 💀 Zombie Stock — колко пари стои мъртва стока в ТОЗИ тип бизнес (реална EUR сума)\n'+
    '2. 📐 Size-Curve Protector — проблемът с грешните размери/цветове в ТАЗИ индустрия\n'+
    '3. 🔔 Lost Revenue — изпуснати продажби (казвай че ги ПРЕДУПРЕЖДАВАШ, никога "будя")\n'+
    '4. 🛒 Basket Analysis — кое с кое се купува, конкретен upsell пример за ТОЗИ бизнес\n'+
    '5. Обобщение: EUR сума загуби/год и "RunMyStore.ai е 588 EUR/год. Разликата в джоба ти."\n'+
    'Кратко (макс 3 изречения). САМО JSON.';
}

async function showLoyaltyOptions(){
  await wait(400);
  aiSay('Ето как ще ти помагам всеки ден:\n\n📦 Следя склада — кое върви, кое стои, кое свършва\n🔔 Предупреждавам те ПРЕДИ стоката да е свършила\n📊 Печалбата за деня — без да събираш хартийки\n🎤 Управляваш всичко с глас\n🔍 Smart Purchasing — кога и какво да поръчаш\n⚖️ Stock Balancer — балансирам наличностите между обектите\n\n30 дни напълно безплатно. После 49 EUR/месец.');
  await wait(4000);
  aiSay('А сега нещо специално — лоялна програма само за твоите клиенти.\nГенерирам 3 варианта специално за твоя бизнес...');
  showTyping();
  var options=[];
  try{
    var r=await aiFetch({action:'loyalty_options',biz:state.biz,segment:state.segment,name:state.name});
    var d=await r.json();
    if(d&&d.options&&d.options.length>0)options=d.options;
  }catch(e){console.error('loyalty_options error:',e);}
  hideTyping();
  if(options.length===0){
    options=[
      {emoji:'⭐',title:'Точки за всяка покупка',desc:'1 EUR = 1 точка. На 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки.'},
      {emoji:'🎯',title:'VIP нива Silver / Gold',desc:'При 300 EUR похарчени ставаш Silver (-5%). При 700 EUR — Gold (-10%) + ранен достъп до новото.'},
      {emoji:'🤝',title:'Доведи приятел',desc:'Доведеш приятел → ти вземаш 10 EUR кредит, той получава 5 EUR отстъпка на първата покупка.'}
    ];
  }
  var html='<div class="loyalty-cards">';
  options.forEach(function(opt,i){
    var safeTitle=opt.title.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    html+='<button class="loyalty-card" onclick="chooseLoyalty(this,\''+safeTitle+'\')">';
    html+='<span class="loyalty-card-emoji">'+(opt.emoji||'⭐')+'</span>';
    html+='<div class="loyalty-card-title">'+esc(opt.title)+'</div>';
    html+='<div class="loyalty-card-desc">'+esc(opt.desc)+'</div>';
    html+='</button>';
  });
  html+='<button class="loyalty-build-btn" onclick="chooseLoyalty(this,\'custom\')">🧩 Ще си я сглобя сам от настройките</button>';
  html+='</div>';
  aiSayWidget(html);
  state.step='loyalty_chosen';
}

function chooseLoyalty(el,title){
  document.querySelectorAll('.loyalty-card').forEach(function(c){c.classList.remove('selected');});
  if(el.classList.contains('loyalty-card'))el.classList.add('selected');
  setTimeout(function(){processInput(title==='custom'?'Ще настройвам сам':title);},400);
}

async function showFinalMessage(){
  showTyping();await wait(1200);hideTyping();
  var isCustom=state.loyaltyChoice==='Ще настройвам сам';
  var loyaltyLine=isCustom
    ?'Лоялната програма ще я настроиш сам от настройките — пълна свобода.'
    :'Активирах "'+state.loyaltyChoice+'" за твоите клиенти.';
  aiSay('Готово, '+state.name+'! 🎉\n\n'+loyaltyLine+'\n\n⭐ Тази лоялна програма остава БЕЗПЛАТНА ЗАВИНАГИ —\nдори и да не ползваш AI асистента.\n\n30 дни пробваш всичко безплатно.\nСлед това е едва 49 EUR/месец.\n\nАз съм тук — питай ме каквото искаш 🚀');
  state.step='done';
  showActions([{label:'Да тръгваме! 🚀',val:'старт',primary:true}]);
}

async function finishOnboarding(){
  showTyping();
  try{
    await fetch('onboarding-save.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({name:state.name,biz:state.biz,segment:state.segment,stores:state.stores,products:state.products,employees:state.employees,loyalty:state.loyaltyChoice})});
  }catch(e){console.error('save error:',e);}
  hideTyping();await wait(500);window.location.href='chat.php';
}
</script>
</body>
</html>
