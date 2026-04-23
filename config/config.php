<?php
/**
 * S79.SECURITY — API keys се четат от /etc/runmystore/api.env
 * НИКОГА hardcoded стойности тук!
 */

$api_env_file = '/etc/runmystore/api.env';
if (!file_exists($api_env_file)) {
    error_log('S79.SECURITY: API config missing: ' . $api_env_file);
    die('API configuration not found. Contact administrator.');
}
if (!is_readable($api_env_file)) {
    error_log('S79.SECURITY: API config not readable (check ownership/permissions): ' . $api_env_file);
    die('API configuration not readable. Contact administrator.');
}

$api_env = parse_ini_file($api_env_file);
if ($api_env === false) {
    error_log('S79.SECURITY: API env file malformed: ' . $api_env_file);
    die('API configuration invalid. Contact administrator.');
}

define('GEMINI_API_KEY',   $api_env['GEMINI_API_KEY']   ?? '');
define('GEMINI_API_KEY_2', $api_env['GEMINI_API_KEY_2'] ?? '');
define('GEMINI_MODEL',     $api_env['GEMINI_MODEL']     ?? 'gemini-2.5-flash');
define('CLAUDE_API_KEY',   $api_env['CLAUDE_API_KEY']   ?? '');
define('OPENAI_API_KEY',   $api_env['OPENAI_API_KEY']   ?? '');
define('OPENAI_MODEL',     $api_env['OPENAI_MODEL']     ?? 'gpt-4o-mini');

unset($api_env, $api_env_file);
