<?php
/**
 * voice-tier2.php — Whisper Tier 2 STT via Groq
 *
 * Spec: BIBLE_v3_0_TECH §3.3, DELIVERY_ORDERS_DECISIONS_FINAL §B/H
 * Закон №1A: винаги показва transcript ПРЕДИ action. Confidence < 0.85 → жълт.
 *
 * Dual-mode:
 *   - require_once → expose functions transcribeWithWhisper(), normalizeWithSynonyms()
 *   - direct POST → multipart upload endpoint, returns JSON {ok,data,error}
 *
 * Credentials: /etc/runmystore/api.env -> GROQ_API_KEY (chmod 600, parsed once)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Чете GROQ API key от secrets файла. Cache-ва за целия PHP request.
 * Връща null ако ключът липсва — caller трябва да реагира явно (не silent fail).
 */
function getGroqApiKey(): ?string {
    static $cached = null;
    static $loaded = false;
    if ($loaded) return $cached;
    $loaded = true;

    $env_file = '/etc/runmystore/api.env';
    if (!is_readable($env_file)) {
        error_log('voice-tier2: api.env unreadable at ' . $env_file);
        return null;
    }
    $env = parse_ini_file($env_file);
    if ($env === false) return null;
    $key = trim($env['GROQ_API_KEY'] ?? '');
    $cached = ($key !== '') ? $key : null;
    return $cached;
}

/**
 * Превежда avg_logprob (от Whisper segments, винаги ≤ 0) в [0..1] confidence.
 * Whisper не връща директна confidence — използваме e^logprob като аппроксимация.
 * Empirically: -0.10 → 0.90, -0.30 → 0.74, -0.70 → 0.50, -1.50 → 0.22.
 */
function whisperLogprobToConfidence(float $avg_logprob): float {
    $p = exp($avg_logprob);
    return max(0.0, min(1.0, $p));
}

/**
 * Превежда audio bytes в transcript през Groq Whisper.
 *
 * @param string $audio_data — суров audio съдържание (wav/webm/m4a/mp3/ogg)
 * @param string $lang — ISO 639-1 ('bg', 'en', 'ro', 'gr', 'hr', 'rs')
 * @param array  $context — optional: ['hints' => [...] / string, 'mime' => 'audio/webm', 'filename' => '...']
 *
 * @return array{transcript:string, confidence:float, engine:string, duration_ms:int, error:?string, raw?:array}
 */
function transcribeWithWhisper(string $audio_data, string $lang = 'bg', array $context = []): array {
    $started = microtime(true);

    if ($audio_data === '') {
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => 0,
            'error' => 'empty audio data',
        ];
    }

    $api_key = getGroqApiKey();
    if (!$api_key) {
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => 0,
            'error' => 'GROQ_API_KEY not configured in /etc/runmystore/api.env',
        ];
    }

    $mime = $context['mime'] ?? 'audio/wav';
    $filename = $context['filename'] ?? 'recording.wav';

    $tmp = tempnam(sys_get_temp_dir(), 'rms_voice_');
    if ($tmp === false) {
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => 0,
            'error' => 'tempnam failed',
        ];
    }
    if (file_put_contents($tmp, $audio_data) === false) {
        @unlink($tmp);
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => 0,
            'error' => 'failed to write tmp audio',
        ];
    }

    $cfile = new CURLFile($tmp, $mime, $filename);
    $post = [
        'file' => $cfile,
        'model' => 'whisper-large-v3',
        'language' => $lang,
        'response_format' => 'verbose_json',
        'temperature' => '0',
    ];

    if (!empty($context['hints'])) {
        $hints = is_array($context['hints']) ? implode(', ', $context['hints']) : (string)$context['hints'];
        if (mb_strlen($hints) > 800) $hints = mb_substr($hints, 0, 800);
        $post['prompt'] = $hints;
    }

    $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);
    @unlink($tmp);

    $duration_ms = (int)((microtime(true) - $started) * 1000);

    if ($response === false) {
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => $duration_ms,
            'error' => 'curl: ' . $curl_err,
        ];
    }
    if ($http_code !== 200) {
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => $duration_ms,
            'error' => 'HTTP ' . $http_code . ': ' . substr((string)$response, 0, 300),
        ];
    }

    $data = json_decode((string)$response, true);
    if (!is_array($data)) {
        return [
            'transcript' => '',
            'confidence' => 0.0,
            'engine' => 'whisper',
            'duration_ms' => $duration_ms,
            'error' => 'invalid JSON from Groq',
        ];
    }

    $transcript = trim((string)($data['text'] ?? ''));

    $confidence = 0.85;
    if (!empty($data['segments']) && is_array($data['segments'])) {
        $sum = 0.0;
        $count = 0;
        foreach ($data['segments'] as $seg) {
            if (isset($seg['avg_logprob'])) {
                $sum += whisperLogprobToConfidence((float)$seg['avg_logprob']);
                $count++;
            }
        }
        if ($count > 0) {
            $confidence = round($sum / $count, 3);
        }
    }

    return [
        'transcript' => $transcript,
        'confidence' => (float)$confidence,
        'engine' => 'whisper',
        'duration_ms' => $duration_ms,
        'error' => null,
        'raw' => $data,
    ];
}

/**
 * Замества synonym-и от voice_synonyms таблицата.
 * Закон H3: „мариса" → „Marina" auto-corrected.
 *
 * Tenant-specific synonyms имат предимство; глобални (tenant_id NULL) са fallback.
 * Increment-ва usage_count за всеки приложен match (best-effort, не блокира).
 */
function normalizeWithSynonyms(string $transcript, int $tenant_id, string $lang = 'bg'): string {
    if (trim($transcript) === '') return $transcript;

    try {
        $rows = DB::run("
            SELECT id, synonym, canonical
            FROM voice_synonyms
            WHERE (tenant_id IS NULL OR tenant_id = ?)
              AND lang = ?
            ORDER BY (tenant_id = ?) DESC, LENGTH(synonym) DESC, usage_count DESC
            LIMIT 500
        ", [$tenant_id, $lang, $tenant_id])->fetchAll();
    } catch (Throwable $e) {
        error_log('voice-tier2 synonym lookup: ' . $e->getMessage());
        return $transcript;
    }

    $normalized = $transcript;
    $hits = [];
    foreach ($rows as $r) {
        $syn = $r['synonym'];
        if ($syn === '' || $syn === null) continue;
        $pattern = '/(?<![\p{L}\d])' . preg_quote($syn, '/') . '(?![\p{L}\d])/iu';
        if (preg_match($pattern, $normalized)) {
            $normalized = preg_replace($pattern, $r['canonical'], $normalized);
            $hits[] = (int)$r['id'];
        }
    }
    if ($hits) {
        try {
            $placeholders = implode(',', array_fill(0, count($hits), '?'));
            DB::run("UPDATE voice_synonyms SET usage_count = usage_count + 1 WHERE id IN ($placeholders)", $hits);
        } catch (Throwable $e) {
            // Best-effort, log only
            error_log('voice-tier2 usage_count update: ' . $e->getMessage());
        }
    }
    return $normalized;
}

/**
 * Записва нов synonym mapping (от user correction).
 * Закон B6: при confirmed mismatch („мариса" → „Marina") → INSERT.
 */
function learnVoiceSynonym(int $tenant_id, string $synonym, string $canonical, string $lang = 'bg', ?string $category = null): bool {
    $synonym = trim($synonym);
    $canonical = trim($canonical);
    if ($synonym === '' || $canonical === '' || mb_strtolower($synonym) === mb_strtolower($canonical)) {
        return false;
    }
    try {
        DB::run("
            INSERT INTO voice_synonyms (tenant_id, lang, synonym, canonical, category, created_by)
            VALUES (?, ?, ?, ?, ?, 'user_correction')
        ", [$tenant_id, $lang, $synonym, $canonical, $category]);
        return true;
    } catch (Throwable $e) {
        error_log('voice-tier2 learnVoiceSynonym: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────
// HTTP ENDPOINT MODE (POST audio file directly to this URL)
// ─────────────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    session_start();
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'data' => null, 'error' => 'unauthorized']);
        exit;
    }

    $tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
    $lang = $_POST['lang'] ?? 'bg';
    if (!preg_match('/^[a-z]{2}$/', $lang)) $lang = 'bg';

    $hints = [];
    if (!empty($_POST['hints'])) {
        $decoded = json_decode((string)$_POST['hints'], true);
        if (is_array($decoded)) $hints = $decoded;
        elseif (is_string($_POST['hints'])) $hints = [$_POST['hints']];
    }

    $audio_data = '';
    $mime = 'audio/wav';
    $filename = 'recording.wav';

    if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
        $audio_data = file_get_contents($_FILES['audio']['tmp_name']);
        $mime = $_FILES['audio']['type'] ?: $mime;
        $filename = $_FILES['audio']['name'] ?: $filename;
    } elseif (!empty($_POST['audio_b64'])) {
        $audio_data = base64_decode((string)$_POST['audio_b64'], true);
        if ($audio_data === false) $audio_data = '';
    }

    if ($audio_data === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'data' => null, 'error' => 'no audio data']);
        exit;
    }

    $result = transcribeWithWhisper($audio_data, $lang, [
        'hints' => $hints,
        'mime' => $mime,
        'filename' => $filename,
    ]);

    if ($result['error'] === null && $result['transcript'] !== '' && $tenant_id > 0) {
        $result['transcript_normalized'] = normalizeWithSynonyms(
            $result['transcript'],
            $tenant_id,
            $lang
        );
    } else {
        $result['transcript_normalized'] = $result['transcript'];
    }

    unset($result['raw']);

    echo json_encode([
        'ok' => $result['error'] === null,
        'data' => $result,
        'error' => $result['error'],
    ]);
    exit;
}
