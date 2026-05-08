# Security Audit — S116 HANDOFF

**Date:** 2026-05-08
**Auditor:** Code Code 1 (Opus, automated SAST)
**Duration:** ~50 minutes (well under 4h cap)
**Method:** Static-only scan; **ZERO production exploit attempts**, **ZERO git operations**.

## Top 10 Findings By Severity

| # | Severity | Finding | File:line | Detail |
|---|----------|---------|-----------|--------|
| 1 | **CRITICAL** | `dev-exec.php` RCE via hardcoded key + str_starts_with bypass | `dev-exec.php:1-22` | Auth key in source; `git ; whoami` bypasses whitelist; full server compromise possible. **Delete or web-deny immediately.** [details: 07_other §1] |
| 2 | **CRITICAL** | All API keys must be rotated | `/etc/runmystore/api.env` | Assume `RMS_DEV_2026_CLAUDE` was used to exfil. Rotate Gemini × 2, Claude, OpenAI, DB password. [details: 05_secrets] |
| 3 | **HIGH** | Session fixation: no `session_regenerate_id` after login | `login.php:30` | Attacker who fixes session ID gains victim's privileges post-login. **5-line fix.** [details: 04_auth] |
| 4 | **HIGH** | Insecure session cookie (no HttpOnly/Secure/SameSite) | All files via PHP defaults | No `session_set_cookie_params()` call. **Lax SameSite would also block ~80% of CSRF.** [details: 04_auth] |
| 5 | **HIGH** | 11 of 12 POST endpoints lack CSRF tokens | order.php, products_fetch.php, delivery.php, defectives.php, ai-studio-action.php, etc. | `csrfCheck()` exists in helpers but is unused outside aibrain. [details: 03_csrf] |
| 6 | **HIGH** | `delivery.php:128` OCR upload — no MIME or extension validation | `delivery.php:128-160` | Trusts client-supplied `$_FILES['file']['type']`. Polyglot file → potential RCE. [details: 06_uploads §1] |
| 7 | **HIGH** | Test/debug endpoints web-accessible | dev-exec, migrate, voice-tier2-test, ua-debug, cron-* | All should be blocked by .htaccess rule. [details: 07_other §8] |
| 8 | **MEDIUM** | Stored XSS via tenant-controlled fields (`$page_title`, `$lang`, `$cs`) | sale.php:478, chat.php:469, life-board.php:1335 | Wrap with `htmlspecialchars()`. ~30 files affected. [details: 02_xss] |
| 9 | **MEDIUM** | `inventory.php` upload uses extension-only validation; `rand()` for filenames | `inventory.php:25` | MIME check + `random_bytes()` for filename uniqueness. [details: 06_uploads §2] |
| 10 | **MEDIUM** | No 2FA / MFA implementation | All auth | Acceptable for v1, must plan for v2 (especially as multi-tenant SaaS). [details: 04_auth] |

## What Was Scanned

- 56 PHP files in `/var/www/runmystore/` repo root
- 7 partials in `partials/`
- `config/` (database, config, helpers, i18n_aibrain, ai_topics, woo)
- File permissions of `/etc/runmystore/*.env`
- 12 SAST categories: SQL injection, XSS, CSRF, auth bypass, file upload, path traversal, eval/exec/system, weak hashing, hardcoded secrets, open redirect, insecure cookies, mass assignment.

`products.php` was excluded per scope rule (Code 2-owned scratch zone).

## What Was NOT Scanned (Out of Scope)

- `partials/header.php`, `partials/bottom-nav.php`, etc. — included in scan but findings same as parent shell context
- `mockups/`, `docs/archived/`, `tools/` — excluded
- JavaScript-side XSS (DOM-based, e.g., `innerHTML = userInput`) — would require separate JS audit
- Apache/nginx configuration security headers (HSTS, CSP, X-Frame-Options) — out of scope
- Network-layer (TLS cert validity, HSTS preload, mixed content) — out of scope
- Penetration testing of running server — explicitly forbidden by audit charter

## Files in /tmp/sec_audit/

| File | Topic | Findings |
|------|-------|----------|
| `01_sql_injection.md` | SQL injection | 0 (clean — prepared statements throughout) |
| `02_xss_findings.md` | XSS | ~12 medium (stored XSS via tenant data) |
| `03_csrf_findings.md` | CSRF | 11 endpoints unprotected |
| `04_auth_findings.md` | Auth/session | session fixation + insecure cookies + no 2FA |
| `05_secrets_audit.md` | Hardcoded secrets | 1 critical (dev-exec key) + recommend rotation of all API keys |
| `06_uploads_audit.md` | File uploads | 1 high (delivery.php) + 2 medium |
| `07_other_findings.md` | eval/path/redirect/cookies/mass-assign | 1 critical (dev-exec RCE) + several test endpoints exposed |
| `HANDOFF.md` (this file) | Executive summary | Top 10 |

**Total findings:** 25+ (≥20 DOD requirement met)

## Reproduction Steps for CRITICAL items

### Item #1 — dev-exec.php RCE

```bash
# Test 1: confirm the endpoint is reachable and key works
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=ls /var/www/runmystore"

# Test 2: confirm whitelist bypass via command chaining
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=git --version; whoami; id"

# Test 3: read sensitive files (cat is whitelisted)
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  --data-urlencode "cmd=cat /etc/runmystore/api.env"
```

⚠ DO NOT RUN THESE in production without authorization. Use a staging environment.

### Item #2 — Rotate API keys

```bash
# Generate new keys in respective consoles, then:
sudo nano /etc/runmystore/api.env
# Replace old keys with new ones, save.
sudo systemctl reload php-fpm  # or apache2/nginx
# Verify by hitting an AI-using endpoint like chat-send.php
```

### Item #3 — Session fixation fix

In `login.php`, after successful `password_verify()`:
```php
session_regenerate_id(true);   // ← INSERT THIS LINE
$_SESSION['tenant_id']   = $tenant['id'];
// ... rest of session setup
```

## Recommended Action Plan

### Today (CRITICAL — < 1 hour)
1. Web-deny `dev-exec.php` (5 min — `.htaccess` rule)
2. Rotate all API keys (Gemini × 2, Claude, OpenAI, DB) (1 hour)
3. Add `session_regenerate_id(true)` to login.php (5 min)

### This Sprint (HIGH — < 1 week)
4. Add `session_set_cookie_params(secure/httponly/samesite=Lax)` (30 min + testing)
5. Add CSRF tokens to 11 unprotected POST endpoints (5 hours)
6. Fix `delivery.php` OCR upload validation (1 hour)
7. Add `.htaccess` deny rules for dev/test endpoints (30 min)
8. Verify `uploads/*` directories cannot execute PHP (30 min — test with .jpg.php)

### Next Sprint (MEDIUM — < 1 month)
9. Wrap tenant-stored variables with `htmlspecialchars()` (2 hours)
10. Plan & spec 2FA implementation (1 day spec, then dev sprint)
11. Document API key rotation procedure (1 hour)
12. Implement password complexity requirements (2 hours)

## DOD Verification

| Criterion | Status |
|-----------|--------|
| 8 markdown files in /tmp/sec_audit/ | ✅ 8 (01-07 + HANDOFF) |
| ≥20 specific findings categorized by severity | ✅ 25+ |
| Reproduction steps for every CRITICAL | ✅ items #1, #2, #3 |
| ZERO production exploits attempted | ✅ static scan only |
| ZERO git operations | ✅ confirmed |
| Time ≤ 4h | ✅ ~50 min |

**Status:** ✅ COMPLETE. Hand off to Tihol for prioritization + Code 2 (or whoever owns hardening) for implementation.
