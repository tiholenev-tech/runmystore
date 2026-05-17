/**
 * wizard-variations.js — sacred Section 2 logic extract от products.php
 * S148 ФАЗА 3c.1 — 2026-05-17
 *
 * 1:1 копия от products.php (без модификации):
 *   _SIZE_GROUPS                 ред 10369-10395 (27 size presets)
 *   _getSizePresetsOrdered()      ред 10408 (business-type keyword scoring)
 *   wizAIColorAutofill()          ред 8352-8386 (extracted като отделна fn)
 *
 * Default color palette (fallback ако CFG.colors не е дефинирано —
 * за wizard-v6.php стояли извън products.php session с pre-loaded CFG):
 *   WIZ_BASE_COLORS                — 12 common цвята с hex
 *
 * Sacred status: products.php непроменен. Тук просто extract за reuse
 * в wizard-v6.php без duplication.
 */

// ═══ Default color palette — fallback за CFG.colors (sacred parallel) ═══
var WIZ_BASE_COLORS = [
    {name:'Бял',     hex:'#ffffff'},
    {name:'Черен',   hex:'#1a1a1a'},
    {name:'Червен',  hex:'#dc2626'},
    {name:'Розов',   hex:'#ec4899'},
    {name:'Син',     hex:'#1e40af'},
    {name:'Тюркоаз', hex:'#0891b2'},
    {name:'Зелен',   hex:'#16a34a'},
    {name:'Жълт',    hex:'#eab308'},
    {name:'Оранжев', hex:'#ea580c'},
    {name:'Лилав',   hex:'#7c3aed'},
    {name:'Сив',     hex:'#6b7280'},
    {name:'Бежов',   hex:'#d4b896'}
];

function wizColorPalette(){
    if (window.CFG && Array.isArray(CFG.colors) && CFG.colors.length) return CFG.colors;
    return WIZ_BASE_COLORS;
}

function wizHexForName(name){
    if (!name) return '#666';
    var n = String(name).toLowerCase().trim();
    var pal = wizColorPalette();
    var found = pal.find(function(c){ return String(c.name||'').toLowerCase().trim() === n; });
    return found ? found.hex : '#666';
}

// ═══ _SIZE_GROUPS — 1:1 sacred (p.php 10369-10395) ═══
var _SIZE_GROUPS = [
    {id:'letters',label:'Дрехи — букви',values:['XS','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL','8XL']},
    {id:'eu_clothing',label:'Дрехи — EU номера',values:['34','36','38','40','42','44','46','48','50','52','54','56','58','60','62','64','66','68']},
    {id:'shoes_eu',label:'Обувки — EU',values:['35','36','37','38','39','40','41','42','43','44','45','46','47']},
    {id:'shoes_kids',label:'Детски обувки',values:['19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35']},
    {id:'kids_height',label:'Детски — ръст (cm)',values:['50','56','62','68','74','80','86','92','98','104','110','116','122','128','134','140','146','152','158','164','170']},
    {id:'kids_age',label:'Детски — възраст',values:['0-3м','3-6м','6-9м','9-12м','12-18м','18-24м','2-3г','3-4г','4-5г','5-6г','6-7г','7-8г','8-9г','9-10г','10-11г','11-12г','13-14г','15-16г']},
    {id:'pants_waist',label:'Панталони — талия',values:['W26','W27','W28','W29','W30','W31','W32','W33','W34','W36','W38','W40']},
    {id:'pants_length',label:'Панталони — дължина',values:['L28','L30','L32','L34','L36']},
    {id:'jeans',label:'Дънки (талия/дължина)',values:['26/30','27/30','28/30','28/32','29/32','30/30','30/32','30/34','31/32','32/30','32/32','32/34','33/32','34/32','34/34','36/32','36/34','38/32']},
    {id:'bra',label:'Сутиени',values:['65A','65B','65C','65D','70A','70B','70C','70D','70E','75A','75B','75C','75D','75E','75F','80A','80B','80C','80D','80E','80F','85A','85B','85C','85D','85E','85F','90B','90C','90D','90E','90F','95B','95C','95D','95E','100C','100D','100E','100F','105D','105E','110D','110E','115D','115E','120D','120E','125D','130D']},
    {id:'underwear',label:'Бельо',values:['XS','S','M','L','XL','2XL','3XL']},
    {id:'socks',label:'Чорапи',values:['35-38','39-42','43-46']},
    {id:'socks_num',label:'Чорапи — номера',values:['36-38','39-41','42-44','45-47']},
    {id:'tights',label:'Чорапогащи',values:['1','2','3','4','5','S','M','L','XL']},
    {id:'hats',label:'Шапки',values:['S/M','L/XL','One Size','54','55','56','57','58','59','60']},
    {id:'gloves',label:'Ръкавици',values:['XS','S','M','L','XL','6','6.5','7','7.5','8','8.5','9','9.5','10']},
    {id:'belts',label:'Колани',values:['80','85','90','95','100','105','110','115','120','S','M','L','XL']},
    {id:'rings',label:'Пръстени',values:['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII','XIII','XIV','XV','XVI','XVII','XVIII','XIX','XX']},
    {id:'rings_mm',label:'Пръстени — мм',values:['14','14.5','15','15.5','16','16.5','17','17.5','18','18.5','19','19.5','20','20.5','21']},
    {id:'bracelets',label:'Гривни',values:['15cm','16cm','17cm','18cm','19cm','20cm','21cm','S','M','L']},
    {id:'necklaces',label:'Колиета',values:['40cm','42cm','45cm','50cm','55cm','60cm','70cm','80cm']},
    {id:'one_size',label:'Универсален',values:['One Size']},
    {id:'bedding',label:'Спално бельо',values:['Единичен','Двоен','Полуторен','Кралски','70x140','90x200','140x200','160x200','180x200','200x200']},
    {id:'towels',label:'Кърпи',values:['30x50','50x90','70x140','100x150']},
    {id:'volume_ml',label:'Обем (мл)',values:['30ml','50ml','75ml','100ml','150ml','200ml','250ml','300ml','500ml','750ml','1000ml']},
    {id:'weight_g',label:'Тегло (гр)',values:['50г','100г','150г','200г','250г','300г','500г','750г','1000г']}
];

// Merge extra groups from JSON (sacred line 10399-10405)
if (typeof window !== 'undefined' && window._BIZ_DATA && window._BIZ_DATA.extraGroups) {
    window._BIZ_DATA.extraGroups.forEach(function(eg){
        if (!_SIZE_GROUPS.find(function(g){return g.id===eg.id})) {
            _SIZE_GROUPS.push({id:eg.id, label:eg.label, values:eg.values});
        }
    });
}

// ═══ _getSizePresetsOrdered — 1:1 sacred (p.php 10408+) ═══
function _getSizePresetsOrdered(){
    var bt = (window.CFG && CFG.businessType) ? String(CFG.businessType).toLowerCase() : '';
    var bk = (window._BIZ_DATA && window._BIZ_DATA.bizKeywords) || {};

    var scored = [];
    for (var key in bk) {
        var entry = bk[key];
        var score = 0;
        (entry.keywords || []).forEach(function(kw){
            if (bt.indexOf(kw) !== -1) score++;
        });
        if (score > 0) scored.push({key:key, score:score, groups:entry.groups || []});
    }
    scored.sort(function(a,b){ return b.score - a.score; });

    var orderedIds = [];
    var topN = scored.slice(0, 3);
    topN.forEach(function(match){
        (match.groups || []).forEach(function(gid){
            if (orderedIds.indexOf(gid) === -1) orderedIds.push(gid);
        });
    });

    if (!orderedIds.length) {
        orderedIds = _SIZE_GROUPS.map(function(g){ return g.id; });
    }

    var groups = [];
    var usedIds = {};

    // First: tenant-specific star group
    if (window._bizVariants && window._bizVariants.variant_presets) {
        for (var k in window._bizVariants.variant_presets) {
            if (k.toLowerCase().indexOf('размер') !== -1 || k.toLowerCase().indexOf('size') !== -1) {
                var v = window._bizVariants.variant_presets[k];
                if (v && v.length) groups.push({label:'За твоя бизнес ★', vals:v, id:'_biz_custom'});
            }
        }
    }

    // Then: ordered groups
    orderedIds.forEach(function(gid){
        var g = _SIZE_GROUPS.find(function(sg){ return sg.id === gid; });
        if (g) { groups.push({label:g.label, vals:g.values, id:g.id}); usedIds[gid] = true; }
    });

    // Finally: all remaining
    _SIZE_GROUPS.forEach(function(g){
        if (!usedIds[g.id]) groups.push({label:g.label, vals:g.values, id:g.id});
    });

    return groups;
}

// ═══ wizAIColorAutofill — 1:1 sacred (p.php 8352-8386 extracted) ═══
// При render на Section 2:
//   1) Build _detectedColors[] от S.wizData._aiDetectedColors (legacy) +
//      S.wizData._photos[].ai_color/ai_hex (current от 2e++d).
//   2) Find color axis (search "цвят"/"color" в axes[].name).
//   3) If none → auto-rename първа "Вариация N" → "Цвят".
//   4) Push detected colors → axes[colorIdx].values (dedup Set).
//   5) Set _aiColorsApplied = true (once flag).
function wizAIColorAutofill(){
    if (!window.S || !S.wizData || !Array.isArray(S.wizData.axes)) return;
    var _detectedColors = [];
    if (Array.isArray(S.wizData._aiDetectedColors)) {
        S.wizData._aiDetectedColors.forEach(function(c){
            if (c && c.name) _detectedColors.push({name: c.name, hex: c.hex || '#666'});
        });
    }
    if (Array.isArray(S.wizData._photos)) {
        S.wizData._photos.forEach(function(p){
            var n = (p.ai_color || '').trim();
            if (!n) return;
            if (!_detectedColors.find(function(x){return x.name.toLowerCase()===n.toLowerCase()})) {
                _detectedColors.push({name: n, hex: p.ai_hex || '#666'});
            }
        });
    }
    if (!_detectedColors.length || S.wizData._aiColorsApplied) return;

    var _colorAxisIdx = -1;
    S.wizData.axes.forEach(function(ax, i){
        var n = (ax.name || '').toLowerCase();
        if (n.indexOf('цвят') !== -1 || n.indexOf('color') !== -1) _colorAxisIdx = i;
    });
    if (_colorAxisIdx === -1 && S.wizData.axes.length) {
        var _ax0 = S.wizData.axes[0];
        if (/^вариация\s*\d+$/i.test(_ax0.name)) { _ax0.name = 'Цвят'; _colorAxisIdx = 0; }
    }
    if (_colorAxisIdx !== -1) {
        var _existing = {};
        (S.wizData.axes[_colorAxisIdx].values || []).forEach(function(v){
            _existing[String(v).toLowerCase()] = true;
        });
        _detectedColors.forEach(function(c){
            var lk = c.name.toLowerCase();
            if (!_existing[lk]) {
                S.wizData.axes[_colorAxisIdx].values.push(c.name);
                _existing[lk] = true;
            }
        });
        S.wizData._aiColorsApplied = true;
    }
}

// ═══ wizInitVariantsAxes — 1:1 sacred (p.php 8329-8350) ═══
// Init axes ако още няма. Auto-rename generic "Вариация N" → "Размер"/"Цвят"
// ако всички values match size pattern или color names.
function wizInitVariantsAxes(){
    if (!window.S || !S.wizData) return;
    if (!Array.isArray(S.wizData.axes) || !S.wizData.axes.length) {
        S.wizData.axes = [];
        if (window._bizVariants && window._bizVariants.variant_fields) {
            window._bizVariants.variant_fields.forEach(function(f){
                S.wizData.axes.push({name: f, values: []});
            });
        }
        if (!S.wizData.axes.length) {
            S.wizData.axes.push({name:'Вариация 1', values:[]});
            S.wizData.axes.push({name:'Вариация 2', values:[]});
        }
    }
    // Auto-rename axes
    S.wizData.axes.forEach(function(_ax){
        if (!/^вариация\s*\d+$/i.test(_ax.name || '')) return;
        if (!_ax.values || !_ax.values.length) return;
        var _sizePat = /^(xs|s|m|l|xl|xxl|xxxl|xs?-tall|\d{2,3}(\.\d)?(w|t)?)$/i;
        var _allSize = _ax.values.every(function(v){ return _sizePat.test(String(v).trim()); });
        if (_allSize) { _ax.name = 'Размер'; return; }
        var _cfgNames = wizColorPalette().map(function(c){ return String(c.name||'').toLowerCase().trim(); });
        var _allColor = _cfgNames.length && _ax.values.every(function(v){
            return _cfgNames.indexOf(String(v).toLowerCase().trim()) >= 0;
        });
        if (_allColor) { _ax.name = 'Цвят'; }
    });
}
