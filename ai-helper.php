<?php
/**
 * ai-helper.php — AI proxy без запис в chat_messages
 *
 * ACTION-и:
 *   onboarding       — AI води интервюто (фаза 1-3), връща JSON с message, phase, data
 *   onboarding_wow   — AI генерира 5 WOW сценария с конкретни EUR суми
 *   analyze_biz_segment — уточняващ въпрос за бизнес типа
 *   loyalty_options   — 3 лоялни варианта ИЛИ summary за онбординг
 *   wow              — 5 WOW съобщения (стар формат)
 *   generate         — AI описание на артикул
 *   scan_product     — разпознаване от снимка
 *   web_search       — Gemini grounding search
 *
 * AI модели: Gemini (основен), Claude (резервен) — fallback при 429/5xx/timeout
 */
session_start();
session_write_close();

if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once 'config/config.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'generate';

// ═══════════════════════════════════════
// GEMINI API CALL
// ═══════════════════════════════════════
function callGemini($system, $userPrompt, $maxTokens = 1024) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userPrompt]]]
        ],
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.4, 'maxOutputTokens' => $maxTokens]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) { error_log('Gemini cURL error: ' . $err); return ''; }
    $decoded = json_decode($response, true);
    if (!$decoded) { error_log('Gemini invalid JSON: ' . substr($response, 0, 500)); return ''; }
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ═══════════════════════════════════════
// GEMINI WITH CONVERSATION HISTORY
// ═══════════════════════════════════════
function callGeminiChat($system, $messages, $maxTokens = 512) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $contents = [];
    foreach ($messages as $msg) {
        $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
        $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
    }

    $payload = [
        'contents' => $contents,
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.5, 'maxOutputTokens' => $maxTokens]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) { error_log('Gemini chat cURL error: ' . $err); return ''; }
    $decoded = json_decode($response, true);
    if (!$decoded) { error_log('Gemini chat invalid JSON: ' . substr($response, 0, 500)); return ''; }
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ═══════════════════════════════════════
// GEMINI MULTIMODAL (снимка + текст)
// ═══════════════════════════════════════
function callGeminiVision($system, $userPrompt, $imageBase64, $mimeType = 'image/jpeg') {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [[
            'role'  => 'user',
            'parts' => [
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]],
                ['text' => $userPrompt]
            ]
        ]],
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.2, 'maxOutputTokens' => 512]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ═══════════════════════════════════════
// ACTION: onboarding — AI води интервюто
// ═══════════════════════════════════════
if ($action === 'onboarding') {
    $history = $input['history'] ?? [];

    $system = 'Ти си онбординг асистент на RunMyStore.ai. Водиш КРАТКО интервю с нов клиент.

ТВОЕТО ПЪРВО СЪОБЩЕНИЕ ВЕЧЕ Е ИЗПРАТЕНО: "Здравей! Приятно ми е — аз съм твоят бъдещ AI бизнес партньор. Как се казваш?"
НЕ го повтаряй. Ти продължаваш разговора от тук нататък.

ФАЗИ (следвай ги в ред):
1. ЗАПОЗНАВАНЕ — клиентът ти каза името си. Кажи "Приятно ми е, [име]!" и попитай какво продава.
2. БИЗНЕС — разпознай какво продава (дори с грешки: "дреги"=дрехи, "офки"=обувки). Задай 1 уточняващ въпрос за ценовия клас или спецификата на бизнеса.
3. МАЩАБ — попитай колко физически обекта (магазина) има.

ПРАВИЛА:
- 1 въпрос на съобщение, НИКОГА повече
- Макс 2-3 изречения
- Топъл разговорен български, като приятел
- Разпознавай жаргон и грешки автоматично — НИКОГА не питай "имаш ли предвид X?"

КОГАТО ИМАШ ЦЯЛАТА ИНФОРМАЦИЯ (име + бизнес + сегмент/ценови клас + брой обекти), върни ЗАДЪЛЖИТЕЛНО:
{"message":"Супер, [име]! Имам всичко необходимо. Сега ще ти покажа нещо интересно...","phase":4,"data":{"name":"...","biz":"...","segment":"...","stores":"..."}}

ДОКАТО НЯМАШ ЦЯЛАТА ИНФОРМАЦИЯ, връщай:
{"message":"твоя отговор тук","phase":ТЕКУЩА_ФАЗА}

ОТГОВАРЯЙ САМО С ВАЛИДЕН JSON. Без markdown, без ```json, без обяснения извън JSON-а.';

    $text = callGeminiChat($system, $history, 300);

    // Parse JSON от отговора
    $result = null;
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $result = json_decode($match[0], true);
    }

    if ($result && isset($result['message'])) {
        echo json_encode($result);
    } else {
        // Fallback — третираме целия текст като съобщение
        $clean = trim(preg_replace('/```json\s*|\s*```/', '', $text));
        echo json_encode(['message' => $clean ?: 'Какво продаваш в твоя магазин?', 'phase' => 1]);
    }
    exit;
}

// ═══════════════════════════════════════
// ACTION: onboarding_wow — 5 WOW сценария
// ═══════════════════════════════════════
if ($action === 'onboarding_wow') {
    $biz     = trim($input['biz'] ?? '');
    $segment = trim($input['segment'] ?? '');
    $stores  = trim($input['stores'] ?? '1');
    $n = max(1, intval($stores));

    $system = 'Ти си бизнес консултант. Говориш конкретно — суми, проценти, факти. Без рекламен език. Отговаряш САМО с валиден JSON.';

    $prompt = "Бизнес: $biz | Сегмент: $segment | Брой обекти: $n

Генерирай ТОЧНО 5 сценария за загуби за ТОЗИ КОНКРЕТЕН бизнес. Адаптирай числата реалистично за $n обекта в сектор '$biz ($segment)'.

Сценариите:
1. 💀 Zombie Stock — стока без движение 90+ дни, конкретна EUR сума замразен капитал
2. 📐 Грешни размери/количества — остатъчни размери или артикули, EUR загуба
3. 🔔 Изпуснати продажби — клиенти идват, стоката я няма, EUR пропуснати приходи
4. 🛒 Пропуснат upsell — конкретен пример за ТОЗИ бизнес, EUR загуба
5. 💸 Неконтролирани отстъпки — продавачи дават отстъпки без контрол, EUR загуба

Всеки сценарий: макс 2 изречения, започва с емоджи и **Bold заглавие**.

Върни САМО JSON:
{\"scenarios\":[\"текст1\",\"текст2\",\"текст3\",\"текст4\",\"текст5\"]}";

    $text = callGemini($system, $prompt, 1200);

    $scenarios = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['scenarios']) && is_array($parsed['scenarios']) && count($parsed['scenarios']) >= 5) {
            $scenarios = $parsed['scenarios'];
        }
    }

    echo json_encode(['scenarios' => $scenarios]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: analyze_biz_segment
// ═══════════════════════════════════════
if ($action === 'analyze_biz_segment') {
    $biz = trim($input['biz'] ?? '');
    if (!$biz) { echo json_encode(['question' => 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?']); exit; }

    $system = 'Ти си умен бизнес асистент. Разбираш разговорен български и АВТОМАТИЧНО разпознаваш правописни грешки. Примери: "дреги"="дрехи", "офки"="обувки", "ехи"="дрехи". Никога не питаш "имаш ли предвид X?" — просто разпознаваш логически и продължаваш. Отговаряш САМО с един кратък въпрос без кавички.';
    $prompt = "Клиентът каза, че продава: '$biz'. Разпознай бизнеса логически. Задай МУ ЕДИН кратък приятелски въпрос за ценовия клас или спецификата. Примери: за дрехи→'Ежедневна мода или по-луксозен бутик?', за обувки→'Спортни или официални обувки предимно?'. Върни САМО въпроса.";

    $text = callGemini($system, $prompt, 100);
    if (!$text) $text = 'Продаваш ли предимно масови артикули или залагаш на по-скъпи стоки?';

    echo json_encode(['question' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: wow — 5 УАУ съобщения (стар формат)
// ═══════════════════════════════════════
if ($action === 'wow') {
    $prompt = trim($input['prompt'] ?? '');
    if (!$prompt) { echo json_encode(['messages' => []]); exit; }

    $system = 'Ти си AI асистент на RunMyStore.ai. Говориш конкретно като бизнес консултант — суми, проценти, факти. Без телешоп език. Отговаряш САМО с валиден JSON.';
    $text = callGemini($system, $prompt, 1024);

    $messages = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['messages']) && is_array($parsed['messages'])) {
            $messages = $parsed['messages'];
        }
    }

    echo json_encode(['messages' => $messages]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: loyalty_options
// ═══════════════════════════════════════
if ($action === 'loyalty_options') {
    $biz     = trim($input['biz'] ?? '');
    $segment = trim($input['segment'] ?? '');
    $name    = trim($input['name'] ?? '');

    $system = 'Ти си експерт по лоялни програми за малкия ритейл. Познаваш 50+ вида бизнеси. Говориш топъл разговорен български. Отговаряш САМО с валиден JSON без Markdown.';
    $prompt = "Бизнес: $biz | Сегмент: $segment | Собственик: $name

Генерирай ЕДНА персонализирана лоялна програма за ТОЗИ бизнес. Тя е БЕЗПЛАТНА ЗАВИНАГИ за клиента.

Включи:
- Как се печелят точки/отстъпки
- Какви нива има (ако има)
- Рожден ден бонус
- Конкретни числа (EUR, проценти)

Върни САМО JSON:
{\"summary\":\"Описание на програмата в 3-4 изречения с конкретни числа.\"}";

    $text = callGemini($system, $prompt, 600);

    $summary = '';
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['summary'])) {
            $summary = $parsed['summary'];
        }
        // Backward compat: ако върне options формат
        if (isset($parsed['options'])) {
            echo json_encode(['options' => $parsed['options']]);
            exit;
        }
    }

    if (!$summary) {
        $summary = 'Точки за всяка покупка: 1 EUR = 1 точка. На 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки. VIP ниво при 500 EUR оборот с постоянна -10% отстъпка.';
    }

    echo json_encode(['summary' => $summary]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: generate — AI описание на артикул
// ═══════════════════════════════════════
if ($action === 'generate') {
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['reply' => '']); exit; }

    $system = 'Ти генерираш кратки продаващи описания на продукти за малки магазини в България. Отговаряй само на български. Максимум 2-3 изречения.';
    $text = callGemini($system, $message, 256);

    echo json_encode(['reply' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: scan_product — разпознаване от снимка
// ═══════════════════════════════════════
if ($action === 'scan_product') {
    $imageBase64 = $input['image'] ?? '';
    $mimeType    = $input['mime']  ?? 'image/jpeg';
    if (!$imageBase64) { echo json_encode(['error' => 'No image']); exit; }

    $system = 'Ти си складов асистент. Анализираш снимки на продукти и извличаш информация. Отговаряш САМО с валиден JSON без Markdown.';
    $prompt = 'Анализирай тази снимка на продукт. Извлечи: name (наименование на български), retail_price (прогнозна цена в EUR, ако се вижда), barcode (ако се вижда), color (цвят), size (размер), description (кратко описание 1-2 изр. на български). Върни САМО JSON: {"name":"...","retail_price":null,"barcode":null,"color":"...","size":null,"description":"..."}';

    $text = callGeminiVision($system, $prompt, $imageBase64, $mimeType);

    $product = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $product = json_decode($match[0], true) ?? [];
    }

    echo json_encode(['product' => $product]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: web_search — Gemini grounding
// ═══════════════════════════════════════
if ($action === 'web_search') {
    $query = trim($input['query'] ?? '');
    if (!$query) { echo json_encode(['result' => '']); exit; }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => "Намери данни за: $query\nВърни само кратки факти за загуби от залежала стока в този ритейл сектор. Макс 80 думи. Само числа и факти."]]]],
        'tools' => [['google_search' => new stdClass()]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 256]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

    echo json_encode(['result' => trim($text)]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
