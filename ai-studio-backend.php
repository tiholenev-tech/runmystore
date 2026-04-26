<?php
/**
 * ai-studio-backend.php — AI Studio backend foundation (S82.STUDIO.BACKEND).
 *
 * Pure helper library — no HTTP I/O, no session_start, no headers.
 * Loaded by ai-image-processor.php and other AI Studio endpoints.
 *
 * Three credit types: bg (background removal), desc (Gemini description), magic (try-on / hero image).
 * Two balance sources: monthly included (tenants.included_*_per_month / *_used_this_month) +
 * purchased pool (ai_credits_balance.{bg,desc,magic}_credits).
 *
 * Quality Guarantee: failed magic-image generations refund the credit and chain via
 * ai_spend_log.parent_log_id; max 2 retries, then refunded_loss + credit returned to user.
 *
 * UI rule: never surface model names like Gemini / fal.ai / nano-banana to the end user.
 * Backend log lines are exempt — useful for debugging.
 */

require_once __DIR__ . '/config/database.php';

// ─────────────────────────────────────────────────────────────────────────
// Config flags — flipped via deploy, not via UI.
// Тихол will pick nano-banana-2 vs nano-banana-pro after pricing review (open question).
// ─────────────────────────────────────────────────────────────────────────
if (!defined('AI_MAGIC_MODEL')) {
    define('AI_MAGIC_MODEL', 'nano-banana-pro'); // alternative: 'nano-banana-2'
}
if (!defined('AI_MAGIC_PRICE')) {
    define('AI_MAGIC_PRICE', 0.50); // alternative: 0.30 for nano-banana-2
}
if (!defined('AI_BG_PRICE'))   { define('AI_BG_PRICE',   0.05); }
if (!defined('AI_DESC_PRICE')) { define('AI_DESC_PRICE', 0.02); }

// Quality Guarantee budget.
if (!defined('AI_MAX_RETRIES'))           { define('AI_MAX_RETRIES', 2); }
if (!defined('AI_ABUSE_DAILY_HARD_CAP'))  { define('AI_ABUSE_DAILY_HARD_CAP', 30); }
if (!defined('AI_ABUSE_RETRY_RATE_SOFT')) { define('AI_ABUSE_RETRY_RATE_SOFT', 0.60); }

if (!function_exists('rms_studio_credit_types')) {

    /**
     * Whitelist of credit types. Centralized so consume/refund/balance/log stay in lockstep.
     */
    function rms_studio_credit_types(): array {
        return ['bg', 'desc', 'magic'];
    }

    /**
     * Map a credit type to its tenants-table column pair.
     */
    function rms_studio_type_columns(string $type): array {
        return [
            'included' => "included_{$type}_per_month",
            'used'     => "{$type}_used_this_month",
            'balance'  => "{$type}_credits",
        ];
    }

    /**
     * Map a feature name (from ai_spend_log.feature) to a credit type.
     * Returns null for features that don't consume credits (e.g. color_detect is plan-gated only).
     */
    function rms_studio_feature_to_type(string $feature): ?string {
        switch ($feature) {
            case 'bg_remove':   return 'bg';
            case 'description': return 'desc';
            case 'magic':
            case 'tryon':       return 'magic';
            default:            return null;
        }
    }

    /**
     * 1) get_credit_balance — combined balance for a credit type.
     *    Returns associative array with included_remaining + purchased + total + reason.
     *    Source of truth: tenants (monthly included) + ai_credits_balance (purchased pool).
     */
    function get_credit_balance(int $tenant_id, string $type): array {
        if ($tenant_id <= 0 || !in_array($type, rms_studio_credit_types(), true)) {
            return ['included_remaining' => 0, 'purchased' => 0, 'total' => 0, 'reason' => 'invalid_input'];
        }

        $cols = rms_studio_type_columns($type);
        $row  = DB::run(
            "SELECT {$cols['included']} AS inc, {$cols['used']} AS used FROM tenants WHERE id = ?",
            [$tenant_id]
        )->fetch();
        $included = (int)($row['inc']  ?? 0);
        $used     = (int)($row['used'] ?? 0);
        $included_remaining = max(0, $included - $used);

        $purchased = (int)(DB::run(
            "SELECT {$cols['balance']} FROM ai_credits_balance WHERE tenant_id = ?",
            [$tenant_id]
        )->fetchColumn() ?: 0);

        return [
            'included_remaining' => $included_remaining,
            'purchased'          => $purchased,
            'total'              => $included_remaining + $purchased,
            'reason'             => null,
        ];
    }

    /**
     * 2) consume_credit — atomic decrement. Spends from monthly included first, then purchased.
     *    Returns ['ok' => bool, 'source' => 'included'|'purchased'|'mixed'|null, 'reason' => ?string].
     *    Wrapped in DB::tx so concurrent calls do not double-spend.
     */
    function consume_credit(int $tenant_id, string $type, int $amount = 1): array {
        if ($tenant_id <= 0 || !in_array($type, rms_studio_credit_types(), true) || $amount < 1) {
            return ['ok' => false, 'source' => null, 'reason' => 'invalid_input'];
        }

        return DB::tx(function (PDO $pdo) use ($tenant_id, $type, $amount) {
            $cols = rms_studio_type_columns($type);

            // Lock tenant row + balance row for the duration of the transaction.
            $tStmt = $pdo->prepare(
                "SELECT {$cols['included']} AS inc, {$cols['used']} AS used FROM tenants WHERE id = ? FOR UPDATE"
            );
            $tStmt->execute([$tenant_id]);
            $t = $tStmt->fetch(PDO::FETCH_ASSOC);
            if (!$t) return ['ok' => false, 'source' => null, 'reason' => 'tenant_not_found'];

            $included         = (int)$t['inc'];
            $used             = (int)$t['used'];
            $inc_remaining    = max(0, $included - $used);

            // Make sure a balance row exists, then lock it.
            $pdo->prepare(
                "INSERT IGNORE INTO ai_credits_balance (tenant_id) VALUES (?)"
            )->execute([$tenant_id]);

            $bStmt = $pdo->prepare(
                "SELECT {$cols['balance']} AS bal FROM ai_credits_balance WHERE tenant_id = ? FOR UPDATE"
            );
            $bStmt->execute([$tenant_id]);
            $purchased = (int)($bStmt->fetch()['bal'] ?? 0);

            if ($inc_remaining + $purchased < $amount) {
                return ['ok' => false, 'source' => null, 'reason' => 'insufficient_credits'];
            }

            $from_included  = min($inc_remaining, $amount);
            $from_purchased = $amount - $from_included;
            $source = $from_included && $from_purchased ? 'mixed'
                    : ($from_included ? 'included' : 'purchased');

            if ($from_included > 0) {
                $pdo->prepare(
                    "UPDATE tenants SET {$cols['used']} = {$cols['used']} + ? WHERE id = ?"
                )->execute([$from_included, $tenant_id]);
            }
            if ($from_purchased > 0) {
                $pdo->prepare(
                    "UPDATE ai_credits_balance SET {$cols['balance']} = {$cols['balance']} - ? WHERE tenant_id = ?"
                )->execute([$from_purchased, $tenant_id]);
            }

            return ['ok' => true, 'source' => $source, 'reason' => null];
        });
    }

    /**
     * 3) refund_credit — flip an ai_spend_log row to refunded_loss + restore one credit
     *    to the tenant's purchased pool (safer than restoring "used" counters since the
     *    month boundary may have moved).
     *    Returns ['ok' => bool, 'reason' => ?string].
     */
    function refund_credit(int $log_id): array {
        if ($log_id <= 0) return ['ok' => false, 'reason' => 'invalid_input'];

        return DB::tx(function (PDO $pdo) use ($log_id) {
            $stmt = $pdo->prepare(
                "SELECT id, tenant_id, feature, status FROM ai_spend_log WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$log_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$log) return ['ok' => false, 'reason' => 'log_not_found'];
            if ($log['status'] !== 'completed_paid') {
                return ['ok' => false, 'reason' => 'already_refunded_or_free'];
            }

            $type = rms_studio_feature_to_type((string)$log['feature']);
            if ($type === null) return ['ok' => false, 'reason' => 'feature_not_refundable'];

            $cols = rms_studio_type_columns($type);
            $pdo->prepare(
                "INSERT IGNORE INTO ai_credits_balance (tenant_id) VALUES (?)"
            )->execute([(int)$log['tenant_id']]);
            $pdo->prepare(
                "UPDATE ai_credits_balance SET {$cols['balance']} = {$cols['balance']} + 1 WHERE tenant_id = ?"
            )->execute([(int)$log['tenant_id']]);
            $pdo->prepare(
                "UPDATE ai_spend_log SET status = 'refunded_loss' WHERE id = ?"
            )->execute([$log_id]);

            return ['ok' => true, 'reason' => null];
        });
    }

    /**
     * 4) check_retry_eligibility — given an original log id, returns how many retries are still allowed.
     *    Quality Guarantee: max AI_MAX_RETRIES extra attempts after the first paid one.
     *    Returns ['retries_used' => int, 'retries_remaining' => int, 'eligible' => bool, 'reason' => ?string].
     */
    function check_retry_eligibility(int $parent_log_id): array {
        if ($parent_log_id <= 0) {
            return ['retries_used' => 0, 'retries_remaining' => 0, 'eligible' => false, 'reason' => 'invalid_input'];
        }

        $parent = DB::run(
            "SELECT id, status FROM ai_spend_log WHERE id = ?",
            [$parent_log_id]
        )->fetch();
        if (!$parent) {
            return ['retries_used' => 0, 'retries_remaining' => 0, 'eligible' => false, 'reason' => 'parent_not_found'];
        }
        if ($parent['status'] === 'refunded_loss') {
            return ['retries_used' => AI_MAX_RETRIES, 'retries_remaining' => 0, 'eligible' => false, 'reason' => 'already_refunded'];
        }

        $retries_used = (int)DB::run(
            "SELECT COUNT(*) FROM ai_spend_log WHERE parent_log_id = ? AND status = 'retry_free'",
            [$parent_log_id]
        )->fetchColumn();
        $remaining = max(0, AI_MAX_RETRIES - $retries_used);

        return [
            'retries_used'      => $retries_used,
            'retries_remaining' => $remaining,
            'eligible'          => $remaining > 0,
            'reason'            => $remaining > 0 ? null : 'retry_budget_exhausted',
        ];
    }

    /**
     * 5) check_anti_abuse — soft warning when retry rate > 60% in last 24h, hard cap at 30 retries/day.
     *    Returns ['blocked' => bool, 'soft_warning' => bool, 'retries_today' => int, 'retry_rate' => float, 'reason' => ?string].
     */
    function check_anti_abuse(int $tenant_id): array {
        if ($tenant_id <= 0) {
            return ['blocked' => true, 'soft_warning' => false, 'retries_today' => 0, 'retry_rate' => 0.0, 'reason' => 'invalid_input'];
        }

        $row = DB::run(
            "SELECT
               SUM(status = 'retry_free')                          AS retries_today,
               SUM(status IN ('completed_paid','retry_free'))      AS total_today
             FROM ai_spend_log
             WHERE tenant_id = ? AND created_at >= NOW() - INTERVAL 1 DAY",
            [$tenant_id]
        )->fetch();
        $retries_today = (int)($row['retries_today'] ?? 0);
        $total_today   = (int)($row['total_today']   ?? 0);
        $rate = $total_today > 0 ? $retries_today / $total_today : 0.0;

        $blocked = $retries_today >= AI_ABUSE_DAILY_HARD_CAP;
        $soft    = !$blocked && $rate > AI_ABUSE_RETRY_RATE_SOFT && $retries_today >= 5;

        return [
            'blocked'       => $blocked,
            'soft_warning'  => $soft,
            'retries_today' => $retries_today,
            'retry_rate'    => round($rate, 3),
            'reason'        => $blocked ? 'daily_retry_cap' : ($soft ? 'high_retry_rate' : null),
        ];
    }

    /**
     * 6) get_prompt_template — fetch active template for category (+ optional subtype).
     *    Returns the template row or null. Falls back to category-level if subtype-specific is missing.
     */
    function get_prompt_template(string $category, ?string $subtype = null): ?array {
        if ($category === '') return null;

        if ($subtype !== null && $subtype !== '') {
            $row = DB::run(
                "SELECT * FROM ai_prompt_templates
                 WHERE category = ? AND subtype = ? AND is_active = 1
                 ORDER BY id DESC LIMIT 1",
                [$category, $subtype]
            )->fetch();
            if ($row) return $row;
        }

        $row = DB::run(
            "SELECT * FROM ai_prompt_templates
             WHERE category = ? AND subtype IS NULL AND is_active = 1
             ORDER BY id DESC LIMIT 1",
            [$category]
        )->fetch();
        return $row ?: null;
    }

    /**
     * 7) build_prompt — substitutes {{placeholders}} in a template using product + options data.
     *    Recognized placeholders: {{name}} {{color}} {{size}} {{composition}} {{features}} {{material}} {{origin}}.
     *    Missing fields render as empty strings; the template should tolerate that.
     */
    function build_prompt(array $product, string $category, array $options = []): ?string {
        $tpl = get_prompt_template($category, $product['ai_subtype'] ?? ($options['subtype'] ?? null));
        if (!$tpl) return null;

        $features = $options['features'] ?? '';
        if (is_array($features)) $features = implode(', ', $features);

        $vars = [
            '{{name}}'        => (string)($product['name']           ?? ''),
            '{{color}}'       => (string)($product['color']          ?? ''),
            '{{size}}'        => (string)($product['size']           ?? ''),
            '{{composition}}' => (string)($product['composition']    ?? ''),
            '{{material}}'    => (string)($product['composition']    ?? ''),
            '{{origin}}'      => (string)($product['origin_country'] ?? ''),
            '{{features}}'    => (string)$features,
        ];

        // Bump usage counter (cheap, one row).
        DB::run(
            "UPDATE ai_prompt_templates SET usage_count = usage_count + 1 WHERE id = ?",
            [(int)$tpl['id']]
        );

        return strtr((string)$tpl['template'], $vars);
    }

    /**
     * 8) count_products_needing_ai — counts active root products needing each operation.
     *    If $category is given, scopes to that ai_category bucket (used by per-category cards).
     *    Returns ['bg' => int, 'desc' => int, 'magic' => int].
     */
    function count_products_needing_ai(int $tenant_id, ?string $category = null): array {
        if ($tenant_id <= 0) return ['bg' => 0, 'desc' => 0, 'magic' => 0];

        $base   = "FROM products WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL";
        $params = [$tenant_id];
        if ($category !== null && $category !== '') {
            $base    .= " AND ai_category = ?";
            $params[] = $category;
        }

        $bg = (int)DB::run(
            "SELECT COUNT(*) $base AND (image_url IS NULL OR image_url = '' OR image_url LIKE 'data:%')",
            $params
        )->fetchColumn();

        $desc = (int)DB::run(
            "SELECT COUNT(*) $base AND (description IS NULL OR description = '') AND (ai_description IS NULL OR ai_description = '')",
            $params
        )->fetchColumn();

        $magic = (int)DB::run(
            "SELECT COUNT(*) $base AND (ai_magic_image IS NULL OR ai_magic_image = '')",
            $params
        )->fetchColumn();

        return ['bg' => $bg, 'desc' => $desc, 'magic' => $magic];
    }

    /**
     * 9) pre_flight_quality_check — call Gemini Vision (gemini-2.5-flash) for a JSON go/no-go
     *    decision before spending a real magic credit. Returns:
     *      ['ok' => bool, 'usable' => bool, 'reasons' => string[], 'http' => int, 'raw' => ?string].
     *
     *    Requires GEMINI_API_KEY in /etc/runmystore/api.env. If missing, returns ['ok' => false, ...].
     *    Network failures return usable = false so the caller falls back to "skip magic, refund flow".
     */
    function pre_flight_quality_check(string $image_url): array {
        $fallback = ['ok' => false, 'usable' => false, 'reasons' => [], 'http' => 0, 'raw' => null];
        if ($image_url === '') {
            $fallback['reasons'][] = 'empty_image_url';
            return $fallback;
        }

        $key = function_exists('rms_api_env') ? rms_api_env('GEMINI_API_KEY') : null;
        if (!$key) {
            error_log('S82.STUDIO.BACKEND: GEMINI_API_KEY missing for pre_flight_quality_check');
            $fallback['reasons'][] = 'config_missing';
            return $fallback;
        }

        $prompt = 'You are a pre-flight quality checker for an e-commerce product photo studio. ' .
                  'Decide whether the input image is suitable for AI image enhancement (background removal + hero magic). ' .
                  'Return STRICT JSON only, no prose. Schema: {"usable": boolean, "reasons": string[]} ' .
                  'Set usable=false if the image is blurry, too dark, contains people only, contains heavy text, ' .
                  'is a screenshot, has watermarks, or lacks a clear product subject. ' .
                  'Reasons must be short English tokens (e.g. "blurry", "no_subject", "watermark").';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['fileData' => ['mimeType' => 'image/jpeg', 'fileUri' => $image_url]],
                ],
            ]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.0],
        ];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($key);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_errno($ch);
        curl_close($ch);

        if ($cerr || $http !== 200 || !is_string($body)) {
            error_log("S82.STUDIO.BACKEND: pre_flight_quality_check HTTP $http cerr=$cerr");
            return ['ok' => false, 'usable' => false, 'reasons' => ['upstream_error'], 'http' => $http, 'raw' => null];
        }

        $data = json_decode($body, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $json = json_decode($text, true);
        if (!is_array($json) || !array_key_exists('usable', $json)) {
            return ['ok' => false, 'usable' => false, 'reasons' => ['malformed_response'], 'http' => $http, 'raw' => $text];
        }

        return [
            'ok'      => true,
            'usable'  => (bool)$json['usable'],
            'reasons' => is_array($json['reasons'] ?? null) ? array_values(array_slice($json['reasons'], 0, 5)) : [],
            'http'    => $http,
            'raw'     => $text,
        ];
    }

    /**
     * Helper for the spend log — single source of truth for INSERT shape, used by retry/refund flow.
     * Returns the new row id, or 0 on failure.
     */
    function rms_studio_log_spend(array $data): int {
        $cols = [
            'tenant_id'      => (int)($data['tenant_id']      ?? 0),
            'user_id'        => isset($data['user_id'])   ? (int)$data['user_id']   : null,
            'product_id'     => isset($data['product_id'])? (int)$data['product_id']: null,
            'feature'        => (string)($data['feature']     ?? ''),
            'category'       => $data['category']             ?? null,
            'model'          => $data['model']                ?? null,
            'cost_eur'       => (float)($data['cost_eur']     ?? 0),
            'status'         => (string)($data['status']      ?? 'completed_paid'),
            'parent_log_id'  => isset($data['parent_log_id']) ? (int)$data['parent_log_id'] : null,
            'attempt_number' => (int)($data['attempt_number'] ?? 1),
            'meta_json'      => isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null,
        ];

        DB::run(
            "INSERT INTO ai_spend_log
               (tenant_id, user_id, product_id, feature, category, model, cost_eur, status, parent_log_id, attempt_number, meta_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array_values($cols)
        );
        return (int)DB::lastInsertId();
    }
}
