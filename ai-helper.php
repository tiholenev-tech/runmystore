<?php
/**
 * ai-helper.php — AI proxy без запис в chat_messages
 *
 * Gemini = основен, Claude = резервен (fallback при 429/5xx/timeout)
 *
 * Actions:
 *   - briefing        — Proactive Briefing при зареждане на chat.php
 *   - pulse           — Пулс бутон: "ОК" или "2 проблема"
 *   - onboarding      — AI води онбординг интервю
 *   - onboarding_wow  — 5 WOW сценария
 *   - analyze_biz_segment
 *   - wow
 *   - loyalty_options
 *   - generate
 *   - scan_product
 *   - web_search
 */
session_start();
session_write_close();

if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'generate';

$tenant_id = $_SESSION['tenant_id'];
$store_id  = $_SESSION['store_id'] ?? null;
$role      = $_SESSION['role'] ?? 'owner';
$user_name = $_SESSION['user_name'] ?? '';
$currency  = $_SESSION['currency'] ?? 'EUR';
$supato_mode = (int)($_SESSION['supato_mode'] ?? 0);

// ═══════════════════════════════════════
// CLAUDE API CALL (резервен)
// ═══════════════════════════════════════
function callClaude($system, $userPrompt, $maxTokens = 1024) {
    if (!defined('CLAUDE_API_KEY') || !CLAUDE_API_KEY) return '';

    $payload = [
        'model'      => 'claude-sonnet-4-5-20250514',
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $userPrompt]]
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT        => 15
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $result = json_decode($response, true);
        return $result['content'][0]['text'] ?? '';
    }
    error_log('Claude error: HTTP ' . $code . ' ' . substr($response, 0, 300));
    return '';
}

function callClaudeChat($system, $messages, $maxTokens = 512) {
    if (!defined('CLAUDE_API_KEY') || !CLAUDE_API_KEY) return '';

    $claudeMessages = [];
    foreach ($messages as $msg) {
        $claudeMessages[] = [
            'role'    => ($msg['role'] === 'assistant') ? 'assistant' : 'user',
            'content' => $msg['content']
        ];
    }

    $payload = [
        'model'      => 'claude-sonnet-4-5-20250514',
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => $claudeMessages
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT        => 15
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $result = json_decode($response, true);
        return $result['content'][0]['text'] ?? '';
    }
    error_log('Claude chat error: HTTP ' . $code);
    return '';
}

// ═══════════════════════════════════════
// GEMINI API CALL (с Claude fallback)
// ═══════════════════════════════════════
function callGemini($system, $userPrompt, $maxTokens = 1024) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig'   => ['temperature' => 0.4, 'maxOutputTokens' => $maxTokens]
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$err && $code >= 200 && $code < 300) {
        $decoded = json_decode($response, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text) return $text;
    }

    error_log("Gemini failed (HTTP $code), falling back to Claude");
    return callClaude($system, $userPrompt, $maxTokens);
}

// ═══════════════════════════════════════
// GEMINI CHAT (с Claude fallback)
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$err && $code >= 200 && $code < 300) {
        $decoded = json_decode($response, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text) return $text;
    }

    error_log("Gemini chat failed (HTTP $code), falling back to Claude");
    return callClaudeChat($system, $messages, $maxTokens);
}

// ═══════════════════════════════════════
// GEMINI MULTIMODAL
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
// HELPER: Събиране на бизнес контекст от БД
// ═══════════════════════════════════════
function gatherBusinessContext($tenant_id, $store_id, $role, $currency) {
    $data = [];

    // Продажби днес
    $today = DB::run(
        'SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as total_sum
         FROM sales WHERE tenant_id = ? AND store_id = ? AND DATE(created_at) = CURDATE() AND status = "completed"',
        [$tenant_id, $store_id]
    )->fetch();
    $data['sales_today'] = "Продажби днес: {$today['cnt']} бр. за {$today['total_sum']} {$currency}";

    // Печалба днес (owner)
    if ($role === 'owner') {
        $profit = DB::run(
            'SELECT COALESCE(SUM(si.total - (si.cost_price * si.quantity)), 0) as profit
             FROM sale_items si JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ? AND s.store_id = ? AND DATE(s.created_at) = CURDATE() AND s.status = "completed"',
            [$tenant_id, $store_id]
        )->fetch();
        $data['profit_today'] = "Печалба днес: {$profit['profit']} {$currency}";
    }

    // Ниски наличности
    $low = DB::run(
        'SELECT p.name, p.size, p.color, i.quantity, i.min_quantity
         FROM inventory i JOIN products p ON p.id = i.product_id
         WHERE i.tenant_id = ? AND i.store_id = ? AND i.quantity > 0 AND i.min_quantity > 0 AND i.quantity <= i.min_quantity AND p.is_active = 1
         ORDER BY i.quantity ASC LIMIT 10',
        [$tenant_id, $store_id]
    )->fetchAll();
    if ($low) {
        $items = [];
        foreach ($low as $r) {
            $l = $r['name'] . ($r['size'] ? ' '.$r['size'] : '') . ($r['color'] ? ' '.$r['color'] : '');
            $items[] = "{$l}: {$r['quantity']} бр. (мин. {$r['min_quantity']})";
        }
        $data['low_stock'] = $items;
        $data['low_stock_text'] = "Ниски наличности (" . count($low) . "): " . implode('; ', $items);
    }

    // Нулеви наличности с продажби последните 30 дни
    $zero = DB::run(
        'SELECT p.name, p.size, p.color
         FROM inventory i JOIN products p ON p.id = i.product_id
         WHERE i.tenant_id = ? AND i.store_id = ? AND i.quantity = 0 AND p.is_active = 1
           AND p.id IN (
             SELECT si.product_id FROM sale_items si JOIN sales s ON s.id = si.sale_id
             WHERE s.tenant_id = ? AND s.store_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND s.status = "completed"
           ) LIMIT 10',
        [$tenant_id, $store_id, $tenant_id, $store_id]
    )->fetchAll();
    if ($zero) {
        $items = [];
        foreach ($zero as $r) {
            $items[] = $r['name'] . ($r['size'] ? ' '.$r['size'] : '') . ($r['color'] ? ' '.$r['color'] : '');
        }
        $data['zero_stock'] = $items;
        $data['zero_stock_text'] = "НУЛА наличност (имали продажби): " . implode(', ', $items);
    }

    // Дългове доставчици (owner)
    if ($role === 'owner') {
        $debts = DB::run(
            'SELECT s.name, SUM(d.total) as debt, MIN(d.payment_due_date) as due
             FROM deliveries d JOIN suppliers s ON s.id = d.supplier_id
             WHERE d.tenant_id = ? AND d.payment_status != "paid"
             GROUP BY d.supplier_id, s.name ORDER BY due ASC LIMIT 5',
            [$tenant_id]
        )->fetchAll();
        if ($debts) {
            $items = [];
            foreach ($debts as $r) {
                $due = $r['due'] ? " (падеж: {$r['due']})" : '';
                $items[] = "{$r['name']}: {$r['debt']} {$currency}{$due}";
            }
            $data['debts'] = $items;
            $data['debts_text'] = "Дългове доставчици: " . implode('; ', $items);
        }
    }

    // Активни трансфери (owner + manager)
    if (in_array($role, ['owner', 'manager'])) {
        $transfers = DB::run(
            'SELECT t.status, fs.name as f, ts.name as t_name
             FROM transfers t JOIN stores fs ON fs.id = t.from_store_id JOIN stores ts ON ts.id = t.to_store_id
             WHERE t.tenant_id = ? AND t.status IN ("pending","in_transit") ORDER BY t.created_at DESC LIMIT 5',
            [$tenant_id]
        )->fetchAll();
        if ($transfers) {
            $items = [];
            foreach ($transfers as $r) {
                $st = $r['status'] === 'pending' ? 'чакащ' : 'в транзит';
                $items[] = "{$r['f']}→{$r['t_name']} ({$st})";
            }
            $data['transfers_text'] = "Активни трансфери: " . implode('; ', $items);
        }
    }

    // Чакащи поръчки (owner)
    if ($role === 'owner') {
        $orders = DB::run(
            'SELECT po.status, s.name FROM purchase_orders po
             LEFT JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.tenant_id = ? AND po.status IN ("draft","sent","partial") LIMIT 5',
            [$tenant_id]
        )->fetchAll();
        if ($orders) {
            $items = [];
            foreach ($orders as $r) {
                $st_map = ['draft'=>'чернова','sent'=>'изпратена','partial'=>'частична'];
                $items[] = "{$r['name']}: " . ($st_map[$r['status']] ?? $r['status']);
            }
            $data['orders_text'] = "Чакащи поръчки: " . implode('; ', $items);
        }
    }

    return $data;
}

// ═══════════════════════════════════════
// ACTION: briefing — Proactive Briefing
// ═══════════════════════════════════════
if ($action === 'briefing') {
    $ctx = gatherBusinessContext($tenant_id, $store_id, $role, $currency);

    $store = DB::run('SELECT name FROM stores WHERE id = ?', [$store_id])->fetch();
    $store_name = $store['name'] ?? 'Обект';

    // Ако няма данни — празен briefing
    $has_issues = !empty($ctx['low_stock']) || !empty($ctx['zero_stock']) || !empty($ctx['debts']) || !empty($ctx['transfers_text']) || !empty($ctx['orders_text']);

    if (!$has_issues && (int)($ctx['sales_today'] ?? 0) === 0) {
        // Няма нищо за показване
        echo json_encode(['items' => [], 'greeting' => "Добро утро! Всичко е наред в {$store_name}."]);
        exit;
    }

    // Събираме текстов контекст
    $context_lines = array_filter([
        $ctx['sales_today'] ?? '',
        $ctx['profit_today'] ?? '',
        $ctx['low_stock_text'] ?? '',
        $ctx['zero_stock_text'] ?? '',
        $ctx['debts_text'] ?? '',
        $ctx['transfers_text'] ?? '',
        $ctx['orders_text'] ?? ''
    ]);
    $context_block = implode("\n", $context_lines);

    $role_filter = match($role) {
        'seller'  => 'Покажи САМО проблеми със стока (ниска/нулева наличност). БЕЗ финанси.',
        'manager' => 'Покажи стока + трансфери + поръчки. БЕЗ печалби, марж, дългове.',
        default   => 'Покажи всичко.'
    };

    $system = "Генерирай кратък сутрешен briefing за магазин \"{$store_name}\". Потребител: {$user_name} ({$role}).
{$role_filter}

ПРАВИЛА:
- Max 5 items
- Приоритет: 🔴 критични (нулева наличност, падеж днес) > 🟠 важни (ниска наличност, трансфери) > 🟡 препоръки
- Ако > 5 проблема от един тип → групирай: '8 артикула свършват' с deeplink
- Всеки item: emoji + кратък текст (1 изречение) + deeplink ако е уместен
- Deeplinks: [📦 Виж артикулите →], [⚠️ Поръчай сега →], [📊 Статистика →], [💳 Дългове →]
- Тон: умен приятел търговец, конкретно, кратко
- САМО валиден JSON

ДАННИ:
{$context_block}";

    $prompt = "Генерирай briefing. САМО JSON:
{\"greeting\":\"Добро утро, {$user_name}!\",\"items\":[{\"priority\":\"red\",\"text\":\"...\",\"deeplink\":\"products.php?filter=zero\"},{\"priority\":\"orange\",\"text\":\"...\"}]}

priority: red/orange/yellow/green. Deeplink е незадължителен.";

    $text = callGemini($system, $prompt, 600);

    // Парсваме JSON
    $result = null;
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $result = json_decode($match[0], true);
    }

    if ($result && isset($result['items'])) {
        // Ограничаваме до 5
        $result['items'] = array_slice($result['items'], 0, 5);
        echo json_encode($result);
    } else {
        // Fallback — ръчно генериран briefing без AI
        $items = [];

        if (!empty($ctx['zero_stock'])) {
            $count = count($ctx['zero_stock']);
            if ($count <= 2) {
                foreach ($ctx['zero_stock'] as $name) {
                    $items[] = ['priority' => 'red', 'text' => "🔴 {$name} — НУЛА! Губиш продажби.", 'deeplink' => 'products.php?filter=zero'];
                }
            } else {
                $items[] = ['priority' => 'red', 'text' => "🔴 {$count} артикула са на нула!", 'deeplink' => 'products.php?filter=zero'];
            }
        }

        if (!empty($ctx['low_stock'])) {
            $count = count($ctx['low_stock']);
            if ($count <= 2) {
                foreach ($ctx['low_stock'] as $info) {
                    $items[] = ['priority' => 'orange', 'text' => "🟠 {$info}", 'deeplink' => 'products.php?filter=low'];
                }
            } else {
                $items[] = ['priority' => 'orange', 'text' => "🟠 {$count} артикула с ниска наличност", 'deeplink' => 'products.php?filter=low'];
            }
        }

        if (!empty($ctx['debts']) && $role === 'owner') {
            $items[] = ['priority' => 'orange', 'text' => "💳 " . ($ctx['debts_text'] ?? 'Имаш неплатени доставки'), 'deeplink' => 'stats.php?tab=finance'];
        }

        $items = array_slice($items, 0, 5);

        echo json_encode([
            'greeting' => "Добро утро" . ($user_name ? ", {$user_name}" : "") . "!",
            'items' => $items
        ]);
    }
    exit;
}

// ═══════════════════════════════════════
// ACTION: pulse — Пулс бутон
// ═══════════════════════════════════════
if ($action === 'pulse') {
    $ctx = gatherBusinessContext($tenant_id, $store_id, $role, $currency);

    $problems = [];

    if (!empty($ctx['zero_stock'])) {
        $c = count($ctx['zero_stock']);
        $problems[] = "{$c} артикул" . ($c > 1 ? 'а' : '') . " на нула";
    }
    if (!empty($ctx['low_stock'])) {
        $c = count($ctx['low_stock']);
        $problems[] = "{$c} с ниска наличност";
    }
    if (!empty($ctx['debts']) && $role === 'owner') {
        $problems[] = "неплатени доставки";
    }

    if (empty($problems)) {
        echo json_encode(['status' => 'ok', 'message' => '✅ Всичко е наред!', 'problems' => 0]);
    } else {
        $count = count($problems);
        $msg = "⚠️ {$count} проблем" . ($count > 1 ? 'а' : '') . ": " . implode(', ', $problems) . ".";
        echo json_encode(['status' => 'issues', 'message' => $msg, 'problems' => $count]);
    }
    exit;
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

    $result = null;
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $result = json_decode($match[0], true);
    }

    if ($result && isset($result['message'])) {
        echo json_encode($result);
    } else {
        // Strip JSON artifacts and markdown fences
        $clean = trim(preg_replace('/```json\s*|\s*```/', '', $text));
        // If still looks like JSON but failed parse — extract readable text
        if (preg_match('/^[\s]*\{/', $clean)) {
            // Try to pull "message" value via regex
            if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $clean, $msgMatch)) {
                $clean = stripcslashes($msgMatch[1]);
            } else {
                // Strip all JSON syntax, keep readable text
                $clean = preg_replace('/[{}\[\]"]+/', '', $clean);
                $clean = preg_replace('/\b(message|phase|data|name|biz|segment|stores)\s*:/i', '', $clean);
                $clean = trim(preg_replace('/\s{2,}/', ' ', $clean));
            }
        }
        if (!$clean) $clean = 'Какво продаваш в твоя магазин?';
        echo json_encode(['message' => $clean, 'phase' => 1]);
    }
    exit;
}

// ═══════════════════════════════════════
// ACTION: onboarding_wow — 5 WOW сценария
// ═══════════════════════════════════════
if ($action === 'onboarding_wow') {
    require_once __DIR__ . '/biz-coefficients.php';

    $biz     = trim($input['biz'] ?? '');
    $segment = trim($input['segment'] ?? '');
    $stores  = trim($input['stores'] ?? '1');
    $n = max(1, intval($stores));

    $bizInfo = findBizCoefficient($biz . ' ' . $segment);
    $losses  = calculateLosses($bizInfo['coeff'], $n);

    $system = 'Ти си бизнес консултант. Получаваш ТОЧНИ изчислени суми. Твоята задача е да ги представиш с контекст за конкретния бизнес. НЕ променяй числата — те са изчислени по формула. САМО валиден JSON.';

    $prompt = "Бизнес: $biz | Сегмент: $segment | Брой обекти: $n | Разпознат като: {$bizInfo['match']} (€{$bizInfo['coeff']}/обект/мес)

ИЗЧИСЛЕНИ ЗАГУБИ (НЕ ГИ ПРОМЕНЯЙ):
1. 💀 Zombie Stock: €{$losses['zombie']}/мес замразен капитал
2. 📐 Грешни размери/количества: €{$losses['sizes']}/мес
3. 🔔 Изпуснати продажби (out-of-stock): €{$losses['outofstock']}/мес
4. 🛒 Пропуснат upsell: €{$losses['upsell']}/мес
5. 💸 Неконтролирани отстъпки: €{$losses['discounts']}/мес

Напиши 5 сценария — макс 2 изречения на всеки. Започни с емоджи и **Bold заглавие**.
Адаптирай КОНТЕКСТА за '$biz' — дай конкретен пример какъв вид стока стои, какъв upsell е пропуснат и т.н.
НО ЗАПАЗИ ТОЧНИТЕ СУМИ от горе!

САМО JSON: {\"scenarios\":[\"текст1\",\"текст2\",\"текст3\",\"текст4\",\"текст5\"]}";

    $text = callGemini($system, $prompt, 1200);

    $scenarios = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['scenarios']) && is_array($parsed['scenarios']) && count($parsed['scenarios']) >= 5) {
            $scenarios = $parsed['scenarios'];
        }
    }

    if (empty($scenarios)) {
        $scenarios = [
            "💀 **Zombie Stock:** Имаш стока която стои 90+ дни без движение. Това са ~€{$losses['zombie']} замразени пари всеки месец.",
            "📐 **Грешни размери/количества:** Всеки сезон остават артикули които никой не купува. Загуба ~€{$losses['sizes']}/мес от блокиран капитал.",
            "🔔 **Изпуснати продажби:** Клиент идва, стоката я няма. Губиш ~€{$losses['outofstock']}/мес от празни рафтове.",
            "🛒 **Пропуснат upsell:** Клиент купува едно, никой не му предлага допълнение. ~€{$losses['upsell']}/мес пропуснати.",
            "💸 **Неконтролирани отстъпки:** Продавач дава 20% без причина. ~€{$losses['discounts']}/мес изтичат."
        ];
    }

    echo json_encode(['scenarios' => $scenarios, 'losses' => $losses, 'biz_match' => $bizInfo['match']]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: analyze_biz_segment
// ═══════════════════════════════════════
if ($action === 'analyze_biz_segment') {
    $biz = trim($input['biz'] ?? '');
    if (!$biz) { echo json_encode(['question' => 'Масови артикули или по-скъпи стоки?']); exit; }

    $system = 'Ти си бизнес асистент. Разпознаваш грешки автоматично. НИКОГА не питаш "имаш ли предвид X?". САМО един кратък въпрос.';
    $prompt = "Клиентът продава: '$biz'. Задай ЕДИН въпрос за ценовия клас или спецификата. САМО въпроса.";

    $text = callGemini($system, $prompt, 100);
    if (!$text) $text = 'Масови артикули или по-скъпи стоки?';

    echo json_encode(['question' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: wow (стар формат)
// ═══════════════════════════════════════
if ($action === 'wow') {
    $prompt = trim($input['prompt'] ?? '');
    if (!$prompt) { echo json_encode(['messages' => []]); exit; }

    $system = 'Бизнес консултант. Конкретно — суми, факти. САМО валиден JSON.';
    $text = callGemini($system, $prompt, 1024);

    $messages = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['messages'])) $messages = $parsed['messages'];
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

    $system = 'Експерт по лоялни програми. Топъл български. САМО валиден JSON.';
    $prompt = "Бизнес: $biz | Сегмент: $segment | Собственик: $name
Генерирай ЕДНА лоялна програма за ТОЗИ бизнес. БЕЗПЛАТНА ЗАВИНАГИ.
Включи: точки, нива, рожден ден бонус, конкретни EUR/проценти.
САМО JSON: {\"summary\":\"3-4 изречения с числа.\"}";

    $text = callGemini($system, $prompt, 600);

    $summary = '';
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $parsed = json_decode($match[0], true);
        if (isset($parsed['summary'])) $summary = $parsed['summary'];
        if (isset($parsed['options'])) { echo json_encode(['options' => $parsed['options']]); exit; }
    }

    if (!$summary) $summary = '1 EUR = 1 точка. 100 точки → 5 EUR отстъпка. Рожден ден = двойни точки. VIP при 500 EUR оборот → постоянна -10%.';

    echo json_encode(['summary' => $summary]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: generate
// ═══════════════════════════════════════
if ($action === 'generate') {
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['reply' => '']); exit; }

    $system = 'Кратки продаващи описания на продукти. Макс 2-3 изречения на български.';
    $text = callGemini($system, $message, 256);
    echo json_encode(['reply' => trim($text)]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: scan_product
// ═══════════════════════════════════════
if ($action === 'scan_product') {
    $imageBase64 = $input['image'] ?? '';
    $mimeType    = $input['mime']  ?? 'image/jpeg';
    if (!$imageBase64) { echo json_encode(['error' => 'No image']); exit; }

    $system = 'Складов асистент. САМО валиден JSON.';
    $prompt = 'Анализирай снимката. Извлечи: name, retail_price, barcode, color, size, description. САМО JSON.';
    $text = callGeminiVision($system, $prompt, $imageBase64, $mimeType);

    $product = [];
    if (preg_match('/\{[\s\S]*\}/u', $text, $match)) {
        $product = json_decode($match[0], true) ?? [];
    }
    echo json_encode(['product' => $product]);
    exit;
}

// ═══════════════════════════════════════
// ACTION: web_search
// ═══════════════════════════════════════
if ($action === 'web_search') {
    $query = trim($input['query'] ?? '');
    if (!$query) { echo json_encode(['result' => '']); exit; }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => "Намери данни за: $query\nКратки факти. Макс 80 думи."]]]],
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
