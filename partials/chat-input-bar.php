<?php
/**
 * partials/chat-input-bar.php — Sticky "Кажи или напиши..." bar (S82.SHELL)
 * Click → opens chat overlay if defined locally, otherwise navigates to chat.php.
 * Lives above bottom-nav, hidden when overlay-open class is on body.
 */
?>
<div class="rms-input-bar" id="rmsInputBar" onclick="rmsOpenChat(event)" role="button" aria-label="AI чат">
    <div class="rms-input-inner">
        <div class="rms-input-waves">
            <div class="rms-input-wave"></div>
            <div class="rms-input-wave"></div>
            <div class="rms-input-wave"></div>
            <div class="rms-input-wave"></div>
            <div class="rms-input-wave"></div>
        </div>
        <span class="rms-input-placeholder">Кажи или напиши...</span>
        <div class="rms-input-mic" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
        </div>
        <div class="rms-input-send" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </div>
    </div>
</div>
