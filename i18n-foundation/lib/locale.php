<?php
/**
 * /lib/locale.php
 * 
 * Locale-aware formatters за всичко: currency, numbers, dates, times
 * 
 * ВАЖНО: Уизползва PHP intl extension (вградена в PHP 8.3 от standard build)
 * 
 * Usage:
 *   Locale::priceFormat(1437.50, $tenant)
 *     → bg-BG: "1437,50 €"
 *     → en-US: "$1,437.50"
 *     → ro-RO: "1.437,50 RON"
 * 
 *   Locale::dateFormat('2026-05-17', $tenant)
 *     → bg-BG: "17 май 2026 г."
 *     → en-US: "May 17, 2026"
 *     → de-DE: "17. Mai 2026"
 */

class Locale {
    
    /**
     * Format amount as currency in tenant's locale
     */
    public static function priceFormat(float $amount, array $tenant): string {
        $locale = $tenant['locale'] ?? 'bg-BG';
        $currency = $tenant['currency'] ?? 'EUR';
        
        if (!class_exists('NumberFormatter')) {
            // Fallback ако intl не е инсталиран
            return number_format($amount, 2, ',', ' ') . ' ' . $currency;
        }
        
        $fmt = numfmt_create($locale, NumberFormatter::CURRENCY);
        return numfmt_format_currency($fmt, $amount, $currency);
    }
    
    /**
     * Format amount without currency symbol (just number)
     */
    public static function numFormat(float $amount, array $tenant, int $decimals = 2): string {
        $locale = $tenant['locale'] ?? 'bg-BG';
        
        if (!class_exists('NumberFormatter')) {
            return number_format($amount, $decimals, ',', ' ');
        }
        
        $fmt = numfmt_create($locale, NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        return $fmt->format($amount);
    }
    
    /**
     * Format date in tenant's locale and timezone
     */
    public static function dateFormat($date, array $tenant, string $width = 'long'): string {
        $locale = $tenant['locale'] ?? 'bg-BG';
        $tz = $tenant['timezone'] ?? 'Europe/Sofia';
        
        $ts = is_int($date) ? $date : strtotime((string)$date);
        if (!$ts) return (string)$date;
        
        $widthMap = [
            'full' => IntlDateFormatter::FULL,
            'long' => IntlDateFormatter::LONG,
            'medium' => IntlDateFormatter::MEDIUM,
            'short' => IntlDateFormatter::SHORT,
        ];
        $w = $widthMap[$width] ?? IntlDateFormatter::LONG;
        
        if (!class_exists('IntlDateFormatter')) {
            return date('Y-m-d', $ts);
        }
        
        $fmt = datefmt_create($locale, $w, IntlDateFormatter::NONE, $tz);
        return datefmt_format($fmt, $ts);
    }
    
    public static function timeFormat($date, array $tenant, string $width = 'short'): string {
        $locale = $tenant['locale'] ?? 'bg-BG';
        $tz = $tenant['timezone'] ?? 'Europe/Sofia';
        
        $ts = is_int($date) ? $date : strtotime((string)$date);
        if (!$ts) return '';
        
        $widthMap = [
            'long' => IntlDateFormatter::LONG,
            'medium' => IntlDateFormatter::MEDIUM,
            'short' => IntlDateFormatter::SHORT,
        ];
        $w = $widthMap[$width] ?? IntlDateFormatter::SHORT;
        
        if (!class_exists('IntlDateFormatter')) {
            return date('H:i', $ts);
        }
        
        $fmt = datefmt_create($locale, IntlDateFormatter::NONE, $w, $tz);
        return datefmt_format($fmt, $ts);
    }
    
    /**
     * Combined date + time
     */
    public static function dateTimeFormat($date, array $tenant, string $width = 'medium'): string {
        return self::dateFormat($date, $tenant, $width) . ' ' . self::timeFormat($date, $tenant, $width);
    }
    
    /**
     * Get day name (Monday/Понеделник/Luni)
     */
    public static function dayName($date, array $tenant): string {
        $locale = $tenant['locale'] ?? 'bg-BG';
        $tz = $tenant['timezone'] ?? 'Europe/Sofia';
        
        $ts = is_int($date) ? $date : strtotime((string)$date);
        if (!$ts) return '';
        
        if (!class_exists('IntlDateFormatter')) {
            return date('l', $ts);
        }
        
        $fmt = datefmt_create($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $tz, IntlDateFormatter::GREGORIAN, 'EEEE');
        return datefmt_format($fmt, $ts);
    }
    
    /**
     * Relative time: "преди 5 минути" / "5 minutes ago"
     */
    public static function relativeTime($date, array $tenant): string {
        $ts = is_int($date) ? $date : strtotime((string)$date);
        if (!$ts) return '';
        
        $diff = time() - $ts;
        
        if ($diff < 60) return t('time.just_now');
        if ($diff < 3600) {
            $m = floor($diff / 60);
            return tp('time.minutes_ago', $m, ['count' => $m]);
        }
        if ($diff < 86400) {
            $h = floor($diff / 3600);
            return tp('time.hours_ago', $h, ['count' => $h]);
        }
        if ($diff < 86400 * 7) {
            $d = floor($diff / 86400);
            return tp('time.days_ago', $d, ['count' => $d]);
        }
        
        return self::dateFormat($ts, $tenant, 'medium');
    }
    
    /**
     * Get RTL flag for locale (Arabic, Hebrew, etc.)
     */
    public static function isRTL(string $locale): bool {
        $rtl_langs = ['ar', 'he', 'fa', 'ur'];
        return in_array(substr($locale, 0, 2), $rtl_langs, true);
    }
    
    /**
     * Detect locale from HTTP header (with country_config check)
     */
    public static function detectFromHeader(PDO $pdo): array {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'bg-BG';
        
        // Load active countries
        $stmt = $pdo->query("SELECT country_code, default_locale, currency, default_timezone FROM country_config WHERE active = TRUE");
        $active = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $supported_locales = array_column($active, 'default_locale');
        $negotiated = I18n::negotiate($accept, $supported_locales, 'bg-BG');
        
        // Match to country
        foreach ($active as $c) {
            if ($c['default_locale'] === $negotiated) {
                return [
                    'locale' => $c['default_locale'],
                    'country_code' => $c['country_code'],
                    'currency' => $c['currency'],
                    'timezone' => $c['default_timezone'],
                ];
            }
        }
        
        // Default BG
        return [
            'locale' => 'bg-BG',
            'country_code' => 'BG',
            'currency' => 'EUR',
            'timezone' => 'Europe/Sofia',
        ];
    }
}
