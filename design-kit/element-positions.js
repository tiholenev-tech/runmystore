// =====================================================================
// ELEMENT POSITIONS v1.0 — visual-gate CHECK 4 helper
// =====================================================================
// IIFE което се инжектира в page преди --dump-dom.
// Извлича bounding rect на всеки visible element и записва JSON масив
// в скрит pre с id "__visual_gate_positions__".
//
// Orchestrator (design-kit/visual-gate.sh) inject-ва този файл преди
// затварящия body tag.
//
// Стартиране (направено от orchestrator-а):
//   chrome --headless --no-sandbox --disable-gpu
//          --window-size=375,812 --dump-dom file:///tmp/instr.html
//
// Output JSON: [{selector, x, y, w, h}, ...]
//   selector = tag + sorted classes (e.g. "div.glass.q-default")
//   coords се закръгляват до int (px).
//
// ВАЖНО: НЕ ползвай "</scr" + "ipt>" символи в коментарите — HTML
// parser-ът би прекъснал script tag-а преди JS да се изпълни.
// =====================================================================

(function visualGatePositions() {
    'use strict';

    const SENTINEL_ID = '__visual_gate_positions__';

    function buildSelector(el) {
        const tag = el.tagName.toLowerCase();
        let raw = '';
        if (typeof el.className === 'string') {
            raw = el.className;
        } else if (el.className && typeof el.className.baseVal === 'string') {
            // SVG elements имат SVGAnimatedString вместо string.
            raw = el.className.baseVal;
        }
        const classes = raw
            .split(/\s+/)
            .map(s => s.trim())
            .filter(Boolean)
            .sort();
        return classes.length ? tag + '.' + classes.join('.') : tag;
    }

    function isVisible(rect, style) {
        if (rect.width <= 0 || rect.height <= 0) return false;
        if (style.display === 'none') return false;
        if (style.visibility === 'hidden') return false;
        return true;
    }

    // S136.ALIGN: walk up the tree looking for an ancestor opting out of the
    // gate (data-vg-skip / hidden attribute). dom-extract.py already filters
    // these subtrees; element-positions.js needs the same filter so position
    // diff doesn't false-flag overlays / hidden modals that are present in
    // the rewrite but absent from the mockup.
    function isGateSkipped(el) {
        for (let cur = el; cur && cur !== document.body; cur = cur.parentElement) {
            if (cur.hasAttribute('data-vg-skip')) return true;
            if (cur.hasAttribute('hidden')) return true;
        }
        return false;
    }

    function collect() {
        const out = [];
        const all = document.querySelectorAll('body, body *');
        for (const el of all) {
            if (el.id === SENTINEL_ID) continue;
            if (isGateSkipped(el)) continue;
            let rect, style;
            try {
                rect = el.getBoundingClientRect();
                style = window.getComputedStyle(el);
            } catch (e) {
                continue;
            }
            if (!isVisible(rect, style)) continue;
            out.push({
                selector: buildSelector(el),
                x: Math.round(rect.left),
                y: Math.round(rect.top),
                w: Math.round(rect.width),
                h: Math.round(rect.height),
            });
        }
        return out;
    }

    function emit(data) {
        let pre = document.getElementById(SENTINEL_ID);
        if (!pre) {
            pre = document.createElement('pre');
            pre.id = SENTINEL_ID;
            pre.style.display = 'none';
            document.body.appendChild(pre);
        }
        pre.textContent = JSON.stringify(data);
    }

    function run() {
        try {
            emit(collect());
        } catch (err) {
            emit({ error: String(err && err.message || err) });
        }
    }

    // Скриптът се inject-ва точно преди затварящ body tag, body вече е parsed.
    // Изчакай fonts.ready, в противен случай text-elements се местят 20-80px
    // докато fonts се swap-ват → false-positive position diffs.
    function go() {
        if (document.body) document.body.offsetHeight; // force layout flush
        run();
    }
    if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
        document.fonts.ready.then(go, go);
    } else {
        go();
    }
})();
