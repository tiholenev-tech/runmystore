# Hardcoded Currency Audit — S117

**Date:** 2026-05-08
**Scope:** All `.php` in repo root (excluding `products.php`, `biz-coefficients.php`).

## Summary

| Status | Count |
|--------|------:|
| Hardcoded `'лв'` / `'BGN'` / `'€'` literals | 13 hits across 5 files |
| `priceFormat()` PHP helper | ❌ DOES NOT EXIST (only as JS in sale.php:2410) |
| `priceFormat()` JS helper | ✅ exists in sale.php:2410 |
| Tenant currency from DB | ✅ `tenants.currency` column exists, used in many files |

## Critical Finding: No PHP `priceFormat()` Helper

The Bible/coding rules mandate `priceFormat($amount, $tenant)` for every monetary display. **No such PHP function exists.** Each file either:

1. Uses `$cs = htmlspecialchars($tenant['currency'] ?? 'лв')` then echoes `<?= $amount ?> <?= $cs ?>`
2. Or hardcodes the symbol (`лв`, `€`)
3. Or uses `number_format($amount, 2)` without symbol

This means:
- Each file invents its own currency formatting (decimal separator, group separator, position of symbol)
- Bulgarian-fallback `'лв'` repeated as default in many places
- Cannot easily switch all to "€" for EU markets — must edit dozens of files

## Hardcoded Currency Findings

| File | Line | Code | Risk | Fix |
|------|-----:|------|------|-----|
| `chat.php` | 48 | `$store_name = $store['name'] ?? 'Магазин';` | LOW (default fallback) | Use `t('common.default_store_name')` |
| `sale.php` | 29 | `$currency = htmlspecialchars($tenant['currency'] ?? 'лв');` | MEDIUM | Tenant fallback should be EUR for EU launch; or no fallback (require currency to be set) |
| `inventory.php` | 16 | `$currency=htmlspecialchars($tenant['currency']??'лв');` | MEDIUM | Same as above |
| `ai-studio.php` | 500 | `<span class="so-price">€<?= number_format($bulk_bg_cost, 2) ?></span>` | HIGH | Hardcoded `€` — should use tenant currency or pricing.app_currency |
| `ai-studio.php` | 516 | `<span class="so-price">€<?= number_format($bulk_desc_cost, 2) ?></span>` | HIGH | Same |
| `ai-studio.php` | 675 | `alert('Купи кредити — модалът идва в STUDIO.17 (3 пакета €5/€15/€40).');` | LOW (placeholder copy) | Move to t() once translation framework lands |
| `products_fetch.php` | 3365 | `<div class="art-prc">120 лв</div>` | LOW (mock/preview data) | Verify if mock — replace with real price + tenant currency |
| `products_fetch.php` | 3372 | `<div class="art-prc">89 лв</div>` | LOW (mock) | Same |
| `products_fetch.php` | 3379 | `<div class="art-prc">24 лв</div>` | LOW (mock) | Same |
| `build-prompt.php` | 1064-1073 | BG-only system prompts mentioning `лв` | LOW (LLM context, not user-facing) | Internationalize per-tenant locale |
| `deliveries.php` | (1 hit) | currency inline | LOW | |
| `defectives.php` | (1 hit) | currency inline | LOW | |

## priceFormat — Missing PHP Helper

**Recommended PHP helper (place in `config/helpers.php`):**

```php
/**
 * Format an amount with the tenant's currency.
 * Locale-aware: uses bg-BG / en-US / ro-RO / sr-RS based on $tenant['lang'].
 * Returns escape-safe string ready to echo into HTML.
 */
function priceFormat(float $amount, array $tenant, array $opts = []): string {
    $lang = $tenant['lang'] ?? 'bg';
    $currency = $tenant['currency'] ?? 'EUR';

    $decimals = $opts['decimals'] ?? 2;
    $symbol_position = $opts['symbol_position'] ?? null;  // 'before' | 'after' | auto

    // Locale-driven defaults
    [$dec_sep, $thou_sep] = match($lang) {
        'bg', 'ro', 'sr' => [',', ' '],   // 1 234,56
        'en'             => ['.', ','],   // 1,234.56
        default          => ['.', ' '],
    };

    // Symbol: USD / EUR / BGN handled
    [$display_sym, $default_pos] = match(strtoupper($currency)) {
        'EUR' => ['€', 'after'],
        'BGN' => ['лв', 'after'],
        'RON' => ['lei', 'after'],
        'RSD' => ['дин', 'after'],
        'USD' => ['$', 'before'],
        'GBP' => ['£', 'before'],
        default => [htmlspecialchars($currency), 'after'],
    };

    $pos = $symbol_position ?? $default_pos;
    $num = number_format($amount, $decimals, $dec_sep, $thou_sep);

    return $pos === 'before'
        ? $display_sym . $num
        : $num . ' ' . $display_sym;
}
```

Usage:
```php
<?= priceFormat($product['retail_price'], $tenant) ?>
```

## Recommendation

| # | Action | Severity | Effort |
|---|--------|----------|--------|
| 1 | Add `priceFormat()` PHP helper to config/helpers.php | HIGH | 1 hour |
| 2 | Replace `<?= $amount ?> <?= $cs ?>` with `<?= priceFormat($amount, $tenant) ?>` | HIGH | 2-3 hours grep-and-replace |
| 3 | Fix hardcoded `€` in ai-studio.php:500, 516 | HIGH | 5 min |
| 4 | Verify products_fetch.php:3365-3379 are mock data (or fix) | MEDIUM | 15 min |
| 5 | Make `$tenant['currency']` REQUIRED, not defaulted to `'лв'` | MEDIUM | 30 min (DB migration) |

## Severity Distribution

- HIGH: 4 (ai-studio hardcoded €, missing PHP priceFormat)
- MEDIUM: 4 (default fallback `'лв'` literals)
- LOW: 5 (mock data, internal LLM prompts)
