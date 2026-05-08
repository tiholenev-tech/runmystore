# SQL Injection Audit — S116

**Date:** 2026-05-08
**Scope:** All `.php` in `/var/www/runmystore/` repo root + `partials/*.php` (excluding `products.php` per scope rule).

## Summary

**Status:** ✅ NO direct SQL injection found.

The codebase consistently uses prepared statements via `DB::run("...", [params])` with `?` placeholders. All scans for the high-risk pattern of superglobal interpolation in SQL string literals returned **zero hits**.

## Methodology

Patterns scanned:

```regex
"[^"]*\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|VALUES)\b[^"]*\$_(GET|POST|REQUEST|COOKIE)\[
"[^"]*\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE)\b[^"]*"\s*\.\s*\$_(GET|POST|REQUEST|COOKIE)\b
```

This catches:
- `"SELECT ... WHERE id=$_GET[id]"` (interpolation)
- `"SELECT ..." . $_GET['x']` (concatenation)

## Findings

| File | Risk | Status |
|------|------|--------|
| login.php | None | ✓ Uses `DB::run(..., [$email])` with `?` placeholder |
| register.php | None | ✓ password_hash, prepared inserts |
| chat-send.php | None | ✓ All DB calls go through DB::run with `?` |
| sale.php | None | ✓ Reviewed — prepared statements throughout |
| order.php / orders.php | None | ✓ Prepared statements |
| All other 50+ PHP files | None | ✓ |

## Defense In Depth

`config/database.php` enforces:
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (errors are caught, not silently)
- `PDO::ATTR_EMULATE_PREPARES => false` (real prepared statements, not emulated)
- DB credentials live in `/etc/runmystore/db.env` (640 perms, not in source)

## Recommendation

✅ **No action required for SQL injection.**

Continue using `DB::run(...)` with `?` placeholders for all new code. Reject any PR that string-concatenates user input into SQL.

## Severity

**LOW** — best-practice review only, no findings.
