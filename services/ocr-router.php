<?php
/**
 * ocr-router.php — Invoice OCR pipeline (Gemini Vision).
 *
 * Spec: BIBLE_v3_0_TECH §13, DELIVERY_ORDERS_DECISIONS_FINAL §I (I1-I7)
 *
 * Pipeline:
 *   Level 0 — File Quality Gate (size/mime/dimensions)
 *   Level 1 — AI Vision extract (Gemini 2.5 Flash, strict JSON)
 *   Level 2 — Math validation (base + vat = total ±0.02)
 *   Level 3 — Type detection (clean/semi/manual) per I6
 *   Confidence routing:
 *     > 0.92  → AUTO_ACCEPT (но override 1: rows с has_variations='true'
 *                без описание → variation_pending=1, не auto)
 *     0.75-0.92 → REVIEW_NEEDED (smart UI, само uncertain полета)
 *     0.5-0.75 → REVIEW_NEEDED (full review)
 *     < 0.5   → REJECT с suggest_voice_fallback=true (override 3)
 *
 * Output schema (decision I7):
 *   {
 *     status: 'AUTO_ACCEPTED'|'REVIEW_NEEDED'|'REJECTED',
 *     confidence: 0..1,
 *     invoice_type: 'clean'|'semi'|'manual',
 *     header: {supplier_name, supplier_eik, invoice_number, date,
 *              base_amount, vat_rate, vat_amount, total_amount, currency},
 *     items: [{ line_number, name, qty, unit_cost, total_cost, vat_rate,
 *               supplier_product_code, has_variations_hint, original_ocr_text,
 *               confidence }],
 *     uncertain_fields: ['header.vat_amount', 'item.3.unit_cost', ...],
 *     errors: [...],
 *     suggest_voice_fallback: bool,
 *     raw_ocr_json: {...}  // pass-through от Gemini
 *   }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

const OCR_AUTO_THRESHOLD    = 0.92;
const OCR_SMART_THRESHOLD   = 0.75;
const OCR_REJECT_THRESHOLD  = 0.50;
const OCR_MATH_TOLERANCE    = 0.02;
const OCR_MAX_FILE_BYTES    = 10 * 1024 * 1024;

class OCRRouter {
    private string $api_key;
    private string $model;

    public function __construct(?string $api_key = null, ?string $model = null) {
        $this->api_key = $api_key ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        $this->model   = $model   ?: (defined('GEMINI_MODEL')   ? GEMINI_MODEL   : 'gemini-2.5-flash');
    }

    /**
     * @param array $files — масив от пътища ИЛИ масив от ['path'=>..,'mime'=>..]
     * @param int   $tenant_id
     * @param array $opts — ['supplier_id'=>?, 'expected_currency'=>?]
     */
    public function process(array $files, int $tenant_id, array $opts = []): array {
        if (empty($files)) {
            return $this->reject('no files provided');
        }

        $normalized = [];
        foreach ($files as $f) {
            if (is_string($f)) {
                $normalized[] = ['path' => $f, 'mime' => $this->detectMime($f)];
            } elseif (is_array($f) && !empty($f['path'])) {
                $normalized[] = [
                    'path' => $f['path'],
                    'mime' => $f['mime'] ?? $this->detectMime($f['path']),
                ];
            }
        }

        // Level 0 — File Quality Gate
        foreach ($normalized as $f) {
            $check = $this->fileQualityGate($f['path'], $f['mime']);
            if (!$check['passed']) {
                return $this->reject($check['reason']);
            }
        }

        if (!$this->api_key) {
            return $this->reject('GEMINI_API_KEY not configured');
        }

        // Level 1 — Vision extract (multi-page auto-stitch если N>1)
        $rawData = $this->aiVisionExtract($normalized, $opts);
        if (!empty($rawData['error'])) {
            return $this->reject($rawData['error'], ['raw' => $rawData]);
        }

        // Level 2 — Math validation
        $math = $this->mathValidator($rawData);

        // Level 3 — Type detection (I6)
        $invoice_type = $this->detectInvoiceType($rawData);

        // Confidence merge
        $confidence = $this->computeMergedConfidence($rawData, $math);

        // Override 3 — < 0.5 → suggest voice fallback (I4)
        if ($confidence < OCR_REJECT_THRESHOLD) {
            return [
                'status'                 => 'REJECTED',
                'confidence'             => $confidence,
                'invoice_type'           => 'manual',
                'header'                 => $rawData['header'] ?? [],
                'items'                  => $rawData['items'] ?? [],
                'uncertain_fields'       => $rawData['uncertain_fields'] ?? [],
                'errors'                 => array_merge($math['issues'] ?? [], ['confidence too low']),
                'suggest_voice_fallback' => true,
                'raw_ocr_json'           => $rawData,
            ];
        }

        // Override 1 — за parent products с has_variations='true' без описание
        // деламе row-ниво variation_pending. Auto-pass правилото игнорирано.
        $items = $this->markVariationPending($rawData['items'] ?? [], $tenant_id);

        $has_variation_pending = false;
        foreach ($items as $it) {
            if (!empty($it['variation_pending'])) {
                $has_variation_pending = true;
                break;
            }
        }

        if ($confidence >= OCR_AUTO_THRESHOLD && !$has_variation_pending) {
            $status = 'AUTO_ACCEPTED';
        } else {
            $status = 'REVIEW_NEEDED';
        }

        return [
            'status'                 => $status,
            'confidence'             => $confidence,
            'invoice_type'           => $invoice_type,
            'header'                 => $rawData['header'] ?? [],
            'items'                  => $items,
            'uncertain_fields'       => $rawData['uncertain_fields'] ?? [],
            'errors'                 => $math['issues'] ?? [],
            'suggest_voice_fallback' => false,
            'raw_ocr_json'           => $rawData,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Level 0 — File Quality Gate
    // ─────────────────────────────────────────────────────────────────
    private function fileQualityGate(string $path, string $mime): array {
        if (!is_file($path) || !is_readable($path)) {
            return ['passed' => false, 'reason' => 'file not readable'];
        }
        $size = filesize($path);
        if ($size === false || $size < 1024) {
            return ['passed' => false, 'reason' => 'file too small (< 1KB)'];
        }
        if ($size > OCR_MAX_FILE_BYTES) {
            return ['passed' => false, 'reason' => 'file too large (> 10MB)'];
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            return ['passed' => false, 'reason' => 'unsupported mime: ' . $mime];
        }
        return ['passed' => true, 'reason' => null];
    }

    private function detectMime(string $path): string {
        $mime = @mime_content_type($path);
        return $mime ?: 'application/octet-stream';
    }

    // ─────────────────────────────────────────────────────────────────
    // Level 1 — Vision extract
    // ─────────────────────────────────────────────────────────────────
    private function aiVisionExtract(array $files, array $opts): array {
        $parts = [['text' => $this->systemPrompt($opts)]];
        foreach ($files as $f) {
            $b64 = base64_encode(file_get_contents($f['path']));
            $parts[] = ['inline_data' => ['mime_type' => $f['mime'], 'data' => $b64]];
        }

        $payload = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 8192,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . urlencode($this->model) . ':generateContent?key=' . $this->api_key;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr     = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http !== 200) {
            error_log("OCRRouter Gemini HTTP $http: " . substr((string)$response, 0, 300));
            return ['error' => 'AI Vision call failed (' . ($cerr ?: 'HTTP ' . $http) . ')'];
        }

        $data = json_decode($response, true);
        $raw = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$raw) {
            return ['error' => 'empty Vision response'];
        }
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            return ['error' => 'Vision returned non-JSON'];
        }
        return $this->normalizeVisionResponse($parsed);
    }

    private function systemPrompt(array $opts): string {
        $expected_currency = $opts['expected_currency'] ?? 'EUR';
        return <<<TXT
Ти си OCR асистент за фактури. Извличаш данни от снимка на фактура.

ВАЖНИ ПРАВИЛА:
1. Връщай САМО валиден JSON, без коментари, без markdown.
2. Ако не четеш поле — върни null. НИКОГА не измисляй.
3. Включи "confidence" 0.00-1.00 за целия документ + per item "confidence".
4. Дати в YYYY-MM-DD формат.
5. Цени с 2 десетични знака, без валута.
6. Извличай САМО видимото, не извеждай по контекст.
7. Маркирай "uncertain_fields": [...] списък с пътеки към полета които не си сигурен (напр. "header.vat_amount" или "items.2.unit_cost").
8. Ако ред изглежда вариационен (текст съдържа размери/цветове) → "has_variations_hint": true.
9. Ако фактурата е ръкописна / нечетлива → confidence < 0.5.

JSON структура:
{
  "header": {
    "supplier_name": null,
    "supplier_eik": null,
    "invoice_number": null,
    "date": null,
    "currency": "$expected_currency",
    "base_amount": null,
    "vat_rate": null,
    "vat_amount": null,
    "total_amount": null
  },
  "items": [
    {
      "line_number": 1,
      "name": "...",
      "qty": 0,
      "unit_cost": 0.00,
      "total_cost": 0.00,
      "vat_rate": null,
      "supplier_product_code": null,
      "has_variations_hint": false,
      "original_ocr_text": "...",
      "confidence": 0.00
    }
  ],
  "confidence": 0.00,
  "uncertain_fields": []
}

Очаквана валута: $expected_currency. Извеждай числа ТОЧНО както са на документа.
TXT;
    }

    private function normalizeVisionResponse(array $r): array {
        $out = [
            'header'           => [],
            'items'            => [],
            'confidence'       => 0.0,
            'uncertain_fields' => [],
        ];

        $h = $r['header'] ?? [];
        $out['header'] = [
            'supplier_name'   => $h['supplier_name']   ?? null,
            'supplier_eik'    => $h['supplier_eik']    ?? null,
            'invoice_number'  => $h['invoice_number']  ?? null,
            'date'            => $h['date']            ?? null,
            'currency'        => $h['currency']        ?? 'EUR',
            'base_amount'     => isset($h['base_amount'])  ? (float)$h['base_amount']  : null,
            'vat_rate'        => isset($h['vat_rate'])     ? (float)$h['vat_rate']     : null,
            'vat_amount'      => isset($h['vat_amount'])   ? (float)$h['vat_amount']   : null,
            'total_amount'    => isset($h['total_amount']) ? (float)$h['total_amount'] : null,
        ];

        $items = $r['items'] ?? [];
        if (!is_array($items)) $items = [];
        foreach ($items as $i => $it) {
            $out['items'][] = [
                'line_number'           => (int)($it['line_number'] ?? ($i + 1)),
                'name'                  => trim((string)($it['name'] ?? '')),
                'qty'                   => (float)($it['qty'] ?? 0),
                'unit_cost'             => isset($it['unit_cost'])  ? (float)$it['unit_cost']  : null,
                'total_cost'            => isset($it['total_cost']) ? (float)$it['total_cost'] : null,
                'vat_rate'              => isset($it['vat_rate'])   ? (float)$it['vat_rate']   : null,
                'supplier_product_code' => $it['supplier_product_code'] ?? null,
                'has_variations_hint'   => !empty($it['has_variations_hint']),
                'original_ocr_text'     => (string)($it['original_ocr_text'] ?? ''),
                'confidence'            => isset($it['confidence']) ? max(0, min(1, (float)$it['confidence'])) : 0.5,
            ];
        }

        $out['confidence'] = isset($r['confidence']) ? max(0, min(1, (float)$r['confidence'])) : 0.0;
        $out['uncertain_fields'] = is_array($r['uncertain_fields'] ?? null) ? $r['uncertain_fields'] : [];

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────
    // Level 2 — Math validator
    // ─────────────────────────────────────────────────────────────────
    private function mathValidator(array $data): array {
        $issues = [];
        $h = $data['header'] ?? [];

        $base  = isset($h['base_amount'])  ? (float)$h['base_amount']  : null;
        $vat   = isset($h['vat_amount'])   ? (float)$h['vat_amount']   : null;
        $total = isset($h['total_amount']) ? (float)$h['total_amount'] : null;

        if ($base !== null && $vat !== null && $total !== null) {
            $expected = round($base + $vat, 2);
            if (abs($expected - $total) > OCR_MATH_TOLERANCE) {
                $issues[] = sprintf('header math mismatch: base+vat=%.2f vs total=%.2f', $expected, $total);
            }
        }

        $items_total = 0.0;
        $items_with_total = 0;
        foreach ($data['items'] ?? [] as $i => $it) {
            $qty  = (float)($it['qty'] ?? 0);
            $u    = isset($it['unit_cost'])  ? (float)$it['unit_cost']  : null;
            $t    = isset($it['total_cost']) ? (float)$it['total_cost'] : null;
            if ($qty > 0 && $u !== null && $t !== null) {
                $exp = round($qty * $u, 2);
                if (abs($exp - $t) > OCR_MATH_TOLERANCE) {
                    $issues[] = sprintf('item %d math: qty*unit=%.2f vs total=%.2f', ($i + 1), $exp, $t);
                }
                $items_total += $t;
                $items_with_total++;
            }
        }
        if ($items_with_total > 0 && $base !== null) {
            if (abs($items_total - $base) > 0.10) {
                $issues[] = sprintf('items sum %.2f vs header base %.2f', $items_total, $base);
            }
        }

        return ['issues' => $issues, 'items_total' => $items_total];
    }

    // ─────────────────────────────────────────────────────────────────
    // Level 3 — Type detection (I6)
    // ─────────────────────────────────────────────────────────────────
    private function detectInvoiceType(array $data): string {
        $conf = (float)($data['confidence'] ?? 0);
        if ($conf < 0.5) return 'manual';   // ръкописна / нечетлива

        $has_var = false;
        foreach ($data['items'] ?? [] as $it) {
            if (!empty($it['has_variations_hint'])) { $has_var = true; break; }
        }
        if ($conf >= 0.85 && $has_var) return 'semi';
        if ($conf >= 0.85) return 'clean';
        return 'semi';
    }

    // ─────────────────────────────────────────────────────────────────
    // Variation pending overlay (Override 1)
    // ─────────────────────────────────────────────────────────────────
    private function markVariationPending(array $items, int $tenant_id): array {
        $names = array_filter(array_map(fn($it) => trim((string)($it['name'] ?? '')), $items));
        if (empty($names)) return $items;

        try {
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $params = array_merge([$tenant_id], array_values($names));
            $rows = DB::run("
                SELECT name, has_variations
                FROM products
                WHERE tenant_id = ? AND name IN ($placeholders)
            ", $params)->fetchAll();
            $map = [];
            foreach ($rows as $r) $map[mb_strtolower($r['name'], 'UTF-8')] = $r['has_variations'];
        } catch (Throwable $e) {
            error_log('OCRRouter markVariationPending: ' . $e->getMessage());
            $map = [];
        }

        foreach ($items as &$it) {
            $name_lc = mb_strtolower(trim((string)($it['name'] ?? '')), 'UTF-8');
            $has_var = $map[$name_lc] ?? null;
            $hint = !empty($it['has_variations_hint']);

            // Pending if existing parent has variations='true' and OCR didn't include desc,
            // OR new product where AI hinted variations.
            $it['variation_pending'] = ($has_var === 'true' || ($has_var === null && $hint)) ? 1 : 0;
        }
        unset($it);
        return $items;
    }

    // ─────────────────────────────────────────────────────────────────
    // Confidence merge
    // ─────────────────────────────────────────────────────────────────
    private function computeMergedConfidence(array $rawData, array $math): float {
        $base = (float)($rawData['confidence'] ?? 0.5);
        if (!empty($math['issues'])) {
            $base -= 0.10 * min(3, count($math['issues']));
        }
        return max(0.0, min(1.0, round($base, 3)));
    }

    private function reject(string $reason, array $extra = []): array {
        return array_merge([
            'status'                 => 'REJECTED',
            'confidence'             => 0.0,
            'invoice_type'           => 'manual',
            'header'                 => [],
            'items'                  => [],
            'uncertain_fields'       => [],
            'errors'                 => [$reason],
            'suggest_voice_fallback' => true,
            'raw_ocr_json'           => null,
        ], $extra);
    }
}

// ─────────────────────────────────────────────────────────────────────
// HTTP ENDPOINT MODE — POST: file (multipart) или files[] + tenant from session
// ─────────────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    session_start();
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'data' => null, 'error' => 'unauthorized']);
        exit;
    }

    $tenant_id = (int)$_SESSION['tenant_id'];
    $files = [];
    $tmp_to_cleanup = [];

    if (!empty($_FILES['file'])) {
        $f = $_FILES['file'];
        if (is_array($f['name'])) {
            for ($i = 0; $i < count($f['name']); $i++) {
                if ($f['error'][$i] === UPLOAD_ERR_OK) {
                    $files[] = ['path' => $f['tmp_name'][$i], 'mime' => $f['type'][$i]];
                }
            }
        } elseif ($f['error'] === UPLOAD_ERR_OK) {
            $files[] = ['path' => $f['tmp_name'], 'mime' => $f['type']];
        }
    }

    if (empty($files)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'data' => null, 'error' => 'no files']);
        exit;
    }

    $opts = [];
    if (!empty($_POST['expected_currency'])) $opts['expected_currency'] = (string)$_POST['expected_currency'];
    if (!empty($_POST['supplier_id']))       $opts['supplier_id']       = (int)$_POST['supplier_id'];

    $router = new OCRRouter();
    $result = $router->process($files, $tenant_id, $opts);

    echo json_encode([
        'ok'    => $result['status'] !== 'REJECTED' || empty($result['errors']),
        'data'  => $result,
        'error' => empty($result['errors']) ? null : implode('; ', (array)$result['errors']),
    ]);
    exit;
}
