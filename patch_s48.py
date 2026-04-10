#!/usr/bin/env python3
"""S48: Add composition/country suggest + AI description composition. NO JS string concat quote issues."""
import sys

f = '/var/www/runmystore/products.php'
with open(f, 'r') as fp:
    code = fp.read()

changes = 0

# ═══ PATCH 1: require biz-compositions.php (after biz-coefficients require) ═══
old1 = "    require_once __DIR__.'/biz-coefficients.php';\n    $bizVars = findBizVariants"
new1 = """    require_once __DIR__.'/biz-coefficients.php';
    if (file_exists(__DIR__.'/biz-compositions.php')) {
        require_once __DIR__.'/biz-compositions.php';
        $bizComps = getBizCompositions($business_type ?: 'магазин');
    } else {
        $bizComps = ['compositions' => [], 'countries' => []];
    }
    $bizVars = findBizVariants"""
if old1 in code:
    code = code.replace(old1, new1, 1)
    changes += 1
    print("✅ PATCH 1: require biz-compositions.php")
else:
    print("❌ PATCH 1: NOT FOUND")

# ═══ PATCH 2: inject window._bizCompositions + _bizCountries in JS ═══
old2 = "window._allBizPresets=<?= json_encode($allBizPresets, JSON_UNESCAPED_UNICODE) ?>;"
new2 = """window._allBizPresets=<?= json_encode($allBizPresets, JSON_UNESCAPED_UNICODE) ?>;
window._bizCompositions=<?= json_encode($bizComps['compositions'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
window._bizCountries=<?= json_encode($bizComps['countries'] ?? [], JSON_UNESCAPED_UNICODE) ?>;"""
if old2 in code:
    code = code.replace(old2, new2, 1)
    changes += 1
    print("✅ PATCH 2: inject _bizCompositions + _bizCountries")
else:
    print("❌ PATCH 2: NOT FOUND")

# ═══ PATCH 3: AI description AJAX - add composition to prompt ═══
old3 = "        $axes = $input['axes'] ?? '';"
new3 = """        $axes = $input['axes'] ?? '';
        $composition = $input['composition'] ?? '';"""
if old3 in code:
    code = code.replace(old3, new3, 1)
    changes += 1
    print("✅ PATCH 3a: parse composition from input")
else:
    print("❌ PATCH 3a: NOT FOUND")

old3b = '        if ($axes) $prompt .= "Available variations: {$axes}\\n";'
new3b = """        if ($axes) $prompt .= "Available variations: {$axes}\\n";
        if ($composition) $prompt .= "Composition/Material: {$composition}\\n";"""
if old3b in code:
    code = code.replace(old3b, new3b, 1)
    changes += 1
    print("✅ PATCH 3b: add composition to prompt")
else:
    print("❌ PATCH 3b: NOT FOUND")

# ═══ PATCH 4: wizGenDescription sends composition ═══
old4 = "body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes})"
new4 = "body:JSON.stringify({name:name,category:cat,supplier:sup,axes:axes,composition:S.wizData.composition||''})"
if old4 in code:
    code = code.replace(old4, new4, 1)
    changes += 1
    print("✅ PATCH 4: wizGenDescription sends composition")
else:
    print("❌ PATCH 4: NOT FOUND")

# ═══ PATCH 5: JS suggest functions + event delegation (APPEND before closing </script>) ═══
# Find the LAST </script> tag
suggest_js = """
// ═══ S48: Composition + Country suggest (event delegation, no inline handlers) ═══
(function(){
    function createDropdown(inputId, listId, items) {
        var inp = document.getElementById(inputId);
        if (!inp) return;
        var val = inp.value.toLowerCase();
        var existing = document.getElementById(listId);
        if (existing) existing.remove();
        if (!val || val.length < 1) return;
        var matches = items.filter(function(it){ return it.toLowerCase().indexOf(val) !== -1; });
        if (!matches.length) return;
        var dd = document.createElement('div');
        dd.id = listId;
        dd.style.cssText = 'position:absolute;left:0;right:0;top:100%;background:#1e1e2e;border:1px solid var(--border-subtle);border-radius:8px;max-height:180px;overflow-y:auto;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.5)';
        matches.slice(0, 8).forEach(function(m){
            var opt = document.createElement('div');
            opt.textContent = m;
            opt.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;color:#e2e8f0;border-bottom:1px solid rgba(255,255,255,0.05)';
            opt.onmousedown = function(e){
                e.preventDefault();
                inp.value = m;
                inp.dispatchEvent(new Event('input', {bubbles:true}));
                if (inputId === 'wOrigin') S.wizData.origin_country = m;
                if (inputId === 'wComposition') S.wizData.composition = m;
                dd.remove();
            };
            opt.onmouseenter = function(){ this.style.background = 'rgba(99,102,241,0.2)'; };
            opt.onmouseleave = function(){ this.style.background = 'transparent'; };
            dd.appendChild(opt);
        });
        inp.parentElement.style.position = 'relative';
        inp.parentElement.appendChild(dd);
    }
    function closeLists(except){
        ['wOriginList','wCompositionList'].forEach(function(id){
            if(id!==except){var e=document.getElementById(id);if(e)e.remove();}
        });
    }
    document.addEventListener('input', function(e){
        if (e.target.id === 'wOrigin') {
            closeLists('wOriginList');
            createDropdown('wOrigin', 'wOriginList', window._bizCountries || []);
        }
        if (e.target.id === 'wComposition') {
            closeLists('wCompositionList');
            createDropdown('wComposition', 'wCompositionList', window._bizCompositions || []);
        }
    });
    document.addEventListener('click', function(e){
        if (e.target.id !== 'wOrigin' && e.target.id !== 'wComposition') closeLists();
    });
})();
// ═══ END S48 suggest ═══"""

# Insert before the very last </script>
last_script_close = code.rfind('</script>')
if last_script_close > 0:
    code = code[:last_script_close] + suggest_js + '\n' + code[last_script_close:]
    changes += 1
    print("✅ PATCH 5: suggest functions + event delegation")
else:
    print("❌ PATCH 5: no </script> found")

# ═══ WRITE ═══
print(f"\n{'='*50}")
print(f"Total changes: {changes}/6")

if changes >= 5:
    with open(f, 'w') as fp:
        fp.write(code)
    print(f"✅ File written: {f}")
    print(f"Lines: {len(code.splitlines())}")
else:
    print("⚠️  Too few patches matched. File NOT written.")
    sys.exit(1)
