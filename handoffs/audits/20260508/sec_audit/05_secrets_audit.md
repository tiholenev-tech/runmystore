# Secrets / Credentials Audit — S116

**Date:** 2026-05-08
**Scope:** Hardcoded API keys, DB credentials, tokens, dev keys.

## Summary

| Type | Status |
|------|--------|
| DB credentials in source | ✅ NONE — read from `/etc/runmystore/db.env` |
| API keys (Gemini/Claude/OpenAI) in source | ✅ NONE — read from `/etc/runmystore/api.env` |
| /etc/runmystore/* file permissions | ✅ 640, restricted to web user |
| Hardcoded "dev" keys in source | ❌ **CRITICAL** — `RMS_DEV_2026_CLAUDE` in dev-exec.php:4 |
| `.env` files in repo | ✅ NONE in repo (only `/etc/runmystore/*.env` outside repo) |
| `.env.example` file | ✅ Present (`./env.example`) — placeholder values only, OK |

## Detailed Findings

### CRITICAL — `dev-exec.php:4` — Hardcoded auth token

```php
$key = $_SERVER['HTTP_X_EXEC_KEY'] ?? '';
if ($key !== 'RMS_DEV_2026_CLAUDE') {
    http_response_code(403);
    die('Forbidden');
}
```

This token is committed to the repository. Anyone with GitHub repo read access (or anyone who clones the repo while it's public, or anyone in commit history forever) has the authentication token.

Combined with the command-whitelist bypass and the fact that `cat`, `mysql`, `git` are whitelisted, this allows:
1. `cat /etc/runmystore/db.env` → DB credentials
2. `cat /etc/runmystore/api.env` → all API keys
3. `mysql -uroot -e "SELECT * FROM users"` (if MySQL local-no-password is set)

**Reproduction:**
```bash
curl -X POST "https://app.runmystore.ai/dev-exec.php" \
  -H "X-EXEC-KEY: RMS_DEV_2026_CLAUDE" \
  -d "cmd=cat /etc/runmystore/api.env"

# → returns full API key contents
```

**Severity rating:** CRITICAL — full data exfiltration possible.

**Fixes (in priority order):**

1. **Immediate:** delete `dev-exec.php` from production. Rebuild the file locally in dev, never commit.
   ```bash
   rm dev-exec.php
   ```

2. **Better:** put dev-exec behind nginx/apache `Allow from 127.0.0.1` only:
   ```apache
   <Files "dev-exec.php">
       Order Deny,Allow
       Deny from all
       Allow from 127.0.0.1
   </Files>
   ```

3. **Best (if dev-exec is still needed):** auth via SSH-tunneled local-only socket, not via web.

4. **Rotate all secrets** because we must assume `RMS_DEV_2026_CLAUDE` was leaked:
   - Generate new Gemini API key (revoke current GEMINI_API_KEY + GEMINI_API_KEY_2 in Google Cloud Console)
   - Generate new Claude API key (revoke current in Anthropic Console)
   - Generate new OpenAI API key (revoke current in OpenAI Platform)
   - Rotate DB password
   - Update `/etc/runmystore/api.env` and `/etc/runmystore/db.env`
   - `systemctl restart php-fpm` (or whatever process holds the keys)

5. **Add `dev-exec.php` to `.htaccess` deny rules** even if not deleted, as belt-and-suspenders.

### MEDIUM — `chat-send.php` references API model names

`chat-send.php` and `config/config.php` reference model names (`gemini-2.5-flash`, `gpt-4o-mini`) — these are NOT secrets but they reveal the AI architecture. Acceptable disclosure for typical web app, but if this is sensitive IP, move to env vars.

### LOW — `printer-setup.php` BLE characteristics

If printer-setup.php contains hardcoded device IDs or pairing keys, these could enable malicious printer attacks. **Not scanned in this pass — flag for follow-up.**

## /etc/runmystore/*.env Verification

```
640 root:www-data /etc/runmystore/api.env
640 www-data:www-data /etc/runmystore/db.env
```

- Both files: 640 (rw for owner, r for group, no other) ✓
- api.env: owned by root, readable by www-data group ✓
- db.env: owned by www-data, readable by www-data only ✓

These permissions are correct. Even if a low-privilege user (e.g., `tihol`) gains shell access, they cannot read these files (assuming `tihol` is not in `www-data` group — verify with `groups tihol`).

## Recommendations

| # | Action | Severity | Effort |
|---|--------|----------|--------|
| 1 | Remove or web-deny `dev-exec.php` | **CRITICAL** | 5 minutes |
| 2 | Rotate all API keys (assume leaked) | **CRITICAL** | 1 hour (key generation + restart) |
| 3 | Rotate DB password | **CRITICAL** | 30 min |
| 4 | Audit `groups www-data` and `groups tihol` to ensure no shared group access to `/etc/runmystore/*.env` | LOW | 5 min |
| 5 | Add explicit `.gitignore` rule for `*.env` (it should already cover this — verify) | LOW | 1 min |
