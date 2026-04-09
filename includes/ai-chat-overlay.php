<?php
$_ov_user_name = $_SESSION['user_name'] ?? '';
$_ov_chat_messages = [];
try {
    $_ov_chat_messages = DB::run(
        'SELECT role, content, created_at FROM chat_messages
         WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
        [(int)$_SESSION['tenant_id'], (int)$_SESSION['store_id']]
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<style>
.chat-ov{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,.6);backdrop-filter:blur(8px);display:none;flex-direction:column;align-items:center;justify-content:flex-end}
.chat-ov.open{display:flex}
.chat-panel{width:100%;height:80vh;max-width:500px;background:rgba(10,12,28,.97);border:1px solid rgba(99,102,241,.4);border-radius:22px 22px 0 0;display:flex;flex-direction:column;box-shadow:0 -12px 50px rgba(99,102,241,.25);animation:ov-slideup .25s ease;overflow:hidden}
@keyframes ov-slideup{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
.chat-ph{display:flex;align-items:center;justify-content:space-between;padding:14px 16px 10px;flex-shrink:0;border-bottom:1px solid rgba(99,102,241,.15)}
.chat-ph-title{display:flex;align-items:center;gap:8px}
.chat-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#9333ea);display:flex;align-items:center;justify-content:center}
.chat-avatar svg{width:14px;height:14px}
.chat-ph-name{font-size:14px;font-weight:700;color:#a5b4fc}
.chat-close{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#6b7280;transition:background .2s}
.chat-close:active{background:rgba(239,68,68,.3)}
.chat-close svg{width:16px;height:16px}
.chat-msgs{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:10px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.chat-msgs::-webkit-scrollbar{display:none}
.chat-mg{display:flex;flex-direction:column;gap:4px}
.chat-meta{font-size:9px;color:#4b5563;display:flex;align-items:center;gap:4px}
.chat-meta.r{justify-content:flex-end}
.chat-msg{max-width:82%;padding:8px 12px;font-size:13px;line-height:1.5;word-break:break-word}
.ai-msg{background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);color:#e2e8f0;border-radius:4px 14px 14px 14px;white-space:pre-wrap}
.usr-msg{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-radius:14px 14px 4px 14px;margin-left:auto;border:.5px solid rgba(255,255,255,.1)}
.chat-typing{display:none;padding:8px 12px;background:rgba(15,20,40,.8);border:.5px solid rgba(99,102,241,.15);border-radius:4px 14px 14px 14px;width:fit-content}
.typing-dots{display:flex;gap:4px;align-items:center}
.typing-dot{width:5px;height:5px;border-radius:50%;background:#818cf8;animation:ov-bounce 1.2s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes ov-bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}
.chat-input-area{padding:8px 12px 12px;flex-shrink:0;border-top:1px solid rgba(99,102,241,.15)}
.chat-input-row{display:flex;gap:6px;align-items:center;background:rgba(10,14,28,.9);border-radius:20px;padding:4px 4px 4px 12px;border:.5px solid rgba(99,102,241,.2)}
.chat-txt{flex:1;background:transparent;border:none;color:#f1f5f9;font-size:13px;padding:8px 0;font-family:inherit;outline:none;resize:none;max-height:80px;line-height:1.4}
.chat-txt::placeholder{color:#374151}
.chat-voice{width:36px;height:36px;border-radius:50%;flex-shrink:0;position:relative;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;background:linear-gradient(135deg,#4f46e5,#9333ea);box-shadow:0 0 12px rgba(99,102,241,.3);transition:all .2s}
.chat-voice.rec{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 0 18px rgba(239,68,68,.5)}
.chat-voice svg{width:16px;height:16px;color:#fff;z-index:1}
.vring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.3);opacity:0}
.chat-voice.rec .vring{border-color:rgba(255,255,255,.5)}
.vr1{width:20px;height:20px;animation:ov-rpulse 2s 0s ease-in-out infinite}
.vr2{width:32px;height:32px;animation:ov-rpulse 2s .3s ease-in-out infinite}
.vr3{width:44px;height:44px;animation:ov-rpulse 2s .6s ease-in-out infinite}
@keyframes ov-rpulse{0%{transform:scale(.5);opacity:.7}100%{transform:scale(1.6);opacity:0}}
.chat-send{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.08);border:.5px solid rgba(255,255,255,.1);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s}
.chat-send:disabled{opacity:.2}
.chat-send svg{width:16px;height:16px}
.rec-bar{display:none;align-items:center;gap:8px;padding:6px 12px;margin-bottom:6px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px}
.rec-bar.on{display:flex}
.rec-dot-s{width:10px;height:10px;border-radius:50%;background:#ef4444;animation:ov-dotpulse 1s ease infinite;box-shadow:0 0 8px rgba(239,68,68,.6)}
@keyframes ov-dotpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
.rec-lbl{font-size:11px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:.5px}
.rec-tr{font-size:12px;color:#e2e8f0;flex:1}
</style>

<div class="chat-ov" id="chatOv">
  <div class="chat-panel">
    <div class="chat-ph">
      <div class="chat-ph-title">
        <div class="chat-avatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <span class="chat-ph-name">AI &#1040;&#1089;&#1080;&#1089;&#1090;&#1077;&#1085;&#1090;</span>
      </div>
      <div class="chat-close" onclick="closeChat()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
      </div>
    </div>
    <div class="chat-msgs" id="chatMsgs">
      <?php if (empty($_ov_chat_messages)): ?>
      <div style="text-align:center;padding:20px;color:#6b7280;font-size:12px">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;background:linear-gradient(135deg,#e5e7eb,#c7d2fe);-webkit-background-clip:text;-webkit-text-fill-color:transparent">
          &#1047;&#1076;&#1088;&#1072;&#1074;&#1077;&#1081;<?= $_ov_user_name ? ', '.htmlspecialchars($_ov_user_name) : '' ?>!
        </div>
        &#1055;&#1086;&#1087;&#1080;&#1090;&#1072;&#1081; &#1082;&#1072;&#1082;&#1074;&#1086;&#1090;&#1086; &#1080;&#1089;&#1082;&#1072;&#1096; &#8212; &#1075;&#1086;&#1074;&#1086;&#1088;&#1080; &#1080;&#1083;&#1080; &#1087;&#1080;&#1096;&#1080;.
      </div>
      <?php else: ?>
      <?php foreach ($_ov_chat_messages as $m): ?>
      <div class="chat-mg">
        <?php if ($m['role']==='assistant'): ?>
        <div class="chat-meta">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          AI &#183; <?= date('H:i', strtotime($m['created_at'])) ?>
        </div>
        <div class="chat-msg ai-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
        <?php else: ?>
        <div class="chat-meta r"><?= date('H:i', strtotime($m['created_at'])) ?></div>
        <div class="chat-msg usr-msg"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
      <div class="chat-typing" id="chatTyping">
        <div class="typing-dots"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>
      </div>
    </div>
    <div class="rec-bar" id="recBar">
      <div class="rec-dot-s"></div>
      <span class="rec-lbl">&#1047;&#1040;&#1055;&#1048;&#1057;&#1042;&#1040;</span>
      <span class="rec-tr" id="recTr">&#1057;&#1083;&#1091;&#1096;&#1072;&#1084;...</span>
    </div>
    <div class="chat-input-area">
      <div class="chat-input-row">
        <textarea class="chat-txt" id="chatIn" placeholder="&#1050;&#1072;&#1078;&#1080; &#1080;&#1083;&#1080; &#1087;&#1080;&#1096;&#1080;..." rows="1"
          oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatSend').disabled=!this.value.trim()"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
        <div class="chat-voice" id="chatVoice" onclick="toggleVoice()">
          <div class="vring vr1"></div><div class="vring vr2"></div><div class="vring vr3"></div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
            <path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
        </div>
        <button class="chat-send" id="chatSend" onclick="sendMsg()" disabled>
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function _ov$(id){return document.getElementById(id)}
function _ovEsc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function openChat(){_ov$('chatOv').classList.add('open');history.pushState({chat:true},'');_ovSB();setTimeout(function(){_ov$('chatIn').focus()},300)}
function closeChat(){_ov$('chatOv').classList.remove('open');stopVoice()}
function _ovSB(){var a=_ov$('chatMsgs');a.scrollTop=a.scrollHeight}
function openChatWithQuestion(q){openChat();setTimeout(function(){_ov$('chatIn').value=q;_ov$('chatSend').disabled=false;sendMsg()},350)}
async function sendMsg(){var inp=_ov$('chatIn'),txt=inp.value.trim();if(!txt)return;_ovAU(txt);inp.value='';inp.style.height='';_ov$('chatSend').disabled=true;_ov$('chatTyping').style.display='block';_ovSB();try{var r=await fetch('chat-send.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:txt})});var d=await r.json();_ov$('chatTyping').style.display='none';_ovAA(d.reply||d.error||'Грешка.');}catch(e){_ov$('chatTyping').style.display='none';_ovAA('Грешка при свързване.');}}
function _ovAU(txt){var g=document.createElement('div');g.className='chat-mg';var t=new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'});g.innerHTML='<div class="chat-meta r">'+t+'</div><div class="chat-msg usr-msg">'+_ovEsc(txt)+'</div>';_ov$('chatMsgs').insertBefore(g,_ov$('chatTyping'));_ovSB()}
function _ovAA(txt){var g=document.createElement('div');g.className='chat-mg';var t=new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'});g.innerHTML='<div class="chat-meta"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI \u00b7 '+t+'</div><div class="chat-msg ai-msg">'+_ovEsc(txt)+'</div>';_ov$('chatMsgs').insertBefore(g,_ov$('chatTyping'));_ovSB()}
var _ovVR=null,_ovRec=false,_ovVTR='';
function toggleVoice(){if(_ovRec){stopVoice();return}var SR=window.SpeechRecognition||window.webkitSpeechRecognition;if(!SR){if(typeof showToast==='function')showToast('No voice support');return}_ovRec=true;_ovVTR='';_ov$('chatVoice').classList.add('rec');_ov$('recBar').classList.add('on');_ov$('recTr').innerText='\u0421\u043b\u0443\u0448\u0430\u043c...';_ovVR=new SR();_ovVR.lang='bg-BG';_ovVR.continuous=false;_ovVR.interimResults=true;_ovVR.onresult=function(e){var fin='',int_='';for(var i=e.resultIndex;i<e.results.length;i++){if(e.results[i].isFinal)fin+=e.results[i][0].transcript;else int_+=e.results[i][0].transcript}if(fin)_ovVTR=fin;_ov$('recTr').innerText=_ovVTR||int_||'\u0421\u043b\u0443\u0448\u0430\u043c...'};_ovVR.onend=function(){_ovRec=false;_ov$('chatVoice').classList.remove('rec');_ov$('recBar').classList.remove('on');if(_ovVTR){_ov$('chatIn').value=_ovVTR;_ov$('chatSend').disabled=false;sendMsg()}};_ovVR.onerror=function(e){stopVoice()};try{_ovVR.start()}catch(e){stopVoice()}}
function stopVoice(){_ovRec=false;_ovVTR='';var v=_ov$('chatVoice'),r=_ov$('recBar');if(v)v.classList.remove('rec');if(r)r.classList.remove('on');if(_ovVR){try{_ovVR.stop()}catch(e){}_ovVR=null}}
window.addEventListener('popstate',function(){if(_ov$('chatOv')&&_ov$('chatOv').classList.contains('open'))closeChat()});
if(_ov$('chatOv'))_ov$('chatOv').addEventListener('click',function(e){if(e.target===_ov$('chatOv'))closeChat()});
window.addEventListener('DOMContentLoaded',function(){if(_ov$('chatMsgs'))_ovSB()});
</script>