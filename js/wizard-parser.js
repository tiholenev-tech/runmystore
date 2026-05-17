/**
 * wizard-parser.js — sacred JS extract от products.php
 * S148 ФАЗА 2a — 2026-05-17
 *
 * 1:1 копия (Q2 одобрено от Тих):
 *   products.php 14341-14373 — _wizMicWhisper (audio recording shell)
 *   products.php 14418-14436 — _bgPrice
 *   products.php 14441-14457 — _BG_WORD_NUMS / _BG_WORD_KEYS / _CYR_B_WP / _CYR_A_WP
 *   products.php 14458-14495 — _wizPriceParse
 *
 * ЕДИНСТВЕНА промяна спрямо source:
 *   _wizMicWhisper ред 14362: fetch('/services/voice-tier2.php', ...)
 *   → fetch('/services/wizard-bridge.php?action=mic_whisper', ...)
 *   (sacred endpoint остава непроменен; bridge е новият URL surface)
 *
 * Външни референции (не са дефинирани тук — wizard-v6.php script ги дефинира):
 *   _wizClearHighlights(), _wizMicWebSpeech(field), _wizMicApply(field, text)
 *
 * Sacred status: products.php остава 1:1 непроменен; нищо в sacred files не пипано.
 */

function _wizMicWhisper(field,lang){
    _wizClearHighlights();
    var fieldMap={retail_price:'wPrice',cost_price:'wCostPrice',wholesale_price:'wWprice',quantity:'wSingleQty',min_quantity:'wMinQty',barcode:'wBarcode',code:'wCode'};
    var targetEl=document.getElementById(fieldMap[field]);
    var targetFg=targetEl?targetEl.closest('.fg'):null;
    if(targetFg)targetFg.classList.add('wiz-active');
    var micBtn=targetFg?targetFg.querySelector('.wiz-mic'):null;
    if(micBtn)micBtn.classList.add('recording');
    var clearUI=function(){if(micBtn)micBtn.classList.remove('recording')};
    var fallback=function(){clearUI();_wizMicWebSpeech(field)};
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
        var rec;
        try{rec=new MediaRecorder(stream,{mimeType:'audio/webm;codecs=opus'})}
        catch(e){stream.getTracks().forEach(function(t){t.stop()});fallback();return}
        var chunks=[];
        rec.ondataavailable=function(ev){if(ev.data&&ev.data.size>0)chunks.push(ev.data)};
        rec.onstop=function(){
            stream.getTracks().forEach(function(t){t.stop()});
            if(!chunks.length){fallback();return}
            var blob=new Blob(chunks,{type:'audio/webm'});
            var fd=new FormData();fd.append('audio',blob,'rec.webm');fd.append('lang',lang||'bg');
            fetch('/services/wizard-bridge.php?action=mic_whisper',{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.ok?r.json():Promise.reject(r.status)})
                .then(function(j){
                    if(j&&j.ok&&j.data){var t=(j.data.transcript_normalized||j.data.transcript||'').trim();if(t){clearUI();_wizMicApply(field,t);return}}
                    fallback();
                })
                .catch(function(){fallback()});
        };
        rec.start();
        setTimeout(function(){if(rec.state==='recording'){try{rec.stop()}catch(e){}}},5000);
    }).catch(function(){fallback()});
}

function _bgPrice(t,forcePrice){
    var raw=t.trim().toLowerCase();
    var hasStotinki=(/стотинки|стот\.|цент[аи]?|cents?|пени|пфениг|сантим|копейк/i).test(raw);
    raw=raw.replace(/лева|лв|евро|€|eur|euro|usd|\$|gbp|£|ron|lei|лей|крон[аи]?|злот[аи]?|динар[аи]?|форинт[аи]?|франк[аи]?|стотинки|стот\.|цент[аи]?|cents?|пени|penny|pence|пфениг[аи]?|pfennig|сантим[аи]?|копейк[аи]?/gi,' ').replace(/\s+/g,' ').trim();
    var dn=raw.replace(',','.');var pf=parseFloat(dn);if(!isNaN(pf)&&/^\d+\.?\d*$/.test(dn))return pf;
    var ones={'нула':0,'един':1,'една':1,'едно':1,'два':2,'две':2,'три':3,'четири':4,'пет':5,'шест':6,'седем':7,'осем':8,'девет':9,'десет':10,'единадесет':11,'единайсет':11,'дванадесет':12,'дванайсет':12,'тринадесет':13,'тринайсет':13,'четиринадесет':14,'четиринайсет':14,'петнадесет':15,'петнайсет':15,'шестнадесет':16,'шестнайсет':16,'седемнадесет':17,'седемнайсет':17,'осемнадесет':18,'осемнайсет':18,'деветнадесет':19,'деветнайсет':19,'двадесет':20,'двайсет':20,'тридесет':30,'трийсет':30,'четиридесет':40,'четирийсет':40,'петдесет':50,'шестдесет':60,'седемдесет':70,'осемдесет':80,'деветдесет':90,'сто':100};
    var tens=[10,20,30,40,50,60,70,80,90];
    function word(w){w=w.trim();var n=parseInt(w);if(!isNaN(n))return n;if(ones[w]!==undefined)return ones[w];return null}
    var parts=raw.split(/\s+и\s+/);
    if(parts.length===1){return word(parts[0])}
    if(parts.length===2){var a=word(parts[0]);var b=word(parts[1]);
        if(a!==null&&b!==null){
            if(forcePrice)return parseFloat(a+'.'+String(b).padStart(2,'0'));
            if(hasStotinki&&tens.indexOf(b)!==-1)return parseFloat(a+'.'+String(b).padStart(2,'0'));
            if(a>=0&&a<=9&&tens.indexOf(b)!==-1)return parseFloat(a+'.'+String(b).padStart(2,'0'));
            return a+b}}
    if(parts.length===3){var a=word(parts[0]);var b=word(parts[1]);var c=word(parts[2]);
        if(a!==null&&b!==null&&c!==null){var leva=a+b;return parseFloat(leva+'.'+String(c).padStart(2,'0'))}}
    return null}

// S95.WIZARD.VOICE: price parser за Bulgarian voice. Word→digit substitution + heuristics.
// Покрива: "1", "едно", "4,55", "4.55", "4 запетая 55", "4 точка 55", "4 лева 55 стотинки",
// "едно петдесет и пет", "сто и петдесет", "пет лева и двадесет стотинки", "20 лв", и т.н.
var _BG_WORD_NUMS={
    'четиринадесет':'14','четиринайсет':'14','четиридесет':'40','четирийсет':'40','четирсе':'40','четирсет':'40',
    'четиристотин':'400','четири':'4',
    'седемнадесет':'17','седемнайсет':'17','седемстотин':'700','седемдесет':'70','седемсе':'70','седемсет':'70','седем':'7',
    'осемнадесет':'18','осемнайсет':'18','осемстотин':'800','осемдесет':'80','осемсе':'80','осемсет':'80','осем':'8',
    'деветнадесет':'19','деветнайсет':'19','деветстотин':'900','деветдесет':'90','деветсе':'90','деветсет':'90','девет':'9',
    'дванадесет':'12','дванайсет':'12','двадесет':'20','двайсет':'20','двайсе':'20',
    'тринадесет':'13','тринайсет':'13','тридесет':'30','трийсет':'30','трийсе':'30','триста':'300','три':'3',
    'единадесет':'11','единайсет':'11','първа':'1','първи':'1','първо':'1','един':'1','една':'1','едно':'1',
    'петнадесет':'15','петнайсет':'15','петстотин':'500','петдесет':'50','педесе':'50','педесет':'50','пет':'5',
    'шестнадесет':'16','шестнайсет':'16','шестстотин':'600','шестдесет':'60','шейсе':'60','шейсет':'60','шест':'6',
    'двеста':'200','втора':'2','втори':'2','второ':'2','две':'2','два':'2',
    'хиляда':'1000','хиляди':'1000','сто':'100','десет':'10','нула':'0','половин':'0.5','половинка':'0.5'
};
var _BG_WORD_KEYS=Object.keys(_BG_WORD_NUMS).sort(function(a,b){return b.length-a.length});
var _CYR_B_WP="(?<![\u0400-\u04FF0-9])";
var _CYR_A_WP="(?![\u0400-\u04FF0-9])";
function _wizPriceParse(text){
    if(text===undefined||text===null)return null;
    var raw=String(text).toLowerCase().trim();
    if(!raw)return null;
    raw=raw.replace(/[.,!?;:\u201E\u201C\u201D]/g,' ').replace(/\s+/g,' ').trim();
    if (/^и\s/i.test(raw)) raw = "1 " + raw.substring(2);
    var hasStotinki=new RegExp(_CYR_B_WP+'(стотинки?|стот|цент[аи]?|cents?|копейк|пени)'+_CYR_A_WP,'i').test(raw);
    var pre=raw.replace(new RegExp(_CYR_B_WP+'запетая'+_CYR_A_WP,'gi'),',').replace(new RegExp(_CYR_B_WP+'точка'+_CYR_A_WP,'gi'),'.');
    for(var i=0;i<_BG_WORD_KEYS.length;i++){
        pre=pre.replace(new RegExp(_CYR_B_WP+_BG_WORD_KEYS[i]+_CYR_A_WP,'gi'),' '+_BG_WORD_NUMS[_BG_WORD_KEYS[i]]+' ');
    }
    pre=pre.replace(/\s+/g,' ').trim();
    var _FILLER_WP='(лева?|лв|евро|€|eur|euro|usd|gbp|ron|lei|лей|стотинки?|стот|цент[аи]?|cents?|пени|пенс|сантим[аи]?|копейк[аи]?|около|примерно|горе|долу|май|по|и|на|за|от)';
    var cleaned=pre.replace(new RegExp(_CYR_B_WP+_FILLER_WP+_CYR_A_WP,'gi'),' ').replace(/[$£]/g,' ').replace(/\s+/g,' ').trim();
    var nums=cleaned.match(/\d+(?:[.,]\d+)?/g);
    if(!nums||!nums.length)return null;
    var first=nums[0].replace(',','.');
    if(first.indexOf('.')>=0){var f=parseFloat(first);if(!isNaN(f))return f}
    var n0=parseFloat(first);
    if(isNaN(n0))return null;
    if(nums.length===1)return n0;
    var n1=parseFloat(nums[1].replace(',','.'));
    if(isNaN(n1))return n0;
    // "X Y0 Z" (e.g., 1 50 5 от "едно петдесет и пет") → X.(Y0+Z) = 1.55
    if(nums.length>=3){
        var n2=parseFloat(nums[2].replace(',','.'));
        if(!isNaN(n2)&&n1>=10&&n1<=90&&n1%10===0&&n2>=1&&n2<=9){
            return parseFloat(n0+'.'+String(n1+n2).padStart(2,'0'));
        }
    }
    // "трийсе и две [лева]" → n0=30 (multi-of-10), n1=2 (<10), no stotinki → 32 (combine)
    if(!hasStotinki && n0>=10 && n0<100 && n0%10===0 && n1>=1 && n1<10){return n0+n1}
    // "сто и петдесет" → 100+50 = 150 (combine when n0>=100, no stotinki)
    if(n0>=100 && !hasStotinki){return n0+n1}
    // Default 2-token: leva.stotinki
    if(n1<100)return parseFloat(n0+'.'+String(Math.round(n1)).padStart(2,'0'));
    return n0+n1/Math.pow(10,String(Math.round(n1)).length);
}
