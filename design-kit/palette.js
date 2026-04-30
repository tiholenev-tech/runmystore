/* RunMyStore — DESIGN KIT · palette.js
 * 2 hue пъзгача под лого → променя --hue1/--hue2 на :root.
 * Записва в localStorage.rms_hue1 / rms_hue2.
 * Bootstrap on load + live update.
 */
(function() {
    var DEF_H1 = 255, DEF_H2 = 222;

    function applyHues(h1, h2, persist) {
        h1 = String(h1); h2 = String(h2);
        document.documentElement.style.setProperty('--hue1', h1);
        document.documentElement.style.setProperty('--hue2', h2);
        var s1 = document.getElementById('rmsHue1');
        var s2 = document.getElementById('rmsHue2');
        if (s1) {
            s1.value = h1;
            s1.parentElement.style.setProperty('--my-hue', h1);
        }
        if (s2) {
            s2.value = h2;
            s2.parentElement.style.setProperty('--my-hue', h2);
        }
        if (persist !== false) {
            try {
                localStorage.setItem('rms_hue1', h1);
                localStorage.setItem('rms_hue2', h2);
            } catch(_){}
        }
    }

    window.rmsRandomPalette = function() {
        var h1 = 120 + Math.floor(Math.random() * 240);
        var h2 = h1 - 80 + (Math.floor(Math.random() * 60) - 30);
        h2 = ((h2 % 360) + 360) % 360;
        applyHues(h1, h2, true);
        if (navigator.vibrate) navigator.vibrate([5, 30, 5]);
    };

    // Bootstrap saved values immediately
    try {
        var sh1 = localStorage.getItem('rms_hue1');
        var sh2 = localStorage.getItem('rms_hue2');
        if (sh1) document.documentElement.style.setProperty('--hue1', sh1);
        if (sh2) document.documentElement.style.setProperty('--hue2', sh2);
    } catch(_){}

    document.addEventListener('DOMContentLoaded', function() {
        var s1 = document.getElementById('rmsHue1');
        var s2 = document.getElementById('rmsHue2');
        if (s1) s1.addEventListener('input', function() {
            applyHues(this.value, document.getElementById('rmsHue2').value, true);
        });
        if (s2) s2.addEventListener('input', function() {
            applyHues(document.getElementById('rmsHue1').value, this.value, true);
        });

        // Sync sliders with saved values on load
        try {
            var sh1 = localStorage.getItem('rms_hue1') || DEF_H1;
            var sh2 = localStorage.getItem('rms_hue2') || DEF_H2;
            applyHues(sh1, sh2, false);
        } catch(_){}
    });
})();
