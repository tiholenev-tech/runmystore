<?php
/**
 * ai-image-credits.php — Plan-aware AI image quota helper (S82.AI_STUDIO)
 * FREE = 0/day, START = 3/day, PRO = 10/day. Per (tenant, day, operation).
 */

if (!function_exists('rms_image_plan_limit')) {
    /**
     * @param string $plan free/start/pro
     * @return int max operations per day (any of bg_remove + color_detect + tryon)
     */
    function rms_image_plan_limit(string $plan): int {
        switch (strtolower($plan)) {
            case 'pro':   return 10;
            case 'start': return 3;
            default:      return 0; // FREE
        }
    }

    /**
     * Returns ['plan'=>..., 'limit'=>int, 'used'=>int, 'remaining'=>int, 'allowed'=>bool, 'reason'=>?string]
     */
    function rms_image_check_quota(int $tenant_id): array {
        if ($tenant_id <= 0) {
            return ['plan'=>'free','limit'=>0,'used'=>0,'remaining'=>0,'allowed'=>false,'reason'=>'Не сте влезли.'];
        }
        // S82.COLOR.7: god-mode tenants — unlimited AI image quota for testing.
        // Listed in /etc/runmystore/api.env as RMS_IMAGE_GOD_TENANTS=7,42,99 (csv).
        $god_csv = (string)(rms_api_env('RMS_IMAGE_GOD_TENANTS') ?? '7');
        $god_ids = array_filter(array_map('intval', explode(',', $god_csv)));
        if (in_array($tenant_id, $god_ids, true)) {
            return ['plan'=>'god','limit'=>999999,'used'=>0,'remaining'=>999999,'allowed'=>true,'reason'=>null];
        }
        $row = DB::run("SELECT plan FROM tenants WHERE id = ?", [$tenant_id])->fetch();
        $plan = strtolower($row['plan'] ?? 'free');
        $limit = rms_image_plan_limit($plan);

        $used = (int)DB::run(
            "SELECT COALESCE(SUM(count), 0) FROM ai_image_usage WHERE tenant_id = ? AND day = CURDATE()",
            [$tenant_id]
        )->fetchColumn();

        $remaining = max(0, $limit - $used);
        $allowed = $remaining > 0;
        $reason = null;
        if ($limit === 0) {
            $reason = 'AI Studio изисква START или PRO план.';
        } elseif (!$allowed) {
            $reason = "Дневен лимит изчерпан ({$used}/{$limit}). Утре пак.";
        }

        return [
            'plan'      => $plan,
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => $remaining,
            'allowed'   => $allowed,
            'reason'    => $reason,
        ];
    }

    /**
     * Atomically increment usage. Call ONLY after successful AI operation.
     */
    function rms_image_record_usage(int $tenant_id, ?int $user_id, string $operation): void {
        // S82.COLOR.7: god-mode tenants don't pollute the usage table.
        $god_csv = (string)(rms_api_env('RMS_IMAGE_GOD_TENANTS') ?? '7');
        $god_ids = array_filter(array_map('intval', explode(',', $god_csv)));
        if (in_array($tenant_id, $god_ids, true)) return;
        DB::run(
            "INSERT INTO ai_image_usage (tenant_id, user_id, operation, day, count)
             VALUES (?, ?, ?, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE count = count + 1, user_id = VALUES(user_id), last_at = CURRENT_TIMESTAMP",
            [$tenant_id, $user_id, $operation]
        );
    }

    /**
     * Reads /etc/runmystore/api.env (trusted INI). Returns key value or null.
     */
    function rms_api_env(string $key): ?string {
        static $cache = null;
        if ($cache === null) {
            $path = '/etc/runmystore/api.env';
            $cache = is_readable($path) ? (parse_ini_file($path) ?: []) : [];
        }
        return isset($cache[$key]) && $cache[$key] !== '' ? (string)$cache[$key] : null;
    }
}
