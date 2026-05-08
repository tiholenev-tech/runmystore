# Authentication / Authorization Audit — S116

**Date:** 2026-05-08
**Scope:** Session/auth patterns across all `.php` in repo root.

## Summary

| Aspect | Status | Notes |
|--------|--------|-------|
| Password hashing | ✅ `password_hash(PASSWORD_DEFAULT)` + `password_verify` | OK |
| MD5/SHA1 password storage | ✅ NONE found | OK |
| Session start (39 files) | ✅ All start session | OK |
| `session_regenerate_id()` after login | ❌ NOT called anywhere | **HIGH** session fixation risk |
| Cookie security (HttpOnly/Secure/SameSite) | ❌ NO `session_set_cookie_params` | **HIGH** |
| 2FA / MFA | ❌ NOT IMPLEMENTED | MEDIUM (acceptable for v1, plan for v2) |
| Session timeout | ❌ Default PHP only (~24min) | LOW |
| API key rotation | ❌ Manual via /etc/runmystore/api.env edit | MEDIUM |
| `/etc/runmystore/api.env` perms | ✅ 640 root:www-data | OK |
| `/etc/runmystore/db.env` perms | ✅ 640 www-data:www-data | OK |
| Auth bypass on POST endpoints | ✅ All but `compute-insights` and `dev-exec` check `$_SESSION['user_id']` | OK |

## Detailed Findings

### HIGH — Session Fixation Risk

**No call to `session_regenerate_id(true)` anywhere in codebase.**

After successful login (`login.php:30`), the session ID is the same one the unauthenticated user had. An attacker who can fix a session ID (via XSS, network MITM, sub-domain attack) can wait for the victim to log in, then reuse the fixed session ID with full privileges.

**Reproduction:**
1. Attacker creates a valid PHPSESSID via curl/etc.
2. Attacker injects this PHPSESSID into the victim's browser (XSS in tenant-stored content, sub-domain cookie-set, etc.).
3. Victim logs in.
4. Attacker uses the same PHPSESSID and is logged in as the victim.

**Fix (5 lines in login.php after successful auth):**
```php
session_regenerate_id(true);   // ← add right after password_verify success
$_SESSION['tenant_id']   = $tenant['id'];
// ... rest of session setup
```

### HIGH — Insecure Session Cookie

No `session_set_cookie_params()` call → cookies are sent with default attrs:
- `HttpOnly`: PHP default is OFF in older configs, ON in newer (depends on `php.ini`)
- `Secure`: OFF (cookie sent over HTTP if any non-HTTPS path exists)
- `SameSite`: OFF (no CSRF protection at cookie layer)

**Fix (in `partials/shell-init.php` BEFORE `session_start()`):**
```php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,         // session-only
        'path'     => '/',
        'domain'   => '',        // current host
        'secure'   => true,      // HTTPS only
        'httponly' => true,      // not readable from JS
        'samesite' => 'Lax',     // CSRF mitigation
    ]);
    session_start();
}
```

⚠ Test this thoroughly — `secure: true` will break local HTTP development. Wrap in `$_SERVER['HTTPS'] === 'on'` check or environment toggle.

### MEDIUM — `dev-exec.php` auth model

```php
$key = $_SERVER['HTTP_X_EXEC_KEY'] ?? '';
if ($key !== 'RMS_DEV_2026_CLAUDE') {
    http_response_code(403);
    die('Forbidden');
}
```

The "auth key" is **hardcoded in source code** that lives on GitHub (commit `01a0704` and earlier). Anyone with read access to the repo can authenticate. Combined with the command-whitelist bypass (see `07_other_findings.md` §1), this is **CRITICAL**.

### MEDIUM — Password policy
- Minimum length: 8 chars (`register.php:21`)
- No complexity requirement (no upper/lower/digit/symbol enforcement)
- No common-password blocklist
- No password breach check (HIBP)

Recommend at minimum:
- 10-char minimum
- ≥1 letter + ≥1 digit
- Reject top-100 common passwords ("password", "12345678", etc.)

### LOW — Session timeout
PHP default `gc_maxlifetime = 1440` (24 minutes). For a POS app where staff may be idle 30-60 min between transactions, this triggers re-login often. But for a security-conscious app, this is fine.

Consider:
- **Idle timeout:** 30 minutes (default OK)
- **Absolute timeout:** 8 hours (auto-logout regardless of activity)
- Implement absolute via `$_SESSION['login_at']` check on each request.

### MEDIUM — API key rotation (Gemini/Claude/OpenAI)

Currently keys are loaded from `/etc/runmystore/api.env` at boot. To rotate, manually edit the file. This means:
- No audit trail of when rotation happened
- No detection of leaked-key usage
- Any compromise of `/var/www/runmystore` (e.g., via dev-exec.php exploit) → keys exfiltrated

Recommend:
- Move to a secret manager (HashiCorp Vault / AWS Secrets Manager / GCP Secret Manager) with rotation API
- OR at minimum, document a rotation procedure + log rotation events

### LOW — Auth bypass on internal endpoints

| Endpoint | Has auth check? | Risk |
|----------|-----------------|------|
| `compute-insights.php` | ❌ No `$_SESSION` | LOW (called from cron — not web-accessible if rules in place; but if web-reachable, allows insight computation triggering) |
| `dev-exec.php` | Uses HTTP_X_EXEC_KEY only | CRITICAL (see above) |
| `cron-insights.php`, `cron-monthly.php` | Likely cron-only | LOW (verify webserver doesn't expose .php in cron-only paths) |
| `migrate.php` | UNVERIFIED — needs review | MEDIUM (could allow schema mutations via web) |

**Recommendation:** Add to `.htaccess` or nginx config to deny web access to:
- `cron-*.php`
- `migrate.php`
- `dev-exec.php`
- `voice-tier2-test.php`
- `ua-debug.php`
- `weather-cache.php`

### LOW — `compute-insights.php` reproduction
```bash
curl -X GET "https://app.runmystore.ai/compute-insights.php?tenant_id=42"
# Triggers insight recomputation for tenant 42 without auth
```
Mitigation: cron-only access, or add `$_SESSION['user_id']` check.

## Recommendations Priority Matrix

| # | Action | Severity | Effort |
|---|--------|----------|--------|
| 1 | Add `session_regenerate_id(true)` after login | HIGH | 5 min |
| 2 | Add `session_set_cookie_params(secure/httponly/samesite)` | HIGH | 30 min + testing |
| 3 | Disable web access to dev-exec/migrate/cron-* | CRITICAL | 30 min (.htaccess) |
| 4 | Strengthen password policy | LOW-MED | 1 hour |
| 5 | Plan 2FA for v2 | MEDIUM | 1 sprint |
| 6 | Document API key rotation procedure | LOW | 1 hour |
