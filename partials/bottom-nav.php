<?php
/**
 * partials/bottom-nav.php — Unified 4-tab bottom navigation (S82.SHELL)
 * AI / Склад / Справки / Продажба — order locked per BIBLE.
 * Active tab auto-detected from $rms_current_module.
 * Requires: partials/shell-init.php loaded first.
 */
if (!defined('RMS_SHELL_INIT')) {
    require __DIR__ . '/shell-init.php';
}

// AI tab is active for chat + simple + life-board pages (entry points)
$isAI       = in_array($rms_current_module, ['chat','simple','life-board','index'], true);
$isWh       = in_array($rms_current_module, ['warehouse','inventory','transfers','deliveries','suppliers','products'], true);
$isStats    = in_array($rms_current_module, ['stats','finance'], true);
$isSale     = ($rms_current_module === 'sale');
?>
<nav class="rms-bottom-nav" id="rmsBottomNav">
    <a href="chat.php" class="rms-nav-tab<?= $isAI ? ' active' : '' ?>" aria-label="AI">
        <svg viewBox="0 0 24 20" fill="none">
            <rect x="2" y="8" width="3" height="7" rx="1.5" fill="currentColor" opacity=".6"><animate attributeName="height" values="7;14;7" dur="1.2s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="1.2s" repeatCount="indefinite"/></rect>
            <rect x="7" y="4" width="3" height="12" rx="1.5" fill="currentColor" opacity=".75"><animate attributeName="height" values="12;6;12" dur="1.2s" begin="0.15s" repeatCount="indefinite"/><animate attributeName="y" values="4;7;4" dur="1.2s" begin="0.15s" repeatCount="indefinite"/></rect>
            <rect x="12" y="2" width="3" height="16" rx="1.5" fill="currentColor" opacity=".9"><animate attributeName="height" values="16;8;16" dur="1.2s" begin="0.3s" repeatCount="indefinite"/><animate attributeName="y" values="2;6;2" dur="1.2s" begin="0.3s" repeatCount="indefinite"/></rect>
            <rect x="17" y="5" width="3" height="10" rx="1.5" fill="currentColor" opacity=".7"><animate attributeName="height" values="10;14;10" dur="1.2s" begin="0.45s" repeatCount="indefinite"/><animate attributeName="y" values="5;3;5" dur="1.2s" begin="0.45s" repeatCount="indefinite"/></rect>
        </svg>
        <span class="rms-nav-tab-label">AI</span>
    </a>
    <a href="warehouse.php" class="rms-nav-tab<?= $isWh ? ' active' : '' ?>" aria-label="Склад">
        <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <span class="rms-nav-tab-label">Склад</span>
    </a>
    <a href="stats.php" class="rms-nav-tab<?= $isStats ? ' active' : '' ?>" aria-label="Справки">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span class="rms-nav-tab-label">Справки</span>
    </a>
    <a href="sale.php" class="rms-nav-tab<?= $isSale ? ' active' : '' ?>" aria-label="Продажба">
        <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span class="rms-nav-tab-label">Продажба</span>
    </a>
</nav>
