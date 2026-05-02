<?php
/**
 * S92.AIBRAIN.PHASE1 — minimal i18n shim for AI Brain strings.
 *
 * Why a separate file: the codebase has no existing t() helper, and adding
 * a global one risks colliding with concurrent work on other modules.
 * This file owns ONLY AI Brain strings; loaded by partials and the backend.
 *
 * When a real i18n system arrives, replace t_aibrain() with t() and merge
 * this strings table into the translations source of truth.
 */

if (!function_exists('t_aibrain')) {
    function t_aibrain(string $key, ?string $fallback = null): string {
        static $strings = null;
        if ($strings === null) {
            $strings = [
                'pill.label'         => 'AI Brain',
                'pill.aria'          => 'Отвори AI Brain',
                'pill.sub'           => 'Питай каквото искаш',
                'rec.title'          => 'AI Brain',
                'rec.recording'     => '● ЗАПИСВА',
                'rec.ready'         => '✓ ГОТОВО',
                'rec.placeholder'   => 'Слушам…',
                'rec.hint_idle'     => 'Натисни и говори. AI ще те чуе.',
                'rec.hint_record'   => 'Кажи каквото искаш — после натисни Изпрати.',
                'rec.hint_ready'    => 'Прегледай текста и натисни Изпрати.',
                'rec.hint_thinking' => 'AI мисли…',
                'rec.cancel'        => 'Затвори',
                'rec.send'          => 'Изпрати',
                'rec.unsupported'   => 'Браузърът не поддържа гласово записване.',
                'rec.mic_denied'    => 'Микрофонът е блокиран.',
                'rec.empty'         => 'Не чух нищо. Опитай пак.',
                'rec.network_err'   => 'Връзката пропадна. Опитай пак.',
                'rec.server_err'    => 'AI не отговори. Опитай пак.',
            ];
        }
        if (isset($strings[$key])) return $strings[$key];
        return $fallback ?? $key;
    }
}

if (!function_exists('aibrain_csrf_token')) {
    /**
     * Returns (and lazily creates) the per-session CSRF token used by
     * ai-brain-record.php. Tied to PHP session, regenerated on logout.
     */
    function aibrain_csrf_token(): string {
        if (empty($_SESSION['aibrain_csrf'])) {
            $_SESSION['aibrain_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['aibrain_csrf'];
    }
}
