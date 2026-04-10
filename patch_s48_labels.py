#!/usr/bin/env python3
"""S48: Step 7 Labels UI with editable quantities + Print labels (50x30mm)"""

f = '/var/www/runmystore/products.php'
with open(f, 'r') as fp:
    code = fp.read()

changes = 0

# ═══ PATCH A: Inject tenant country in JS ═══
old_a = "window._bizCountries=<?= json_encode($bizComps['countries'] ?? [], JSON_UNESCAPED_UNICODE) ?>;"
new_a = old_a + "\nwindow._tenantCountry=<?= json_encode($tenant['country'] ?? 'BG') ?>;"
if old_a in code:
    code = code.replace(old_a, new_a, 1)
    changes += 1
    print("✅ PATCH A: tenant country injected")
else:
    print("❌ PATCH A: NOT FOUND")

# ═══ PATCH B: Store print combos in wizSave before wizGo(7) ═══
old_b = "            S.wizSavedId=r.id;\n            wizGo(7);"
new_b = """            S.wizSavedId=r.id;
            var _pc=wizBuildCombinations();
            document.querySelectorAll('[data-combo][data-field="qty"]').forEach(function(inp){var ci=parseInt(inp.dataset.combo);if(_pc[ci])_pc[ci].printQty=parseInt(inp.value)||1;});
            if(!_pc.length||(!_pc[0]?.parts?.length&&!_pc[0]?.axisValues)){
                _pc=[{parts:[],printQty:parseInt(document.getElementById('wSingleQty')?.value)||1}];
            }
            S.wizData._printCombos=_pc;
            wizGo(7);"""
if old_b in code:
    code = code.replace(old_b, new_b, 1)
    changes += 1
    print("✅ PATCH B: store print combos")
else:
    print("❌ PATCH B: NOT FOUND")

# ═══ PATCH C: Replace entire Step 7 ═══
start_marker = '    // ═══ STEP 7: УСПЕШЕН ЗАПИС ═══'
end_marker = "\n    return '';"
try:
    si = code.index(start_marker)
    ei = code.index(end_marker, si)
    
    new_step7 = r"""    // ═══ STEP 7: ПЕЧАТ НА ЕТИКЕТИ ═══
    if(step===7){
        var pid=S.wizSavedId||0;
        var combos=S.wizData._printCombos||[];
        var isBG=(window._tenantCountry||'').toUpperCase()==='BG';
        var beforeDL=new Date()<new Date('2026-08-08');
        var showDual=isBG&&beforeDL;
        if(!S.wizData._printMode)S.wizData._printMode=showDual?'dual':'eur';
        var pm=S.wizData._printMode;
        var sup=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
        var supName=sup?sup.name:'';
        var supCity=sup?sup.city||'':'';

        var tabsH='<div style="display:flex;gap:4px;margin-bottom:10px;background:rgba(255,255,255,0.05);border-radius:10px;padding:3px">';
        if(showDual)tabsH+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='dual'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S.wizData._printMode=\'dual\';renderWizard()">€ + лв</div>';
        tabsH+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='eur'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S.wizData._printMode=\'eur\';renderWizard()">Само €</div>';
        tabsH+='<div style="flex:1;text-align:center;padding:7px 4px;border-radius:8px;font-size:11px;cursor:pointer;'+(pm==='noprice'?'background:rgba(99,102,241,0.2);font-weight:600;color:#a5b4fc':'color:#64748b')+'" onclick="S.wizData._printMode=\'noprice\';renderWizard()">Без цена</div>';
        tabsH+='</div>';

        var warnH='';
        if(showDual&&pm==='dual')warnH='<div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:7px 10px;margin-bottom:10px;display:flex;align-items:center;gap:6px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span style="font-size:10px;color:#fbbf24">Двойно изписване до 08.08.2026. След тази дата тази опция ще изчезне автоматично.</span></div>';

        var totalQty=0;
        var listH='<div style="font-size:11px;color:#64748b;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center"><span>Вариации за печат:</span><button type="button" class="abtn" onclick="wizLabelsX2()" style="font-size:10px;padding:4px 10px;border-color:rgba(99,102,241,0.2)">x2 по 2</button></div>';
        combos.forEach(function(c,i){
            var parts=c.parts||[];
            var labelH='';
            parts.forEach(function(p){
                var n=p.axis.toLowerCase();
                if(n.includes('размер')||n.includes('size'))labelH+='<span style="font-size:13px;font-weight:700;margin-right:4px">'+esc(p.value)+'</span>';
                else if(n.includes('цвят')||n.includes('color')){
                    var cc=CFG.colors.find(function(x){return x.name===p.value});
                    var hex=cc?cc.hex:'#666';
                    labelH+='<span style="display:inline-flex;align-items:center;gap:3px"><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:'+hex+';border:1px solid rgba(255,255,255,0.2)"></span><span style="font-size:11px">'+esc(p.value)+'</span></span>';
                }else{
                    labelH+='<span style="font-size:11px;margin-right:4px">'+esc(p.value)+'</span>';
                }
            });
            if(!labelH)labelH='<span style="font-size:12px;font-weight:600">Единичен</span>';
            var qty=c.printQty||1;
            totalQty+=qty;
            listH+='<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;padding:6px 10px;border-radius:8px;background:rgba(17,24,44,0.3);border:1px solid var(--border-subtle)">'+
            '<div style="flex:1;display:flex;align-items:center;flex-wrap:wrap;gap:4px">'+labelH+'</div>'+
            '<div style="display:flex;align-items:center;gap:0">'+
            '<button type="button" onclick="wizLblAdj('+i+',-1)" style="width:22px;height:26px;border:1px solid var(--border-subtle);border-radius:4px 0 0 4px;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:13px;cursor:pointer;padding:0">\u2212</button>'+
            '<input type="number" class="fc" id="lblQty'+i+'" style="width:32px;padding:2px 0;text-align:center;font-size:12px;font-weight:700;border-radius:0;border-left:0;border-right:0" value="'+qty+'" min="0" onchange="wizLblRecalc()">'+
            '<button type="button" onclick="wizLblAdj('+i+',1)" style="width:22px;height:26px;border:1px solid var(--border-subtle);border-radius:0 4px 4px 0;background:rgba(17,24,44,0.5);color:var(--text-primary);font-size:13px;cursor:pointer;padding:0">+</button></div>'+
            '<div onclick="wizPrintLabels('+i+')" style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;cursor:pointer"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></div></div>';
        });

        var btnH='<button type="button" class="abtn save" style="margin-top:12px;font-size:14px;padding:12px" onclick="wizPrintLabels(-1)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:5px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Печатай всички (<span id="lblTotal">'+totalQty+'</span> ет.)</button>';

        return '<div class="wiz-page active"><div style="text-align:center;padding:16px 0 10px">'+
        '<div style="width:48px;height:48px;border-radius:50%;background:rgba(34,197,94,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 8px"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>'+
        '<div style="font-size:15px;font-weight:700;color:var(--success)">Артикулът е записан!</div>'+
        '<div style="font-size:12px;color:var(--text-secondary);margin-top:2px">'+esc(S.wizData.name||'')+' \u00b7 '+fmtPrice(S.wizData.retail_price)+'</div></div>'+
        tabsH+warnH+listH+btnH+
        '<div style="display:flex;gap:8px;margin-top:10px">'+
        '<button class="abtn" onclick="closeWizard();openManualWizard()" style="flex:1;font-size:12px;padding:10px;border-color:rgba(99,102,241,0.2)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--indigo-300)" stroke-width="2" style="vertical-align:-1px;margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Добави нов</button>'+
        '<button class="abtn" onclick="closeWizard()" style="flex:1;font-size:12px;padding:10px;color:var(--text-secondary)">Затвори</button></div></div>';
    }
"""
    
    code = code[:si] + new_step7 + code[ei:]
    changes += 1
    print("✅ PATCH C: Step 7 labels UI")
except ValueError as e:
    print(f"❌ PATCH C: {e}")

# ═══ PATCH D: Label helper functions before END S48 suggest ═══
old_d = '// ═══ END S48 suggest ═══'
new_d = r"""// ═══ S48: Label print functions ═══
function wizLblAdj(idx,delta){
    var inp=document.getElementById('lblQty'+idx);
    if(!inp)return;
    var v=Math.max(0,parseInt(inp.value||0)+delta);
    inp.value=v;
    wizLblRecalc();
}
function wizLblRecalc(){
    var total=0;
    document.querySelectorAll('[id^="lblQty"]').forEach(function(inp){total+=parseInt(inp.value)||0;});
    var el=document.getElementById('lblTotal');
    if(el)el.textContent=total;
}
function wizLabelsX2(){
    document.querySelectorAll('[id^="lblQty"]').forEach(function(inp){
        inp.value=Math.max(1,(parseInt(inp.value)||1)*2);
    });
    wizLblRecalc();
}
function wizPrintLabels(comboIdx){
    var combos=S.wizData._printCombos||[];
    var pm=S.wizData._printMode||'eur';
    var sup=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
    var supName=sup?sup.name:'';
    var supCity=sup?sup.city||'':'';
    var barcode=S.wizData.barcode||('200'+String(S.wizSavedId||0).padStart(9,'0'));
    if(barcode.length===12){var sum=0;for(var bi=0;bi<12;bi++)sum+=parseInt(barcode[bi])*(bi%2===0?1:3);barcode+=String((10-sum%10)%10);}
    var name=S.wizData.name||'';
    var code=S.wizData.code||'';
    var price=parseFloat(S.wizData.retail_price)||0;
    var priceBGN=Math.round(price*195583)/100000;
    priceBGN=priceBGN.toFixed(2);
    var composition=S.wizData.composition||'';
    var origin=S.wizData.origin_country||'';
    var isDomestic=S.wizData.is_domestic;
    var importLine='';
    if(!isDomestic&&supName){importLine='Внос: '+supName+(supCity?', гр. '+supCity:'');}
    var originLine='';
    if(composition)originLine+=composition;
    if(origin&&!isDomestic)originLine+=(originLine?' · ':'')+origin;

    var items=[];
    if(comboIdx===-1){
        combos.forEach(function(c,i){
            var qty=parseInt(document.getElementById('lblQty'+i)?.value)||0;
            if(qty>0)items.push({combo:c,qty:qty});
        });
    }else{
        var qty=parseInt(document.getElementById('lblQty'+comboIdx)?.value)||1;
        items.push({combo:combos[comboIdx],qty:qty});
    }
    if(!items.length){showToast('Няма етикети за печат','error');return;}

    var labels=[];
    items.forEach(function(item){
        var parts=item.combo.parts||[];
        var sizeVal='',colorVal='';
        parts.forEach(function(p){
            var n=p.axis.toLowerCase();
            if(n.includes('размер')||n.includes('size'))sizeVal=p.value;
            else if(n.includes('цвят')||n.includes('color'))colorVal=p.value;
        });
        for(var q=0;q<item.qty;q++){
            labels.push({size:sizeVal,color:colorVal,barcode:barcode});
        }
    });

    var fmtEur=function(v){return v.toFixed(2).replace('.',',')+' \u20ac';};
    var fmtBgn=function(v){return parseFloat(v).toFixed(2).replace('.',',')+' \u043b\u0432';};

    var html='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Етикети</title>';
    html+='<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>';
    html+='<style>';
    html+='@page{size:50mm 30mm;margin:0}';
    html+='*{box-sizing:border-box;margin:0;padding:0}';
    html+='body{font-family:Arial,sans-serif;color:#000}';
    html+='.label{width:50mm;height:30mm;padding:1.5mm 2mm;display:flex;flex-direction:column;justify-content:space-between;page-break-after:always;overflow:hidden}';
    html+='.l-top{display:flex;gap:1.5mm;align-items:flex-start}';
    html+='.l-name{font-size:7pt;font-weight:700;line-height:1.15}';
    html+='.l-code{font-size:5pt;color:#555;margin-top:0.3mm}';
    html+='.l-mid{display:flex;align-items:center;gap:1.5mm}';
    html+='.l-sz{background:#000;color:#fff;font-size:10pt;font-weight:700;padding:0.5mm 2.5mm;border-radius:1mm}';
    html+='.l-clr{font-size:7pt;color:#333}';
    html+='.l-dash{border-top:0.3mm dashed #aaa;padding-top:0.8mm}';
    html+='.l-pr{display:flex;align-items:baseline;gap:1.5mm}';
    html+='.l-eur{font-size:12pt;font-weight:700}';
    html+='.l-bgn{font-size:8pt;font-weight:600;color:#444}';
    html+='.l-eur-only{font-size:14pt;font-weight:700;text-align:center}';
    html+='.l-sz-big{font-size:16pt;padding:1mm 4mm}';
    html+='.l-clr-big{font-size:10pt;font-weight:500}';
    html+='.l-bot{border-top:0.3mm dashed #aaa;padding-top:0.5mm;display:flex;justify-content:space-between;font-size:4.5pt;color:#444}';
    html+='@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}';
    html+='</style></head><body>';

    labels.forEach(function(lb,idx){
        html+='<div class="label">';
        html+='<div class="l-top"><svg id="bc'+idx+'" style="width:18mm;height:8mm;flex-shrink:0"></svg>';
        html+='<div><div class="l-name">'+name+(supName?' '+supName:'')+'</div>';
        html+='<div class="l-code">'+(code||'')+' \u00b7 '+lb.barcode+'</div></div></div>';

        if(pm==='noprice'){
            html+='<div style="display:flex;align-items:center;gap:2mm;justify-content:center;flex:1">';
            if(lb.size)html+='<div class="l-sz l-sz-big">'+lb.size+'</div>';
            if(lb.color)html+='<span class="l-clr-big">'+lb.color+'</span>';
            html+='</div>';
        }else{
            html+='<div class="l-mid">';
            if(lb.size)html+='<div class="l-sz">'+lb.size+'</div>';
            if(lb.color)html+='<span class="l-clr">'+lb.color+'</span>';
            html+='</div>';
            html+='<div class="l-dash">';
            if(pm==='dual'){
                html+='<div class="l-pr"><span class="l-eur">'+fmtEur(price)+'</span><span style="color:#aaa;font-size:6pt">|</span><span class="l-bgn">'+fmtBgn(priceBGN)+'</span></div>';
            }else{
                html+='<div class="l-eur-only">'+fmtEur(price)+'</div>';
            }
            html+='</div>';
        }

        html+='<div class="l-bot">';
        if(originLine)html+='<span>'+originLine+'</span>';
        if(importLine)html+='<span>'+importLine+'</span>';
        html+='</div>';
        html+='</div>';
    });

    html+='<script>';
    html+='var opts={format:"EAN13",width:1,height:28,displayValue:false,margin:0};';
    html+='for(var i=0;i<'+labels.length+';i++){try{JsBarcode("#bc"+i,"'+barcode+'",opts)}catch(e){}}';
    html+='setTimeout(function(){window.print()},400);';
    html+='<\/script></body></html>';

    var w=window.open('','_blank','width=400,height=600');
    if(w){w.document.write(html);w.document.close();}
    else{showToast('Позволи pop-up прозорци','error');}
}
// ═══ END S48 suggest ═══"""
if old_d in code:
    code = code.replace(old_d, new_d, 1)
    changes += 1
    print("✅ PATCH D: label print functions")
else:
    print("❌ PATCH D: NOT FOUND")

# ═══ WRITE ═══
print(f"\n{'='*50}")
print(f"Total changes: {changes}/4")
if changes >= 3:
    with open(f, 'w') as fp:
        fp.write(code)
    print(f"✅ File written: {f}")
    print(f"Lines: {len(code.splitlines())}")
else:
    print("⚠️  Too few patches matched. File NOT written.")
    import sys; sys.exit(1)
