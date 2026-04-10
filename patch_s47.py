#!/usr/bin/env python3
import sys

SRC = '/var/www/runmystore/products.php'

with open(SRC, 'r', encoding='utf-8') as f:
    code = f.read()

errors = []

# PATCH 1
OLD1 = "} else { $bizVars = []; $allBizPresets = ['sizes'=>[],'colors'=>[],'other'=>[]]; }"
NEW1 = "} else { $bizVars = []; $allBizPresets = ['sizes'=>[],'colors'=>[],'other'=>[]]; }\n\n// S47: Biz compositions + countries\n$bizComps = ['compositions'=>[], 'countries'=>[]];\nif (file_exists(__DIR__ . '/biz-compositions.php')) {\n    require_once __DIR__ . '/biz-compositions.php';\n    $bizComps = getBizCompositions($business_type ?: 'Магазин за дамски дрехи');\n}"
if OLD1 in code: code = code.replace(OLD1, NEW1, 1)
else: errors.append("PATCH 1 not found")

# PATCH 2
OLD2 = "window._sizePresets="
NEW2 = "window._bizCompositions=<?= json_encode($bizComps['compositions'], JSON_UNESCAPED_UNICODE) ?>;\nwindow._bizCountries=<?= json_encode($bizComps['countries'], JSON_UNESCAPED_UNICODE) ?>;\nwindow._sizePresets="
if OLD2 in code: code = code.replace(OLD2, NEW2, 1)
else: errors.append("PATCH 2 not found")

# PATCH 3
OLD3 = "'<input type=\"text\" class=\"fc\" id=\"wOrigin\" value=\"'+esc(S.wizData.origin_country||'')+\'\" placeholder=\"напр. Турция, Китай, Италия...\" oninput=\"S.wizData.origin_country=this.value\"></div>\'+"
if OLD3 not in code:
    OLD3 = """'<input type="text" class="fc" id="wOrigin" value="'+esc(S.wizData.origin_country||'')+'" placeholder="напр. Турция, Китай, Италия..." oninput="S.wizData.origin_country=this.value"></div>'+"""
NEW3 = """'<div style="position:relative"><input type="text" class="fc" id="wOrigin" value="'+esc(S.wizData.origin_country||'')+'" placeholder="напр. Турция, Китай..." autocomplete="off" oninput="S.wizData.origin_country=this.value;wizCountrySuggest(this.value)" onblur="setTimeout(()=>{var l=document.getElementById(\'wOriginList\');if(l)l.style.display=\'none\'},200)"><div id="wOriginList" class="wiz-dd-list" style="display:none"></div></div></div>'+"""
if OLD3 in code: code = code.replace(OLD3, NEW3, 1)
else: errors.append("PATCH 3 not found")

# PATCH 4 — два пъти (is_domestic и само)
OLD4 = """'<input type="text" class="fc" id="wComposition" value="'+esc(S.wizData.composition||'')+'" placeholder="напр. 95% памук, 5% еластан" oninput="S.wizData.composition=this.value"></div>'"""
NEW4 = """'<div style="position:relative"><input type="text" class="fc" id="wComposition" value="'+esc(S.wizData.composition||'')+'" placeholder="напр. 95% памук, 5% еластан" autocomplete="off" oninput="S.wizData.composition=this.value;wizCompositionSuggest(this.value)" onblur="setTimeout(()=>{var l=document.getElementById(\'wCompositionList\');if(l)l.style.display=\'none\'},200)"><div id="wCompositionList" class="wiz-dd-list" style="display:none"></div></div>'"""
count4 = code.count(OLD4)
if count4 > 0: code = code.replace(OLD4, NEW4)
else: errors.append("PATCH 4 not found")

# PATCH 5 — 8 spaces
OLD5 = "        if ($axes) $prompt .= \"Available variations: {$axes}\\n\";\n        $prompt .= \"\\nRULES"
NEW5 = "        if ($axes) $prompt .= \"Available variations: {$axes}\\n\";\n        $composition = $input['composition'] ?? '';\n        if ($composition) $prompt .= \"Composition/Material: {$composition}\\n\";\n        $prompt .= \"\\nRULES"
if OLD5 in code: code = code.replace(OLD5, NEW5, 1)
else: errors.append("PATCH 5 not found")

# PATCH 6
OLD6 = "body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes})"
NEW6 = "body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes,composition:S.wizData.composition||''})"
if OLD6 in code: code = code.replace(OLD6, NEW6, 1)
else: errors.append("PATCH 6 not found")

# PATCH 7 — suggest functions
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
        return '<div class="wiz-dd-item" onmousedown="event.preventDefault()" onclick="wizPickComposition(\''+v.replace(/'/g,"\\'")+'\')">'+esc(v)+'</div>';
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
        return '<div class="wiz-dd-item" onmousedown="event.preventDefault()" onclick="wizPickCountry(\''+v.replace(/'/g,"\\'")+'\')">'+esc(v)+'</div>';
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
if OLD7 in code: code = code.replace(OLD7, NEW7, 1)
else: errors.append("PATCH 7 not found")

if errors:
    print("ERRORS:", errors)
    sys.exit(1)

with open(SRC, 'w', encoding='utf-8') as f:
    f.write(code)

print("S47 patch OK —", 7 - len(errors), "changes applied")
