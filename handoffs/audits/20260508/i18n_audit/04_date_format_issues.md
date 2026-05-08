# Date / Number / Phone Format Issues — S117

**Date:** 2026-05-08
**Scope:** All `.php` in repo root (excluding products.php, biz-coefficients.php).

## Summary

| Format type | Hardcoded hits | Status |
|-------------|---------------:|--------|
| Date format `d.m.Y`, `d.m`, `Y-m-d`, etc. | 60+ | HIGH risk for non-BG locales |
| Number format (decimal/thousands separator) | many `number_format(..., 2, '.', ',')` calls | MEDIUM |
| Phone number format | ❓ Not scanned (low priority) | LOW |
| Address format | ❓ Not scanned (low priority) | LOW |
| Time format (12h vs 24h) | All 24h (`H:i`) | LOW (24h universally acceptable) |

## Date Format Findings

### Hot files (date format hardcoded)

| File | Hits | Sample |
|------|-----:|--------|
| chat.php | 13 | `date('Y-m-d')` (line 167), `date('Y-m-d', strtotime('-1 day'))` (line 169) |
| xchat.php | 13 | similar |
| stats.php | 6 | `date('Y-m-d', strtotime('monday this week'))` (line 24) |
| deliveries.php | 6 | `date('d.m', strtotime((string)$d['payment_due_date']))` (line 166) |
| build-prompt.php | 5 | `$today_str = date('d.m.Y')` (line 193) |
| delivery.php | 4 | `date('d.m', strtotime(...))` (line 698, 713) |
| selection-engine.php | 3 | (not inspected line-by-line) |
| life-board.php | 3 | `date('Y-m-d')` (line 49) |
| weather-cache.php | 2 | (cache key timestamps) |
| cron-insights.php | 2 | (cron logs) |
| defectives.php | (~3) | `date('d.m', strtotime(...))` (line 286) |
| order.php | (~2) | (order date displays) |
| orders.php | (~2) | `date('d.m', strtotime((string)$o['created_at']))` (line 255) |

### Two distinct format families used

| Format | Use case | BG OK? | EN-friendly? | RO? | SR? |
|--------|----------|--------|--------------|-----|-----|
| `d.m.Y` (08.05.2026) | Display | ✓ | ✗ | ✓ | ✓ |
| `d.m` (08.05) | Short display | ✓ | ✗ | ✓ | ✓ |
| `Y-m-d` (2026-05-08) | DB queries, internal | ✓ | ✓ | ✓ | ✓ (universal ISO) |
| `H:i` (15:30) | Time | ✓ | ✓ (some prefer 12h) | ✓ | ✓ |

### Risk by category

- `Y-m-d` for DB/internal: ✅ correct, do not change
- `d.m.Y` / `d.m` for display: ❌ Bulgarian convention; for EN must be `m/d/Y` or `M j, Y`; for RO `d.m.Y` works; for SR `d.m.Y` works.

### Recommendation: Locale-aware date helper

**In `config/helpers.php`:**

```php
function dateFormat(string|int|null $date, array $tenant, string $variant = 'short'): string {
    if ($date === null) return '—';
    $ts = is_int($date) ? $date : strtotime((string)$date);
    if (!$ts) return '—';

    $lang = $tenant['lang'] ?? 'bg';

    $patterns = [
        'short' => match($lang) {
            'en'    => 'M j',           // May 8
            'bg', 'ro', 'sr' => 'd.m',  // 08.05
            default => 'd.m',
        },
        'medium' => match($lang) {
            'en'    => 'M j, Y',        // May 8, 2026
            'bg', 'ro', 'sr' => 'd.m.Y',
            default => 'd.m.Y',
        },
        'long' => match($lang) {
            'en'    => 'l, F j, Y',     // Friday, May 8, 2026
            'bg'    => 'l, j F Y',      // петък, 8 май 2026 (with localized month names)
            default => 'l, j F Y',
        },
        'iso' => 'Y-m-d',
        'time' => 'H:i',
        'datetime' => match($lang) {
            'en'    => 'M j, Y H:i',
            default => 'd.m.Y H:i',
        },
    ];

    return date($patterns[$variant] ?? 'Y-m-d', $ts);
}
```

Usage:
```php
<?= dateFormat($order['created_at'], $tenant, 'short') ?>   // 08.05 (BG) / May 8 (EN)
<?= dateFormat($invoice['due_date'], $tenant, 'medium') ?>  // 08.05.2026 / May 8, 2026
<?= dateFormat(time(), $tenant, 'datetime') ?>              // 08.05.2026 15:30 / May 8, 2026 15:30
```

## Number Format Findings

| File | Pattern | Notes |
|------|---------|-------|
| Many | `number_format($v, 2)` (no separators specified — uses C locale: `.` decimal, `,` thousands) | Works for EN; wrong for BG/RO/SR (which use `,` decimal, ` ` thousands) |
| sale.php | JS `priceFormat()` (line 2410) | Defines its own — hopefully locale-aware |
| stats.php | `number_format(round($profit_today), 0, '.', ' ')` | uses ` ` thousands — BG-correct |
| Other | mixed | inconsistent |

**Recommendation:** Add `numberFormat($v, $tenant, $decimals = 0)` helper similar to dateFormat, or make priceFormat handle this implicitly.

## Phone Format

Not deeply scanned this pass. Likely:
- Bulgarian `+359 88 1234567` style
- Should be e.164-stored, locale-formatted on display

**Recommendation for future S118 i18n pass:** Scan for phone-related fields and verify libphonenumber-style handling.

## Address Format

Not scanned. Likely free-text or Bulgaria-specific multiline. Tenant-stored, low immediate risk.

## Recommendation Priority

| # | Action | Severity | Effort |
|---|--------|----------|--------|
| 1 | Add `dateFormat()` PHP helper | HIGH | 1 hour |
| 2 | Migrate display date calls to `dateFormat($x, $tenant, 'short|medium')` | HIGH | 4 hours grep-replace |
| 3 | Add `numberFormat()` / extend `priceFormat()` to handle non-currency numbers | MEDIUM | 1 hour |
| 4 | Audit phone/address handling | LOW | future S118 |
| 5 | Localize month/day names (Friday=петък etc.) — use `IntlDateFormatter` | MEDIUM | 2 hours |
