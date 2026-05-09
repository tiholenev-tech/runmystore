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
