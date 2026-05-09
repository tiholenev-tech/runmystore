<?php
/**
 * VISUAL GATE router — used as the "router script" of `php -S`.
 *   php -S 127.0.0.1:8765 -t <doc_root> design-kit/visual-gate-router.php
 *
 * Behaviour:
 *  1. If VG_AUTH=1, requires auth-fixture.php FIRST so that $_SESSION is
 *     primed before the target script runs session_start().
 *  2. Resolves the request URI against DOCUMENT_ROOT and delegates to the
 *     PHP built-in server's native file handling (return false) when the
 *     file exists. Static assets and .php files are served as usual.
 *  3. Returns 404 plain text otherwise.
 *
 * SAFETY: cli-server only. The auth fixture itself enforces this too.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(500);
    exit('visual-gate-router: refused (cli-server only)');
}

if (getenv('VG_AUTH') === '1') {
    require __DIR__ . '/auth-fixture.php';
}

// S136 PHASE A — VG_DB_NAME override. When set, force DB class to point at
// the sandbox schema instead of the production DB_NAME from db.env. Loaded
// here so the override is in place before the target script's first DB call.
//
// Mechanism: require_once config/database.php (declares DB class, no PDO yet
// because PDO is lazy in DB::get()), then poke private static DB::$config via
// reflection. Subsequent DB::get() in target code uses the overridden config.
if (getenv('VG_DB_NAME')) {
    $config_php = dirname(__DIR__) . '/config/database.php';
    if (is_file($config_php)) {
        require_once $config_php;
        if (class_exists('DB')) {
            $env = @parse_ini_file('/etc/runmystore/db.env');
            if ($env && isset($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'])) {
                try {
                    $r = new ReflectionClass('DB');
                    $p = $r->getProperty('config');
                    $p->setAccessible(true);
                    $p->setValue(null, [
                        'host'    => $env['DB_HOST'],
                        'dbname'  => getenv('VG_DB_NAME'),
                        'user'    => $env['DB_USER'],
                        'pass'    => $env['DB_PASS'],
                        'charset' => 'utf8mb4',
                    ]);
                    error_log('visual-gate-router: DB::$config overridden to dbname=' . getenv('VG_DB_NAME'));
                } catch (Throwable $e) {
                    error_log('visual-gate-router: VG_DB_NAME override failed: ' . $e->getMessage());
                }
            }
        }
    }
}

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uri;

if (is_file($path)) {
    // .php → require it in this request so $_SESSION primed by auth-fixture
    // remains visible. For static assets, defer to the built-in server.
    if (substr($path, -4) === '.php') {
        $_SERVER['SCRIPT_FILENAME'] = $path;
        $_SERVER['SCRIPT_NAME']     = $uri;
        $_SERVER['PHP_SELF']        = $uri;
        chdir(dirname($path));
        require $path;
        return true;
    }
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 Not Found: {$uri}\n";
