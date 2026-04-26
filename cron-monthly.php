<?php
/**
 * cron-monthly.php — Reset monthly AI usage counters (S82.STUDIO.BACKEND).
 *
 * Resets tenants.{bg,desc,magic}_used_this_month to 0 on the 1st of each month.
 * The included_*_per_month columns are NOT touched — they are the plan allowance,
 * separate from the monthly counter.
 *
 * NOT installed in crontab automatically — Тихол reviews + installs manually:
 *   sudo crontab -u www-data -e
 *   0 0 1 * * cd /var/www/runmystore && /usr/bin/php cron-monthly.php >> /var/log/runmystore/cron-monthly.log 2>&1
 *
 * Idempotent: running twice on the same day just sets values to 0 again — no damage.
 *
 * Manual run from CLI: php /var/www/runmystore/cron-monthly.php
 *
 * Exits non-zero on DB error so cron mail / log shows the failure.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only.';
    exit(1);
}

require_once __DIR__ . '/config/database.php';

$started = microtime(true);
$ts      = date('Y-m-d H:i:s');

try {
    $stmt = DB::run(
        "UPDATE tenants
            SET bg_used_this_month    = 0,
                desc_used_this_month  = 0,
                magic_used_this_month = 0
          WHERE bg_used_this_month + desc_used_this_month + magic_used_this_month > 0"
    );
    $affected = $stmt->rowCount();
    $elapsed  = round((microtime(true) - $started) * 1000);
    fwrite(STDOUT, "[$ts] cron-monthly OK: reset $affected tenants in {$elapsed}ms\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[$ts] cron-monthly FAIL: " . $e->getMessage() . "\n");
    exit(2);
}
