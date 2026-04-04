<?php
/**
 * onboarding.php — AI-Driven Conversational Onboarding (Cruip Dark)
 * Сесия 18 FIX — robust JSON parsing, no more freezing
 *
 * FLOW:
 *   Фаза 1-3: AI интервю (свободен текст/глас) — име, бизнес, уточняване, мащаб
 *   Фаза 4: WOW — 5 сценария един по един с бутон "Напред" + обобщение
 *   Фаза 5: Суперсили — 10 функции една по една с бутон "Напред"
 *   Фаза 6: Лоялна — AI предложение + 2 бутона
 *   Фаза 7: Финал — "Готов ли си?" + бутон → chat.php
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
<title>Добре дошъл — RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
html,body{height:100dvh;overflow:hidden}
body{display:flex;flex-direction:column}
.ob-wrap{flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;position:relative}
.ob-hdr{flex-shrink:0;padding:14px 16px;display:flex;align-items:center;justify-content:center;position:relative;z-index:50;background:rgba(17,24,39,.85);backdrop-filter:blur(16px);border-bottom:1px solid rgba(99,102,241,.15)}
.ob-brand{font-size:18px;font-weight:800;background:linear-gradient(to right,var(--color-gray-200),var(--color-indigo-200),var(--color-gray-50),var(--color-indigo-300),var(--color-gray-200));background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gradient 6s linear infinite;font-family:var(--font-nacelle,ui-sans-serif,system-ui,sans-serif)}
.ob-chat{flex:1;overflow-y:auto;overflow-x:hidden;padding:20px 16px 8px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;scrollbar-width:none;position:relative;z-index:1}
.ob-chat::-webkit-scrollbar{display:none}
.msg-g{margin-bottom:14px;animation:fadeUp .3s ease both}
.msg-meta{font-size:11px;color:rgba(165,180,252,.5);margin-bottom:5px;display:flex;align-items:center;gap:6px;font-weight:500}
.msg-meta.r{justify-content:flex-end}
.ai-ava{width:26px;height:26px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--color-indigo-500),var(--color-indigo-400));display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(99,102,241,.3)}
.ai-bars{display:flex;gap:2px;align-items:center;height:11px}
.ai-bar{width:2px;border-radius:1px;background:#fff;animation:barDance 1s ease-in-out infinite}
.ai-bar:nth-child(1){height:4px}.ai-bar:nth-child(2){height:8px;animation-delay:.15s}.ai-bar:nth-child(3){height:11px;animation-delay:.3s}.ai-bar:nth-child(4){height:6px;animation-delay:.45s}
.msg{max-width:88%;padding:12px 16px;font-size:14px;line-height:1.55;word-break:break-word}
.msg.ai{background:rgba(30,27,75,.4);border:1px solid rgba(99,102,241,.2);color:var(--color-gray-200);border-radius:4px 16px 16px 16px}
.msg.user{background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));color:#fff;border-radius:16px 16px 4px 16px;margin-left:auto;box-shadow:0 4px 12px rgba(99,102,241,.25)}
.msg.wow{border-color:rgba(99,102,241,.4);background:rgba(30,27,75,.6)}
.typing-w{display:none;padding:10px 16px;background:rgba(30,27,75,.4);border:1px solid rgba(99,102,241,.15);border-radius:4px 16px 16px 16px;width:fit-content;margin-bottom:12px}
.dots{display:flex;gap:5px;align-items:center}
.dot{width:6px;height:6px;border-radius:50%;background:var(--color-indigo-400);animation:bounce 1.2s infinite}
.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}
.act-wrap{padding:8px 12px 12px;flex-shrink:0;position:relative;z-index:1}
.act-row{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.act-btn{flex:1;min-width:100px;max-width:300px;padding:11px 14px;border-radius:14px;font-size:13px;font-weight:700;border:1px solid rgba(99,102,241,.25);color:var(--color-indigo-200);background:rgba(30,27,75,.4);cursor:pointer;font-family:inherit;transition:all .2s;text-align:center}
.act-btn:active{transform:scale(.96)}
.act-btn.primary{background:linear-gradient(to top,var(--color-indigo-600),var(--color-indigo-500));border-color:transparent;color:#fff;box-shadow:0 4px 14px rgba(99,102,241,.35)}
.inp-area{background:rgba(17,24,39,.85);backdrop-filter:blur(16px);padding:12px 16px 20px;flex-shrink:0;position:relative;z-index:1;border-top:1px solid rgba(99,102,241,.1)}
.inp-row{display:flex;gap:10px;align-items:center}
.txt-inp{flex:1;background:rgba(17,24,39,.6);border:1px solid rgba(99,102,241,.2);border-radius:24px;color:var(--color-gray-200);font-size:14px;padding:12px 18px;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4;transition:all .2s}
.txt-inp:focus{border-color:var(--color-indigo-500);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.txt-inp::placeholder{color:rgba(165,180,252,.35)}
.vc-wrap{position:relative;flex-shrink:0;width:48px;height:48px;cursor:pointer}
.vc-ring{position:absolute;border-radius:50%;border:1px solid rgba(99,102,241,.2);animation:waveOut 2s ease-out infinite;pointer-events:none}
.vc-ring:nth-child(1){inset:-4px}.vc-ring:nth-child(2){inset:-9px;animation-delay:.55s}
.vc-inner{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));display:flex;align-items:center;justify-content:center;position:relative;z-index:1;box-shadow:0 4px 14px rgba(99,102,241,.4)}
.vc-vbars{display:flex;gap:3px;align-items:center;height:18px}
.vc-vbar{width:3px;border-radius:2px;background:#fff;animation:barDance 1s ease-in-out infinite}
.vc-vbar:nth-child(1){height:7px}.vc-vbar:nth-child(2){height:14px;animation-delay:.15s}.vc-vbar:nth-child(3){height:18px;animation-delay:.3s}.vc-vbar:nth-child(4){height:10px;animation-delay:.45s}
.vc-wrap.rec .vc-inner{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 18px rgba(239,68,68,.5)}
.send-b{width:44px;height:44px;border-radius:50%;background:rgba(30,27,75,.4);border:1px solid rgba(99,102,241,.25);color:var(--color-indigo-300);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.send-b:active{background:var(--color-indigo-600);transform:scale(.92)}
.send-b:disabled{opacity:.3;cursor:default}
.mic-scr{display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:32px 24px;text-align:center;position:relative;z-index:1}
.mic-scr.show{display:flex}
.mic-ico{width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));display:flex;align-items:center;justify-content:center;margin-bottom:28px;box-shadow:0 10px 30px rgba(99,102,241,.3);animation:recPulse 2s ease-out infinite}
.mic-t{font-size:22px;font-weight:800;color:#fff;margin-bottom:12px;font-family:var(--font-nacelle,ui-sans-serif,system-ui,sans-serif)}
.mic-s{font-size:15px;color:var(--color-indigo-200);line-height:1.65;margin-bottom:32px;max-width:300px;opacity:.7}
.mic-go{padding:16px 36px;border:none;border-radius:20px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;background:linear-gradient(to top,var(--color-indigo-600),var(--color-indigo-500));box-shadow:0 8px 24px rgba(99,102,241,.35)}
.mic-skip{margin-top:20px;font-size:14px;color:rgba(165,180,252,.5);cursor:pointer;text-decoration:underline;padding:8px 16px}
.rec-ov{position:fixed;inset:0;background:rgba(3,7,18,.5);z-index:400;display:none;align-items:flex-end;justify-content:center;padding:0 16px 24px;backdrop-filter:blur(8px)}
.rec-ov.show{display:flex}
.rec-box{width:100%;max-width:420px;background:rgba(17,24,39,.95);border:1px solid rgba(99,102,241,.3);border-radius:20px;padding:16px;box-shadow:0 20px 60px rgba(0,0,0,.8);animation:fadeUp .3s ease both}
.rec-head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.rec-dot{width:10px;height:10px;border-radius:50%;background:#ef4444;box-shadow:0 0 12px #ef4444;animation:recPulse 1.5s ease-out infinite;flex-shrink:0}
.rec-label{font-size:14px;font-weight:700;color:var(--color-indigo-200);flex:1}
.rec-x{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#9ca3af;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rec-transcript{min-height:48px;max-height:120px;overflow-y:auto;padding:12px 14px;background:rgba(0,0,0,.3);border:1px solid rgba(99,102,241,.15);border-radius:12px;color:var(--color-gray-200);font-size:15px;line-height:1.5;font-family:inherit;outline:none;margin-bottom:12px;word-break:break-word}
.rec-transcript:empty::before{content:attr(placeholder);color:rgba(165,180,252,.3);pointer-events:none}
.rec-foot{display:flex;gap:10px}
.rec-cancel{flex:1;padding:12px;border-radius:12px;background:transparent;border:1px solid rgba(255,255,255,.1);color:#9ca3af;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.rec-send{flex:1;padding:12px;border-radius:12px;background:linear-gradient(135deg,var(--color-indigo-600),var(--color-indigo-500));border:none;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(99,102,241,.35)}
.rec-send:disabled{opacity:.4;cursor:default}
@keyframes gradient{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes barDance{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.25)}}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
@keyframes waveOut{0%{transform:scale(1);opacity:.5}100%{transform:scale(1.9);opacity:0}}
@keyframes recPulse{0%{box-shadow:0 0 0 0 rgba(99,102,241,.3)}70%{box-shadow:0 0 0 20px rgba(99,102,241,0)}100%{box-shadow:0 0 0 0 rgba(99,102,241,0)}}
</style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased">
<div class="pointer-events-none absolute left-1/2 top-0 -z-10 -translate-x-1/4" aria-hidden="true"><img class="max-w-none" src="./images/page-illustration.svg" width="846" height="594" alt=""></div>
<div class="pointer-events-none absolute left-1/2 top-[400px] -z-10 -mt-20 -translate-x-full opacity-50" aria-hidden="true"><img class="max-w-none" src="./images/blurred-shape-gray.svg" width="760" height="668" alt=""></div>
<div class="pointer-events-none absolute left-1/2 top-[440px] -z-10 -translate-x-1/3" aria-hidden="true"><img class="max-w-none" src="./images/blurred-shape.svg" width="760" height="668" alt=""></div>
<div class="ob-wrap">
  <div class="ob-hdr"><div class="ob-brand">RunMyStore.ai</div></div>
  <div class="mic-scr show" id="micScreen">
    <div class="mic-ico"><svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg></div>
    <div class="mic-t">Здравей! Аз съм AI асистентът ти</div>
    <div class="mic-s">Разреши микрофона — ще е по-лесно да си говорим вместо да пишеш.</div>
    <button class="mic-go" id="micBtn">Разреши микрофона</button>
    <div class="mic-skip" id="micSkip">Ще пиша засега</div>
  </div>
  <div id="chatUI" style="display:none;flex:1 1 0;min-height:0;flex-direction:column;overflow:hidden">
    <div class="ob-chat" id="chatArea">
      <div class="typing-w" id="typing"><div class="dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
    </div>
    <div class="act-wrap" id="actWrap" style="display:none"><div class="act-row" id="actRow"></div></div>
    <div class="inp-area" id="inpArea">
      <div class="inp-row">
        <textarea class="txt-inp" id="chatInput" placeholder="Пиши тук..." rows="1" oninput="autoResize(this);document.getElementById('btnSend').disabled=!this.value.trim()" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendText()}"></textarea>
        <div class="vc-wrap" id="voiceWrap" onclick="toggleVoice()"><div class="vc-ring"></div><div class="vc-ring"></div><div class="vc-inner"><div class="vc-vbars"><div class="vc-vbar"></div><div class="vc-vbar"></div><div class="vc-vbar"></div><div class="vc-vbar"></div></div></div></div>
        <button class="send-b" id="btnSend" onclick="sendText()" disabled><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg></button>
      </div>
    </div>
  </div>
</div>
<div class="rec-ov" id="recOverlay">
  <div class="rec-box">
    <div class="rec-head">
      <div class="rec-dot"></div>
      <span class="rec-label">Слушам...</span>
      <button class="rec-x" onclick="cancelVoice()">✕</button>
    </div>
    <div class="rec-transcript" id="recTranscript" contenteditable="true" placeholder="Говори..."></div>
    <div class="rec-foot">
      <button class="rec-cancel" onclick="cancelVoice()">Откажи</button>
      <button class="rec-send" id="recSendBtn" onclick="sendVoiceText()" disabled>Изпрати →</button>
    </div>
  </div>
</div>
<script>
function wait(ms){return new Promise(r=>setTimeout(r,ms))}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function autoResize(el){el.style.height='';el.style.height=Math.min(el.scrollHeight,80)+'px'}
function bold(s){return s.replace(/\*\*(.+?)\*\*/g,'<strong style="color:var(--color-indigo-300)">$1</strong>')}

/* ── ROBUST AI FETCH — С18 FIX ── */
async function aiFetch(body){
    try {
        var r = await fetch('ai-helper.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
        if (!r.ok) throw new Error('HTTP '+r.status);
        var text = await r.text();
        // Try JSON parse
        try { return JSON.parse(text); }
        catch(e) {
            // Try to extract JSON from mixed response
            var m = text.match(/\{[\s\S]*\}/);
            if (m) { try { return JSON.parse(m[0]); } catch(e2){} }
            // Return as plain message
            return {message: text.trim() || 'Моля, опитай пак.', phase: null};
        }
    } catch(e) {
        console.error('aiFetch error:', e);
        return {message: 'Нещо се обърка. Опитай пак.', phase: null, _error: true};
    }
}

var S={phase:'interview',name:'',biz:'',segment:'',stores:'',wowIdx:0,featIdx:0,loyaltyChoice:''};
var chatHistory=[];
var voiceRec=null,isRec=false;
var chatArea=document.getElementById('chatArea'),typing=document.getElementById('typing');
var actWrap=document.getElementById('actWrap'),actRow=document.getElementById('actRow');
var inputLocked=false;
var userMsgCount=0;
var MAX_USER_MSGS=30;

var wowItems=[];
var featItems=[
  {icon:'💀',title:'Zombie Stock',desc:'AI засича стока без движение 45+ дни и предлага намаление или пакетна оферта. Парите ти не стоят замразени.'},
  {icon:'📐',title:'Size-Curve Protector',desc:'AI анализира кои размери се продават и ти казва точната пропорция за следващата поръчка. Край на остатъчни XS и XXL.'},
  {icon:'🔔',title:'Lost Revenue Alert',desc:'Предупреждава те ПРЕДИ топ артикул да свърши. Не губиш продажби от празен рафт.'},
  {icon:'🛒',title:'Basket Analysis',desc:'AI открива кои продукти се купуват заедно. Слагаш ги един до друг — продаваш повече.'},
  {icon:'⚖️',title:'Stock Balancer',desc:'Обект А има 15 бройки, обект Б — 0. AI предлага трансфер с едно натискане.'},
  {icon:'🔍',title:'Smart Purchasing',desc:'Сравнява продажбите този месец vs миналата година и ти казва ТОЧНО какво и колко да поръчаш.'},
  {icon:'🎤',title:'Гласово управление',desc:'Казваш "Прати 5 Nike 42 от Центъра в Мола" — AI изпълнява. Без писане, без менюта.'},
  {icon:'📊',title:'ABC Анализ',desc:'Показва кои артикули носят 80% от приходите (A), кои 15% (B) и кои 5% (C). Фокусираш се върху важните.'},
  {icon:'🎁',title:'Лоялна програма',desc:'БЕЗПЛАТНА ЗАВИНАГИ. Клиентите ти събират точки, получават отстъпки, връщат се при теб — без да плащаш нищо.'},
  {icon:'🏪',title:'Многообектен склад',desc:'Виждаш наличностите на всички обекти. Трансфери с глас. Знаеш къде е всяка бройка.'}
];

/* ── MIC ── */
document.getElementById('micBtn').addEventListener('click',function(){
  this.disabled=true;this.textContent='Изчакай...';
  if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){s.getTracks().forEach(t=>t.stop());startChat()}).catch(function(){startChat()});
  }else startChat();
});
document.getElementById('micSkip').addEventListener('click',startChat);

function startChat(){
  document.getElementById('micScreen').classList.remove('show');document.getElementById('micScreen').style.display='none';
  document.getElementById('chatUI').style.display='flex';
  setTimeout(function(){aiSay('Здравей! Приятно ми е — аз съм твоят бъдещ AI бизнес партньор.\nКак се казваш?')},400);
}

function scrollBot(){chatArea.scrollTop=chatArea.scrollHeight;setTimeout(function(){chatArea.scrollTop=chatArea.scrollHeight},100);setTimeout(function(){chatArea.scrollTop=chatArea.scrollHeight},400)}
function showTyping(){typing.style.display='block';scrollBot()}
function hideTyping(){typing.style.display='none'}
function aiSay(text,isWow){
  if(typeof text==='string'&&text.trim().charAt(0)==='{'){try{var j=JSON.parse(text);if(j.message)text=j.message}catch(e){var m=text.match(/"message"\s*:\s*"([^"]+)"/);if(m)text=m[1]}}
  hideTyping();hideActs();var g=document.createElement('div');g.className='msg-g';
  g.innerHTML='<div class="msg-meta"><div class="ai-ava"><div class="ai-bars"><div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div></div></div>AI Асистент</div><div class="msg ai'+(isWow?' wow':'')+'">'+bold(esc(text)).replace(/\n/g,'<br>')+'</div>';
  chatArea.insertBefore(g,typing);scrollBot();
}
function userSay(text){
  var g=document.createElement('div');g.className='msg-g';
  g.innerHTML='<div class="msg-meta r">'+new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})+'</div><div style="display:flex;justify-content:flex-end"><div class="msg user">'+esc(text)+'</div></div>';
  chatArea.insertBefore(g,typing);scrollBot();
}
function showActs(btns){
  actRow.innerHTML=btns.map(b=>'<button class="act-btn'+(b.p?' primary':'')+'" onclick="handleAct(\''+b.v.replace(/'/g,"\\'")+'\')">'+(b.l)+'</button>').join('');
  actWrap.style.display='block';scrollBot();
}
function hideActs(){actWrap.style.display='none';actRow.innerHTML=''}
function showInput(){document.getElementById('inpArea').style.display='block'}
function hideInput(){document.getElementById('inpArea').style.display='none'}

/* ── VOICE ── */
function toggleVoice(){
  if(isRec){stopVoice();return}
  var SR=window.SpeechRecognition||window.webkitSpeechRecognition;if(!SR)return;
  isRec=true;document.getElementById('voiceWrap').classList.add('rec');document.getElementById('recOverlay').classList.add('show');
  voiceRec=new SR();voiceRec.lang='bg-BG';voiceRec.interimResults=false;
  voiceRec.onresult=function(e){stopVoice();processInput(e.results[0][0].transcript)};
  voiceRec.onerror=function(){stopVoice()};voiceRec.onend=function(){if(isRec)stopVoice()};
  try{voiceRec.start()}catch(e){stopVoice()}
}
function stopVoice(){
  isRec=false;document.getElementById('voiceWrap').classList.remove('rec');document.getElementById('recOverlay').classList.remove('show');
  if(voiceRec){try{voiceRec.stop()}catch(e){}voiceRec=null}
}
function sendText(){var inp=document.getElementById('chatInput');var t=inp.value.trim();if(!t)return;inp.value='';inp.style.height='';document.getElementById('btnSend').disabled=true;processInput(t)}
function handleAct(v){processInput(v)}

/* ══════════════════════════════════════════════════
   MAIN LOGIC — С18 FIX: robust, no more freezing
   ══════════════════════════════════════════════════ */
async function processInput(text){
  if(inputLocked) return;

  var isBtn=(S.phase==='wow_walk'||S.phase==='feat_walk'||S.phase==='wow_done'||S.phase==='loyalty_choice'||S.phase==='final');
  if(!isBtn) userSay(text);

  switch(S.phase){

    // ── ФАЗА 1-3: AI ИНТЕРВЮ ──
    case 'interview':
      userMsgCount++;
      if(userMsgCount>=MAX_USER_MSGS){
        aiSay('Хайде да продължим напред! Имам достатъчно информация.');
        S.name=S.name||'Приятел';S.biz=S.biz||'магазин';S.segment=S.segment||'среден клас';S.stores=S.stores||'1';
        showInput();inputLocked=false;
        await wait(1500);S.phase='wow_generate';await generateWow();
        break;
      }
      inputLocked=true;
      chatHistory.push({role:'user',content:text});
      showTyping();hideInput();
      try{
        var d=await aiFetch({action:'onboarding',history:chatHistory});
        hideTyping();

        var msg = d.message || d.text || '';
        if(!msg && !d._error) msg = 'Продължаваме — какво продаваш?';

        aiSay(msg);
        chatHistory.push({role:'assistant',content:msg});

        // Check if AI says we're done (phase 4+)
        if(d.phase>=4 && d.data){
          S.name=d.data.name||'';S.biz=d.data.biz||'';S.segment=d.data.segment||'';S.stores=d.data.stores||'1';
          showInput();inputLocked=false;
          await wait(2000);S.phase='wow_generate';await generateWow();
        } else {
          showInput();inputLocked=false;
        }
      }catch(e){
        hideTyping();showInput();inputLocked=false;
        console.error('Interview error:',e);
        aiSay('Нещо се обърка. Опитай пак.');
      }
      break;

    // ── ФАЗА 4: WOW ЕДИН ПО ЕДИН ──
    case 'wow_walk':
      hideActs();S.wowIdx++;
      if(S.wowIdx<wowItems.length){
        await wait(400);aiSay(wowItems[S.wowIdx],true);
        showActs([{l:'Напред →',v:'next',p:true}]);
      }else{await wait(400);showWowSummary()}
      break;

    // ── ФАЗА 4→5: СЛЕД ОБОБЩЕНИЕ ──
    case 'wow_done':
      hideActs();S.phase='feat_walk';S.featIdx=0;
      await wait(400);aiSay('Ето с какво ще спестяваш тези пари:');
      await wait(1500);var f0=featItems[0];aiSay(f0.icon+' **'+f0.title+'**\n'+f0.desc);
      showActs([{l:'Напред →',v:'next',p:true}]);
      break;

    // ── ФАЗА 5: СУПЕРСИЛИ ЕДНА ПО ЕДНА ──
    case 'feat_walk':
      hideActs();S.featIdx++;
      if(S.featIdx<featItems.length){
        await wait(400);var f=featItems[S.featIdx];aiSay(f.icon+' **'+f.title+'**\n'+f.desc);
        showActs([{l:'Напред →',v:'next',p:true}]);
      }else{S.phase='loyalty';await generateLoyalty()}
      break;

    // ── ФАЗА 6: ЛОЯЛНА ИЗБОР ──
    case 'loyalty_choice':
      hideActs();S.loyaltyChoice=text;
      await wait(400);S.phase='final';
      aiSay('Готов ли си да спестяваш пари всеки месец?');
      showActs([{l:'Да тръгваме! 🚀',v:'start',p:true}]);
      hideInput();
      break;

    // ── ФАЗА 7: ФИНАЛ ──
    case 'final':
      hideActs();await finishOnboarding();break;
  }
}

/* ══════════════════════════════════════════════════
   ФАЗА 4: WOW ГЕНЕРИРАНЕ
   ══════════════════════════════════════════════════ */
var serverLosses=null;

async function generateWow(){
  aiSay('Изчислявам загубите за твоя тип бизнес...');
  showTyping();hideInput();
  try{
    var d=await aiFetch({action:'onboarding_wow',biz:S.biz,segment:S.segment,stores:S.stores});
    hideTyping();
    if(d.losses) serverLosses=d.losses;
    if(d.scenarios&&d.scenarios.length>=5) wowItems=d.scenarios;
    else wowItems=buildFallbackWow();
  }catch(e){hideTyping();wowItems=buildFallbackWow()}
  S.phase='wow_walk';S.wowIdx=0;
  await wait(500);aiSay(wowItems[0],true);
  showActs([{l:'Напред →',v:'next',p:true}]);
}

function buildFallbackWow(){
  var L=serverLosses||{zombie:250,sizes:170,outofstock:210,upsell:85,discounts:125,monthly:500,yearly:6000};
  return[
    '💀 **Zombie Stock:** Имаш стока която стои 90+ дни без движение. Това са ~€'+L.zombie+' замразени пари всеки месец.',
    '📐 **Грешни размери:** Всеки сезон остават артикули които никой не купува. Загуба ~€'+L.sizes+'/мес от блокиран капитал.',
    '🔔 **Изпуснати продажби:** Клиент идва, стоката я няма. Губиш ~€'+L.outofstock+'/мес от празни рафтове.',
    '🛒 **Пропуснат upsell:** Клиент купува едно, никой не му предлага допълнение. ~€'+L.upsell+'/мес.',
    '💸 **Неконтролирани отстъпки:** Продавач дава 20% без причина. ~€'+L.discounts+'/мес изтичат.'
  ];
}

function showWowSummary(){
  var n=parseInt(S.stores)||1;
  var L=serverLosses;
  var monthly,yearly;
  if(L){monthly=L.monthly;yearly=L.yearly}
  else{monthly=500*n;yearly=monthly*12}
  var cost=Math.round(588+(n>1?(n-1)*119.88:0));
  var saved=yearly-cost;
  var example=saved>5000?'ремонт на обект или нова колекция':(saved>2000?'нова витрина или рекламна кампания':'ново оборудване или 2 месеца наем');
  aiSay('📊 **Обобщение:**\n\nГубиш ~**€'+monthly.toLocaleString('de')+' на месец** = **€'+yearly.toLocaleString('de')+' на година**.\n\nRunMyStore.ai струва **€49/мес + €9,99 за допълнителен магазин** = €'+cost+'/год.\n\n💰 Спестяваш **€'+saved.toLocaleString('de')+'/год** — това е '+example+'.',true);
  S.phase='wow_done';
  showActs([{l:'Покажи ми как! →',v:'next',p:true}]);
}

/* ══════════════════════════════════════════════════
   ФАЗА 6: ЛОЯЛНА
   ══════════════════════════════════════════════════ */
async function generateLoyalty(){
  aiSay('Генерирам персонализирана лоялна програма за твоя бизнес...');
  showTyping();
  var loyaltyText='';
  try{
    var d=await aiFetch({action:'loyalty_options',biz:S.biz,segment:S.segment,name:S.name});
    hideTyping();
    if(d.summary) loyaltyText=d.summary; else loyaltyText='Точки за всяка покупка: 1 EUR = 1 точка. На 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки. VIP ниво при 500 EUR оборот.';
  }catch(e){hideTyping();loyaltyText='1 EUR = 1 точка. 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки. VIP при 500 EUR оборот → постоянна -10%.';}
  aiSay('🎁 **Лоялна програма (БЕЗПЛАТНА ЗАВИНАГИ):**\n\n'+loyaltyText);
  await wait(500);S.phase='loyalty_choice';
  showActs([{l:'✅ Харесва ми, активирай!',v:'Стандартна лоялна',p:true},{l:'⚙️ Ще си направя собствена от настройки',v:'Ще настроя сам'}]);
}

/* ══════════════════════════════════════════════════
   ФИНАЛ
   ══════════════════════════════════════════════════ */
async function finishOnboarding(){
  showTyping();
  try{await fetch('onboarding-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:S.name,biz:S.biz,segment:S.segment,stores:S.stores,loyalty:S.loyaltyChoice||''})})}catch(e){}
  hideTyping();await wait(400);window.location.href='chat.php';
}
</script>
</body>
</html>
