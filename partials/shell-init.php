<?php
/**
 * partials/shell-init.php — Shared shell context (S82.SHELL)
 *
 * Sets up $rms_plan, $rms_plan_label, $rms_tenant_id, $rms_user_id,
 * $rms_current_module from session + DB. Idempotent — safe to include
 * multiple times. Modules already manage their own session/auth.
 */

if (!defined('RMS_SHELL_INIT')) {
    define('RMS_SHELL_INIT', 1);

    // Session is expected to be started by the host module's auth check
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $rms_tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    $rms_user_id   = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id']   : 0;
    $rms_plan      = 'free';
    $rms_plan_label= 'FREE';

    if ($rms_tenant_id > 0 && class_exists('DB')) {
        try {
            $row = DB::run("SELECT plan FROM tenants WHERE id = ?", [$rms_tenant_id])->fetch();
            if ($row && !empty($row['plan'])) {
                $p = strtolower($row['plan']);
                if (in_array($p, ['free','start','pro'], true)) {
                    $rms_plan = $p;
                    $rms_plan_label = strtoupper($p);
                }
            }
        } catch (Throwable $e) {
            // tenants.plan may not exist on some envs — keep FREE default
        }
    }

    // Detect active module from running script for nav highlighting
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $rms_current_module = strtolower(pathinfo($script, PATHINFO_FILENAME));
}
