<?php
/**
 * voice-router.php — S93.WIZARD.V4
 *
 * Spec: PRODUCTS_WIZARD_v4_SPEC §2 (VOICE ROUTING POLICY).
 * Single dispatch point for STT routing. Numeric fields → Whisper Groq,
 * text fields → Web Speech (caller already has the transcript).
 *
 * Не пиши тук UI prompt-и — само routing + uniform result envelope.
 */

require_once __DIR__ . '/voice-tier2.php';

const VOICE_NUMERIC_FIELDS = [
    'price_retail',
    'price_wholesale',
    'price_cost',
    'cost_price',
    'retail_price',
    'wholesale_price',
    'quantity',
    'qty',
    'discount_percent',
    'markup_percent',
    'barcode',
    'code',
    'code_sku',
];

const VOICE_TEXT_FIELDS = [
    'name',
    'description',
    'material',
    'composition',
    'origin',
    'origin_country',
    'notes',
    'supplier_name',
    'customer_name',
    'category',
    'subcategory',
    'color',
    'size',
    'zone',
    'location',
];

/**
 * Decide STT engine for a given wizard field.
 * Returns 'whisper' | 'web_speech' | 'hybrid'.
 * 'hybrid' се връща за полета с context-зависим mix (не routing-decision a-priori,
 * а сигнал към caller да пусне и двете и да викне parseHybridTranscript).
 */
function voiceEngineForField(string $field_type): string {
    $f = strtolower(trim($field_type));
    if (in_array($f, VOICE_NUMERIC_FIELDS, true)) return 'whisper';
    if (in_array($f, VOICE_TEXT_FIELDS, true))    return 'web_speech';
    return 'hybrid';
}

/**
 * Unified envelope returned by router. Frontend винаги вика този endpoint
 * (или директно voice-tier2.php при чисто Whisper). Това дава една точка за
 * cost logging + analytics.
 *
 * @param string $field_type — wizard.field name (e.g. 'price_retail', 'name')
 * @param string|null $audio_b64 — base64 audio за Whisper (само при numeric/hybrid)
 * @param array $web_speech — ['transcript' => ..., 'confidence' => 0..1] (от browser)
 * @param string $lang — bg|en|ro|gr|hr|rs
 * @param array $context — optional hints за Whisper prompt
 *
 * @return array{
 *   ok:bool, engine:string, transcript:string, confidence:float,
 *   duration_ms:int, error:?string, raw:?array
 * }
 */
function routeVoice(
    string $field_type,
    ?string $audio_b64,
    array $web_speech,
    string $lang = 'bg',
    array $context = []
): array {
    $engine = voiceEngineForField($field_type);

    if ($engine === 'web_speech') {
        return [
            'ok'          => true,
            'engine'      => 'web_speech',
            'transcript'  => trim((string)($web_speech['transcript'] ?? '')),
            'confidence'  => (float)($web_speech['confidence'] ?? 0.0),
            'duration_ms' => (int)($web_speech['duration_ms'] ?? 0),
            'error'       => null,
            'raw'         => null,
        ];
    }

    $audio_data = '';
    if (!empty($audio_b64)) {
        $audio_data = base64_decode($audio_b64, true) ?: '';
    }
    if ($audio_data === '') {
        return [
            'ok' => false, 'engine' => $engine, 'transcript' => '', 'confidence' => 0.0,
            'duration_ms' => 0, 'error' => 'no audio for whisper field', 'raw' => null,
        ];
    }

    $whisper = transcribeWithWhisper($audio_data, $lang, [
        'mime'     => $context['mime']     ?? 'audio/webm',
        'filename' => $context['filename'] ?? 'recording.webm',
        'hints'    => $context['hints']    ?? [],
    ]);

    return [
        'ok'          => $whisper['error'] === null,
        'engine'      => 'whisper',
        'transcript'  => (string)$whisper['transcript'],
        'confidence'  => (float)$whisper['confidence'],
        'duration_ms' => (int)$whisper['duration_ms'],
        'error'       => $whisper['error'],
        'raw'         => null,
    ];
}
