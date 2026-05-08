# 📋 S118.CSRF_DRAFT — Final Handoff

**Date:** 2026-05-08
**Session:** S118 (DRAFT-only, NO production touch)
**Source:** `/tmp/sec_audit/03_csrf_findings.md` (S116 audit)
**Status:** ✅ All Phase 0-3 deliverables complete.

---

## Executive summary

Out of **12 POST endpoints** in repo root, only `aibrain-modal-actions.php` carries
CSRF protection. The remaining 11 (or 10 — `dev-exec.php` already QUARANTINED) accept
state-mutating POSTs based solely on session cookies, so any logged-in user visiting
an attacker-controlled site can be forced to perform arbitrary actions on their own
behalf (cancel orders, commit deliveries, drain magic credits, etc.).

S118 produces **patch packages** that apply the existing `csrfCheck()` /
`csrfToken()` helpers (`config/helpers.php:434-447`) — proven in production by
`products.php:52` and `aibrain-modal-actions.php`. Pattern is one ~5-line server
guard at the top of each file plus 1-3 client-side touchpoints (form hidden input
or fetch header). **No schema changes. No new dependencies.**

**No production files were modified during S118.** All artifacts live in `/tmp/csrf_fix/`.

---

## Per-endpoint patch index

| # | Patch file                                | Target file                    | Severity | Diff size  | Risk if unfixed |
|---|-------------------------------------------|--------------------------------|----------|------------|-----------------|
| 0 | `00_PATTERN.md`                           | (reference doc)                | —        | —          | —               |
| 1 | `01_order_csrf.patch.php`                 | `order.php`                    | HIGH     | +12 server, ~3 caller sites | Order create/cancel/send forced via cross-origin POST |
| 2 | `02_products_fetch_csrf.patch.php`        | `products_fetch.php`           | HIGH     | +13 server, ~10 caller sites | Adds suppliers/categories/AI uploads forced; same exposure as products.php pre-fix |
| 3 | `03_delivery_csrf.patch.php`              | `delivery.php`                 | HIGH     | +12 server, ~6 caller sites | OCR upload + commit-to-inventory forced; can corrupt stock counts |
| 4 | `04_defectives_csrf.patch.php`            | `defectives.php`               | HIGH     | +12 server, 2 caller sites | Mass return-to-supplier or write-off forced; financial impact |
| 5 | `05_ai-studio-action_csrf.patch.php`      | `ai-studio-action.php`         | HIGH     | +13 server, ~5 caller sites | Drains magic credits or fakes refunds; financial impact |
| 6 | `06_chat-send_csrf.patch.php`             | `chat-send.php`                | MEDIUM   | +9 server (header-only), ~6 caller sites | Forces AI prompts on victim's account; data exfiltration via prompt injection |
| 7 | `07_ai-color-detect_csrf.patch.php`       | `ai-color-detect.php`          | MEDIUM   | +9 server, 1-2 caller sites | Drains vision credits |
| 8 | `08_register_csrf.patch.php`              | `register.php`                 | MEDIUM   | +6 server, 1 form input | Pre-auth state pollution / forced registration on victim's email |
| 9 | `09_login_csrf.patch.php`                 | `login.php`                    | LOW      | +24 server (incl. else-branch restructure), 1 form input | Login-CSRF (attacker logs victim into attacker's account) |
|10 | `10_mark-insight-shown_csrf.patch.php`    | `mark-insight-shown.php`       | LOW      | +7 server, 1 caller site | Bookkeeping pollution (low impact; idempotent) |
|11 | `11_dev-exec_csrf.patch.php`              | `dev-exec.php`                 | —        | NO-OP (file is QUARANTINED) | None (route already unreachable; see audit row) |

**Totals (excluding patch 11 no-op and patch 0 reference):**
- 10 server-side guards × ~10 lines each ≈ **~100 server lines added**
- ~37 client-side fetch callsites need 1-2 lines each ≈ **~50-70 client lines edited**
- 2 form hidden inputs (login + register) ≈ **2 lines added**

---

## Why the existing helper is the right tool

`config/helpers.php` already exposes:
```php
function csrfToken(): string;     // S97.HARDEN.PH4 — per-session in $_SESSION['app_csrf']
function csrfCheck(string $token): bool;
```

This is the SAME helper that already protects `products.php` (line 52).
`aibrain-modal-actions.php` uses a parallel session key (`$_SESSION['aibrain_csrf']`)
for historical reasons; we leave it alone (separate scope) and converge new patches
on `app_csrf`.

**Why not a single shell-init.php-level guard?** Endpoints have different bootstrap
orders (some include helpers.php late, some never include it, some need to render
HTML first). A per-file 5-line guard is simpler than retrofitting all entry points
to use a shared bootstrap.

---

## Apply procedure (recommended order)

**Estimated total time: ~2 hours apply + ~1 hour smoke testing = 3 hours.**

### Stage 0 — Prep
```bash
cd /var/www/runmystore
TS=$(date +%Y%m%d-%H%M)
mkdir -p backups/s118_${TS}
# Backup ALL 11 files (even patch 11 target, for audit trail)
for f in order.php products_fetch.php delivery.php defectives.php \
         ai-studio-action.php chat-send.php ai-color-detect.php \
         register.php login.php mark-insight-shown.php; do
    [ -f "$f" ] && cp -p "$f" "backups/s118_${TS}/${f}.session-start"
done
ls -la backups/s118_${TS}/
```

### Stage 1 — Apply patches in batches (low → high severity, smoke after each)

**Batch A: HIGH (5 files, ~45 min):**
1. Apply `01_order_csrf.patch.php` to `order.php` — follow APPLY INSTRUCTIONS in the patch.
2. Apply `02_products_fetch_csrf.patch.php` to `products_fetch.php`.
3. Apply `03_delivery_csrf.patch.php` to `delivery.php`.
4. Apply `04_defectives_csrf.patch.php` to `defectives.php`.
5. Apply `05_ai-studio-action_csrf.patch.php` to `ai-studio-action.php`.

After each: `php -l <file>` MUST pass. If syntax error → restore from backup, retry.

**Batch B: MEDIUM (3 files, ~25 min):**
6. Apply `06_chat-send_csrf.patch.php`.
7. Apply `07_ai-color-detect_csrf.patch.php`.
8. Apply `08_register_csrf.patch.php`.

**Batch C: LOW (2 files, ~15 min):**
9. Apply `09_login_csrf.patch.php`.
10. Apply `10_mark-insight-shown_csrf.patch.php`.

**Batch D: dev-exec.php cleanup**
11. Update `sec_audit/03_csrf_findings.md` — change dev-exec.php row to "✓ QUARANTINED".
    Optionally `git rm dev-exec.php.QUARANTINED` after the 30-day retention window.

### Stage 2 — Caller-side patches (in chat.php / products.php etc.)

For each fetch() callsite that targets a patched endpoint:
- Add `'X-CSRF-Token': window.RMS_CSRF || ''` to headers.
- For multipart `FormData` uploads, ALSO append `_csrf` to the form data.

**Pre-flight grep:**
```bash
# Find all fetch() POSTs targeting our 10 patched endpoints:
grep -rnE "fetch\(['\"][^'\"]*(order|products_fetch|delivery|defectives|ai-studio-action|chat-send|ai-color-detect|register|login|mark-insight-shown)\.php" \
     --include='*.php' --include='*.js' /var/www/runmystore/ | grep -v 'mockups\|backups\|/\.git/'
```

Expected match count: ~30-50 callsites across products.php, chat.php, life-board.php, etc.

### Stage 3 — Smoke test (1 hour)

Run `/tmp/csrf_fix/TEST_PLAN.md` checks:
1. **Curl-driven blackbox** (`smoke_all.sh` at end of TEST_PLAN.md) — verifies all 10
   endpoints return 403 on no-token POST.
2. **Browser UI smoke** — open chat.php, products.php, exercise:
   - Chat send (text + voice)
   - AI scan / color detect / studio
   - Order create/cancel
   - Delivery OCR upload + commit
   - Defectives return-all
   - Insight pill tap
   - Login + Register (incognito)

### Stage 4 — Commit

Per file (recommended: 11 small commits, easier to bisect if regression appears):
```bash
git add order.php
git commit -m "S118.CSRF: order.php — guard 6 POST routes with csrfCheck()"
# repeat for each file
```

OR a single sweep commit:
```bash
git add order.php products_fetch.php delivery.php defectives.php \
        ai-studio-action.php chat-send.php ai-color-detect.php \
        register.php login.php mark-insight-shown.php
git commit -m "S118.CSRF: 10 POST endpoints — adopt csrfCheck() pattern from products.php"
```

---

## Defense-in-depth follow-ups (S119+)

Per S116 §80-83 — NOT included in S118 base patches:

1. **`SameSite=Lax` (or `Strict`) on session cookie** — single-line change in
   `partials/shell-init.php` BEFORE any `session_start()`:
   ```php
   session_set_cookie_params([
       'lifetime' => 0,
       'path' => '/',
       'domain' => '',
       'secure' => true,
       'httponly' => true,
       'samesite' => 'Lax',
   ]);
   ```
   This blocks cross-origin POST cookie attachment in modern browsers and renders
   most CSRF moot — the per-file guards become belt-and-suspenders.

2. **Origin/Referer fallback** — for browsers without SameSite support
   (older Android WebViews). Pattern is in `aibrain-modal-actions.php:67-73`.

3. **Token rotation after successful mutation** — regenerate `$_SESSION['app_csrf']`
   after every successful POST (per S116 §61). Adds churn to client state — defer.

4. **`session_regenerate_id(true)` after login** — see `09_login_csrf.patch.php` §end.
   Defends against session fixation. Single line in login flow.

5. **Add a CI check** — pre-commit hook that fails if a new `.php` file in repo root
   handles `$_POST` without including `helpers.php` and calling `csrfCheck()`.
   Pattern:
   ```bash
   # .git/hooks/pre-commit additions:
   for f in $(git diff --cached --name-only | grep -E '^[^/]+\.php$'); do
       if grep -q '\$_POST' "$f" && ! grep -q 'csrfCheck' "$f"; then
           echo "❌ $f handles \$_POST but doesn't call csrfCheck() — see /tmp/csrf_fix/00_PATTERN.md"
           exit 1
       fi
   done
   ```

---

## Out of scope (do NOT touch in S118)

- ❌ `products.php` — Code 1 owns; already protected via line 52.
- ❌ `aibrain-modal-actions.php` — already protected (`$_SESSION['aibrain_csrf']`).
- ❌ Schema migrations — pure code patches, no DB changes.
- ❌ `dev-exec.php` resurrection — file is QUARANTINED for valid reasons (S116 §22).
- ❌ Cross-origin policy / CORS headers — separate concern (S116 §47).
- ❌ Rate limiting — separate concern.

---

## Files in `/tmp/csrf_fix/`

```
00_PATTERN.md                              — reference pattern (server + client + form)
01_order_csrf.patch.php                    — HIGH
02_products_fetch_csrf.patch.php           — HIGH
03_delivery_csrf.patch.php                 — HIGH
04_defectives_csrf.patch.php               — HIGH
05_ai-studio-action_csrf.patch.php         — HIGH
06_chat-send_csrf.patch.php                — MEDIUM (JSON body, header-only)
07_ai-color-detect_csrf.patch.php          — MEDIUM
08_register_csrf.patch.php                 — MEDIUM
09_login_csrf.patch.php                    — LOW (login-CSRF defense)
10_mark-insight-shown_csrf.patch.php       — LOW
11_dev-exec_csrf.patch.php                 — N/A (file QUARANTINED)
TEST_PLAN.md                               — per-endpoint repro + verify + regression
HANDOFF.md                                 — this file
```

---

## DOD verification

| Item | Target | Actual |
|------|--------|--------|
| 11 patch files in `/tmp/csrf_fix/` | 11 | 11 ✅ (1 no-op for QUARANTINED) |
| `TEST_PLAN.md` | yes | yes ✅ |
| `HANDOFF.md` (this file) | yes | yes ✅ |
| ZERO git ops | yes | yes ✅ |
| ZERO production .php touched | yes | yes ✅ |
| Time budget | ≤ 3h | ~1h (within budget) |

---

**End of S118 deliverables.** Waiting for Тихол to:
1. Review patches.
2. Apply when window allows (recommend pre-ENI-launch).
3. Commit + push.
4. Confirm via `TEST_PLAN.md` smoke checks.

If approval requires changes — say so and I'll iterate the patches in `/tmp/csrf_fix/`
(still no production touch).
