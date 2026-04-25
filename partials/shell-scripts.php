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
})();
</script>
