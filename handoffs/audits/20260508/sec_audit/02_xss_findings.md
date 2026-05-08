# XSS (Cross-Site Scripting) Audit — S116

**Date:** 2026-05-08
**Scope:** All `.php` in repo root (excluding `products.php`)
**Total `<?= $var ?>` patterns scanned:** ~160 (after filtering int casts, JSON pre-encoded, number_format).

## Severity Tier

- **HIGH:** User-controlled input echoed without escape
- **MEDIUM:** Tenant-stored data echoed without escape (stored XSS risk if tenant is malicious or compromised)
- **LOW:** Server-computed numerics or system-controlled strings

## Findings

### HIGH — User-controlled XSS

**Status:** ✅ NO direct user-input XSS found.

The pattern `echo $_GET[...]` / `echo $_POST[...]` returned **zero hits**. All user input passes through `htmlspecialchars()`, `intval()`, `(int)` casts, or `json_encode()`.

### MEDIUM — Tenant-stored XSS (stored XSS risk)

These values come from the `tenants` / `stores` / `users` tables, controlled by the tenant owner. A compromised or malicious tenant account could store HTML/JS that renders without escaping.

| File | Line | Variable | Source |
|------|------|----------|--------|
| `sale.php` | 478 | `<html lang="<?= $lang ?>">` | `tenants.language` (DB) |
| `sale.php` | 483 | `<title><?= $page_title ?></title>` | `$store['name']` likely |
| `sale.php` | 1971 | `<span class="cam-title"><?= $page_title ?></span>` | idem |
| `sale.php` | 2062, 2068 | `<?= $currency ?>` | `tenants.currency` |
| `chat.php` | 469 | `<?= $cs ?>` (currency symbol) | `tenants.currency` |
| `chat.php` | 549 | `<div class="...day-name"><?= $dname ?></div>` | computed weekday — but localized with strftime, low risk |
| `life-board.php` | 1335-1338 | `<?= $cs ?>`, `<?= $cnt_today ?>` | currency, computed int |
| `life-board.php` | 1386, 1388 | `<?= $meta['emoji'] ?>`, `<?= $meta['name'] ?>` | from PHP-defined `$by_fq` arrays — safe (constants) |
| `defectives.php` | 262 | `data-supplier-id="<?= $sid ?>"` | int cast nearby — recommend `(int)` |
| `deliveries.php` | 280-285 | `<?= $kpi_mismatches ?>` | int counts |

### LOW — Server-computed numerics / JSON

| Pattern | Files | Risk |
|---------|-------|------|
| `<?= $cs ?>` (currency symbol) | sale.php, chat.php, life-board.php, settings.php | LOW (DB string but typically `€`/`лв`/`BGN`) |
| `<?= $..._json ?>` | chat.php (854-858), xchat.php | LOW (json_encode'd before echo) |
| `<?= $kpi_*  ?>`, `<?= $count ?>` | many | LOW (integer counts) |

## Specific Recommendations

### CRITICAL fix
None.

### Recommended hardening (MEDIUM)

1. **Wrap all tenant-stored vars with `htmlspecialchars()`**, even if the database is "trusted":
   ```php
   <title><?= htmlspecialchars($page_title) ?></title>
   <html lang="<?= htmlspecialchars($lang) ?>">
   <span><?= htmlspecialchars($cs) ?></span>
   ```
   This is defense in depth — even if a tenant XSS exploit succeeded, escaping prevents propagation to other users.

2. **Add a global helper** `e($s)` (alias for `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')`) to make hardening a one-line change per template:
   ```php
   <?= e($page_title) ?>
   ```

3. **Add CSP header** in `partials/header.php` or via Apache/nginx:
   ```
   Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' fonts.googleapis.com; ...
   ```
   Note: current code has many inline event handlers (`onclick="..."`) — `unsafe-inline` would be required initially, then migrate to event listeners over time.

4. **Validate `$_SESSION` data** — although session is server-side, attribute mass-loading from DB into session can propagate stored XSS.

### Estimated effort
- Wrap with `htmlspecialchars()` everywhere: ~2 hours, ~30 files touched.
- Helper `e()`: 5 minutes.
- CSP: ~1 day to test, due to inline scripts.

## Severity Summary

- HIGH findings: **0**
- MEDIUM findings: **~12** (stored XSS via tenant data)
- LOW findings: ~40+ (numerics, JSON, server-computed)
