#!/usr/bin/env python3
"""
RunMyStore.ai — Wizard Rewrite Deployment
Run on server: python3 deploy_wizard.py
"""
import os, sys

TARGET = '/var/www/runmystore/products.php'
BACKUP = '/var/www/runmystore/products.php.bak2'

if not os.path.exists(TARGET):
    print(f"ERROR: {TARGET} not found"); sys.exit(1)

# Backup
import shutil
shutil.copy2(TARGET, BACKUP)
print(f"Backup: {BACKUP}")

with open(TARGET, 'r') as f:
    content = f.read()

print(f"Original: {len(content)} bytes, {content.count(chr(10))} lines")


# ═══ 1. ADD CSS ═══
NEW_CSS = """
/* ═══ WIZARD INFO SYSTEM ═══ */
.wiz-info-btn{display:inline-flex;width:18px;height:18px;border-radius:50%;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);align-items:center;justify-content:center;font-size:10px;font-weight:700;cursor:pointer;margin-left:4px;vertical-align:middle;transition:all 0.15s;flex-shrink:0}
.wiz-info-btn:active{background:rgba(99,102,241,0.2);transform:scale(0.9)}
.wiz-info-overlay{position:fixed;inset:0;z-index:400;background:rgba(3,7,18,0.7);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px}
.wiz-info-box{background:#080818;border:1px solid var(--border-glow);border-radius:16px;padding:16px;max-width:320px;width:100%;box-shadow:0 10px 40px rgba(99,102,241,0.2)}
"""

if '.wiz-info-btn' not in content:
    content = content.replace('</style>', NEW_CSS + '\n</style>', 1)
    print("CSS injected")
else:
    print("CSS already present")


# ═══ 2. READ NEW WIZARD JS ═══
NEW_WIZARD_JS = r"""
// ═══════════════════════════════════════════════════════════
// WIZARD REWRITE — 8 стъпки, info бутони, voice-compatible
// ═══════════════════════════════════════════════════════════

const WIZ_LABELS=['Вид','Снимка','AI обработка','Основна информация','Вариации','Детайли','Преглед и запис','Етикети'];

const WIZ_INFO={
    type_single:'Единичен артикул без варианти — например една чанта, едно бижу, или артикул който се продава само в един вид.',
    type_variant:'Артикул с варианти — различни размери, цветове или комбинации. Например тениска в S/M/L/XL и Черен/Бял.',
    photo:'Снимката помага на AI да разпознае артикула, генерира описание и обработи снимката. Сложи продукта на равна светла повърхност, без други предмети, с добро осветление.',
    studio:'AI обработва снимката ти — махане на фон, обличане на модел, студийна снимка за бижута и предмети. Снимката се използва и за AI описание на артикула.',
    name:'Наименованието е как клиентите ще виждат артикула. Бъди конкретен: "Nike Air Max 90 Черни" е по-добре от "Маратонки".',
    code:'Артикулният номер е уникален код за вътрешно ползване. AI го генерира автоматично ако е празен.',
    price:'Цената на дребно е крайната цена за клиента с ДДС.',
    wholesale:'Цена на едро — за клиенти които купуват на количество.',
    barcode:'Баркодът (EAN/UPC) се генерира автоматично ако е празен. Може да сканираш съществуващ с камерата.',
    supplier:'Доставчикът е от кого купуваш тази стока.',
    category:'Категорията помага да организираш стоката — напр. "Тениски", "Обувки", "Бижута".',
    subcategory:'Подкатегорията е по-тесен филтър — напр. "Спортни" в "Обувки".',
    variations:'Вариациите са различните версии на артикула — размер, цвят, материал и др. AI предлага типични за твоя бизнес.',
    unit:'Мерната единица определя как се брои стоката — бройка, чифт, комплект, метър и др.',
    min_qty:'Минималното количество е границата под която AI те предупреждава да поръчаш. Напр. 3 означава: под 3 бройки = "Свършва!"',
    description:'SEO описанието помага артикулът да се намира в Google. AI го генерира от снимката, името и вариациите.',
    bg_removal:'Премахва фона на снимката и го заменя с чисто бяло. Идеално за онлайн магазин, Instagram или етикети.',
    tryon_clothes:'AI облича артикула на модел. Избери тип модел и AI генерира реалистична снимка. Запазва точните пропорции на дрехата.',
    tryon_objects:'AI създава студийна снимка на предмета — бижута, обувки, чанти, аксесоари. Избери стил на снимката.',
    credits:'Безплатните кредити са включени в месечния ти план. Бял фон: 0.05 EUR/бр, AI Магия: 0.50 EUR/бр. Когато свършат, можеш да купиш допълнителни.'
};

function showWizInfo(key){
    const text=WIZ_INFO[key]||'Информация не е налична.';
    const el=document.createElement('div');
    el.className='wiz-info-overlay';
    el.onclick=function(e){if(e.target===el)el.remove()};
    el.innerHTML='<div class="wiz-info-box"><div style="font-size:13px;color:var(--text-primary);line-height:1.6">'+esc(text)+'</div><button class="abtn" onclick="this.closest(\'.wiz-info-overlay\').remove()" style="margin-top:10px">Разбрах ✓</button></div>';
    document.body.appendChild(el);
}

function infoBtn(key,color){
    color=color||'var(--indigo-400)';
    return '<div class="wiz-info-btn" onclick="event.stopPropagation();showWizInfo(\''+key+'\')" style="color:'+color+'">i</div>';
}

function fieldLabel(text,key,extra){
    extra=extra||'';
    return '<label class="fl">'+text+' '+infoBtn(key)+extra+'</label>';
}

// ─── MANUAL WIZARD ───
function openManualWizard(){
    S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;
    S.wizVoiceMode=false;
    document.getElementById('wizTitle').textContent='Нов артикул';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}

// ─── VOICE WIZARD — same steps, with skip buttons ───
function openVoiceWizard(){
    S.wizStep=0;S.wizData={};S.wizType=null;S.wizEditId=null;
    S.wizVoiceMode=true;
    document.getElementById('wizTitle').textContent='Нов артикул (с глас)';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
    // Auto voice for step 0
    setTimeout(()=>voiceForStep(0),500);
}

function voiceForStep(step){
    if(!S.wizVoiceMode)return;
    const hints={
        0:'Кажи: единичен или с варианти',
        1:null, // photo - manual
        2:null, // studio - manual
        3:'Кажи име, цена и доставчик',
        4:'Кажи размерите, после цветовете',
        5:null, // details - skip
        6:null, // preview - confirm
        7:null  // labels - manual
    };
    if(hints[step]){
        openVoice(hints[step],text=>handleVoiceStep(step,text));
    }
}

function handleVoiceStep(step,text){
    const t=text.toLowerCase();
    if(step===0){
        if(t.includes('вариант')||t.includes('размер')||t.includes('цвят'))S.wizType='variant';
        else S.wizType='single';
        showToast(S.wizType==='variant'?'С варианти ✓':'Единичен ✓','success');
        wizGo(1);
    }else if(step===3){
        parseVoiceToFields(text);
        renderWizard();
        showToast('Попълнено ✓','success');
    }else if(step===4){
        if(!S.wizData.axes)S.wizData.axes=[];
        const vals=text.split(/[\s,]+/).filter(Boolean);
        if(vals.length){
            const hasSize=S.wizData.axes.find(a=>a.name.toLowerCase().includes('размер'));
            if(!hasSize){
                S.wizData.axes.push({name:'Размер',values:vals});
                showToast('Размери добавени ✓','success');
                renderWizard();
                setTimeout(()=>openVoice('Цветове? Или кажи "без"',text2=>{
                    const t2=text2.toLowerCase();
                    if(!t2.includes('без')&&!t2.includes('няма')){
                        const v2=text2.split(/[\s,]+/).filter(Boolean);
                        if(v2.length)S.wizData.axes.push({name:'Цвят',values:v2});
                    }
                    renderWizard();
                }),600);
            }else{
                S.wizData.axes.push({name:'Цвят',values:vals});
                renderWizard();
            }
        }
    }
}

function parseVoiceToFields(text){
    const priceMatch=text.match(/(\d+[.,]?\d*)\s*(лева|лв|евро|€|eur)?/i);
    if(priceMatch)S.wizData.retail_price=parseFloat(priceMatch[1].replace(',','.'));
    const tl=text.toLowerCase();
    for(const s of CFG.suppliers){if(tl.includes(s.name.toLowerCase())){S.wizData.supplier_id=s.id;break}}
    for(const c of CFG.categories){if(tl.includes(c.name.toLowerCase())){S.wizData.category_id=c.id;break}}
    if(!S.wizData.name){
        let name=text.replace(/(\d+[.,]?\d*)\s*(лева|лв|евро|€|eur)?/gi,'').trim();
        for(const s of CFG.suppliers)name=name.replace(new RegExp(s.name,'gi'),'');
        for(const c of CFG.categories)name=name.replace(new RegExp(c.name,'gi'),'');
        name=name.replace(/\s+/g,' ').trim();
        if(name.length>2)S.wizData.name=name;
    }
}

function closeWizard(){
    document.getElementById('wizModal').classList.remove('open');
    document.body.style.overflow='';
}

function wizGo(step){
    if(S.wizStep>=3&&S.wizStep<=5)wizCollectData();
    S.wizStep=step;
    renderWizard();
    if(S.wizVoiceMode)setTimeout(()=>voiceForStep(step),400);
}

function renderWizard(){
    let sb='';
    for(let i=0;i<8;i++){
        let cls=i<S.wizStep?'done':i===S.wizStep?'active':'';
        sb+='<div class="wiz-step '+cls+'"></div>';
    }
    document.getElementById('wizSteps').innerHTML=sb;
    document.getElementById('wizLabel').innerHTML=(S.wizStep+1)+' · <b>'+WIZ_LABELS[S.wizStep]+'</b>';
    document.getElementById('wizBody').innerHTML=renderWizPage(S.wizStep);
    document.getElementById('wizBody').scrollTop=0;
    // Subcategory loader for step 3
    if(S.wizStep===3){
        const wCat=document.getElementById('wCat');
        if(wCat){wCat.onchange=async function(){
            const id=this.value;const sel=document.getElementById('wSubcat');
            sel.innerHTML='<option value="">\u2014 Няма \u2014</option>';
            if(!id)return;
            const d=await api('products.php?ajax=subcategories&parent_id='+id);
            if(d&&d.length)d.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;sel.appendChild(o)});
        };if(S.wizData.category_id)wCat.onchange()}
    }
}

function renderWizPage(step){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo('+(step+1)+')" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // ═══ STEP 0: ВИД ═══
    if(step===0){
        const ss=S.wizType==='single'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1)':'';
        const vs=S.wizType==='variant'?'border-color:var(--indigo-500);background:rgba(99,102,241,0.1)':'';
        return '<div class="wiz-page active"><div style="display:flex;align-items:center;gap:6px;margin-bottom:10px"><div style="font-size:15px;font-weight:700">Какъв е артикулът?</div>'+infoBtn('type_single')+'</div>'+
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">'+
        '<div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;'+ss+'" onclick="S.wizType=\'single\';renderWizard()"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><rect x="3" y="3" width="18" height="18" rx="3"/></svg><div style="font-size:13px;font-weight:600">Единичен</div><div style="font-size:10px;color:var(--text-secondary)">Без варианти</div>'+infoBtn('type_single')+'</div>'+
        '<div style="padding:18px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);text-align:center;cursor:pointer;'+vs+'" onclick="S.wizType=\'variant\';renderWizard()"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><rect x="2" y="2" width="9" height="9" rx="2"/><rect x="13" y="2" width="9" height="9" rx="2"/><rect x="2" y="13" width="9" height="9" rx="2"/><rect x="13" y="13" width="9" height="9" rx="2"/></svg><div style="font-size:13px;font-weight:600">С варианти</div><div style="font-size:10px;color:var(--text-secondary)">Размери, цветове...</div>'+infoBtn('type_variant')+'</div></div>'+
        (S.wizType?'<button class="abtn primary" onclick="wizGo(1)">Напред →</button>':'');
    }

    // ═══ STEP 1: СНИМКА ═══
    if(step===1){
        return '<div class="wiz-page active" style="text-align:center">'+
        '<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:4px"><div style="font-size:15px;font-weight:600">Снимай артикула</div>'+infoBtn('photo')+'</div>'+
        '<div style="font-size:11px;color:var(--text-secondary);margin-bottom:14px">Силно препоръчително — AI използва снимката за описание и обработка</div>'+
        '<div style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:10px;margin-bottom:14px;text-align:left"><div style="font-size:9px;font-weight:700;color:#fbbf24;margin-bottom:4px">СЪВЕТИ ЗА СНИМКА</div><div style="font-size:10px;color:#d4d4d8;line-height:1.6">✓ Сложи на равна светла повърхност<br>✓ Без други предмети около<br>✓ Добро осветление<br>✓ Ясна, неразмазана снимка<br>✓ Максимално добро качество</div></div>'+
        '<div style="display:flex;gap:8px;margin-bottom:14px">'+
        '<div style="flex:1;padding:16px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);cursor:pointer" onclick="wizTakePhoto()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg><div style="font-size:11px;font-weight:600">Камера</div></div>'+
        '<div style="flex:1;padding:16px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border-subtle);cursor:pointer" onclick="document.getElementById(\'photoInput\').click()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="1.5" style="margin-bottom:4px"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg><div style="font-size:11px;font-weight:600">Галерия</div></div></div>'+
        '<div id="wizPhotoPreview"></div><div id="wizScanResult"></div>'+
        '<button class="abtn primary" onclick="wizGo(2)" style="margin-top:10px">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(0)" style="margin-top:6px">← Назад</button>'+
        vskip+'</div>';
    }

    // ═══ STEP 2: AI IMAGE STUDIO ═══
    if(step===2){
        return renderStudioStep();
    }

    // ═══ STEP 3: ОСНОВНА ИНФОРМАЦИЯ ═══
    if(step===3){
        const nm=S.wizData.name||'';const pr=S.wizData.retail_price||'';const wp=S.wizData.wholesale_price||'';
        let supO='<option value="">— Избери —</option>';
        CFG.suppliers.forEach(s=>supO+='<option value="'+s.id+'" '+(S.wizData.supplier_id==s.id?'selected':'')+'>'+esc(s.name)+'</option>');
        let catO='<option value="">— Избери —</option>';
        CFG.categories.filter(c=>!c.parent_id).forEach(c=>catO+='<option value="'+c.id+'" '+(S.wizData.category_id==c.id?'selected':'')+'>'+esc(c.name)+'</option>');
        const wpHidden=CFG.skipWholesale?'display:none':'';
        return '<div class="wiz-page active">'+
        '<div class="fg">'+fieldLabel('Наименование *','name')+'<input type="text" class="fc" id="wName" value="'+esc(nm)+'" placeholder="напр. Nike Air Max 90 Черни"></div>'+
        '<div class="fg">'+fieldLabel('Артикулен номер *','code','<span class="hint">(AI генерира ако е празно)</span>')+'<input type="text" class="fc" id="wCode" value="'+esc(S.wizData.code||'')+'" placeholder="автоматично"></div>'+
        '<div class="form-row">'+
        '<div class="fg">'+fieldLabel('Цена дребно *','price')+'<input type="number" step="0.01" class="fc" id="wPrice" value="'+pr+'" placeholder="0,00"></div>'+
        '<div class="fg" style="'+wpHidden+'">'+fieldLabel('Цена едро','wholesale')+'<input type="number" step="0.01" class="fc" id="wWprice" value="'+wp+'" placeholder="0,00"></div></div>'+
        '<div class="fg">'+fieldLabel('Баркод','barcode','<span class="hint">(автоматично ако е празно)</span>')+'<input type="text" class="fc" id="wBarcode" value="'+esc(S.wizData.barcode||'')+'" placeholder="сканирай или въведи"></div>'+
        '<div class="fg">'+fieldLabel('Доставчик','supplier','<span class="fl-add" onclick="toggleInl(\'inlSup\')">+ Нов</span>')+'<select class="fc" id="wSup">'+supO+'</select><div class="inline-add" id="inlSup"><input type="text" placeholder="Име" id="inlSupName"><button onclick="wizAddInline(\'supplier\')">Запази</button></div></div>'+
        '<div class="fg">'+fieldLabel('Категория','category','<span class="fl-add" onclick="toggleInl(\'inlCat\')">+ Нова</span>')+'<select class="fc" id="wCat">'+catO+'</select><div class="inline-add" id="inlCat"><input type="text" placeholder="Име" id="inlCatName"><button onclick="wizAddInline(\'category\')">Запази</button></div></div>'+
        '<div class="fg">'+fieldLabel('Подкатегория','subcategory','<span class="fl-add" onclick="toggleInl(\'inlSubcat\')">+ Нова</span>')+'<select class="fc" id="wSubcat"><option value="">— Няма —</option></select><div class="inline-add" id="inlSubcat"><input type="text" placeholder="Име" id="inlSubcatName"><button onclick="wizAddSubcat()">Запази</button></div></div>'+
        '<button class="abtn primary" onclick="wizGo(4)">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(2)" style="margin-top:6px">← Назад</button>'+
        vskip+'</div>';
    }
    return renderWizPagePart2(step);
}
function renderWizPagePart2(step){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo('+(step+1)+')" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // ═══ STEP 4: ВАРИАЦИИ ═══
    if(step===4){
        if(S.wizType==='single')return '<div class="wiz-page active"><div style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Единичен артикул — без вариации.</div><button class="abtn primary" onclick="wizGo(5)">Напред →</button><button class="abtn" onclick="wizGo(3)" style="margin-top:6px">← Назад</button></div>';

        // Pre-load from biz-coefficients if no axes yet
        if(!S.wizData.axes||S.wizData.axes.length===0){
            S.wizData.axes=[];
            // Try to get from biz-coefficients via PHP-injected data
            if(window._bizVariants&&window._bizVariants.variant_fields){
                window._bizVariants.variant_fields.forEach(f=>{
                    const presets=window._bizVariants.variant_presets?.[f]||[];
                    S.wizData.axes.push({name:f,values:[...presets]});
                });
            }
            if(S.wizData.axes.length===0){
                S.wizData.axes.push({name:'Размер',values:[]});
                S.wizData.axes.push({name:'Цвят',values:[]});
            }
        }

        let axesH='';
        S.wizData.axes.forEach((ax,i)=>{
            const vals=ax.values.map((v,vi)=>'<span style="display:inline-block;padding:3px 9px;border-radius:6px;background:rgba(99,102,241,0.12);color:var(--indigo-300);font-size:11px;font-weight:600;margin:2px;cursor:pointer" onclick="S.wizData.axes['+i+'].values.splice('+vi+',1);renderWizard()">'+esc(v)+' ✕</span>').join('');
            axesH+='<div style="margin-bottom:10px;padding:10px;border-radius:10px;background:rgba(17,24,44,0.5);border:1px solid var(--border-subtle)">'+
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><span style="font-size:12px;font-weight:700;color:var(--indigo-300)">'+esc(ax.name)+'</span><span style="font-size:10px;color:var(--danger);cursor:pointer" onclick="S.wizData.axes.splice('+i+',1);renderWizard()">✕ Махни</span></div>'+
            '<div style="margin-bottom:6px">'+(vals||'<span style="font-size:10px;color:var(--text-secondary)">Добави стойности</span>')+'</div>'+
            '<div style="display:flex;gap:6px"><input type="text" class="fc" id="axVal'+i+'" placeholder="Добави стойност..." style="font-size:12px;padding:6px 10px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizAddAxisValue('+i+')}"><button class="abtn" style="width:auto;padding:6px 12px;font-size:11px" onclick="wizAddAxisValue('+i+')">+</button></div></div>';
        });

        const combos=wizCountCombinations();
        return '<div class="wiz-page active">'+
        '<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px"><div style="font-size:9px;font-weight:700;color:var(--text-secondary);text-transform:uppercase">Вариации на артикула</div>'+infoBtn('variations')+'</div>'+
        axesH+
        '<div style="display:flex;gap:6px;margin-bottom:10px"><input type="text" class="fc" id="newAxisName" placeholder="Добави нова вариация (напр. Материал)" style="font-size:12px;padding:6px 10px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();wizAddAxis()}"><button class="abtn" style="width:auto;padding:6px 12px;font-size:11px" onclick="wizAddAxis()">+ Добави</button></div>'+
        (combos>0?'<div style="font-size:10px;color:var(--text-secondary);margin-bottom:10px;padding:6px 10px;border-radius:6px;background:rgba(99,102,241,0.04)">Кръстоска: <b style="color:var(--indigo-300)">'+combos+'</b> вариации ще бъдат създадени</div>':'')+
        '<button class="abtn primary" onclick="wizGo(5)">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(3)" style="margin-top:6px">← Назад</button>'+vskip+'</div>';
    }

    // ═══ STEP 5: ДЕТАЙЛИ ═══
    if(step===5){
        let unitO='';CFG.units.forEach(u=>unitO+='<option value="'+u+'" '+(S.wizData.unit===u?'selected':'')+'>'+u+'</option>');
        return '<div class="wiz-page active">'+
        '<div class="fg">'+fieldLabel('Мерна единица','unit','<span class="fl-add" onclick="toggleInl(\'inlUnit\')">+ Друга</span>')+
        '<select class="fc" id="wUnit">'+unitO+'</select>'+
        '<div class="inline-add" id="inlUnit"><input type="text" placeholder="напр. метър, кг..." id="inlUnitName"><button onclick="wizAddUnit()">Запази</button></div></div>'+
        '<div class="fg">'+fieldLabel('Минимално количество','min_qty')+
        '<input type="number" class="fc" id="wMinQty" value="'+(S.wizData.min_quantity||0)+'" placeholder="0"></div>'+
        '<button class="abtn primary" onclick="wizGoPreview()">Напред →</button>'+
        '<button class="abtn" onclick="wizGo(4)" style="margin-top:6px">← Назад</button>'+vskip+'</div>';
    }

    // ═══ STEP 6: ПРЕГЛЕД + AI ОПИСАНИЕ + ЗАПИС ═══
    if(step===6){
        wizCollectData();
        const combos=wizBuildCombinations();
        let combosH='';
        if(combos.length<=1&&!combos[0]?.axisValues){
            combosH='<div class="form-row"><div class="fg"><label class="fl">Начална наличност</label><input type="number" class="fc" id="wSingleQty" value="0"></div><div class="fg"><label class="fl">Мин. наличност</label><input type="number" class="fc" id="wSingleMin" value="'+(S.wizData.min_quantity||0)+'"></div></div>';
        }else{
            combosH='<div style="font-size:9px;color:var(--text-secondary);margin-bottom:4px;font-weight:700;text-transform:uppercase">'+combos.length+' вариации — начална наличност</div>';
            combos.forEach((v,i)=>{
                const label=v.axisValues||v.label||'';
                combosH+='<div style="display:flex;gap:4px;align-items:center;margin-bottom:3px;padding:4px 8px;border-radius:6px;background:rgba(17,24,44,0.3)"><span style="font-size:11px;flex:1">'+esc(label)+'</span><input type="number" class="fc" style="width:50px;padding:4px;text-align:center;font-size:12px" value="0" data-combo="'+i+'"></div>';
            });
        }

        // AI description
        let descH='<div class="fg" style="margin-top:10px">'+fieldLabel('AI SEO описание','description')+
        '<textarea class="fc" id="wDesc" rows="3" placeholder="AI генерира...">'+(S.wizData.description?esc(S.wizData.description):'')+'</textarea>'+
        '<button class="abtn" onclick="wizGenDescription()" style="margin-top:4px;font-size:11px">✦ Генерирай AI описание</button></div>';

        return '<div class="wiz-page active">'+
        '<div style="font-size:14px;font-weight:700;margin-bottom:2px">'+esc(S.wizData.name||'Артикул')+'</div>'+
        '<div style="font-size:11px;color:var(--text-secondary);margin-bottom:10px">Цена: '+fmtPrice(S.wizData.retail_price)+' · Код: '+esc(S.wizData.code||'AI генерира')+'</div>'+
        (S.wizData.studioResult?'<div style="margin-bottom:10px;text-align:center"><img src="'+S.wizData.studioResult+'" style="max-width:120px;border-radius:10px;border:1px solid var(--border-subtle)"></div>':'')+
        combosH+
        descH+
        '<button class="abtn save" style="margin-top:14px;font-size:15px;padding:14px" onclick="wizSave()">✓ Запази артикула</button>'+
        '<button class="abtn" onclick="wizGo(5)" style="margin-top:6px">← Назад</button></div>';
    }

    // ═══ STEP 7: ЕТИКЕТИ ═══
    if(step===7){
        return '<div class="wiz-page active"><div style="text-align:center;padding:20px"><div style="font-size:18px;margin-bottom:6px">✓</div><div style="font-size:13px;font-weight:600;color:var(--success)">Артикулът е записан!</div><div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Зареждам етикети...</div></div></div>';
    }

    return '';
}

// ═══ AI IMAGE STUDIO STEP ═══
function renderStudioStep(){
    const vskip=S.wizVoiceMode?'<button class="abtn" onclick="wizGo(3)" style="margin-top:6px;border-color:rgba(245,158,11,0.2);color:#fbbf24">⏭ Пропусни</button>':'';

    // Screen 1: Photo info + credits + Screen 2: Options
    // We combine in one scrollable view with clear sections
    return '<div class="wiz-page active">'+

    // Section header
    '<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px"><div style="font-size:14px;font-weight:600">AI Image Studio</div>'+infoBtn('studio')+'</div>'+
    '<div style="font-size:10px;color:var(--text-secondary);margin-bottom:10px">AI обработва снимката — махане на фон, обличане на модел, студийна снимка за бижута и предмети. Снимката се използва и за AI описание.</div>'+

    // Credits
    '<div style="padding:8px 12px;border-radius:8px;background:rgba(34,197,94,0.04);border:1px solid rgba(34,197,94,0.15);margin-bottom:8px">'+
    '<div style="font-size:9px;color:#6b7280;margin-bottom:4px">БЕЗПЛАТНИ КРЕДИТИ (ВКЛЮЧЕНИ В ПЛАНА)</div>'+
    '<div style="display:flex;gap:16px;align-items:center">'+
    '<div><span style="font-size:18px;font-weight:700;color:#22c55e">'+CFG.aiBg+'</span> <span style="font-size:10px;color:#6b7280">бял фон (0.05€)</span></div>'+
    '<div style="width:1px;height:20px;background:rgba(99,102,241,0.15)"></div>'+
    '<div><span style="font-size:18px;font-weight:700;color:#a78bfa">'+CFG.aiTryon+'</span> <span style="font-size:10px;color:#6b7280">магия (0.50€)</span></div></div></div>'+

    // Buy credits
    (CFG.aiBg<=0||CFG.aiTryon<=0?'<div style="padding:6px 10px;border-radius:8px;background:rgba(239,68,68,0.04);border:1px solid rgba(239,68,68,0.15);margin-bottom:8px;display:flex;align-items:center;gap:8px"><div style="flex:1;font-size:10px;color:#fca5a5">Кредитите свършиха!</div><button class="abtn" style="width:auto;padding:4px 12px;font-size:10px;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;border:none" onclick="location.href=\'settings.php?buy_credits=1\'">Купи</button></div>':'')+

    // ─── OPTION 1: Бял фон ───
    '<div style="padding:10px;border-radius:12px;background:rgba(34,197,94,0.04);border:1px solid rgba(34,197,94,0.2);margin-bottom:6px;cursor:pointer" onclick="doStudioWhiteBg()">'+
    '<div style="display:flex;align-items:center;gap:8px">'+
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'+
    '<div style="flex:1"><div style="font-size:13px;font-weight:500;color:#e2e8f0">Бял фон</div><div style="font-size:10px;color:#6b7280">Махва фона, чисто бяло</div></div>'+
    '<span style="font-size:11px;font-weight:500;color:#22c55e">0.05€</span>'+infoBtn('bg_removal','#22c55e')+'</div></div>'+

    // ─── OPTION 2: Дрехи на модел ───
    '<div style="padding:10px;border-radius:12px;background:rgba(139,92,246,0.04);border:1px solid rgba(139,92,246,0.2);margin-bottom:6px">'+
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">'+
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="1.5"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>'+
    '<div style="flex:1"><div style="font-size:13px;font-weight:500;color:#e2e8f0">AI Магия — дрехи</div><div style="font-size:10px;color:#6b7280">Облечи на модел</div></div>'+
    '<span style="font-size:11px;font-weight:500;color:#a78bfa">0.50€</span>'+infoBtn('tryon_clothes','#a78bfa')+'</div>'+
    // 6 models
    '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-bottom:6px">'+
    studioModelBtn('woman','Жена',true)+studioModelBtn('man','Мъж',false)+studioModelBtn('girl','Момиче',false)+
    studioModelBtn('boy','Момче',false)+studioModelBtn('teen_f','Тийн F',false)+studioModelBtn('teen_m','Тийн M',false)+'</div>'+
    '<div style="margin-bottom:6px"><input type="text" class="fc" id="studioPromptClothes" placeholder="допълни: стояща поза, профил..." style="font-size:11px;padding:6px 10px"></div>'+
    '<button class="abtn" onclick="doStudioTryon()" style="background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:11px">Генерирай на модел</button></div>'+

    // ─── OPTION 3: Предмети ───
    '<div style="padding:10px;border-radius:12px;background:rgba(234,179,8,0.04);border:1px solid rgba(234,179,8,0.2);margin-bottom:6px">'+
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">'+
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.5"><path d="M12 2L9 9H2l5.5 4-2 7L12 16l6.5 4-2-7L22 9h-7z"/></svg>'+
    '<div style="flex:1"><div style="font-size:13px;font-weight:500;color:#e2e8f0">AI Магия — предмети</div><div style="font-size:10px;color:#6b7280">Бижута, обувки, чанти, аксесоари</div></div>'+
    '<span style="font-size:11px;font-weight:500;color:#fbbf24">0.50€</span>'+infoBtn('tryon_objects','#fbbf24')+'</div>'+
    // 8 presets
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:6px">'+
    studioPreset('Бижу на ръка')+studioPreset('На кадифе')+studioPreset('На мрамор')+studioPreset('Макро близък план')+
    studioPreset('На дърво')+studioPreset('Lifestyle сцена')+studioPreset('Обувка на крак')+studioPreset('Чанта на рамо')+'</div>'+
    '<div style="margin-bottom:6px"><input type="text" class="fc" id="studioPromptObjects" placeholder="или опиши: пръстен в кутийка..." style="font-size:11px;padding:6px 10px"></div>'+
    '<button class="abtn" onclick="doStudioObjects()" style="background:linear-gradient(135deg,#b45309,#d97706);color:#fff;border:none;font-size:11px">Генерирай студийна снимка</button></div>'+

    // Skip
    '<div style="padding:8px;border-radius:10px;border:1px dashed rgba(255,255,255,0.08);text-align:center;margin-bottom:6px;cursor:pointer" onclick="wizGo(3)"><span style="font-size:11px;color:#4b5563">Запази оригинала без обработка →</span></div>'+

    '<button class="abtn" onclick="wizGo(1)" style="margin-top:4px">← Назад</button>'+
    vskip+'</div>';
}

function studioModelBtn(key,label,sel){
    const bg=sel?'rgba(139,92,246,0.12);border:1px solid rgba(139,92,246,0.35)':'rgba(99,102,241,0.05);border:0.5px solid rgba(99,102,241,0.15)';
    return '<div style="text-align:center;padding:7px 2px;border-radius:7px;background:'+bg+';cursor:pointer" onclick="selectStudioModel(\''+key+'\',this)">'+
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="'+(sel?'#c4b5fd':'#a5b4fc')+'" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-3.87 3.13-7 7-7s7 3.13 7 7"/></svg>'+
    '<div style="font-size:9px;color:'+(sel?'#c4b5fd':'#a5b4fc')+';font-weight:500">'+label+'</div></div>';
}

function studioPreset(label){
    return '<div style="padding:5px 8px;border-radius:6px;background:rgba(234,179,8,0.06);border:0.5px solid rgba(234,179,8,0.15);cursor:pointer;font-size:10px;color:#fcd34d" onclick="selectStudioPreset(\''+label+'\',this)">'+label+'</div>';
}

S.studioModel='woman';
S.studioPreset='';

function selectStudioModel(key,el){
    S.studioModel=key;
    el.parentElement.querySelectorAll('div').forEach(d=>{d.style.background='rgba(99,102,241,0.05)';d.style.border='0.5px solid rgba(99,102,241,0.15)'});
    el.style.background='rgba(139,92,246,0.12)';el.style.border='1px solid rgba(139,92,246,0.35)';
}

function selectStudioPreset(label,el){
    S.studioPreset=label;
    el.parentElement.querySelectorAll('div').forEach(d=>{d.style.background='rgba(234,179,8,0.06)';d.style.border='0.5px solid rgba(234,179,8,0.15)'});
    el.style.background='rgba(234,179,8,0.12)';el.style.border='1px solid rgba(234,179,8,0.35)';
    document.getElementById('studioPromptObjects').value=label;
}

async function doStudioWhiteBg(){
    showToast('AI обработва... 5-15 сек','');
    // TODO: fal.ai birefnet call via ai-image-processor.php
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'bg_removal'})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Бял фон приложен ✓','success');wizGo(3)}
}

async function doStudioTryon(){
    const prompt=document.getElementById('studioPromptClothes')?.value||'';
    showToast('AI генерира... 10-20 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'tryon_'+S.studioModel,prompt})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Генерирано ✓','success');wizGo(3)}
}

async function doStudioObjects(){
    const prompt=document.getElementById('studioPromptObjects')?.value||S.studioPreset||'';
    showToast('AI генерира... 10-20 сек','');
    const d=await api('products.php?ajax=ai_image',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'object_studio',prompt})});
    if(d?.error)showToast(d.error,'error');
    else{showToast('Генерирано ✓','success');wizGo(3)}
}

// ─── HELPERS ───

function wizAddAxis(){
    const inp=document.getElementById('newAxisName');
    const name=inp?.value.trim();
    if(!name)return;
    if(!S.wizData.axes)S.wizData.axes=[];
    S.wizData.axes.push({name,values:[]});
    renderWizard();
}

function wizAddAxisValue(axIdx){
    const inp=document.getElementById('axVal'+axIdx);
    const val=inp?.value.trim();
    if(!val)return;
    S.wizData.axes[axIdx].values.push(val);
    renderWizard();
}

function wizCountCombinations(){
    if(!S.wizData.axes||!S.wizData.axes.length)return 0;
    return S.wizData.axes.filter(a=>a.values.length>0).reduce((acc,ax)=>acc*ax.values.length,1);
}

function wizBuildCombinations(){
    if(!S.wizData.axes||!S.wizData.axes.length)return[{label:'Единичен',qty:0}];
    const axes=S.wizData.axes.filter(a=>a.values.length>0);
    if(!axes.length)return[{label:'Единичен',qty:0}];
    let combos=[{parts:[]}];
    for(const ax of axes){
        const next=[];
        for(const combo of combos){for(const val of ax.values){next.push({parts:[...combo.parts,{axis:ax.name,value:val}]})}}
        combos=next;
    }
    return combos.map(c=>({
        axisValues:c.parts.map(p=>p.value).join(' / '),
        parts:c.parts,qty:0
    }));
}

function wizCollectData(){
    const name=document.getElementById('wName')?.value.trim();
    if(name)S.wizData.name=name;
    S.wizData.code=document.getElementById('wCode')?.value.trim()||'';
    S.wizData.retail_price=parseFloat(document.getElementById('wPrice')?.value)||0;
    S.wizData.wholesale_price=parseFloat(document.getElementById('wWprice')?.value)||0;
    S.wizData.barcode=document.getElementById('wBarcode')?.value.trim()||'';
    S.wizData.supplier_id=document.getElementById('wSup')?.value||null;
    S.wizData.category_id=document.getElementById('wCat')?.value||null;
    S.wizData.unit=document.getElementById('wUnit')?.value||'бр';
    S.wizData.min_quantity=parseInt(document.getElementById('wMinQty')?.value)||0;
    S.wizData.description=document.getElementById('wDesc')?.value||'';
}

async function wizGoPreview(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');wizGo(3);return}
    if(!S.wizData.retail_price){showToast('Въведи цена','error');wizGo(3);return}
    if(!S.wizData.code&&S.wizData.name){
        const d=await api('products.php?ajax=ai_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:S.wizData.name})});
        if(d?.code)S.wizData.code=d.code;
    }
    wizGo(6);
}

async function wizGenDescription(){
    const name=S.wizData.name||document.getElementById('wName')?.value||'';
    if(!name){showToast('Въведи име първо','error');return}
    showToast('AI генерира описание...','');
    const cat=document.getElementById('wCat')?.selectedOptions[0]?.text||'';
    const sup=document.getElementById('wSup')?.selectedOptions[0]?.text||'';
    const d=await api('products.php?ajax=ai_description',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,category:cat,supplier:sup})});
    if(d?.description){
        document.getElementById('wDesc').value=d.description;
        S.wizData.description=d.description;
        showToast('Описание генерирано ✓','success');
    }
}

async function wizSave(){
    wizCollectData();
    if(!S.wizData.name){showToast('Въведи наименование','error');return}
    const combos=wizBuildCombinations();
    document.querySelectorAll('[data-combo]').forEach(inp=>{
        const idx=parseInt(inp.dataset.combo);
        if(combos[idx])combos[idx].qty=parseInt(inp.value)||0;
    });
    const singleQty=parseInt(document.getElementById('wSingleQty')?.value)||0;

    let sizes=[],colors=[],extraAxes=[];
    (S.wizData.axes||[]).forEach(ax=>{
        const n=ax.name.toLowerCase();
        if(n.includes('размер')||n.includes('size'))sizes=ax.values;
        else if(n.includes('цвят')||n.includes('color'))colors=ax.values;
        else extraAxes.push(ax);
    });

    const variants=combos.map(c=>{
        const sizeVal=c.parts?.find(p=>p.axis.toLowerCase().includes('размер')||p.axis.toLowerCase().includes('size'))?.value||null;
        const colorVal=c.parts?.find(p=>p.axis.toLowerCase().includes('цвят')||p.axis.toLowerCase().includes('color'))?.value||null;
        const extras=c.parts?.filter(p=>{const n=p.axis.toLowerCase();return !n.includes('размер')&&!n.includes('size')&&!n.includes('цвят')&&!n.includes('color')}).map(p=>p.value)||[];
        const finalSize=[sizeVal,...extras].filter(Boolean).join(' / ')||null;
        return{size:finalSize,color:colorVal,qty:c.qty||0};
    });

    const payload={
        name:S.wizData.name,barcode:S.wizData.barcode,
        retail_price:S.wizData.retail_price,wholesale_price:S.wizData.wholesale_price,
        cost_price:0,supplier_id:S.wizData.supplier_id,category_id:S.wizData.category_id,
        code:S.wizData.code,unit:S.wizData.unit,min_quantity:S.wizData.min_quantity,
        description:S.wizData.description,
        product_type:S.wizType==='variant'?'variant':'simple',
        sizes,colors,variants:S.wizType==='variant'?variants:[{size:null,color:null,qty:singleQty}],
        initial_qty:singleQty,
        id:S.wizEditId||undefined,action:S.wizEditId?'edit':'create'
    };

    showToast('Запазвам...','');
    try{
        const r=await api('product-save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        if(r&&(r.success||r.id)){
            showToast('Артикулът е добавен!','success');
            S.wizSavedId=r.id;
            wizGo(7);
            setTimeout(()=>openLabels(r.id),500);
            setTimeout(()=>closeWizard(),600);
            loadScreen();
        }else{showToast(r?.error||'Грешка','error')}
    }catch(e){showToast('Мрежова грешка','error')}
}

function wizAddSubcat(){
    const name=document.getElementById('inlSubcatName')?.value.trim();
    const parentId=document.getElementById('wCat')?.value;
    if(!name||!parentId){showToast('Избери категория и въведи име','error');return}
    api('products.php?ajax=add_subcategory',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(name)+'&parent_id='+parentId}).then(d=>{
        if(d?.id){
            const sel=document.getElementById('wSubcat');
            const o=document.createElement('option');o.value=d.id;o.textContent=d.name;o.selected=true;
            sel.appendChild(o);
            showToast('Подкатегория добавена ✓','success');
            document.getElementById('inlSubcat').classList.remove('open');
        }
    });
}

function wizAddUnit(){
    const unit=document.getElementById('inlUnitName')?.value.trim();
    if(!unit)return;
    api('products.php?ajax=add_unit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'unit='+encodeURIComponent(unit)}).then(d=>{
        if(d?.units){
            CFG.units=d.units;
            S.wizData.unit=d.added;
            renderWizard();
            showToast('Мерна единица добавена ✓','success');
        }
    });
}

// Photo handlers
function wizTakePhoto(){openCamera('photo')}

document.getElementById('photoInput').addEventListener('change',async function(){
    if(!this.files?.[0])return;
    const preview=document.getElementById('wizPhotoPreview');
    const result=document.getElementById('wizScanResult');
    if(preview)preview.innerHTML='<div style="font-size:12px;color:var(--text-secondary);margin-top:8px">Снимка заредена ✓</div>';
    if(result)result.innerHTML='<div style="font-size:12px;color:var(--indigo-300);margin-top:6px">✦ AI анализира...</div>';
    const reader=new FileReader();
    reader.onload=async e=>{
        const base64=e.target.result.split(',')[1];
        const d=await api('products.php?ajax=ai_scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image:base64})});
        if(d&&!d.error){
            S.wizData={...S.wizData,...d};
            if(d.sizes?.length){
                S.wizData.axes=S.wizData.axes||[];
                if(!S.wizData.axes.find(a=>a.name.toLowerCase().includes('размер')))
                    S.wizData.axes.push({name:'Размер',values:d.sizes});
                if(!S.wizType)S.wizType='variant';
            }
            if(d.colors?.length){
                S.wizData.axes=S.wizData.axes||[];
                if(!S.wizData.axes.find(a=>a.name.toLowerCase().includes('цвят')))
                    S.wizData.axes.push({name:'Цвят',values:d.colors});
            }
            if(result)result.innerHTML='<div style="font-size:12px;color:var(--success);margin-top:6px">✓ AI разпозна — данните попълнени</div>';
            showToast('AI разпозна ✓','success');
            setTimeout(()=>wizGo(2),800);
        }else{
            if(result)result.innerHTML='<div style="font-size:12px;color:var(--warning);margin-top:6px">AI не разпозна — продължи ръчно</div>';
        }
    };
    reader.readAsDataURL(this.files[0]);
    this.value='';
});

// Inline add helpers
function toggleInl(id){document.getElementById(id)?.classList.toggle('open')}

async function wizAddInline(type){
    if(type==='supplier'){
        const n=document.getElementById('inlSupName')?.value.trim();
        if(!n)return;
        const d=await api('products.php?ajax=add_supplier',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(n)});
        if(d?.id){CFG.suppliers.push({id:d.id,name:d.name});S.wizData.supplier_id=d.id;showToast('Добавен ✓','success');renderWizard()}
    }else{
        const n=document.getElementById('inlCatName')?.value.trim();
        if(!n)return;
        const d=await api('products.php?ajax=add_category',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'name='+encodeURIComponent(n)});
        if(d?.id){CFG.categories.push({id:d.id,name:d.name});S.wizData.category_id=d.id;showToast('Добавена ✓','success');renderWizard()}
    }
}

// Edit existing product
async function editProduct(id){
    closeDrawer('detail');
    const d=await api('products.php?ajax=product_detail&id='+id);
    if(!d||d.error)return;
    const p=d.product;
    S.wizEditId=id;S.wizType='single';S.wizStep=3;
    S.wizData={name:p.name,code:p.code,retail_price:p.retail_price,wholesale_price:p.wholesale_price,
        barcode:p.barcode,supplier_id:p.supplier_id,category_id:p.category_id,
        description:p.description,unit:p.unit,min_quantity:p.min_quantity,axes:[]};
    S.wizVoiceMode=false;
    document.getElementById('wizTitle').textContent='Редактирай';
    renderWizard();
    history.pushState({modal:'wizard'},'','#wizard');
    document.getElementById('wizModal').classList.add('open');
    document.body.style.overflow='hidden';
}
"""

# ═══ 3. FIND AND REPLACE WIZARD JS BLOCK ═══
start_markers = [
    '// ═══════════════════════════════════════════════════════════\n// PART 4:',
    '// ═══════════════════════════════════════════════════════════\n// WIZARD REWRITE',
    "const WIZ_LABELS=['Вид','Снимка + AI','Основна информация',",
    "const WIZ_LABELS=['Вид','Снимка','AI обработка',",
    "const WIZ_LABELS=[",
]

start_pos = -1
for marker in start_markers:
    pos = content.find(marker)
    if pos > 0:
        start_pos = pos
        print(f"Found start at pos {pos}: {marker[:50]}...")
        break

end_marker = "// ─── INIT ───"
end_pos = content.find(end_marker)
print(f"Found end at pos {end_pos}")

if start_pos > 0 and end_pos > start_pos:
    content = content[:start_pos] + NEW_WIZARD_JS + '\n\n' + content[end_pos:]
    print(f"Replaced wizard JS block ({end_pos - start_pos} bytes -> {len(NEW_WIZARD_JS)} bytes)")
else:
    print("ERROR: Could not find wizard block boundaries!")
    print("Attempting to find any WIZ_LABELS...")
    for i, line in enumerate(content.split('\n')):
        if 'WIZ_LABELS' in line:
            print(f"  Line {i+1}: {line[:80]}")
    sys.exit(1)

# ═══ 4. FIX BUTTON LABELS ═══
content = content.replace('<span>AI Модул Артикули</span>', '<span>С глас</span>')
content = content.replace('<span>CSV</span>', '<span>Файл</span>')
content = content.replace('onclick="openAIWizard()"', 'onclick="openVoiceWizard()"')
print("Button labels fixed")

# ═══ 5. INFO BUTTON: ✦ → ℹ ═══
if 'info-ai-btn' in content:
    content = content.replace(
        'class="info-ai-btn" onclick="toggleInfoPanel()">✦</div>',
        'class="info-ai-btn" onclick="toggleInfoPanel()">ℹ</div>'
    )
    print("Info button icon changed")

# ═══ 6. BIZ-COEFFICIENTS INJECTION ═══
if 'findBizVariants' not in content:
    biz_php = '''
// Biz-coefficients for wizard pre-loading
if (file_exists(__DIR__ . '/biz-coefficients.php')) {
    require_once __DIR__ . '/biz-coefficients.php';
    $bizVars = findBizVariants($business_type ?: 'магазин');
} else {
    $bizVars = [];
}
'''
    content = content.replace(
        "// ============================================================\n// PAGE DATA LOADING",
        biz_php + "\n// ============================================================\n// PAGE DATA LOADING"
    )
    print("Biz-coefficients PHP injected")

if '_bizVariants' not in content:
    biz_js = '\n// Biz variants from PHP\nwindow._bizVariants = <?= json_encode($bizVars ?? [], JSON_UNESCAPED_UNICODE) ?>;\n'
    content = content.replace("const CFG = {", biz_js + "\nconst CFG = {")
    print("Biz-coefficients JS injected")

# ═══ 7. UPDATE INFO TEXT ═══
content = content.replace(
    'AI обработва снимката ти — махане на фон, обличане на модел или студийна снимка. Снимката се използва и за AI описание на артикула.',
    'AI обработва снимката ти — махане на фон, обличане на модел, студийна снимка за бижута, обувки, чанти и предмети. Снимката се използва и за AI описание на артикула.'
)

# ═══ 8. FIX LABEL POSITION ═══
content = content.replace('bottom:142px', 'bottom:150px')

# ═══ WRITE ═══
with open(TARGET, 'w') as f:
    f.write(content)

print(f"\n✓ Done! {len(content)} bytes, {content.count(chr(10))} lines")
print(f"Backup at: {BACKUP}")
print("Test: https://runmystore.ai/products.php")
