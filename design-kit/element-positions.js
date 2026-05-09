// =====================================================================
// ELEMENT POSITIONS v1.3 — visual-gate CHECK 4 helper
// =====================================================================
// IIFE injected into the page before chromium dump-dom.
// For each visible element extracts a STABLE STRUCTURAL SELECTOR (path
// from <body> through ancestors with class signature + nth-of-type
// among same-tag siblings) and its bounding rect.
//
// Output JSON: [{selector, x, y, w, h}, ...]  written to a hidden
// <pre id="__visual_gate_positions__"> for the orchestrator to pluck.
//
// v1.3 change vs v1.0:
//   v1.0 selector = tag + sorted classes (NOT unique — many elements
//   share it, e.g. <div class="lb-card">). Position diff paired by
//   index-within-selector-group, which mismatched whenever element
//   counts differed across mockup vs rewrite, cascading into hundreds
//   of false-positive "moved" reports.
//   v1.3 selector = full tree path with nth-of-type qualifiers:
//   "body>main.app:nth-of-type(1)>div.lb-card.q-loss:nth-of-type(3)>span.shine:nth-of-type(1)"
//   Each visible element gets a globally-unique structural identifier.
//   Position diff in visual-gate.sh now does 1:1 lookup by selector
//   (mockup-only / rewrite-only selectors are caught by the DOM check
//   already, so position diff focuses only on elements present in
//   BOTH renders).
//
// Skips (preserved from v1.2):
//   - elements with hidden / data-vg-skip on themselves OR an ancestor
//   - elements with display:none, visibility:hidden, zero-size rect
//
// IMPORTANT: do NOT include "</scr" + "ipt>" sequences in comments.
// =====================================================================

(function visualGatePositions() {
    'use strict';

    const SENTINEL_ID = '__visual_gate_positions__';

    function getClassesSorted(el) {
        let raw = '';
        if (typeof el.className === 'string') {
            raw = el.className;
        } else if (el.className && typeof el.className.baseVal === 'string') {
            // SVG elements have SVGAnimatedString instead of string.
            raw = el.className.baseVal;
        }
        return raw.split(/\s+/).map(s => s.trim()).filter(Boolean).sort();
    }

    // Build the per-element structural identifier used by position diff.
    // Walks ancestors up to <body> and stitches together one segment per
    // ancestor: "<tag>.<classes>:nth-of-type(<N>)" where N counts only
    // siblings of the same tag (matches CSS nth-of-type semantics).
    // body itself contributes a constant "body" prefix so equivalent
    // structural positions in mockup vs rewrite produce identical strings
    // even when body class differs (which is normal — JS adds class).
    function buildStableSelector(el) {
        if (!el || el === document.body) return 'body';
        const parts = [];
        let cur = el;
        while (cur && cur !== document.body && cur.parentElement) {
            const tag = cur.tagName.toLowerCase();
            const classes = getClassesSorted(cur);
            // nth-of-type position among same-tag siblings of cur.parentElement
            let idx = 1;
            let sib = cur.previousElementSibling;
            while (sib) {
                if (sib.tagName && sib.tagName.toLowerCase() === tag) idx++;
                sib = sib.previousElementSibling;
            }
            const seg = (classes.length ? tag + '.' + classes.join('.') : tag)
                      + ':nth-of-type(' + idx + ')';
            parts.unshift(seg);
            cur = cur.parentElement;
        }
        return 'body>' + parts.join('>');
    }

    function isVisible(rect, style) {
        if (rect.width <= 0 || rect.height <= 0) return false;
        if (style.display === 'none') return false;
        if (style.visibility === 'hidden') return false;
        return true;
    }

    // Gate-skip walker (v1.2 carry-over): treat any ancestor with hidden /
    // data-vg-skip the same as the element itself being skipped.
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
                selector: buildStableSelector(el),
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

    // Wait for fonts.ready — without it text-elements drift 20-80px while
    // fonts swap, producing false position diffs.
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
