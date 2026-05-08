# CSRF Audit — S116

**Date:** 2026-05-08
**Scope:** PHP files with `$_POST[...]` handlers in repo root (excluding `products.php`).

## Summary

**Status:** ⚠️ HIGH — **11 of 12 POST endpoints lack CSRF token verification.**

Only `aibrain-modal-actions.php` uses CSRF tokens (per-session token in `$_SESSION['aibrain_csrf']`, validated via `hash_equals()`). The remaining 11 POST endpoints rely solely on session cookies (which is exactly the vector CSRF attacks exploit).

`config/helpers.php:434-447` defines `csrfToken()` and `csrfCheck()` but they are **not used** outside the aibrain flow.

## Detailed Per-Endpoint Status

| File | POST handlers | CSRF check | Severity |
|------|---------------|------------|----------|
| `aibrain-modal-actions.php` | 10 | ✅ YES (`$_SESSION['aibrain_csrf']` + `hash_equals`) | OK |
| `chat-send.php` | 3 (JSON body) | ❌ NO | MEDIUM (JSON Content-Type partial protection via SOP) |
| `defectives.php` | 3 | ❌ NO | HIGH (mutates inventory state) |
| `delivery.php` | 10 (incl. file upload) | ❌ NO | HIGH (mutates orders, uploads files) |
| `dev-exec.php` | 1 | ❌ NO (uses hardcoded HTTP header key) | CRITICAL (see other_findings) |
| `login.php` | 3 | ❌ NO | LOW (login doesn't typically need CSRF; consider login-CSRF for SSRF prevention) |
| `mark-insight-shown.php` | 4 | ❌ NO | LOW (idempotent state mutation) |
| `order.php` | 11 | ❌ NO | HIGH (creates/cancels orders) |
| `products_fetch.php` | 10 (incl. file upload) | ❌ NO | HIGH (state mutations + uploads) |
| `register.php` | 5 | ❌ NO | MEDIUM (no auth before, but creates tenant) |
| `ai-color-detect.php` | 1 (file upload) | ❌ NO | MEDIUM |
| `ai-studio-action.php` | 5 (file upload + DB writes) | ❌ NO | HIGH |

## Reproduction (HIGH severity — order.php)

A logged-in tenant visits an attacker-controlled site with:

```html
<form id="x" method="POST" action="https://app.runmystore.ai/order.php" enctype="multipart/form-data">
  <input name="action" value="cancel">
  <input name="order_id" value="42">
</form>
<script>document.getElementById('x').submit()</script>
```

→ Order #42 is canceled without user consent. Same vector applies to defectives, delivery, products_fetch.

For `chat-send.php` the body is JSON (`Content-Type: application/json`), which non-`text/plain`/`form-encoded` fetch from the browser triggers a CORS preflight — providing some protection. However, this can be bypassed:
- If `Access-Control-Allow-Origin: *` is set anywhere → preflight passes.
- An XSS elsewhere in the app can call the endpoint same-origin.

## Recommendation

### Immediate (within 1 sprint)

For all 11 missing-CSRF endpoints, adopt the existing `csrfToken()` / `csrfCheck()` pattern from `config/helpers.php`:

**Server-side (top of every state-mutating endpoint):**
```php
require_once __DIR__ . '/config/helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'csrf']);
        exit;
    }
}
```

**Client-side (single global fetch wrapper):**
```js
window.csrfToken = '<?= htmlspecialchars(csrfToken()) ?>';
async function rmsPost(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': window.csrfToken, 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
}
```

### Defense in depth

1. **SameSite=Lax (or Strict) on session cookie:** add `session_set_cookie_params(['samesite'=>'Lax', ...])` before `session_start()`. This blocks cross-origin POST cookie attachment in modern browsers and renders most CSRF moot.
2. **Origin/Referer checking:** as fallback for browsers that don't support SameSite.
3. **Login CSRF:** `register.php` should also generate a token (defense vs login-CSRF / pre-auth state pollution).

### Effort estimate
- Per-endpoint integration: ~30 minutes per file × 11 = 5-6 hours
- Global SameSite cookie setting: 10 minutes (one line in shell-init.php)
- JS wrapper migration: 2 hours (find all `fetch(` POST calls)

## Severity Summary

- CRITICAL: **0** (no destructive endpoints fully unprotected — all require auth)
- HIGH: **5** (order, products_fetch, delivery, defectives, ai-studio-action)
- MEDIUM: **3** (chat-send JSON-protected, ai-color-detect, register)
- LOW: **3** (login, mark-insight-shown, dev-exec auth-by-key)
