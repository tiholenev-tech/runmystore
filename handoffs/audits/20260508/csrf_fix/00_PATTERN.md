# S118 — Standard CSRF Pattern (reference for all 11 patches)

**Source:** `config/helpers.php:434-447` (already in production, used by `aibrain-modal-actions.php` and `products.php`).

**API:**
```php
function csrfToken(): string;     // Mints (or reuses) $_SESSION['app_csrf'], returns hex string.
function csrfCheck(string $token): bool;   // hash_equals against $_SESSION['app_csrf'].
```

---

## Server-side (5-line guard at top of every state-mutating endpoint)

Insert IMMEDIATELY after `session_start();` + `require_once 'config/helpers.php';` and BEFORE
any `$_POST` consumption / DB writes.

```php
// S118.CSRF — reject cross-origin POST without valid token.
// Token sources (in order): X-CSRF-Token header (XHR/fetch), $_POST['_csrf'] (HTML form).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $__csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!csrfCheck($__csrf)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
}
```

**Note:** Some files (e.g. `chat-send.php`) read JSON via `php://input` — `$_POST['_csrf']` is
empty there, so callers MUST send the `X-CSRF-Token` header. Audit recommendation §50.

---

## Client-side (HTML forms)

Inside every `<form method="POST">`:

```php
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
```

(Requires `csrfToken()` to have been called BEFORE the form renders — usually via the
`require_once` at top, since `csrfToken()` mints lazily.)

---

## Client-side (JS fetch)

Expose token to JS once per page (in `<head>` or before main JS):

```php
<script>window.RMS_CSRF = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
```

Then use header in every fetch:

```js
fetch('/order.php?api=cancel', {
    method: 'POST',
    headers: { 'X-CSRF-Token': window.RMS_CSRF || '' },
    body: fd
});
```

---

## Login-CSRF (special case)

`login.php` and `register.php` are pre-auth — `csrfToken()` returns empty if no session.
Wrap with explicit `session_start()` + `csrfToken()` BEFORE form render:

```php
session_start();
require_once __DIR__ . '/config/helpers.php';
$__login_csrf = csrfToken();   // forces session creation + token mint
```

Then in the form: `<input type="hidden" name="_csrf" value="<?= htmlspecialchars($__login_csrf, ENT_QUOTES) ?>">`.
Server-side guard SAME as above.

**Why login needs CSRF:** prevents login-CSRF (attacker logs victim into attacker's
account → harvests data the victim enters thinking it's their own session). Also
protects against pre-auth state pollution.

---

## Failure mode contract

Every guard returns:
- HTTP **403**
- `Content-Type: application/json; charset=utf-8`
- Body: `{"ok": false, "error": "csrf"}`

Frontend should detect 403 + `error=csrf` and trigger a "session expired — refresh page"
flow (regenerate token, retry once).

---

## Token regeneration (defense-in-depth, OPTIONAL S119)

Per S116 §3 advice, regenerate token after any successful state mutation:

```php
// At end of successful POST handler:
$_SESSION['app_csrf'] = bin2hex(random_bytes(32));
```

NOT included in S118 base patches — adds churn to client-side state. Defer to S119.

---

## Out-of-scope for S118

- `dev-exec.php` — file is already QUARANTINED (`dev-exec.php.QUARANTINED`); no patch needed.
  Audit §22 should be updated to reflect quarantine.
- `products.php` (Code 1 owns) — already has CSRF check at line 52, untouched.
- `aibrain-modal-actions.php` — already protected via `$_SESSION['aibrain_csrf']`; leave as-is
  (separate scope, voice flow).

---

## Effort estimate

- Per patch (5 server lines + 1-3 client touchpoints): ~5-10 minutes apply + smoke test
- Total 11 patches × ~10 min = **~2h apply effort**, 0 schema changes, 0 dependency adds
