<?php
/**
 * RunMyStore.ai — cron-insights.php
 * S52 | 12.04.2026
 * 
 * Cron job: на всеки 15 минути.
 * Crontab: */15 * * * * php /var/www/runmystore/cron-insights.php >> /var/log/runmystore-insights.log 2>&1
 * 
 * Обхожда всички активни tenants и техните stores.
 * За всеки store извиква computeAllInsights() от compute-insights.php.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/compute-insights.php';

$startTime = microtime(true);
$tenantsProcessed = 0;
$storesProcessed = 0;

echo "[" . date('Y-m-d H:i:s') . "] cron-insights START\n";

try {
    // Всички активни tenants
    $tenants = DB::run(
        "SELECT id, currency FROM tenants WHERE is_active=1"
    )->fetchAll();
    
    foreach ($tenants as $tenant) {
        $tid = $tenant['id'];
        $currency = $tenant['currency'] ?: 'EUR';
        
        // Всички stores на този tenant
        $stores = DB::run(
            "SELECT id FROM stores WHERE tenant_id=?",
            [$tid]
        )->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($stores as $sid) {
            try {
                computeAllInsights($tid, $sid, $currency);
                $storesProcessed++;
            } catch (\Exception $e) {
                echo "  ERROR tenant=$tid store=$sid: " . $e->getMessage() . "\n";
            }
        }
        
        $tenantsProcessed++;
    }
    
} catch (\Exception $e) {
    echo "  FATAL: " . $e->getMessage() . "\n";
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] cron-insights DONE — $tenantsProcessed tenants, $storesProcessed stores, {$elapsed}s\n\n";
