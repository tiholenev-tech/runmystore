/* ════════════════════════════════════════════════════════════
 * RunMyStore — DESIGN KIT · theme-toggle.js · v1.1 (01.05.2026)
 *
 * ЗАЩО: design-kit/partial-header.html има <button onclick="rmsToggleTheme()">,
 *       но функцията не съществуваше никъде в kit-а. Резултат: theme toggle =
 *       мъртъв бутон в всеки модул построен по design-kit (S89 GAP REPORT).
 *
 * КАКВО ПРАВИ:
 *  1) Дефинира window.rmsToggleTheme() като глобална функция.
 *  2) Превключва <html data-theme="light"|null> с persist в localStorage.
 *  3) При load (DOMContentLoaded) и при toggle — sync-ва иконите sun/moon.
 *  4) Inline bootstrap в partial-header.html ще set-не data-theme=light ако
 *     localStorage.rms_theme === 'light' преди първия paint.
 *
 * ИНСТРУКЦИЯ ЗА МОДУЛИТЕ:
 *  В <head> ПРЕДИ </body> добави:
 *     <script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
 *  ПРЕДИ <script src="/design-kit/palette.js"></script>.
 *
 * ВАЖНО: <html lang="bg"> — БЕЗ data-theme="dark" атрибут.
 *  Default state = няма data-theme = тъмно.
 *  Bootstrap script-а в partial-header (или в <head> на модула) добавя
 *  data-theme="light" САМО ако localStorage казва така.
 *  Ако в <html> hardcode-неш data-theme="dark" — light won't apply на reload.
 * ════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    var STORAGE_KEY = 'rms_theme';
    var ATTR = 'data-theme';
    var ROOT = document.documentElement;

    function getStoredTheme() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (_) { return null; }
    }
    function setStoredTheme(v) {
        try {
            if (v === 'light') localStorage.setItem(STORAGE_KEY, 'light');
            else localStorage.removeItem(STORAGE_KEY);
        } catch (_) {}
    }

    function applyTheme(theme) {
        if (theme === 'light') ROOT.setAttribute(ATTR, 'light');
        else ROOT.removeAttribute(ATTR);
        syncIcons();
        // notify other listeners (palette.js слуша за това при нужда)
        try {
            window.dispatchEvent(new CustomEvent('rms:theme-changed', { detail: { theme: theme || 'dark' } }));
        } catch (_) {}
    }

    function syncIcons() {
        var sun  = document.getElementById('themeIconSun');
        var moon = document.getElementById('themeIconMoon');
        if (!sun || !moon) return;
        var isLight = ROOT.getAttribute(ATTR) === 'light';
        // Light mode → show sun; Dark mode → show moon
        sun.style.display  = isLight ? '' : 'none';
        moon.style.display = isLight ? 'none' : '';
    }

    // Public API
    window.rmsToggleTheme = function () {
        var current = ROOT.getAttribute(ATTR) === 'light' ? 'light' : 'dark';
        var next = current === 'light' ? 'dark' : 'light';
        applyTheme(next);
        setStoredTheme(next);
        if (navigator.vibrate) try { navigator.vibrate(5); } catch (_) {}
    };

    window.rmsApplyTheme = applyTheme; // helper за settings page или други callers

    // On DOMContentLoaded — make sure icons match current state.
    // (data-theme is already set by inline bootstrap in <head>, преди да се
    //  стигне до тук, така че просто sync-ваме иконите.)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncIcons);
    } else {
        syncIcons();
    }
})();
