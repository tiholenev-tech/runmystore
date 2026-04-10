#!/usr/bin/env python3
"""S48: Reset labels button + CSV download"""

f = '/var/www/runmystore/products.php'
with open(f, 'r') as fp:
    code = fp.read()

ch = 0

# FIX 1: Add reset button next to x2
old1 = 'onclick="wizLabelsX2()" style="font-size:10px;padding:4px 10px;border-color:rgba(99,102,241,0.2)">x2 по 2</button>'
new1 = 'onclick="wizLabelsX2()" style="font-size:10px;padding:4px 10px;border-color:rgba(99,102,241,0.2)">x2</button><button type="button" class="abtn" onclick="wizLabelsReset()" style="font-size:10px;padding:4px 10px;border-color:rgba(245,158,11,0.2);color:#fbbf24;margin-left:4px">1:1</button>'
if old1 in code:
    code = code.replace(old1, new1, 1)
    ch += 1
    print("OK 1: reset button")
else:
    print("SKIP 1")

# FIX 2: Add CSV button before "Добави нов"
old2 = """'<div style="display:flex;gap:8px;margin-top:10px">'+
        '<button class="abtn" onclick="closeWizard();openManualWizard()\""""
new2 = """'<button type="button" class="abtn" onclick="wizDownloadCSV()" style="margin-top:8px;font-size:12px;padding:10px;width:100%;border-color:rgba(99,102,241,0.15);color:var(--indigo-300)">Свали CSV за онлайн магазин</button>'+
        '<div style="display:flex;gap:8px;margin-top:10px">'+
        '<button class="abtn" onclick="closeWizard();openManualWizard()\""""
if old2 in code:
    code = code.replace(old2, new2, 1)
    ch += 1
    print("OK 2: CSV button")
else:
    print("SKIP 2")

# FIX 3: Add functions
old3 = '// ═══ END S48 suggest ═══'
new3 = r"""function wizLabelsReset(){
    var combos=S.wizData._printCombos||[];
    combos.forEach(function(c,i){
        var inp=document.getElementById('lblQty'+i);
        if(inp)inp.value=c.printQty||1;
    });
    wizLblRecalc();
}
function wizDownloadCSV(){
    var combos=S.wizData._printCombos||[];
    var name=S.wizData.name||'';
    var price=S.wizData.retail_price||'';
    var desc=(S.wizData.description||'').replace(/"/g,'""');
    var pcode=S.wizData.code||'';
    var barcode=S.wizData.barcode||'';
    var composition=S.wizData.composition||'';
    var origin=S.wizData.origin_country||'';
    var sup=CFG.suppliers.find(function(s){return s.id==S.wizData.supplier_id});
    var supName=sup?sup.name:'';
    var cat=CFG.categories.find(function(c2){return c2.id==S.wizData.category_id});
    var catName=cat?cat.name:'';
    var esc2=function(v){return (v||'').toString().replace(/"/g,'""');};
    var rows=['"Наименование","Код","Баркод","Размер","Цвят","Цена","Бройка","Категория","Доставчик","Състав","Произход","Описание"'];
    if(!combos.length||(!combos[0].parts||!combos[0].parts.length)){
        rows.push('"'+esc2(name)+'","'+esc2(pcode)+'","'+esc2(barcode)+'","","","'+price+'","1","'+esc2(catName)+'","'+esc2(supName)+'","'+esc2(composition)+'","'+esc2(origin)+'","'+esc2(desc)+'"');
    }else{
        combos.forEach(function(c){
            var sz='',cl='';
            (c.parts||[]).forEach(function(p){
                var n=p.axis.toLowerCase();
                if(n.indexOf('размер')!==-1||n.indexOf('size')!==-1)sz=p.value;
                else if(n.indexOf('цвят')!==-1||n.indexOf('color')!==-1)cl=p.value;
            });
            rows.push('"'+esc2(name)+'","'+esc2(pcode)+'","'+esc2(barcode)+'","'+esc2(sz)+'","'+esc2(cl)+'","'+price+'","'+(c.printQty||1)+'","'+esc2(catName)+'","'+esc2(supName)+'","'+esc2(composition)+'","'+esc2(origin)+'","'+esc2(desc)+'"');
        });
    }
    var csv='\uFEFF'+rows.join('\n');
    var blob=new Blob([csv],{type:'text/csv;charset=utf-8'});
    var a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download=(name||'product').replace(/[^a-zA-Z0-9\u0430-\u044f\u0410-\u042f]/g,'_')+'.csv';
    a.click();
}
// ═══ END S48 suggest ═══"""
if old3 in code:
    code = code.replace(old3, new3, 1)
    ch += 1
    print("OK 3: functions")
else:
    print("SKIP 3")

print(f"\nTotal: {ch}/3")
if ch >= 2:
    with open(f, 'w') as fp:
        fp.write(code)
    print(f"Written, lines: {len(code.splitlines())}")
else:
    print("NOT written")
