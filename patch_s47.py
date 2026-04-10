#!/usr/bin/env python3
"""
S47 patch: products.php — composition + origin suggest в wizard стъпка 3
4 промени:
  1. require biz-compositions.php + inject JS vars след biz-coefficients блока
  2. wOrigin input → suggest dropdown
  3. wComposition input → suggest dropdown
  4. wizGenDescription → изпраща composition към AI
  5. ai_description AJAX → добавя Composition в промпта
"""
import re

SRC = '/var/www/runmystore/products.php'

with open(SRC, 'r', encoding='utf-8') as f:
    code = f.read()

# ─── PATCH 1: require + JS vars след "$allBizPresets блока ───
OLD1 = "} else { $bizVars = []; $allBizPresets = ['sizes'=>[],'colors'=>[],'other'=>[]]; }"
NEW1 = """} else { $bizVars = []; $allBizPresets = ['sizes'=>[],'colors'=>[],'other'=>[]]; }

// S47: Biz compositions + countries
$bizComps = ['compositions'=>[], 'countries'=>[]];
if (file_exists(__DIR__ . '/biz-compositions.php')) {
    require_once __DIR__ . '/biz-compositions.php';
    $bizComps = getBizCompositions($business_type ?: 'Магазин за дамски дрехи');
}"""
assert OLD1 in code, "PATCH 1 not found"
code = code.replace(OLD1, NEW1, 1)

# ─── PATCH 2: inject JS vars в PHP→JS блока ───
OLD2 = "window._sizePresets="
NEW2 = """window._bizCompositions=<?= json_encode($bizComps['compositions'], JSON_UNESCAPED_UNICODE) ?>;
window._bizCountries=<?= json_encode($bizComps['countries'], JSON_UNESCAPED_UNICODE) ?>;
window._sizePresets="""
assert OLD2 in code, "PATCH 2 not found"
code = code.replace(OLD2, NEW2, 1)

# ─── PATCH 3: wOrigin input → suggest ───
OLD3 = """'<input type="text" class="fc" id="wOrigin" value="'+esc(S.wizData.origin_country||'')+'" placeholder="напр. Турция, Китай, Италия..." oninput="S.wizData.origin_country=this.value"></div>'+"""
NEW3 = """'<div style="position:relative"><input type="text" class="fc" id="wOrigin" value="'+esc(S.wizData.origin_country||'')+'" placeholder="напр. Турция, Китай..." autocomplete="off" oninput="S.wizData.origin_country=this.value;wizCountrySuggest(this.value)" onblur="setTimeout(()=>{var l=document.getElementById(\'wOriginList\');if(l)l.style.display=\'none\'},200)"><div id="wOriginList" class="wiz-dd-list" style="display:none"></div></div></div>'+"""
assert OLD3 in code, "PATCH 3 not found"
code = code.replace(OLD3, NEW3, 1)

# ─── PATCH 4: wComposition input → suggest ───
OLD4 = """'<input type="text" class="fc" id="wComposition" value="'+esc(S.wizData.composition||'')+'" placeholder="напр. 95% памук, 5% еластан" oninput="S.wizData.composition=this.value"></div>'"""
NEW4 = """'<div style="position:relative"><input type="text" class="fc" id="wComposition" value="'+esc(S.wizData.composition||'')+'" placeholder="напр. 95% памук, 5% еластан" autocomplete="off" oninput="S.wizData.composition=this.value;wizCompositionSuggest(this.value)" onblur="setTimeout(()=>{var l=document.getElementById(\'wCompositionList\');if(l)l.style.display=\'none\'},200)"><div id="wCompositionList" class="wiz-dd-list" style="display:none"></div></div>'"""
assert OLD4 in code, "PATCH 4 not found"
code = code.replace(OLD4, NEW4, 1)

# ─── PATCH 5: ai_description prompt → add Composition ───
OLD5 = "    if ($axes) $prompt .= \"Available variations: {$axes}\\n\";\n    $prompt .= \"\\nRULES"
NEW5 = "    if ($axes) $prompt .= \"Available variations: {$axes}\\n\";\n    $composition = $input['composition'] ?? '';\n    if ($composition) $prompt .= \"Composition/Material: {$composition}\\n\";\n    $prompt .= \"\\nRULES"
assert OLD5 in code, "PATCH 5 not found"
code = code.replace(OLD5, NEW5, 1)

# ─── PATCH 6: wizGenDescription → send composition ───
OLD6 = "        body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes})"
NEW6 = "        body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes,composition:S.wizData.composition||''})"
assert OLD6 in code, "PATCH 6 not found"
code = code.replace(OLD6, NEW6, 1)

# ─── PATCH 7: add suggest functions before // ─── INIT ─── ───
OLD7 = "// ─── INIT ───"
NEW7 = """// ─── COMPOSITION + COUNTRY SUGGEST (S47) ───
function wizCompositionSuggest(q) {
    var list = document.getElementById('wCompositionList');
    if (!list) return;
    var lq = q.toLowerCase().trim();
    if (!lq) { list.style.display='none'; return; }
    var all = window._bizCompositions || [];
    var filtered = all.filter(function(v){ return v.toLowerCase().indexOf(lq) !== -1; });
    if (!filtered.length) { list.style.display='none'; return; }
    list.innerHTML = filtered.slice(0,10).map(function(v){
        return '<div class="wiz-dd-item" onmousedown="event.preventDefault()" onclick="wizPickComposition(\''+v.replace(/'/g,"\\'")+'\')" style="font-size:12px">'+esc(v)+'</div>';
    }).join('');
    list.style.display = 'block';
}
function wizPickComposition(val) {
    var inp = document.getElementById('wComposition');
    if (inp) inp.value = val;
    S.wizData.composition = val;
    var list = document.getElementById('wCompositionList');
    if (list) list.style.display = 'none';
}
function wizCountrySuggest(q) {
    var list = document.getElementById('wOriginList');
    if (!list) return;
    var lq = q.toLowerCase().trim();
    if (!lq) { list.style.display='none'; return; }
    var all = window._bizCountries || [];
    var filtered = all.filter(function(v){ return v.toLowerCase().indexOf(lq) !== -1; });
    if (!filtered.length) { list.style.display='none'; return; }
    list.innerHTML = filtered.slice(0,8).map(function(v){
        return '<div class="wiz-dd-item" onmousedown="event.preventDefault()" onclick="wizPickCountry(\''+v.replace(/'/g,"\\'")+'\')" style="font-size:12px">'+esc(v)+'</div>';
    }).join('');
    list.style.display = 'block';
}
function wizPickCountry(val) {
    var inp = document.getElementById('wOrigin');
    if (inp) inp.value = val;
    S.wizData.origin_country = val;
    var list = document.getElementById('wOriginList');
    if (list) list.style.display = 'none';
}

// ─── INIT ───"""
assert "// ─── INIT ───" in code, "PATCH 7 not found"
code = code.replace(OLD7, NEW7, 1)

with open(SRC, 'w', encoding='utf-8') as f:
    f.write(code)

print("S47 patch applied OK — 7 changes")
