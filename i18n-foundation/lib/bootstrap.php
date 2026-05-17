<?php
/**
 * /lib/bootstrap.php
 * 
 * Include в началото на всяка страница (след DB connect, преди render):
 * 
 *   require_once '/lib/bootstrap.php';
 *   $tenant = bootstrapTenant($pdo, $tenant_id);
 *   // Sega:
 *   echo t('home.greeting_morning', ['name' => $tenant['name']]);
 *   echo Locale::priceFormat(1437.50, $tenant);
 *   echo Locale::dateFormat('2026-05-17', $tenant);
 */

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/locale.php';
require_once __DIR__ . '/../tax/TaxEngine.php';

/**
 * Load tenant with locale context and bootstrap I18n.
 * Returns tenant array enriched with locale defaults.
 */
function bootstrapTenant(PDO $pdo, int $tenant_id): array {
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        throw new RuntimeException("Tenant {$tenant_id} not found");
    }
    
    // Fallback defaults ако колоните липсват (преди migration)
    $tenant['locale'] = $tenant['locale'] ?? 'bg-BG';
    $tenant['country_code'] = $tenant['country_code'] ?? 'BG';
    $tenant['currency'] = $tenant['currency'] ?? 'EUR';
    $tenant['timezone'] = $tenant['timezone'] ?? 'Europe/Sofia';
    $tenant['tax_jurisdiction'] = $tenant['tax_jurisdiction'] ?? 'BG';
    
    // Bootstrap I18n with this tenant's locale
    I18n::init($tenant, $pdo);
    
    // Optional: load DB ui_translations overrides
    // I18n::loadDbOverrides();
    
    return $tenant;
}

/**
 * Get tax engine for current tenant (lazy load)
 */
function taxEngine(array $tenant, PDO $pdo): TaxEngineInterface {
    static $cache = [];
    $key = $tenant['id'] ?? 'anon';
    if (!isset($cache[$key])) {
        $cache[$key] = TaxEngineFactory::for($tenant, $pdo);
    }
    return $cache[$key];
}

/**
 * Get active country list for signup dropdown
 */
function getActiveCountries(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT country_code, name_en, currency, default_locale, default_timezone
        FROM country_config WHERE active = TRUE 
        ORDER BY name_en
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Setup tenant locale on signup (use detectFromHeader + user choice)
 */
function setupTenantLocale(PDO $pdo, int $tenant_id, ?string $locale = null): void {
    if (!$locale) {
        $detected = Locale::detectFromHeader($pdo);
        $locale = $detected['locale'];
        $country = $detected['country_code'];
        $currency = $detected['currency'];
        $timezone = $detected['timezone'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM country_config WHERE default_locale = ?");
        $stmt->execute([$locale]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        $country = $config['country_code'] ?? 'BG';
        $currency = $config['currency'] ?? 'EUR';
        $timezone = $config['default_timezone'] ?? 'Europe/Sofia';
    }
    
    $stmt = $pdo->prepare("
        UPDATE tenants 
        SET locale = ?, country_code = ?, currency = ?, timezone = ?, tax_jurisdiction = ?
        WHERE id = ?
    ");
    $stmt->execute([$locale, $country, $currency, $country, $tenant_id]);
}
