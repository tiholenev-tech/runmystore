<?php
/**
 * ai-brain-record.php — S92.AIBRAIN.PHASE1 (Reactive only).
 *
 * Phase 1 contract:
 *   POST JSON { csrf, text, source } → forward text to chat-send.php
 *   (server-side loopback, same session) → return AI reply as { reply }.
 *
 * Phase 2 will add: persistence to ai_brain_queue (proactive items) +
 * voice-blob upload + STT + escalation cron. None of that lives here yet.
 *
 * Auth: session user_id (same as chat-send.php).
 * CSRF: per-session token from aibrain_csrf_token() (i18n_aibrain.php).
 *       Token comes in body.csrf and X-AI-Brain-CSRF header — both must match.
 * Tenant guard: tenant_id must be present and ≥ 1 in session.
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/i18n_aibrain.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function aibrain_fail(int $code, string $key): void {
    http_response_code($code);
    echo json_encode(['error' => t_aibrain($key)], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    aibrain_fail(405, 'rec.server_err');
}

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id   = (int) $_SESSION['user_id'];
if ($tenant_id < 1 || $user_id < 1) {
    aibrain_fail(403, 'rec.server_err');
}

$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body)) {
    aibrain_fail(400, 'rec.server_err');
}

$expected = aibrain_csrf_token();
$csrfBody   = (string) ($body['csrf'] ?? '');
$csrfHeader = (string) ($_SERVER['HTTP_X_AI_BRAIN_CSRF'] ?? '');
if (!hash_equals($expected, $csrfBody) || !hash_equals($expected, $csrfHeader)) {
    aibrain_fail(403, 'rec.server_err');
}

$text = trim((string) ($body['text'] ?? ''));
if ($text === '') {
    aibrain_fail(400, 'rec.empty');
}
$text = mb_substr(strip_tags($text), 0, 2000);
if ($text === '') {
    aibrain_fail(400, 'rec.empty');
}

// ── Phase 1 passthrough → chat-send.php (loopback POST, same session). ──
$source = (string) ($body['source'] ?? 'aibrain_pill');

$reply = '';
$err   = null;
try {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url     = $scheme . '://' . $host . '/chat-send.php';
    $payload = json_encode(['message' => $text], JSON_UNESCAPED_UNICODE);

    $cookieHeader = '';
    if (!empty($_SERVER['HTTP_COOKIE'])) {
        $cookieHeader = (string) $_SERVER['HTTP_COOKIE'];
    } elseif (session_id()) {
        $cookieHeader = session_name() . '=' . session_id();
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Forwarded-For-AI-Brain: ' . $source,
            ],
            CURLOPT_COOKIE         => $cookieHeader,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            $err = $cerr ?: 'rec.network_err';
        } elseif ($http >= 500) {
            $err = 'rec.server_err';
        } else {
            $decoded = json_decode($resp, true);
            if (is_array($decoded)) {
                if (!empty($decoded['error'])) {
                    $err = (string) $decoded['error'];
                } else {
                    $reply = (string) (
                        $decoded['reply']    ??
                        $decoded['message']  ??
                        $decoded['response'] ??
                        $decoded['text']     ??
                        ''
                    );
                }
            } else {
                $err = 'rec.server_err';
            }
        }
    } else {
        $err = 'rec.server_err';
        error_log('[ai-brain-record] cURL extension unavailable');
    }
} catch (Throwable $e) {
    error_log('[ai-brain-record] tenant=' . $tenant_id . ' err=' . $e->getMessage());
    $err = 'rec.server_err';
}

if ($err !== null && $reply === '') {
    aibrain_fail(502, $err === 'rec.network_err' ? 'rec.network_err' : 'rec.server_err');
}

if ($reply === '') {
    $reply = t_aibrain('rec.server_err');
}

echo json_encode([
    'reply'  => $reply,
    'source' => $source,
    'phase'  => 1,
], JSON_UNESCAPED_UNICODE);
exit;
