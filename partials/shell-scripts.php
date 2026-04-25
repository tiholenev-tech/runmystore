<?php
/**
 * partials/shell-scripts.php — Shared shell JS (S82.SHELL)
 * Theme toggle + logout dropdown + AI chat entry point.
 * Idempotent — duplicate inclusion does nothing because of window guard.
 */
?>
<script>
(function(){
    if (window.__rmsShellLoaded) return;
    window.__rmsShellLoaded = true;

    // ─── THEME (default DARK, persists in localStorage['rms_theme']) ───
    try {
        var saved = localStorage.getItem('rms_theme');
        if (saved === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (_) {}

    function syncThemeIcons() {
        var sun = document.getElementById('themeIconSun');
        var moon = document.getElementById('themeIconMoon');
        if (!sun || !moon) return;
        var isLight = document.documentElement.getAttribute('data-theme') === 'light';
        sun.style.display = isLight ? '' : 'none';
        moon.style.display = isLight ? 'none' : '';
    }
    document.addEventListener('DOMContentLoaded', syncThemeIcons);

    window.rmsToggleTheme = function () {
        var cur = document.documentElement.getAttribute('data-theme') || 'dark';
        var nxt = (cur === 'light') ? 'dark' : 'light';
        if (nxt === 'light') document.documentElement.setAttribute('data-theme', 'light');
        else document.documentElement.removeAttribute('data-theme');
        try { localStorage.setItem('rms_theme', nxt); } catch (_) {}
        syncThemeIcons();
        if (navigator.vibrate) navigator.vibrate(5);
    };

    // ─── LOGOUT DROPDOWN ───
    window.rmsToggleLogout = function (e) {
        if (e && e.stopPropagation) e.stopPropagation();
        var dd = document.getElementById('logoutDrop');
        if (dd) dd.classList.toggle('show');
    };
    document.addEventListener('click', function (e) {
        var btn = document.getElementById('logoutBtn');
        var dd = document.getElementById('logoutDrop');
        if (btn && dd && !btn.contains(e.target)) dd.classList.remove('show');
    });

    // ─── PRINTER (hook to existing logic if loaded) ───
    window.rmsOpenPrinter = function () {
        if (typeof window.openPrinterSettings === 'function') return window.openPrinterSettings();
        if (typeof window.printerPair === 'function') return window.printerPair();
        if (typeof window.showToast === 'function') return window.showToast('Свързване с принтер — отвори Настройки');
        location.href = 'printer-setup.php';
    };

    // ─── AI CHAT ENTRY POINT ───
    // If host page defines openChat() (chat.php) — call it. Else navigate to chat.php.
    window.rmsOpenChat = function (e) {
        if (e && e.preventDefault) e.preventDefault();
        if (typeof window.openChat === 'function') return window.openChat();
        location.href = 'chat.php';
    };

    // ─── HEADER PRINT-STATUS sync (reads window.rmsPrinterStatus if set) ───
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('printStatusBtn');
        if (!btn) return;
        var s = window.rmsPrinterStatus;
        if (s === 'paired') btn.classList.add('paired');
        else if (s === 'error') btn.classList.add('error');
    });

    // ─── Auto-add has-rms-shell so content gets bottom padding for chat-input + bottom-nav ───
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('has-rms-shell')) {
            document.body.classList.add('has-rms-shell');
        }
    });

    // ─── Horizontal SWIPE NAVIGATION between bottom-nav modules ───
    // Order matches the unified bottom-nav: AI ←→ Склад ←→ Справки ←→ Продажба.
    // Ignores swipes that start on inputs / open drawers / overlays / scrollable areas.
    var NAV_ORDER = ['chat.php', 'warehouse.php', 'stats.php', 'sale.php'];
    var NAV_MAP = {
        'chat.php':1<<31, // AI group fallback handled below
        'simple.php':0,'life-board.php':0,'index.php':0,
        'warehouse.php':1,'products.php':1,'inventory.php':1,'transfers.php':1,'deliveries.php':1,'suppliers.php':1,
        'stats.php':2,'finance.php':2,'finance.html':2,
        'sale.php':3
    };
    var SWIPE_THRESHOLD = 80;
    var SWIPE_MAX_VERTICAL = 50;
    var SWIPE_BLOCK_SELECTOR =
        'input, textarea, select, button, [contenteditable], a[href], '
      + '.modal-ov.open, .ov-bg.open, .preset-ov, .camera-ov, .camera-ov.open, '
      + '.rec-ov.active, .drawer.open, .ws-sheet.open, .ws-overlay.open, '
      + '.parked-overlay.open, .pay-sheet.open, .ew-panel.open, '
      + '#wizModal[style*="display: flex"], #wizModal.open, '
      + '.cam-header, video, canvas, '
      + '[data-no-swipe], .v-axis-tabs, .period-bar, .rev-pills, '
      + '.zt-tabs, .scroll-x, [data-horizontal-scroll]';

    function isHorizScrollable(el) {
        while (el && el !== document.body) {
            if (el.scrollWidth > el.clientWidth + 4) {
                var oc = getComputedStyle(el).overflowX;
                if (oc === 'auto' || oc === 'scroll') return true;
            }
            el = el.parentElement;
        }
        return false;
    }

    function currentNavIndex() {
        var name = (location.pathname.split('/').pop() || 'chat.php').toLowerCase();
        if (name === '' ) name = 'chat.php';
        if (NAV_MAP[name] !== undefined && NAV_MAP[name] !== (1<<31)) return NAV_MAP[name];
        // Default fallback (chat / unknown root)
        return 0;
    }

    var _sx = 0, _sy = 0, _sActive = false;

    document.addEventListener('touchstart', function (e) {
        if (e.touches.length !== 1) { _sActive = false; return; }
        var t = e.target;
        if (t && t.closest && t.closest(SWIPE_BLOCK_SELECTOR)) { _sActive = false; return; }
        if (isHorizScrollable(t)) { _sActive = false; return; }
        _sx = e.touches[0].clientX;
        _sy = e.touches[0].clientY;
        _sActive = true;
    }, { passive: true });

    document.addEventListener('touchend', function (e) {
        if (!_sActive) return;
        _sActive = false;
        if (e.changedTouches.length !== 1) return;
        var dx = e.changedTouches[0].clientX - _sx;
        var dy = e.changedTouches[0].clientY - _sy;
        if (Math.abs(dx) < SWIPE_THRESHOLD) return;
        if (Math.abs(dy) > SWIPE_MAX_VERTICAL) return;
        // Edge swipe within 24px from screen edge: ignore (often = browser back gesture)
        if (_sx < 24 || _sx > (window.innerWidth - 24)) return;
        var cur = currentNavIndex();
        var nxt = (dx < 0) ? cur + 1 : cur - 1;
        if (nxt < 0 || nxt >= NAV_ORDER.length) return;
        if (NAV_ORDER[nxt] === (location.pathname.split('/').pop() || 'chat.php').toLowerCase()) return;
        // small visual hint
        document.body.style.transition = 'opacity .15s ease';
        document.body.style.opacity = '.55';
        location.href = NAV_ORDER[nxt];
    }, { passive: true });
})();
</script>
