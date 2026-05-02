<?php
/**
 * parse-hybrid-voice.php — S93.WIZARD.V4
 *
 * Spec: PRODUCTS_WIZARD_v4_SPEC §2 (Hybrid / Mixed input) + §3 (magic words).
 *
 * Воice command може да съдържа и текст, и числа.
 *   "червени тениски 25 лева" → name="червени тениски", price=25
 *   "черно три бели пет"      → matrix: black=3, white=5
 *
 * Web Speech улавя BG думи добре, Whisper — числа и mix.
 * Парсваме и двата transcript-а и mergeват се по rules.
 */

const MAGIC_WORDS = [
    'next'   => ['следващ', 'напред', 'по-нататък', 'по нататък', 'пропусни'],
    'back'   => ['назад', 'предишен', 'върни'],
    'save'   => ['запази', 'запиши', 'готово'],
    'print'  => ['печатай', 'печат', 'отпечатай'],
    'cancel' => ['отказ', 'затвори', 'спри се'],
    'copy'   => ['като предния', 'като предишния', 'копирай предния'],
    'search' => ['търси', 'намери'],
    'stop'   => ['стоп', 'спри'],
    'undo'   => ['не', 'поправи', 'грешка'],
];

const BG_NUMBER_WORDS = [
    'нула' => 0, 'едно' => 1, 'един' => 1, 'една' => 1,
    'две' => 2, 'два' => 2, 'три' => 3, 'четири' => 4, 'пет' => 5,
    'шест' => 6, 'седем' => 7, 'осем' => 8, 'девет' => 9, 'десет' => 10,
    'единадесет' => 11, 'дванадесет' => 12, 'тринадесет' => 13,
    'четиринадесет' => 14, 'петнадесет' => 15, 'шестнадесет' => 16,
    'седемнадесет' => 17, 'осемнадесет' => 18, 'деветнадесет' => 19,
    'двадесет' => 20, 'тридесет' => 30, 'четиридесет' => 40, 'петдесет' => 50,
    'шестдесет' => 60, 'седемдесет' => 70, 'осемдесет' => 80, 'деветдесет' => 90,
    'сто' => 100, 'двеста' => 200, 'триста' => 300, 'четиристотин' => 400,
    'петстотин' => 500, 'хиляда' => 1000,
];

const CURRENCY_UNITS = ['лева', 'лв', 'лв.', 'евро', 'eur', 'euro', '€'];

/**
 * Detect magic command в transcript. Връща action key или null.
 */
function detectMagicWord(string $transcript): ?string {
    $t = mb_strtolower(trim($transcript));
    if ($t === '') return null;
    foreach (MAGIC_WORDS as $action => $variants) {
        foreach ($variants as $v) {
            if ($t === $v || $t === $v . '.' || $t === $v . ',') return $action;
        }
    }
    return null;
}

/**
 * Извлича числа от transcript — както от digits, така и от BG word forms.
 * Връща array от [['value' => float, 'pos' => int, 'raw' => string], ...]
 * sorted by позиция.
 */
function extractNumbers(string $transcript): array {
    $out = [];

    if (preg_match_all('/\d+(?:[.,]\d+)?/u', $transcript, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $out[] = [
                'value' => (float) str_replace(',', '.', $hit[0]),
                'pos'   => mb_strlen(substr($transcript, 0, (int)$hit[1])),
                'raw'   => $hit[0],
            ];
        }
    }

    $lower = mb_strtolower($transcript);
    foreach (BG_NUMBER_WORDS as $word => $val) {
        $offset = 0;
        while (($p = mb_strpos($lower, $word, $offset)) !== false) {
            $before = ($p === 0) ? ' ' : mb_substr($lower, $p - 1, 1);
            $after  = mb_substr($lower, $p + mb_strlen($word), 1) ?: ' ';
            if (!preg_match('/\p{L}/u', $before) && !preg_match('/\p{L}/u', $after)) {
                $out[] = ['value' => (float)$val, 'pos' => $p, 'raw' => $word];
            }
            $offset = $p + mb_strlen($word);
        }
    }

    usort($out, fn($a, $b) => $a['pos'] <=> $b['pos']);
    return $out;
}

/**
 * Премахва числови tokens (digits + BG number words + currency units) от transcript,
 * връща чистия "текстов гръб". Полезно за извличане на name от "червени тениски 25 лева".
 */
function stripNumericTokens(string $transcript): string {
    $t = preg_replace('/\d+(?:[.,]\d+)?/u', ' ', $transcript) ?? $transcript;
    $lower = mb_strtolower($t);
    foreach (array_merge(array_keys(BG_NUMBER_WORDS), CURRENCY_UNITS) as $tok) {
        $lower = preg_replace('/(?<!\p{L})' . preg_quote($tok, '/') . '(?!\p{L})/iu', ' ', $lower) ?? $lower;
    }
    return trim(preg_replace('/\s+/u', ' ', $lower) ?? '');
}

/**
 * Главният hybrid parser.
 *
 * @param string $web_speech_text — what browser heard (text-leaning)
 * @param string $whisper_text    — what Whisper heard (number-leaning)
 * @param string $context         — wizard step context: 'step1', 'step1_voice_add',
 *                                  'matrix', 'matrix_cell', 'free' (default)
 *
 * @return array{
 *   magic:?string,
 *   text_parts:array<string>,
 *   number_parts:array<float>,
 *   units:array<string>,
 *   structured:array<string,mixed>
 * }
 */
function parseHybridTranscript(
    string $web_speech_text,
    string $whisper_text,
    string $context = 'free'
): array {
    $magic = detectMagicWord($web_speech_text) ?? detectMagicWord($whisper_text);
    if ($magic !== null) {
        return [
            'magic'        => $magic,
            'text_parts'   => [],
            'number_parts' => [],
            'units'        => [],
            'structured'   => [],
        ];
    }

    $primary_for_text = $web_speech_text !== '' ? $web_speech_text : $whisper_text;
    $text_clean = stripNumericTokens($primary_for_text);
    $text_parts = ($text_clean === '') ? [] : (preg_split('/\s+/u', $text_clean) ?: []);

    $primary_for_num = $whisper_text !== '' ? $whisper_text : $web_speech_text;
    $numbers = extractNumbers($primary_for_num);
    $number_parts = array_map(fn($n) => $n['value'], $numbers);

    $units = [];
    $lower = mb_strtolower($primary_for_num);
    foreach (CURRENCY_UNITS as $u) {
        if (preg_match('/(?<!\p{L})' . preg_quote($u, '/') . '(?!\p{L})/iu', $lower)) {
            $units[] = $u;
        }
    }

    $structured = [];
    if ($context === 'step1' || $context === 'step1_voice_add') {
        if (!empty($text_parts)) {
            $structured['name'] = trim(implode(' ', $text_parts));
        }
        if (!empty($number_parts)) {
            $structured['price_retail'] = $number_parts[0];
        }
    } elseif ($context === 'matrix') {
        $pairs = [];
        $color_buf = [];
        foreach ($numbers as $hit) {
            $left = mb_substr($primary_for_num, 0, $hit['pos']);
            $left_clean = stripNumericTokens($left);
            $tokens = preg_split('/\s+/u', trim($left_clean)) ?: [];
            $color = end($tokens) ?: null;
            if ($color) {
                $pairs[] = ['color' => $color, 'qty' => (int)$hit['value']];
            }
        }
        $structured['matrix_pairs'] = $pairs;
    } elseif ($context === 'matrix_cell') {
        if (!empty($number_parts)) {
            $structured['quantity'] = (int)$number_parts[0];
        }
    }

    return [
        'magic'        => null,
        'text_parts'   => $text_parts,
        'number_parts' => $number_parts,
        'units'        => array_values(array_unique($units)),
        'structured'   => $structured,
    ];
}
