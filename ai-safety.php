<?php
/**
 * ai-safety.php — SafetyNet за AI Advisor
 * RunMyStore.ai С28 / Fix С30
 *
 * 3 функции:
 *   preValidate()  — преди Gemini: генерира ограничения за промпта
 *   postValidate() — след Gemini: филтрира отговора
 *   logAdvice()    — записва в ai_advice_log
 *
 * Използване в chat-send.php:
 *   require_once __DIR__ . '/ai-safety.php';
 *   $safety = preValidate($tenant_id, $store_id, $role);
 *   $prompt .= "\n" . $safety['constraints'];
 *   // ... Gemini call → $rawReply ...
 *   $reply = postValidate($rawReply);
 *   logAdvice($tenant_id, $store_id, $user_id, $message, $rawReply, $reply);
 */

// ═══════════════════════════════════════════════════════════════
// СЛОЙ 1: PRE-VALIDATION (преди Gemini)
// ═══════════════════════════════════════════════════════════════
function preValidate(int $tenant_id, int $store_id, string $role): array
{
    $constraints = [];
    $context     = [];

    // ── 1. РЕАЛНИ ДНИ С ПРОДАЖБИ (FIX С30) ─────────────────────
    // Брои РАЗЛИЧНИ дни с completed продажби — не tenant created_at
    $data_days = (int)DB::run(
        'SELECT COUNT(DISTINCT DATE(created_at))
         FROM sales
         WHERE tenant_id = ? AND status != "canceled"',
        [$tenant_id]
    )->fetchColumn();

    if ($data_days === 0) {
        // Fallback: няма продажби → ползвай tenant age
        $tenant_row = DB::run(
            'SELECT created_at FROM tenants WHERE id = ? LIMIT 1',
            [$tenant_id]
        )->fetch();
        if ($tenant_row && !empty($tenant_row['created_at'])) {
            $data_days = max(0, (int)floor((time() - strtotime($tenant_row['created_at'])) / 86400));
        }
    }

    $context['data_days'] = $data_days;

    if ($data_days < 14) {
        $left          = 14 - $data_days;
        $constraints[] = "HARD RULE: Only {$data_days} distinct days with sales data. "
                       . "DO NOT make trend predictions. DO NOT declare zombies. "
                       . "DO NOT do basket analysis. "
                       . "Say: 'Трябват ми още {$left} дни данни.'";
    }

    // ── 2. COUNTRY (за празници) ─────────────────────────────────
    $tenant = DB::run(
        'SELECT country FROM tenants WHERE id = ? LIMIT 1',
        [$tenant_id]
    )->fetch();
    $country = strtoupper($tenant['country'] ?? 'BG');

    // ── 3. ПРАЗНИЦИ ──────────────────────────────────────────────
    if (isHoliday($country)) {
        $constraints[] = "CONTEXT: TODAY IS A PUBLIC HOLIDAY. Zero or low sales are NORMAL. Do not panic.";
    }

    // ── 4. ВРЕМЕ ОТ ДЕНЯ ─────────────────────────────────────────
    $hour = (int)date('H');
    if ($hour >= 8 && $hour < 11) {
        $constraints[] = "CONTEXT: Morning. User wants PLAN. Lead with top 3 actions.";
    } elseif ($hour >= 11 && $hour < 14) {
        $constraints[] = "CONTEXT: Midday. User wants STATUS. Lead with numbers.";
    } elseif ($hour >= 14 && $hour < 17) {
        $constraints[] = "CONTEXT: Afternoon. User wants ANALYSIS.";
    } elseif ($hour >= 17 && $hour < 20) {
        $constraints[] = "CONTEXT: Evening. SUMMARY + prep for tomorrow.";
    } elseif ($hour >= 20 || $hour < 8) {
        $constraints[] = "CONTEXT: Night. Keep short. Only urgent alerts.";
    }

    if (date('N') == 5 && $hour >= 17) {
        $constraints[] = "CONTEXT: FRIDAY EVENING. Extra cautious about discounts.";
    }

    // ── 5. ДОСТАВКИ В ПЪТ (FIX С29/С30) ──────────────────────────
    // deliveries: НЯМА expected_date, НЯМА status! Ползвай delivered_at IS NULL
    $pending = DB::run(
        'SELECT d.id, s.name AS supplier,
                COALESCE(GROUP_CONCAT(DISTINCT p.name SEPARATOR ", "), "неуточнени артикули") AS products
         FROM deliveries d
         JOIN suppliers s ON s.id = d.supplier_id
         LEFT JOIN delivery_items di ON di.delivery_id = d.id
         LEFT JOIN products p ON p.id = di.product_id
         WHERE d.tenant_id = ? AND d.delivered_at IS NULL
         GROUP BY d.id
         LIMIT 5',
        [$tenant_id]
    )->fetchAll();

    if (!empty($pending)) {
        $lines = ["CONTEXT: DELIVERIES IN TRANSIT — before suggesting orders, check:"];
        foreach ($pending as $d) {
            $lines[] = "  - {$d['supplier']}: ({$d['products']})";
        }
        $constraints[] = implode("\n", $lines);
    }

    // ── 6. ДОСТАВНИ ЦЕНИ — ПОКРИТИЕ ──────────────────────────────
    $cs = DB::run(
        'SELECT COUNT(*) AS total,
                SUM(CASE WHEN cost_price > 0 THEN 1 ELSE 0 END) AS with_cost
         FROM products
         WHERE tenant_id = ? AND is_active = 1',
        [$tenant_id]
    )->fetch();

    $total_p  = (int)($cs['total']     ?? 0);
    $has_cost = (int)($cs['with_cost'] ?? 0);
    $pct      = $total_p > 0 ? (int)round($has_cost / $total_p * 100) : 0;
    $missing  = $total_p - $has_cost;

    $context['cost_coverage_pct'] = $pct;

    if ($pct < 70) {
        $constraints[] = "HARD RULE: Only {$pct}% of products have cost_price ({$missing} missing). "
                       . "DO NOT calculate overall margin. "
                       . "Say: 'Нямам доставни цени за {$missing} артикула.'";
    }

    // ── 7. ТРАНЗАКЦИИ ─────────────────────────────────────────────
    $tx = (int)DB::run(
        'SELECT COUNT(*)
         FROM sales
         WHERE tenant_id = ? AND store_id = ? AND status != "canceled"
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        [$tenant_id, $store_id]
    )->fetchColumn();

    $context['tx_30d'] = $tx;

    if ($tx < 50) {
        $constraints[] = "HARD RULE: Only {$tx} transactions in 30 days. "
                       . "DO NOT do basket analysis. "
                       . "Say: 'Трябват ми поне 50 продажби.'";
    }

    // ── 8. MUTED ТЕМИ ─────────────────────────────────────────────
    $muted = getMutedTopics($tenant_id);
    if (!empty($muted)) {
        $list          = implode(', ', $muted);
        $constraints[] = "HARD RULE: User REJECTED these topics 3+ times: [{$list}]. DO NOT mention them.";
    }

    // ── 9. ПОДОЗРИТЕЛНИ ЦЕНИ ──────────────────────────────────────
    $bad_prices = DB::run(
        'SELECT code
         FROM products
         WHERE tenant_id = ? AND is_active = 1
           AND cost_price > 0 AND retail_price > 0
           AND (retail_price < cost_price * 0.2 OR retail_price > cost_price * 10)
         LIMIT 5',
        [$tenant_id]
    )->fetchAll();

    if (!empty($bad_prices)) {
        $codes         = implode(', ', array_column($bad_prices, 'code'));
        $constraints[] = "WARNING: Suspicious pricing on: [{$codes}]. DO NOT give pricing advice for them.";
    }

    // ── СГЛОБЯВАНЕ ────────────────────────────────────────────────
    $str = '';
    if (!empty($constraints)) {
        $str  = "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $str .= "DYNAMIC SAFETY CONSTRAINTS (auto-generated)\n";
        $str .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $str .= implode("\n\n", $constraints);
    }

    return ['constraints' => $str, 'context' => $context];
}

// ═══════════════════════════════════════════════════════════════
// СЛОЙ 2: POST-VALIDATION (след Gemini, преди Пешо)
// ═══════════════════════════════════════════════════════════════
function postValidate(string $response): string
{
    // ── 1. ПРИЗНАВАНЕ НА ГРЕШКА → "Прав си" ───────────────────
    $response = preg_replace(
        '/(сбърках|грешка моя|моя грешка|извинявам се|извинявай|не бях прав|обърках се|съжалявам)/ui',
        'Прав си. Коригирам подхода',
        $response
    );

    // ── 2. ПАНИКА → смекчаване ────────────────────────────────
    $response = preg_replace(
        '/(катастрофа|фалираш|ще загубиш всичко|пълен провал|тотален крах|пропадане|фатално)/ui',
        'сериозно отклонение',
        $response
    );

    // ── 3. ПЕРСОНАЛ → неутрално ───────────────────────────────
    $response = preg_replace(
        '/(уволни|изгони|махни го|разкарай|накажи|глоби)\s/ui',
        'обсъди с персонала ',
        $response
    );

    // ── 4. ДАНЪЦИ/ПРАВО → счетоводител ────────────────────────
    $response = preg_replace(
        '/(данъчн|данъци|НАП|незаконно|нелегално|укри)/ui',
        'Питай счетоводителя за',
        $response
    );

    // ── 5. ЗАБРАНЕН ЖАРГОН ────────────────────────────────────
    $response = preg_replace('/\b(брат|братле|машино)\b/ui', '', $response);
    $response = preg_replace('/(разбихме ги|избихме рибата|сипи си едно|бачкаме за слава)/ui', '', $response);
    $response = preg_replace('/Как мога да помогна\??/ui', '', $response);
    $response = preg_replace('/Разбира се!/ui', '', $response);

    // ── 6. ОТСТЪПКИ — МАКС 25% ───────────────────────────────
    $response = preg_replace_callback(
        '/([\-\x{2212}]\s*|намали\s+(?:с\s+)?|отстъпка\s+)(3[0-9]|[4-9][0-9]|100)\s*%/ui',
        function ($m) { return $m[1] . '25%'; },
        $response
    );
    $response = preg_replace('/наполовина/ui', 'с 25%', $response);

    // ── 7. ЦЕНА НАГОРЕ — МАКС 15% ────────────────────────────
    $response = preg_replace_callback(
        '/(\+\s*|вдигни\s+(?:с\s+)?|увеличи\s+(?:с\s+)?)(1[6-9]|[2-9][0-9]|100)\s*%/ui',
        function ($m) { return $m[1] . '15%'; },
        $response
    );

    // ── 8. ПОРЪЧКИ — ТОЧНИ ЧИСЛА → ДИАПАЗОНИ ─────────────────
    $response = preg_replace_callback(
        '/(поръчай|зареди|вземи|добави)(\s+още)?\s+(\d+)\s*(бройки|бр\.?|чифта|парчета)/ui',
        function ($m) {
            $verb  = $m[1];
            $extra = $m[2] ?? '';
            $num   = (int)$m[3];
            $unit  = $m[4];
            if ($num <= 5) return "{$verb}{$extra} {$num} {$unit}";
            $min = max(1, (int)floor($num * 0.8));
            $max = (int)ceil($num * 1.2);
            if ($num >= 20) {
                $min = (int)(round($min / 5) * 5);
                $max = (int)(round($max / 5) * 5);
            }
            if ($min >= $max) $max = $min + 5;
            return "{$verb}{$extra} {$min}-{$max} {$unit}";
        },
        $response
    );

    // ── 9. МАКС 3 ДЕЙСТВИЯ ────────────────────────────────────
    preg_match_all('/\[[^\]]*?→\]/u', $response, $acts, PREG_OFFSET_CAPTURE);
    if (count($acts[0]) > 3) {
        $cut_byte = $acts[0][2][1] + strlen($acts[0][2][0]);
        $response = substr($response, 0, $cut_byte) . "\n\nОсталото може да изчака.";
    }

    // ── 10. ПОЧИСТВАНЕ ────────────────────────────────────────
    $response = preg_replace('/\n{3,}/', "\n\n", $response);
    $response = preg_replace('/ +/', ' ', $response);

    return trim($response);
}

// ═══════════════════════════════════════════════════════════════
// СЛОЙ 3: LOGGING + MUTE LOGIC
// ═══════════════════════════════════════════════════════════════
function logAdvice(
    int    $tenant_id,
    int    $store_id,
    int    $user_id,
    string $user_message,
    string $ai_raw,
    string $ai_filtered
): void {
    $advice_type = detectAdviceType($ai_filtered);
    $product_id  = detectProductId($ai_filtered, $tenant_id);
    $topic_hash  = md5($advice_type . ':' . ($product_id ?? 'general'));
    $filters     = detectAppliedFilters($ai_raw, $ai_filtered);

    try {
        DB::run(
            'INSERT INTO ai_advice_log
             (tenant_id, store_id, user_id, user_message,
              ai_response_raw, ai_response_filtered,
              filters_applied, advice_type, product_id, topic_hash)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $tenant_id,
                $store_id,
                $user_id,
                mb_substr($user_message, 0, 5000),
                mb_substr($ai_raw,       0, 10000),
                mb_substr($ai_filtered,  0, 10000),
                json_encode($filters, JSON_UNESCAPED_UNICODE),
                $advice_type,
                $product_id,
                $topic_hash,
            ]
        );
    } catch (Throwable $e) {
        error_log("ai_advice_log INSERT error: " . $e->getMessage());
    }

    // Пазим последните 500 записа
    try {
        $cutoff_id = DB::run(
            'SELECT id FROM ai_advice_log
             WHERE tenant_id = ?
             ORDER BY created_at DESC
             LIMIT 1 OFFSET 500',
            [$tenant_id]
        )->fetchColumn();
        if ($cutoff_id) {
            DB::run(
                'DELETE FROM ai_advice_log WHERE tenant_id = ? AND id < ?',
                [$tenant_id, (int)$cutoff_id]
            );
        }
    } catch (Throwable $e) {
        // Cleanup не е критичен
    }
}

/**
 * Muted теми: 3+ rejected за 30 дни ИЛИ user_action = "muted"
 */
function getMutedTopics(int $tenant_id): array
{
    $rejected = DB::run(
        'SELECT advice_type, COUNT(*) AS cnt
         FROM ai_advice_log
         WHERE tenant_id = ? AND user_action = "rejected"
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY advice_type
         HAVING cnt >= 3',
        [$tenant_id]
    )->fetchAll();

    $muted = DB::run(
        'SELECT DISTINCT advice_type
         FROM ai_advice_log
         WHERE tenant_id = ? AND user_action = "muted"',
        [$tenant_id]
    )->fetchAll();

    $topics = [];
    foreach (array_merge($rejected, $muted) as $r) {
        if (!empty($r['advice_type'])) {
            $topics[] = $r['advice_type'];
        }
    }
    return array_unique($topics);
}

// ═══════════════════════════════════════════════════════════════
// ПОМОЩНИ ФУНКЦИИ
// ═══════════════════════════════════════════════════════════════
function detectAdviceType(string $text): string
{
    $t   = mb_strtolower($text);
    $map = [
        'restock'      => ['поръчай','зареди','свършва','свърши','на нула','наличност'],
        'zombie'       => ['zombie','зомби','стои от','не мърда','замразен'],
        'margin'       => ['марж','печалба','маржа','маржът'],
        'price_change' => ['вдигни цена','намали цена','промени цена','вдигни с','намали с'],
        'promotion'    => ['промоция','отстъпка','намалени','разпродажба'],
        'delivery'     => ['доставка','доставчик','закъснява'],
        'vip'          => ['клиент','vip','не е идвал'],
    ];
    foreach ($map as $type => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($t, $kw) !== false) return $type;
        }
    }
    return 'general';
}

function detectProductId(string $text, int $tenant_id): ?int
{
    if (preg_match('/\[([A-Za-z0-9\-]{3,20})\]/', $text, $m)) {
        $row = DB::run(
            'SELECT id FROM products WHERE tenant_id = ? AND code = ? LIMIT 1',
            [$tenant_id, $m[1]]
        )->fetch();
        if ($row) return (int)$row['id'];
    }
    return null;
}

function detectAppliedFilters(string $raw, string $filtered): array
{
    if ($raw === $filtered) return [];
    $f = [];
    if (preg_match('/(сбърках|грешка моя|извинявам|съжалявам)/ui', $raw))        $f[] = 'apology_replaced';
    if (preg_match('/(катастрофа|фалираш|тотален крах|пропадане)/ui', $raw))      $f[] = 'panic_replaced';
    if (preg_match('/(уволни|изгони|махни го)/ui', $raw))                         $f[] = 'personnel_replaced';
    if (preg_match('/(данъчн|данъци|НАП|незаконно)/ui', $raw))                    $f[] = 'legal_replaced';
    if (preg_match('/([\-\x{2212}]\s*[3-9]\d)\s*%/u', $raw))                     $f[] = 'discount_capped';
    if (preg_match('/(\+\s*(?:1[6-9]|[2-9]\d))\s*%/u', $raw))                    $f[] = 'price_increase_capped';
    if (preg_match('/\b(брат|братле|машино)\b/ui', $raw))                         $f[] = 'slang_removed';
    if (empty($f)) $f[] = 'text_cleaned';
    return $f;
}

/**
 * Проверка за официален празник по държава.
 * Фиксирани дати + православен Великден до 2035.
 */
function isHoliday(string $country): bool
{
    $today = date('m-d');
    $year  = (int)date('Y');

    $holidays = [
        'BG' => ['01-01','03-03','05-01','05-06','05-24','09-06','09-22','11-01','12-24','12-25','12-26'],
        'RO' => ['01-01','01-24','05-01','06-01','08-15','11-30','12-01','12-25','12-26'],
    ];

    $list = $holidays[$country] ?? $holidays['BG'];
    if (in_array($today, $list)) return true;

    // Православен Великден
    $easter = [
        2025 => '04-20', 2026 => '04-12', 2027 => '05-02', 2028 => '04-16',
        2029 => '04-08', 2030 => '04-28', 2031 => '04-13', 2032 => '05-02',
        2033 => '04-24', 2034 => '04-09', 2035 => '04-29',
    ];

    if (in_array($country, ['BG', 'RO']) && isset($easter[$year])) {
        $ets = strtotime("{$year}-{$easter[$year]}");
        for ($d = -2; $d <= 1; $d++) {
            if (date('m-d', $ets + $d * 86400) === $today) return true;
        }
    }

    return false;
}
