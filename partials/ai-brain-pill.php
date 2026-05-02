<?php
/**
 * partials/ai-brain-pill.php — S92.AIBRAIN.PHASE1
 *
 * Renders the AI Brain pill that opens partials/voice-overlay.php on tap.
 *
 * Two render modes (set $aibrain_mode BEFORE include):
 *   'pill' (default) — ~80×44px pill, used under the 4 ops buttons in life-board.php
 *   'fab'           — 42×42px floating mini-FAB for Simple Mode modules
 *                     (sale.php, products.php etc. — Phase 3 will wire this in)
 *
 * Always includes partials/voice-overlay.php once. Repeat includes are safe
 * because voice-overlay.php is idempotent (window.__aibrainOvLoaded guard).
 *
 * Strings via t_aibrain() — no hardcoded BG.
 */
require_once __DIR__ . '/../config/i18n_aibrain.php';

$aibrain_mode = $aibrain_mode ?? 'pill';
?>
<?php if ($aibrain_mode === 'fab'): ?>
<button type="button"
        class="aibrain-fab s87v3-tap"
        id="aibrainFab"
        aria-label="<?= htmlspecialchars(t_aibrain('pill.aria'), ENT_QUOTES) ?>"
        onclick="window.aibrainOpen && window.aibrainOpen()">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
        <path d="M19 11v1a7 7 0 0 1-14 0v-1"/>
        <line x1="12" y1="19" x2="12" y2="23"/>
        <line x1="8"  y1="23" x2="16" y2="23"/>
    </svg>
</button>
<?php else: ?>
<div class="aibrain-pill-row">
    <button type="button"
            class="glass sm aibrain-pill s87v3-tap"
            id="aibrainPill"
            aria-label="<?= htmlspecialchars(t_aibrain('pill.aria'), ENT_QUOTES) ?>"
            onclick="window.aibrainOpen && window.aibrainOpen()">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="aibrain-pill-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M9 3a4 4 0 0 0-4 4 4 4 0 0 0-2 7 4 4 0 0 0 3 6 4 4 0 0 0 7 1 4 4 0 0 0 7-1 4 4 0 0 0 3-6 4 4 0 0 0-2-7 4 4 0 0 0-4-4 4 4 0 0 0-4 2 4 4 0 0 0-4-2z"/>
            </svg>
        </span>
        <span class="aibrain-pill-text">
            <span class="aibrain-pill-label"><?= htmlspecialchars(t_aibrain('pill.label')) ?></span>
            <span class="aibrain-pill-sub"><?= htmlspecialchars(t_aibrain('pill.sub')) ?></span>
        </span>
    </button>
</div>
<?php endif; ?>

<style>
/* ─── AI Brain pill (life-board) — q-magic hue 280/310 per BIBLE ─── */
.aibrain-pill-row{display:flex;justify-content:center;margin-top:11px}
.aibrain-pill{
    --hue1:280;--hue2:310;
    padding:10px 22px;display:inline-flex;align-items:center;gap:10px;
    cursor:pointer;text-decoration:none;color:inherit;font-family:inherit;
    background:transparent;border:1px solid hsl(280 50% 35% / .55);
    min-height:44px;min-width:200px;position:relative;
}
.aibrain-pill > *{position:relative;z-index:5}
.aibrain-pill-icon{display:inline-flex;align-items:center;justify-content:center}
.aibrain-pill-icon svg{
    width:18px;height:18px;fill:none;
    stroke:hsl(290 90% 80%);stroke-width:1.6;
    stroke-linecap:round;stroke-linejoin:round;
    filter:drop-shadow(0 0 8px hsl(290 90% 60% / .75));
}
.aibrain-pill-text{display:flex;flex-direction:column;align-items:flex-start;line-height:1.1}
.aibrain-pill-label{
    font-size:12.5px;font-weight:900;letter-spacing:.3px;
    color:hsl(290 95% 92%);
    text-shadow:0 0 10px hsl(290 85% 60% / .5);
}
.aibrain-pill-sub{
    font-size:7.5px;color:hsl(290 65% 75%);font-weight:700;
    letter-spacing:.5px;text-transform:uppercase;margin-top:1px;
}

/* ─── AI Brain mini-FAB (Simple Mode modules) — Phase 3 wires this in ─── */
.aibrain-fab{
    position:fixed;
    right:calc(16px + env(safe-area-inset-right,0px));
    bottom:calc(16px + env(safe-area-inset-bottom,0px));
    width:42px;height:42px;border-radius:50%;
    background:linear-gradient(135deg,hsl(280 65% 45%),hsl(310 65% 38%));
    border:1px solid hsl(290 70% 60% / .55);
    box-shadow:0 6px 18px hsl(290 70% 50% / .45),inset 0 1px 0 rgba(255,255,255,0.12);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-family:inherit;color:#fff;
    z-index:200;padding:0;
}
.aibrain-fab svg{
    width:20px;height:20px;fill:none;stroke:#fff;stroke-width:1.8;
    stroke-linecap:round;stroke-linejoin:round;
    filter:drop-shadow(0 0 6px hsl(290 90% 80% / .55));
}
</style>

<?php include __DIR__ . '/voice-overlay.php'; ?>
