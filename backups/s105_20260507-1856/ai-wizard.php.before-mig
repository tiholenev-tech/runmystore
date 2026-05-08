<?php
/**
 * ai-wizard.php — AI Wizard endpoint за добавяне на артикул чрез разговор
 * Сесия 22 — ЗАКОН №1: Пешо не пише нищо
 *
 * POST JSON:
 *   business_type: string (от tenants.business_type)
 *   image_base64: string|null (снимка → Gemini Vision)
 *   conversation: [{role:'user'|'ai', text:'...'}]
 *   collected: {name:'', retail_price:0, supplier:'', category:'', ...}
 *
 * RESPONSE JSON:
 *   message: string (AI казва)
 *   field: string|null (кое поле пита)
 *   done: bool
 *   collected: {} (обновени данни)
 *   variants_preview: [] (ако done=true, кръстоска)
 *   image_type: string|null (какво разпозна от снимка)
 */
session_start();
if (!isset($_SESSION['tenant_id'])) { http_response_code(401); exit; }

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/biz-coefficients.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['error' => 'no_input']); exit; }

$tenant_id = $_SESSION['tenant_id'];
$store_id  = $_SESSION['store_id'] ?? null;

// Данни от клиента
$business_type = $input['business_type'] ?? '';
$image_base64  = $input['image_base64'] ?? null;
$conversation  = $input['conversation'] ?? [];
$collected     = $input['collected'] ?? [];
$image_type    = $input['image_type'] ?? null;

// ── Контекст от БД ──
$cats = DB::run("SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$sups = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name", [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
$catNames = array_column($cats, 'name');
$supNames = array_column($sups, 'name');

// ── Variant контекст от biz-coefficients.php ──
$bizVariants = findBizVariants($business_type ?: 'магазин');
$variantContext = '';
if ($bizVariants['has_variants']) {
    $variantContext = "Този бизнес ({$bizVariants['match']}) обикновено има вариации по: " . implode(', ', $bizVariants['variant_fields']) . ".\n";
    foreach ($bizVariants['variant_presets'] as $field => $presets) {
        if (!empty($presets)) {
            $variantContext .= "Стандартни {$field}: " . implode(', ', $presets) . "\n";
        }
    }
    $variantContext .= "Мерни единици: " . implode(', ', $bizVariants['units']) . "\n";
}
if (!empty($bizVariants['typical_fields'])) {
    $variantContext .= "Типични полета за този бизнес: " . implode(', ', $bizVariants['typical_fields']) . "\n";
}

// ── Ако има снимка — Gemini Vision ──
if ($image_base64 && !$image_type) {
    $visionUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    $visionPayload = [
        'contents' => [['parts' => [
            ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $image_base64]],
            ['text' => "Какъв артикул виждаш на снимката? Отговори с 1-3 думи на български. Примери: 'бикини', 'чанта', 'маратонки', 'тениска'. Ако не разпознаваш — кажи 'неразпознат'. САМО типа, нищо друго."]
        ]]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 30]
    ];
    $ch = curl_init($visionUrl);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($visionPayload), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $vResp = curl_exec($ch); curl_close($ch);
    $vData = json_decode($vResp, true);
    $image_type = trim($vData['candidates'][0]['content']['parts'][0]['text'] ?? 'неразпознат');
    $image_type = preg_replace('/["\.\n]/', '', $image_type);

    if (mb_strtolower($image_type) === 'неразпознат') {
        echo json_encode([
            'message' => 'Не разпознах какво е. Кажи ми какво добавяме?',
            'field' => 'name', 'done' => false,
            'collected' => $collected, 'image_type' => null
        ]);
        exit;
    }

    // Разпознахме типа
    echo json_encode([
        'message' => "Виждам {$image_type}. Как да го кръстим?",
        'field' => 'name', 'done' => false,
        'collected' => $collected, 'image_type' => $image_type
    ]);
    exit;
}

// ── Определяме какво липсва ──
$required_fields = ['name', 'retail_price', 'supplier', 'category'];
if ($bizVariants['has_variants'] && !empty($bizVariants['variant_fields'])) {
    foreach ($bizVariants['variant_fields'] as $vf) {
        $key = 'variant_' . mb_strtolower(str_replace(' ', '_', $vf));
        $required_fields[] = $key;
    }
}

// ── Gemini system prompt ──
$collectedStr = !empty($collected) ? json_encode($collected, JSON_UNESCAPED_UNICODE) : '{}';
$systemPrompt = "Ти си AI асистент в складова програма. Помагаш на потребителя да добави нов артикул чрез РАЗГОВОР.

КОНТЕКСТ:
- Бизнес тип: {$business_type}
{$variantContext}
- Категории в системата: " . implode(', ', $catNames) . "
- Доставчици в системата: " . implode(', ', $supNames) . "
" . ($image_type ? "- От снимката разпознах: {$image_type}" : "") . "

СЪБРАНИ ДАННИ ДОСЕГА:
{$collectedStr}

ЗАДЪЛЖИТЕЛНИ ПОЛЕТА:
- name (наименование)
- retail_price (продажна цена в EUR)
- supplier (доставчик — трябва да съвпада с някой от списъка или нов)
- category (категория — трябва да съвпада с някой от списъка или нова)
" . ($bizVariants['has_variants'] ? "- sizes (размери)\n- colors (цветове)\n" : "") . "

ПРАВИЛА:
1. НИКОГА не измисляй данни — само питай
2. Пита ЕДНО нещо наведнъж
3. Ако потребителят каже много наведнъж — парсни всичко, питай САМО за липсващото
4. Кратко, 1-2 изречения максимум
5. Разбирай жаргон: дреги=дрехи, офки=обувки, якита=якета
6. Ако потребителят каже доставчик/категория близо до съществуващ — мачни автоматично, НЕ питай
7. Цена: ако каже '25' или '25 лева' → retail_price = 25

ВЪРНИ САМО JSON (без markdown, без \`\`\`):
{
  \"message\": \"текст към потребителя\",
  \"field\": \"кое поле питаш (name/retail_price/supplier/category/sizes/colors/barcode) или null ако имаш всичко\",
  \"done\": false,
  \"collected\": { ...обновените данни... }
}

Когато имаш ВСИЧКИ задължителни полета:
{
  \"message\": \"Ето обобщение:\\nИме: X\\nЦена: Y €\\nДоставчик: Z\\nКатегория: W\\n[Размери/Цветове ако има]\\n\\nВсичко ок ли е?\",
  \"field\": null,
  \"done\": false,
  \"collected\": { ...всичко... },
  \"awaiting_confirmation\": true
}

Когато потребителят потвърди (да/добре/ок/потвърждавам/запиши):
{
  \"message\": \"Записвам!\",
  \"field\": null,
  \"done\": true,
  \"collected\": { ...финални данни... }
}

Когато потребителят иска корекция (махни XL / добави розов / промени цената на 30):
- Направи корекцията в collected
- Покажи обновено обобщение
- done = false";

// ── Gemini conversation ──
$geminiContents = [];
foreach ($conversation as $msg) {
    $role = ($msg['role'] === 'ai') ? 'model' : 'user';
    $geminiContents[] = ['role' => $role, 'parts' => [['text' => $msg['text']]]];
}

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
$payload = [
    'contents' => $geminiContents,
    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
    'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 500]
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$resp = curl_exec($ch); curl_close($ch);
$data = json_decode($resp, true);
$text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
$text = preg_replace('/```json\s*|\s*```/', '', $text);

$parsed = json_decode($text, true);
if ($parsed && isset($parsed['message'])) {
    // Ако done=true — генерирай вариации
    if (!empty($parsed['done']) && !empty($parsed['collected'])) {
        $c = $parsed['collected'];
        $variants = [];
        $sizes = $c['sizes'] ?? [];
        $colors = $c['colors'] ?? [];
        if (is_string($sizes)) $sizes = preg_split('/[\s,]+/', $sizes);
        if (is_string($colors)) $colors = preg_split('/[\s,]+/', $colors);
        $sizes = array_filter($sizes);
        $colors = array_filter($colors);

        if (!empty($sizes) && !empty($colors)) {
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $variants[] = ['size' => $size, 'color' => $color, 'qty' => 0];
                }
            }
        } elseif (!empty($sizes)) {
            foreach ($sizes as $size) { $variants[] = ['size' => $size, 'color' => null, 'qty' => 0]; }
        } elseif (!empty($colors)) {
            foreach ($colors as $color) { $variants[] = ['size' => null, 'color' => $color, 'qty' => 0]; }
        } else {
            $variants[] = ['size' => null, 'color' => null, 'qty' => 0];
        }

        // Match supplier/category IDs
        $supId = null; $catId = null;
        $supName = $c['supplier'] ?? '';
        $catName = $c['category'] ?? '';
        foreach ($sups as $s) {
            if (mb_strtolower($s['name']) === mb_strtolower($supName)) { $supId = $s['id']; break; }
        }
        foreach ($cats as $ct) {
            if (mb_strtolower($ct['name']) === mb_strtolower($catName)) { $catId = $ct['id']; break; }
        }

        $parsed['supplier_id'] = $supId;
        $parsed['category_id'] = $catId;
        $parsed['variants_preview'] = $variants;
    }

    echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
} else {
    // Fallback
    echo json_encode([
        'message' => $text ?: 'Какво добавяме? Кажи или снимай.',
        'field' => 'name', 'done' => false,
        'collected' => $collected, 'image_type' => $image_type
    ], JSON_UNESCAPED_UNICODE);
}
