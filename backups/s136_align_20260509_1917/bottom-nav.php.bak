<?php
/**
 * partials/bottom-nav.php — Unified 4-tab bottom navigation
 * S82.SHELL · S136.PARTIALS_STANDARD (flat redesign, no animations)
 *
 * 4 tabs (order locked): AI / Склад / Справки / Продажба
 * Icons (per S136 spec):
 *   AI       = sparkle
 *   Склад    = box
 *   Справки  = chart
 *   Продажба = cart
 *
 * Active tab auto-detected from $rms_current_module.
 * Active state: colored icon + neon glow (dark) or recessed shadow (light).
 * Inactive state: monochrome icon + flat surface.
 *
 * BICHROMATIC v4.1 styling lives inline below the markup so this partial
 * is self-contained and doesn't depend on css/shell.css being loaded after.
 *
 * Requires: partials/shell-init.php loaded first.
 */
if (!defined('RMS_SHELL_INIT')) {
    require __DIR__ . '/shell-init.php';
}

$isAI       = in_array($rms_current_module, ['chat','simple','life-board','index'], true);
$isWh       = in_array($rms_current_module, ['warehouse','inventory','transfers','deliveries','suppliers','products'], true);
$isStats    = in_array($rms_current_module, ['stats','finance'], true);
$isSale     = ($rms_current_module === 'sale');
?>
<nav class="rms-bottom-nav" id="rmsBottomNav">
    <a href="chat.php" class="rms-nav-tab<?= $isAI ? ' active' : '' ?>" aria-label="AI">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6z"/>
            <path d="M19 14l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>
            <path d="M5 14l.5 1.4 1.4.5-1.4.5L5 17.8l-.5-1.4L3 15.9l1.4-.5z"/>
        </svg>
        <span class="rms-nav-tab-label">AI</span>
    </a>
    <a href="warehouse.php" class="rms-nav-tab<?= $isWh ? ' active' : '' ?>" aria-label="Склад">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <span class="rms-nav-tab-label">Склад</span>
    </a>
    <a href="stats.php" class="rms-nav-tab<?= $isStats ? ' active' : '' ?>" aria-label="Справки">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
        <span class="rms-nav-tab-label">Справки</span>
    </a>
    <a href="sale.php" class="rms-nav-tab<?= $isSale ? ' active' : '' ?>" aria-label="Продажба">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1.5"/>
            <circle cx="18" cy="21" r="1.5"/>
            <path d="M3 3h2l2.7 12.3a2 2 0 0 0 2 1.7h7.6a2 2 0 0 0 2-1.5L21 8H6"/>
        </svg>
        <span class="rms-nav-tab-label">Продажба</span>
    </a>
</nav>

<style>
/* ─────────────────────────────────────────────────────────────────────
   S136.PARTIALS_STANDARD — bottom-nav v4.1 BICHROMATIC
   Self-contained styles. Tokens already defined upstream by tokens.css.
   ───────────────────────────────────────────────────────────────────── */
.rms-bottom-nav {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 80;
    display: grid; grid-template-columns: repeat(4, 1fr);
    padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
    gap: 6px;
    border-top: 1px solid transparent;
}
.rms-nav-tab {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 3px; padding: 8px 4px; border-radius: var(--radius-sm, 14px);
    color: var(--text-muted, #64748b); text-decoration: none;
    transition: color var(--dur-fast, 150ms) var(--ease, cubic-bezier(0.5,1,0.89,1)),
                transform var(--dur-fast, 150ms) var(--ease, cubic-bezier(0.5,1,0.89,1)),
                box-shadow var(--dur-fast, 150ms) var(--ease, cubic-bezier(0.5,1,0.89,1));
}
.rms-nav-tab svg {
    width: 22px; height: 22px;
    transition: filter var(--dur-fast, 150ms) var(--ease, cubic-bezier(0.5,1,0.89,1));
}
.rms-nav-tab-label {
    font-size: 10px; font-weight: 600; letter-spacing: 0.04em; line-height: 1;
}
.rms-nav-tab:active { transform: scale(var(--press, 0.97)); }

/* ─── LIGHT (Neumorphism) ─────────────────────────────────────────── */
[data-theme="light"] .rms-bottom-nav,
:root:not([data-theme]) .rms-bottom-nav {
    background: var(--surface, #e0e5ec);
    box-shadow: 0 -4px 12px rgba(163, 177, 198, 0.18);
}
[data-theme="light"] .rms-nav-tab,
:root:not([data-theme]) .rms-nav-tab {
    background: transparent;
}
[data-theme="light"] .rms-nav-tab.active,
:root:not([data-theme]) .rms-nav-tab.active {
    color: hsl(var(--hue1, 255), 70%, 50%);
    /* recessed neumorphic well + soft inner shadow */
    background: var(--surface-2, #d1d9e6);
    box-shadow:
        inset 2px 2px 5px rgba(163, 177, 198, 0.55),
        inset -2px -2px 5px rgba(255, 255, 255, 0.85);
}
[data-theme="light"] .rms-nav-tab.active svg,
:root:not([data-theme]) .rms-nav-tab.active svg {
    filter: drop-shadow(0 1px 2px hsla(var(--hue1, 255), 70%, 50%, 0.35));
}

/* ─── DARK (Neon Glass — conic shine + glow on active) ────────────── */
[data-theme="dark"] .rms-bottom-nav {
    background: hsl(220 25% 4.8% / 0.92);
    backdrop-filter: blur(16px) saturate(0.85);
    border-top: 1px solid hsl(220 25% 12% / 0.5);
}
[data-theme="dark"] .rms-nav-tab {
    color: hsl(220 10% 55%);
    position: relative; overflow: hidden;
}
[data-theme="dark"] .rms-nav-tab.active {
    color: hsl(var(--hue1, 255), 80%, 65%);
    background:
        radial-gradient(ellipse at center 65%, hsla(var(--hue1, 255), 80%, 50%, 0.18), transparent 70%),
        hsl(220 20% 8% / 0.75);
    box-shadow:
        0 0 0 1px hsla(var(--hue1, 255), 70%, 50%, 0.32),
        0 -2px 12px hsla(var(--hue1, 255), 80%, 50%, 0.20);
}
[data-theme="dark"] .rms-nav-tab.active::before {
    content: '';
    position: absolute; inset: 0;
    border-radius: inherit;
    background: conic-gradient(
        from -45deg at center,
        transparent 12%,
        hsla(var(--hue1, 255), 80%, 60%, 0.35),
        transparent 50%
    );
    pointer-events: none; mix-blend-mode: plus-lighter;
    opacity: 0.55;
    animation: rmsNavShine 4s linear infinite;
}
[data-theme="dark"] .rms-nav-tab.active svg {
    filter: drop-shadow(0 0 4px hsla(var(--hue1, 255), 90%, 60%, 0.6));
}
@keyframes rmsNavShine {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ─── BACK BUTTON (used in partials/header.php when mode=simple) ──── */
.rms-back-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: var(--radius-icon, 50%);
    color: var(--text-muted, #64748b); background: transparent;
    transition: color var(--dur-fast, 150ms) var(--ease, cubic-bezier(0.5,1,0.89,1)),
                transform var(--dur-fast, 150ms) var(--ease, cubic-bezier(0.5,1,0.89,1));
}
.rms-back-btn svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.rms-back-btn:active { transform: scale(var(--press, 0.97)) translateX(-1px); }
[data-theme="light"] .rms-back-btn:hover,
:root:not([data-theme]) .rms-back-btn:hover { color: hsl(var(--hue1, 255), 70%, 45%); }
[data-theme="dark"] .rms-back-btn:hover { color: hsl(var(--hue1, 255), 80%, 70%); }
</style>
