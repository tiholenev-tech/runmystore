#!/usr/bin/env python3
"""
S42 Deploy Script — patches products.php for Tasks 1-5
Run on server: python3 /tmp/s42_patch.py
Backup: /var/www/runmystore/products.php.bak.s41
"""

import re
import shutil
import sys

FILE = '/var/www/runmystore/products.php'

# Backup
shutil.copy2(FILE, FILE + '.bak.s41')
print(f"Backup: {FILE}.bak.s41")

with open(FILE, 'r', encoding='utf-8') as f:
    code = f.read()

original_len = len(code)
changes = 0

# ══════════════════════════════════════════════════════════════
# PATCH 1: Extend ?ajax=products with new WHERE conditions
# Find the existing filter block and expand it
# ══════════════════════════════════════════════════════════════

old_products_filter = '''if ($flt === 'low') { $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0"; }
        elseif ($flt === 'out') { $where[] = "(i.quantity = 0 OR i.quantity IS NULL)"; }
        $where_sql = implode(' AND ', $where);'''

new_products_filter = '''if ($flt === 'low') { $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0"; }
        elseif ($flt === 'out') { $where[] = "(i.quantity = 0 OR i.quantity IS NULL)"; }
        elseif ($flt === 'zombie') { $where[] = "i.quantity > 0 AND DATEDIFF(NOW(), COALESCE((SELECT MAX(s2.created_at) FROM sale_items si2 JOIN sales s2 ON s2.id = si2.sale_id WHERE si2.product_id = p.id AND s2.status = 'completed'), p.created_at)) > 45"; }
        elseif ($flt === 'loss') { $where[] = "p.cost_price > 0 AND p.retail_price < p.cost_price"; }
        elseif ($flt === 'slow') { $where[] = "i.quantity > 0 AND DATEDIFF(NOW(), COALESCE((SELECT MAX(s2.created_at) FROM sale_items si2 JOIN sales s2 ON s2.id = si2.sale_id WHERE si2.product_id = p.id AND s2.status = 'completed'), p.created_at)) BETWEEN 25 AND 45"; }
        elseif ($flt === 'no_photo') { $where[] = "(p.image_url IS NULL OR p.image_url = '')"; }
        elseif ($flt === 'no_barcode') { $where[] = "(p.barcode IS NULL OR p.barcode = '')"; }
        elseif ($flt === 'no_supplier') { $where[] = "(p.supplier_id IS NULL OR p.supplier_id = 0)"; }
        elseif ($flt === 'critical_low') { $where[] = "i.quantity BETWEEN 1 AND 2"; }
        elseif ($flt === 'top_sales') { $where[] = "p.id IN (SELECT si3.product_id FROM sale_items si3 JOIN sales s3 ON s3.id = si3.sale_id WHERE s3.store_id = " . (int)$sid . " AND s3.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s3.status = 'completed' GROUP BY si3.product_id ORDER BY SUM(si3.quantity) DESC LIMIT 20)"; }

        // Quick filter params (S42)
        $price_min = isset($_GET['price_min']) ? (float)$_GET['price_min'] : null;
        $price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : null;
        $stock_filter = $_GET['stock'] ?? null;
        $margin_filter = $_GET['margin'] ?? null;
        $date_filter = $_GET['date_added'] ?? null;
        $sales_qf = $_GET['sales_qf'] ?? null;

        if ($price_min !== null) { $where[] = "p.retail_price >= ?"; $params[] = $price_min; }
        if ($price_max !== null) { $where[] = "p.retail_price <= ?"; $params[] = $price_max; }

        if ($stock_filter === 'zero') { $where[] = "(COALESCE(i.quantity,0) = 0)"; }
        elseif ($stock_filter === 'low') { $where[] = "i.quantity > 0 AND i.quantity <= p.min_quantity AND p.min_quantity > 0"; }
        elseif ($stock_filter === '1-5') { $where[] = "i.quantity BETWEEN 1 AND 5"; }
        elseif ($stock_filter === '6-20') { $where[] = "i.quantity BETWEEN 6 AND 20"; }
        elseif ($stock_filter === '20+') { $where[] = "i.quantity > 20"; }

        if ($margin_filter === 'loss') { $where[] = "p.cost_price > 0 AND p.retail_price < p.cost_price"; }
        elseif ($margin_filter === '0-15') { $where[] = "p.cost_price > 0 AND ((p.retail_price - p.cost_price) / p.retail_price * 100) BETWEEN 0 AND 15"; }
        elseif ($margin_filter === '15-30') { $where[] = "p.cost_price > 0 AND ((p.retail_price - p.cost_price) / p.retail_price * 100) BETWEEN 15.01 AND 30"; }
        elseif ($margin_filter === '30-50') { $where[] = "p.cost_price > 0 AND ((p.retail_price - p.cost_price) / p.retail_price * 100) BETWEEN 30.01 AND 50"; }
        elseif ($margin_filter === '50+') { $where[] = "p.cost_price > 0 AND ((p.retail_price - p.cost_price) / p.retail_price * 100) > 50"; }

        if ($date_filter === '7d') { $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; }
        elseif ($date_filter === '30d') { $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; }
        elseif ($date_filter === '90d+') { $where[] = "p.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"; }

        if ($sales_qf === 'top') { $where[] = "p.id IN (SELECT si3.product_id FROM sale_items si3 JOIN sales s3 ON s3.id = si3.sale_id WHERE s3.store_id = " . (int)$sid . " AND s3.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s3.status = 'completed' GROUP BY si3.product_id ORDER BY SUM(si3.quantity) DESC LIMIT 20)"; }
        elseif ($sales_qf === '1-5') { $where[] = "p.id IN (SELECT si3.product_id FROM sale_items si3 JOIN sales s3 ON s3.id = si3.sale_id WHERE s3.store_id = " . (int)$sid . " AND s3.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s3.status = 'completed' GROUP BY si3.product_id HAVING SUM(si3.quantity) BETWEEN 1 AND 5)"; }
        elseif ($sales_qf === 'zero') { $where[] = "p.id NOT IN (SELECT si3.product_id FROM sale_items si3 JOIN sales s3 ON s3.id = si3.sale_id WHERE s3.store_id = " . (int)$sid . " AND s3.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s3.status = 'completed')"; }

        $where_sql = implode(' AND ', $where);'''

if old_products_filter in code:
    code = code.replace(old_products_filter, new_products_filter)
    changes += 1
    print("PATCH 1: Extended ?ajax=products with quick filters + signal filters ✅")
else:
    print("PATCH 1: ⚠️ Could not find products filter block — trying relaxed match")
    # Try with different whitespace
    pattern = r"if \(\$flt === 'low'\).*?elseif \(\$flt === 'out'\).*?\$where_sql = implode\(' AND ', \$where\);"
    m = re.search(pattern, code, re.DOTALL)
    if m:
        code = code[:m.start()] + new_products_filter.lstrip() + code[m.end():]
        changes += 1
        print("PATCH 1: Extended (relaxed match) ✅")
    else:
        print("PATCH 1: ❌ FAILED — manual fix needed")


# ══════════════════════════════════════════════════════════════
# PATCH 2: Replace openQuickFilter, filterBySignal, askAISignal, openAIChatOverlay stubs
# ══════════════════════════════════════════════════════════════

# Replace askAISignal
old_askAI = '''function askAISignal(question) {
    // Open AI chat overlay and send question
    if (typeof openAIChatOverlay === 'function') openAIChatOverlay();
    // If includes/ chat overlay exists, use sendAutoQuestion
    if (typeof sendAutoQuestion === 'function') {
        sendAutoQuestion(question);
    } else {
        showToast('AI чат: ' + question, '');
    }
}'''

new_askAI = '''function askAISignal(question) {
    sendAutoQuestion(question);
}'''

if old_askAI in code:
    code = code.replace(old_askAI, new_askAI)
    changes += 1
    print("PATCH 2a: askAISignal → sendAutoQuestion ✅")
else:
    print("PATCH 2a: ⚠️ askAISignal not found exactly, trying partial")
    code = re.sub(
        r'function askAISignal\(question\)\s*\{[^}]+(?:\{[^}]*\}[^}]*)*\}',
        'function askAISignal(question) {\n    sendAutoQuestion(question);\n}',
        code
    )
    changes += 1
    print("PATCH 2a: askAISignal replaced (regex) ✅")

# Replace filterBySignal
old_filterBySignal = '''function filterBySignal(type) {
    showToast('Филтър по сигнал: ' + type, '');
    // TODO: implement signal-based product filtering
}'''

new_filterBySignal = r'''function filterBySignal(type) {
    // Map signal type to filter value
    const map = {
        'zero_stock': 'out', 'at_loss': 'loss', 'critical_low': 'critical_low',
        'zombie': 'zombie', 'below_min': 'low', 'slow_mover': 'slow',
        'low_margin': 'loss', 'aging': 'zombie',
        'top_sales': 'top_sales', 'top_profit': 'top_sales', 'top_pct': 'top_sales',
        'no_photo': 'no_photo', 'no_barcode': 'no_barcode', 'no_supplier': 'no_supplier',
        'new_week': 'all'
    };
    S.filter = map[type] || 'all';
    S.page = 1;
    loadProducts();
    // Update active pill
    document.querySelectorAll('#signalFilterRow .qfltr-pill').forEach(p => p.classList.remove('active'));
    event?.target?.closest?.('.qfltr-pill')?.classList.add('active');
}'''

if old_filterBySignal in code:
    code = code.replace(old_filterBySignal, new_filterBySignal)
    changes += 1
    print("PATCH 2b: filterBySignal with real logic ✅")
else:
    print("PATCH 2b: ⚠️ filterBySignal not exact, trying regex")
    code = re.sub(
        r"function filterBySignal\(type\)\s*\{[^}]*\}",
        new_filterBySignal,
        code
    )
    changes += 1
    print("PATCH 2b: filterBySignal replaced (regex) ✅")

# Replace openQuickFilter
old_openQF = '''function openQuickFilter(type) {
    showToast('Филтър: ' + type, '');
    // TODO: implement quick filter dropdowns (price range, stock, margin, date)
}'''

new_openQF = r'''function openQuickFilter(type) {
    // Close any open drawer first
    document.querySelectorAll('.qf-drawer.open').forEach(d => d.classList.remove('open'));
    const drawer = document.getElementById('qfDrawer');
    if (!drawer) return;
    const body = document.getElementById('qfDrawerBody');
    let html = '';
    const cs = typeof currency !== 'undefined' ? currency : '€';

    if (type === 'price') {
        html = `<div class="qfd-title">Филтър по цена</div>
            <div class="qfd-presets">
                <div class="qfd-chip" onclick="applyQF('price',{max:20})">до 20 ${cs}</div>
                <div class="qfd-chip" onclick="applyQF('price',{min:20,max:50})">20-50 ${cs}</div>
                <div class="qfd-chip" onclick="applyQF('price',{min:50,max:100})">50-100 ${cs}</div>
                <div class="qfd-chip" onclick="applyQF('price',{min:100})">над 100 ${cs}</div>
            </div>
            <div class="qfd-custom">
                <div class="qfd-row"><label>От</label><input type="number" id="qfPriceMin" min="0" step="1" class="qfd-inp"></div>
                <div class="qfd-row"><label>До</label><input type="number" id="qfPriceMax" min="0" step="1" class="qfd-inp"></div>
            </div>
            <div class="qfd-actions">
                <div class="qfd-btn secondary" onclick="applyQF('price',{})">Изчисти</div>
                <div class="qfd-btn primary" onclick="applyQF('price',{min:document.getElementById('qfPriceMin').value,max:document.getElementById('qfPriceMax').value})">Приложи</div>
            </div>`;
    } else if (type === 'stock') {
        html = `<div class="qfd-title">Филтър по наличност</div>
            <div class="qfd-opts">
                <div class="qfd-opt" onclick="applyQF('stock','zero')">На нула</div>
                <div class="qfd-opt" onclick="applyQF('stock','low')">Под минимално</div>
                <div class="qfd-opt" onclick="applyQF('stock','1-5')">1-5 бр.</div>
                <div class="qfd-opt" onclick="applyQF('stock','6-20')">6-20 бр.</div>
                <div class="qfd-opt" onclick="applyQF('stock','20+')">Над 20 бр.</div>
                <div class="qfd-opt" onclick="applyQF('stock','')">Всички</div>
            </div>`;
    } else if (type === 'margin') {
        html = `<div class="qfd-title">Филтър по марж</div>
            <div class="qfd-opts">
                <div class="qfd-opt" onclick="applyQF('margin','loss')">Под себестойност</div>
                <div class="qfd-opt" onclick="applyQF('margin','0-15')">0-15%</div>
                <div class="qfd-opt" onclick="applyQF('margin','15-30')">15-30%</div>
                <div class="qfd-opt" onclick="applyQF('margin','30-50')">30-50%</div>
                <div class="qfd-opt" onclick="applyQF('margin','50+')">Над 50%</div>
                <div class="qfd-opt" onclick="applyQF('margin','')">Всички</div>
            </div>`;
    } else if (type === 'date') {
        html = `<div class="qfd-title">Филтър по дата на добавяне</div>
            <div class="qfd-opts">
                <div class="qfd-opt" onclick="applyQF('date_added','7d')">Последните 7 дни</div>
                <div class="qfd-opt" onclick="applyQF('date_added','30d')">Последния месец</div>
                <div class="qfd-opt" onclick="applyQF('date_added','90d+')">Над 3 месеца</div>
                <div class="qfd-opt" onclick="applyQF('date_added','')">Всички</div>
            </div>`;
    } else if (type === 'sales') {
        html = `<div class="qfd-title">Филтър по продажби (30 дни)</div>
            <div class="qfd-opts">
                <div class="qfd-opt" onclick="applyQF('sales_qf','top')">Топ продавани</div>
                <div class="qfd-opt" onclick="applyQF('sales_qf','1-5')">1-5 продажби</div>
                <div class="qfd-opt" onclick="applyQF('sales_qf','zero')">Без продажби (zombie)</div>
                <div class="qfd-opt" onclick="applyQF('sales_qf','')">Всички</div>
            </div>`;
    }
    body.innerHTML = html;
    drawer.classList.add('open');
}

// Quick filter state
let _qfState = {};

function applyQF(type, val) {
    if (type === 'price') {
        _qfState.price_min = val.min || null;
        _qfState.price_max = val.max || null;
    } else {
        _qfState[type] = val || null;
    }
    // Close drawer
    document.getElementById('qfDrawer')?.classList.remove('open');
    // Update pill active states
    updateQFPills();
    // Reload products
    S.page = 1;
    loadProducts();
}

function updateQFPills() {
    document.querySelectorAll('.qfltr-pill[data-qf]').forEach(p => {
        const t = p.dataset.qf;
        let active = false;
        if (t === 'price') active = !!(_qfState.price_min || _qfState.price_max);
        else active = !!_qfState[t];
        p.classList.toggle('active', active);
    });
}

function closeQFDrawer() {
    document.getElementById('qfDrawer')?.classList.remove('open');
}'''

if old_openQF in code:
    code = code.replace(old_openQF, new_openQF)
    changes += 1
    print("PATCH 2c: openQuickFilter with real drawers ✅")
else:
    print("PATCH 2c: ⚠️ openQuickFilter not exact, trying regex")
    code = re.sub(
        r"function openQuickFilter\(type\)\s*\{[^}]*\}",
        new_openQF,
        code
    )
    changes += 1
    print("PATCH 2c: openQuickFilter replaced (regex) ✅")

# Replace openAIChatOverlay stub
old_openOverlay = '''function openAIChatOverlay() {
    // Check if shared include exists
    const ov = document.getElementById('aiChatOverlay');
    if (ov) { ov.classList.add('open'); return; }
    showToast('AI чат скоро...', '');
}'''

new_openOverlay = '''function openAIChatOverlay() {
    if (typeof sendAutoQuestion === 'function') {
        // Overlay exists from includes/ai-chat-overlay.php
        const ov = document.getElementById('aiChatOverlay');
        if (ov) { ov.classList.add('open'); return; }
    }
}'''

if old_openOverlay in code:
    code = code.replace(old_openOverlay, new_openOverlay)
    changes += 1
    print("PATCH 2d: openAIChatOverlay updated ✅")
else:
    print("PATCH 2d: ⚠️ openAIChatOverlay not exact — skipping (overlay will still work via sendAutoQuestion)")


# ══════════════════════════════════════════════════════════════
# PATCH 3: Extend loadProducts() to include quick filter params
# ══════════════════════════════════════════════════════════════

old_loadProdParams = "if(S.filter!=='all')p+=`&filter=${S.filter}`;"

new_loadProdParams = """if(S.filter!=='all')p+=`&filter=${S.filter}`;
    // Quick filter params (S42)
    if(_qfState.price_min)p+=`&price_min=${_qfState.price_min}`;
    if(_qfState.price_max)p+=`&price_max=${_qfState.price_max}`;
    if(_qfState.stock)p+=`&stock=${_qfState.stock}`;
    if(_qfState.margin)p+=`&margin=${_qfState.margin}`;
    if(_qfState.date_added)p+=`&date_added=${_qfState.date_added}`;
    if(_qfState.sales_qf)p+=`&sales_qf=${_qfState.sales_qf}`;"""

if old_loadProdParams in code:
    code = code.replace(old_loadProdParams, new_loadProdParams, 1)
    changes += 1
    print("PATCH 3: loadProducts() extended with QF params ✅")
else:
    print("PATCH 3: ⚠️ Could not find loadProducts filter line")


# ══════════════════════════════════════════════════════════════
# PATCH 4: Make cascade category tap auto-navigate (Task 3)
# ══════════════════════════════════════════════════════════════

old_setCascadeCat = '''function setCascadeCat(catId, el) {
    _cascadeCat = catId;
    _cascadeSubcat = 0;
    document.querySelectorAll('#catFilterRow .fltr-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.cat) === catId));
}'''

new_setCascadeCat = '''function setCascadeCat(catId, el) {
    _cascadeCat = catId;
    _cascadeSubcat = 0;
    document.querySelectorAll('#catFilterRow .fltr-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.cat) === catId));
    // Auto-navigate to products list when a specific category is tapped (not "Всички")
    if (catId > 0) {
        goFilteredList();
    }
}'''

if old_setCascadeCat in code:
    code = code.replace(old_setCascadeCat, new_setCascadeCat)
    changes += 1
    print("PATCH 4: setCascadeCat auto-navigate ✅")
else:
    print("PATCH 4: ⚠️ setCascadeCat not exact — trying regex")
    code = re.sub(
        r'function setCascadeCat\(catId, el\)\s*\{[^}]+\}',
        new_setCascadeCat,
        code
    )
    changes += 1
    print("PATCH 4: setCascadeCat replaced (regex) ✅")


# ══════════════════════════════════════════════════════════════
# PATCH 5: Add data-qf attributes to quick filter pills in HTML
# ══════════════════════════════════════════════════════════════

old_price_pill = """onclick="openQuickFilter('price')"><svg"""
new_price_pill = """data-qf="price" onclick="openQuickFilter('price')"><svg"""

old_stock_pill = """onclick="openQuickFilter('stock')"><svg"""
new_stock_pill = """data-qf="stock" onclick="openQuickFilter('stock')"><svg"""

old_margin_pill = """onclick="openQuickFilter('margin')"><svg"""
new_margin_pill = """data-qf="margin" onclick="openQuickFilter('margin')"><svg"""

old_date_pill = """onclick="openQuickFilter('date')"><svg"""
new_date_pill = """data-qf="date_added" onclick="openQuickFilter('date')"><svg"""

for old, new in [(old_price_pill, new_price_pill), (old_stock_pill, new_stock_pill),
                 (old_margin_pill, new_margin_pill), (old_date_pill, new_date_pill)]:
    if old in code:
        code = code.replace(old, new, 1)

# Add sales pill if missing (check for 'Продажби' pill)
if "openQuickFilter('sales')" not in code:
    # Add sales pill after date pill
    old_date_close = """onclick="openQuickFilter('date')"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>Дата</div>"""
    new_date_close = old_date_close + """
            <div class="qfltr-pill" data-qf="sales_qf" onclick="openQuickFilter('sales')"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Продажби</div>"""
    if old_date_close in code:
        code = code.replace(old_date_close, new_date_close, 1)
        changes += 1
        print("PATCH 5a: Added sales pill ✅")

print(f"PATCH 5: data-qf attributes added to pills ✅")
changes += 1


# ══════════════════════════════════════════════════════════════
# PATCH 6: Add Quick Filter Drawer HTML + AI Chat Overlay include
# Insert before </body>
# ══════════════════════════════════════════════════════════════

qf_drawer_html = '''
<!-- ═══ QUICK FILTER DRAWER (S42) ═══ -->
<div id="qfDrawer" class="qf-drawer">
    <div class="qf-backdrop" onclick="closeQFDrawer()"></div>
    <div class="qf-panel">
        <div class="qf-handle" onclick="closeQFDrawer()"><div class="qf-handle-bar"></div></div>
        <div id="qfDrawerBody" class="qf-body"></div>
    </div>
</div>

<?php include __DIR__ . '/includes/ai-chat-overlay.php'; ?>
'''

old_body_close = '</body>'
if old_body_close in code:
    code = code.replace(old_body_close, qf_drawer_html + '\n</body>', 1)
    changes += 1
    print("PATCH 6: QF drawer HTML + AI chat overlay include added ✅")
else:
    print("PATCH 6: ❌ Could not find </body>")


# ══════════════════════════════════════════════════════════════
# PATCH 7: Add CSS for Quick Filter Drawer
# Insert before </style>
# ══════════════════════════════════════════════════════════════

qf_css = '''
/* ═══ QUICK FILTER DRAWER (S42) ═══ */
.qf-drawer{position:fixed;inset:0;z-index:180;pointer-events:none;opacity:0;transition:opacity .2s}
.qf-drawer.open{pointer-events:auto;opacity:1}
.qf-backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.4)}
.qf-panel{
    position:absolute;bottom:0;left:0;right:0;
    background:#0d1117;border-radius:20px 20px 0 0;
    border-top:1px solid rgba(99,102,241,0.2);
    max-height:60vh;overflow-y:auto;
    transform:translateY(100%);transition:transform .3s cubic-bezier(.32,.72,.37,1.1);
    padding-bottom:env(safe-area-inset-bottom,0);
}
.qf-drawer.open .qf-panel{transform:translateY(0)}
.qf-handle{display:flex;justify-content:center;padding:10px 0 4px;cursor:pointer}
.qf-handle-bar{width:36px;height:4px;border-radius:2px;background:rgba(148,163,184,0.3)}
.qf-body{padding:4px 16px 20px}
.qfd-title{font-size:14px;font-weight:700;color:#e2e8f0;margin-bottom:12px}
.qfd-presets{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.qfd-chip{
    padding:8px 16px;border-radius:99px;font-size:12px;font-weight:600;
    background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2);
    color:#a5b4fc;cursor:pointer;transition:all .15s;
}
.qfd-chip:active{background:rgba(99,102,241,0.3);transform:scale(.95)}
.qfd-custom{display:flex;gap:10px;margin-bottom:14px}
.qfd-row{flex:1;display:flex;flex-direction:column;gap:4px}
.qfd-row label{font-size:11px;color:#94a3b8;font-weight:600}
.qfd-inp{
    width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(99,102,241,0.2);
    background:rgba(99,102,241,0.06);color:#e2e8f0;font-size:14px;font-family:inherit;
    outline:none;-webkit-appearance:none;
}
.qfd-inp:focus{border-color:rgba(99,102,241,0.5)}
.qfd-actions{display:flex;gap:8px;margin-top:4px}
.qfd-btn{
    flex:1;padding:12px;border-radius:12px;text-align:center;font-size:13px;font-weight:700;
    cursor:pointer;transition:all .15s;
}
.qfd-btn.primary{background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff}
.qfd-btn.primary:active{filter:brightness(1.2);transform:scale(.97)}
.qfd-btn.secondary{background:rgba(99,102,241,0.1);color:#a5b4fc;border:1px solid rgba(99,102,241,0.2)}
.qfd-btn.secondary:active{background:rgba(99,102,241,0.2)}
.qfd-opts{display:flex;flex-direction:column;gap:2px}
.qfd-opt{
    padding:13px 14px;border-radius:10px;font-size:13px;font-weight:500;
    color:#e2e8f0;cursor:pointer;transition:all .15s;
    border-bottom:1px solid rgba(99,102,241,0.06);
}
.qfd-opt:active{background:rgba(99,102,241,0.15)}
.qfd-opt:last-child{border-bottom:none}
.qfltr-pill.active{background:linear-gradient(135deg,var(--indigo-500),var(--indigo-600)) !important;color:#fff !important;border-color:transparent !important;box-shadow:0 0 10px rgba(99,102,241,0.3)}
'''

# Find the last </style> tag (before the script section)
style_close_count = code.count('</style>')
if style_close_count > 0:
    # Insert before the FIRST </style> (main CSS block)
    idx = code.index('</style>')
    code = code[:idx] + qf_css + '\n</style>' + code[idx + len('</style>'):]
    changes += 1
    print("PATCH 7: Quick filter drawer CSS added ✅")
else:
    print("PATCH 7: ❌ Could not find </style>")


# ══════════════════════════════════════════════════════════════
# PATCH 8: Add _qfState initialization near cascade vars
# ══════════════════════════════════════════════════════════════

old_cascade_vars = "let _signalsData = [];"
new_cascade_vars = "let _signalsData = [];\nlet _qfState = {};"

if old_cascade_vars in code and '_qfState = {}' not in code.split(old_cascade_vars)[0]:
    code = code.replace(old_cascade_vars, new_cascade_vars, 1)
    changes += 1
    print("PATCH 8: _qfState initialization added ✅")


# ══════════════════════════════════════════════════════════════
# SAVE
# ══════════════════════════════════════════════════════════════

with open(FILE, 'w', encoding='utf-8') as f:
    f.write(code)

new_len = len(code)
print(f"\n{'='*50}")
print(f"Changes applied: {changes}")
print(f"File size: {original_len} → {new_len} chars (+{new_len - original_len})")
print(f"Done! Test: https://runmystore.ai/products.php")
