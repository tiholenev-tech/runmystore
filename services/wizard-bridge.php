<?php
/**
 * wizard-bridge.php — router към sacred PHP endpoints за wizard-v6.php
 * S148 ФАЗА 2b — 2026-05-17
 *
 * Цел: единствена URL surface за wizard-v6 JS вместо директни fetch към
 * различни sacred файлове. Всеки action `require`-ва съответния sacred
 * endpoint inline — session_start, auth, headers, JSON output идват
 * от target-а, бриджът само рутира.
 *
 * Sacred status: НЕ пипа voice-tier2.php · price-ai.php · ai-color-detect.php.
 * Q1 (Тих, 17.05.2026): "router САМО към PHP sacred endpoints, НЕ wrap-ва JS функции".
 *
 * Usage:
 *   POST /services/wizard-bridge.php?action=mic_whisper  (multipart audio)
 *   POST /services/wizard-bridge.php?action=price_parse  (JSON {text, lang})
 *   POST /services/wizard-bridge.php?action=color_detect (multipart image)
 *   POST /services/wizard-bridge.php?action=ai_vision    (multipart image)  [2c]
 *   POST /services/wizard-bridge.php?action=ai_markup    (JSON)             [2d]
 */

$action = (string)($_GET['action'] ?? '');

$map = [
    'mic_whisper'  => __DIR__ . '/voice-tier2.php',
    'price_parse'  => __DIR__ . '/price-ai.php',
    'color_detect' => __DIR__ . '/../ai-color-detect.php',
    'ai_vision'    => __DIR__ . '/ai-vision.php',
    'ai_markup'    => __DIR__ . '/ai-markup.php',
];

if (!isset($map[$action])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'error' => 'unknown action',
        'valid' => array_keys($map),
    ]);
    exit;
}

$target = $map[$action];
if (!is_file($target)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'     => false,
        'error'  => 'endpoint not yet deployed',
        'action' => $action,
    ]);
    exit;
}

require $target;
