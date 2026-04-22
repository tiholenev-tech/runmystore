<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);

define('COMPUTE_INSIGHTS_NO_CLI', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/compute-insights.php';

$start = microtime(true);
$job_name = 'compute_insights_15min';
$status = 'ok';
$error_msg = null;
$total_inserted = 0;
$tenants_processed = 0;
$tenants_failed = 0;

try {
    $tenants = DB::run(
        "SELECT id, name, plan, trial_ends_at
         FROM tenants
         WHERE is_active=1
           AND (plan IN ('start','pro') OR (trial_ends_at IS NOT NULL AND trial_ends_at > NOW()))
         ORDER BY id"
    )->fetchAll();

    $stamp = date('Y-m-d H:i:s');
    fwrite(STDOUT, "[$stamp] cron-insights: " . count($tenants) . " active tenants
");

    foreach ($tenants as $t) {
        $tid = (int)$t['id'];
        $tname = (string)$t['name'];
        $t_start = microtime(true);
        try {
            $result = computeProductInsights($tid);
            $inserted = 0;
            if (is_array($result)) {
                foreach ($result as $info) {
                    if (is_array($info) && isset($info['count'])) {
                        $inserted += (int)$info['count'];
                    }
                }
            }
            $total_inserted += $inserted;
            $tenants_processed++;
            $elapsed = (int)((microtime(true) - $t_start) * 1000);
            fwrite(STDOUT, "  tenant " . $tid . " (" . $tname . "): " . $inserted . " insights, " . $elapsed . "ms
");
        } catch (Throwable $e) {
            $tenants_failed++;
            error_log("cron-insights: tenant $tid failed: " . $e->getMessage());
            fwrite(STDERR, "  ERROR tenant $tid: " . $e->getMessage() . "
");
            try {
                DB::run(
                    "INSERT INTO audit_log
                     (tenant_id, user_id, store_id, table_name, record_id, action, source, source_detail, old_values, new_values, ip_address, user_agent, created_at)
                     VALUES (?, NULL, NULL, 'ai_insights', 0, 'cron_run', 'cron', ?, NULL, ?, NULL, NULL, NOW())",
                    [$tid, 'compute_insights_error', json_encode(['error' => $e->getMessage()])]
                );
            } catch (Throwable $_) {}
        }
    }
} catch (Throwable $e) {
    $status = 'error';
    $error_msg = $e->getMessage();
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "
");
}

$duration_ms = (int)((microtime(true) - $start) * 1000);

try {
    DB::run(
        "INSERT INTO cron_heartbeats
           (job_name, last_run_at, last_status, last_error, last_duration_ms, expected_interval_minutes)
         VALUES (?, NOW(), ?, ?, ?, 15)
         ON DUPLICATE KEY UPDATE
           last_run_at=NOW(), last_status=VALUES(last_status),
           last_error=VALUES(last_error), last_duration_ms=VALUES(last_duration_ms)",
        [$job_name, $status, $error_msg, $duration_ms]
    );
} catch (Throwable $e) {
    error_log("cron-insights: heartbeat write failed: " . $e->getMessage());
}

try {
    // audit_log.tenant_id is NOT NULL and has FK to tenants.id — use first tenant or skip if none
    $first_tenant = DB::run("SELECT id FROM tenants WHERE is_active=1 ORDER BY id LIMIT 1")->fetch();
    $audit_tid = $first_tenant ? (int)$first_tenant['id'] : 0;
    if ($audit_tid > 0) {
        DB::run(
            "INSERT INTO audit_log
             (tenant_id, user_id, store_id, table_name, record_id, action, source, source_detail, old_values, new_values, ip_address, user_agent, created_at)
             VALUES (?, NULL, NULL, 'ai_insights', 0, 'cron_run', 'cron', ?, NULL, ?, NULL, NULL, NOW())",
            [
                $audit_tid,
                'compute_insights_15min_summary',
                json_encode([
                    'status' => $status,
                    'duration_ms' => $duration_ms,
                    'tenants_processed' => $tenants_processed,
                    'tenants_failed' => $tenants_failed,
                    'total_inserted' => $total_inserted,
                ], JSON_UNESCAPED_UNICODE)
            ]
        );
    }
} catch (Throwable $e) {
    error_log("cron-insights: summary audit failed: " . $e->getMessage());
}

$stamp2 = date('Y-m-d H:i:s');
fwrite(STDOUT, "[$stamp2] cron-insights DONE: $status, {$duration_ms}ms, {$tenants_processed}/{$tenants_failed} tenants, $total_inserted insights
");
exit($status === 'ok' ? 0 : 1);
