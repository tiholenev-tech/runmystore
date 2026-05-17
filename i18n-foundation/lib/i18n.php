<?php
/**
 * /lib/i18n.php
 * 
 * I18n engine за RunMyWallet + RunMyStore.AI
 * 
 * Usage:
 *   I18n::setLocale($tenant['locale']);
 *   echo t('home.greeting_morning', ['name' => $user['name']]);
 *   echo t('tax.vat_threshold_approaching', ['pct' => 70, 'threshold' => '51 130 €', 'remaining' => '15 339 €']);
 * 
 * Fallback chain:
 *   bg-BG → bg → en → key (returned verbatim)
 * 
 * Load order:
 *   1. Try DB ui_translations override (cached в Redis/APCu)
 *   2. Try /lang/{lang}.json file
 *   3. Try /lang/en.json fallback
 *   4. Return key verbatim (so missing strings are visible)
 */

class I18n {
    private static array $strings = [];
    private static array $db_overrides = [];
    private static string $locale = 'bg-BG';
    private static string $lang = 'bg';
    private static string $base_dir = __DIR__ . '/../lang';
    private static ?PDO $pdo = null;
    
    /**
     * Initialize from tenant context
     */
    public static function init(array $tenant, ?PDO $pdo = null): void {
        self::$pdo = $pdo;
        self::setLocale($tenant['locale'] ?? 'bg-BG');
    }
    
    public static function setLocale(string $locale): void {
        self::$locale = $locale;
        self::$lang = substr($locale, 0, 2);
        self::loadLang(self::$lang);
        
        // Fallback EN винаги loaded
        if (self::$lang !== 'en') self::loadLang('en');
    }
    
    public static function getLocale(): string {
        return self::$locale;
    }
    
    public static function getLang(): string {
        return self::$lang;
    }
    
    private static function loadLang(string $lang): void {
        if (isset(self::$strings[$lang])) return;
        
        $path = self::$base_dir . "/{$lang}.json";
        if (file_exists($path)) {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw, true);
            self::$strings[$lang] = $decoded ?: [];
        } else {
            self::$strings[$lang] = [];
        }
    }
    
    /**
     * Translate key with optional variable replacement
     * 
     * @param string $key Translation key (e.g. 'home.greeting_morning')
     * @param array $vars Variables to interpolate (e.g. ['name' => 'Стефан'])
     * @return string Translated string with vars replaced, or key if missing
     */
    public static function trans(string $key, array $vars = []): string {
        // 1. Check DB override (tenant-specific or global)
        if (isset(self::$db_overrides[self::$lang][$key])) {
            $value = self::$db_overrides[self::$lang][$key];
        }
        // 2. Check loaded lang file
        elseif (isset(self::$strings[self::$lang][$key])) {
            $value = self::$strings[self::$lang][$key];
        }
        // 3. Fallback EN
        elseif (isset(self::$strings['en'][$key])) {
            $value = self::$strings['en'][$key];
        }
        // 4. Return key verbatim (visible for debugging)
        else {
            $value = $key;
            self::logMissing($key);
        }
        
        // Variable interpolation: {name} → value
        foreach ($vars as $k => $v) {
            $value = str_replace('{' . $k . '}', (string)$v, $value);
        }
        
        return $value;
    }
    
    /**
     * Pluralization helper
     * t.plural('records.count', $count, ['count' => $count])
     * JSON: {"records.count": {"zero":"Няма записи","one":"1 запис","other":"{count} записа"}}
     */
    public static function plural(string $key, int $count, array $vars = []): string {
        $forms = self::$strings[self::$lang][$key] 
              ?? self::$strings['en'][$key] 
              ?? null;
        
        if (!is_array($forms)) return self::trans($key, array_merge($vars, ['count' => $count]));
        
        $form = match(true) {
            $count === 0 => $forms['zero'] ?? $forms['other'] ?? $key,
            $count === 1 => $forms['one'] ?? $forms['other'] ?? $key,
            default => $forms['other'] ?? $key,
        };
        
        foreach (array_merge($vars, ['count' => $count]) as $k => $v) {
            $form = str_replace('{' . $k . '}', (string)$v, $form);
        }
        return $form;
    }
    
    /**
     * Load DB overrides (cache for 5 min)
     */
    public static function loadDbOverrides(): void {
        if (!self::$pdo) return;
        
        $cache_file = sys_get_temp_dir() . '/i18n_db_' . self::$lang . '.cache';
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 300) {
            self::$db_overrides[self::$lang] = unserialize(file_get_contents($cache_file));
            return;
        }
        
        $stmt = self::$pdo->prepare("SELECT key_name, value FROM ui_translations WHERE locale = ?");
        $stmt->execute([self::$locale]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        self::$db_overrides[self::$lang] = $rows;
        file_put_contents($cache_file, serialize($rows));
    }
    
    private static function logMissing(string $key): void {
        $log = sys_get_temp_dir() . '/i18n_missing.log';
        $line = date('Y-m-d H:i:s') . " [{$key}] locale=" . self::$locale . "\n";
        @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Negotiate best locale from HTTP Accept-Language header
     */
    public static function negotiate(string $accept_header, array $supported, string $fallback = 'en-US'): string {
        if (empty($accept_header)) return $fallback;
        
        // Parse "bg-BG,bg;q=0.9,en-US;q=0.8,en;q=0.7"
        $entries = [];
        foreach (explode(',', $accept_header) as $entry) {
            $parts = explode(';', trim($entry));
            $tag = trim($parts[0]);
            $q = 1.0;
            if (isset($parts[1]) && preg_match('/q=([\d.]+)/', $parts[1], $m)) {
                $q = (float)$m[1];
            }
            $entries[] = ['tag' => $tag, 'q' => $q];
        }
        usort($entries, fn($a, $b) => $b['q'] <=> $a['q']);
        
        foreach ($entries as $e) {
            // Exact match
            if (in_array($e['tag'], $supported, true)) return $e['tag'];
            // Language prefix match
            $lang = substr($e['tag'], 0, 2);
            foreach ($supported as $sup) {
                if (substr($sup, 0, 2) === $lang) return $sup;
            }
        }
        return $fallback;
    }
}

/**
 * Global shortcut function
 * 
 * Use навсякъде в кода:
 *   echo t('home.greeting');
 *   echo t('user.welcome', ['name' => $name]);
 */
function t(string $key, array $vars = []): string {
    return I18n::trans($key, $vars);
}

function tp(string $key, int $count, array $vars = []): string {
    return I18n::plural($key, $count, $vars);
}
