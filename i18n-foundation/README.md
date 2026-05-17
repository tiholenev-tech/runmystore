# i18n Foundation — Day 1 Deployment Guide

**Дата:** 17.05.2026 (S149)  
**Цел:** BG launch ден 1, готов за глобален растеж без code rewrites.

---

## 📦 Какво има

```
i18n-foundation/
├── migrations/
│   └── 001_i18n_schema.sql       ← DB migration (idempotent, безопасна)
├── lib/
│   ├── i18n.php                  ← Translation engine
│   ├── locale.php                ← Formatting (currency, dates, numbers)
│   └── bootstrap.php             ← Integration helper
├── lang/
│   ├── bg.json                   ← MASTER (твоят език)
│   └── en.json                   ← English draft (professional translation later)
└── tax/
    └── TaxEngine.php             ← Interface + BGTaxEngine + GenericTaxEngine
```

---

## 🚀 Deployment стъпки

### 1. DB Migration

```bash
# В droplet console:
cd /var/www/runmystore
mysql -u root runmystore < migrations/001_i18n_schema.sql

# Verify:
mysql -u root runmystore -e "SELECT country_code, name_en, active FROM country_config;"
# Очаквам: 9 records, BG active = TRUE
```

### 2. Качи library файлове

```bash
mkdir -p /var/www/runmystore/lib /var/www/runmystore/lang /var/www/runmystore/tax

# Copy:
cp i18n-foundation/lib/i18n.php       /var/www/runmystore/lib/
cp i18n-foundation/lib/locale.php     /var/www/runmystore/lib/
cp i18n-foundation/lib/bootstrap.php  /var/www/runmystore/lib/
cp i18n-foundation/lang/bg.json       /var/www/runmystore/lang/
cp i18n-foundation/lang/en.json       /var/www/runmystore/lang/
cp i18n-foundation/tax/TaxEngine.php  /var/www/runmystore/tax/

# Permissions:
chmod 644 /var/www/runmystore/lib/*.php
chmod 644 /var/www/runmystore/lang/*.json
chmod 644 /var/www/runmystore/tax/*.php
```

### 3. Update existing tenants с BG defaults

```sql
-- Чрез mysql:
UPDATE tenants 
SET locale = 'bg-BG', 
    country_code = 'BG', 
    currency = 'EUR', 
    timezone = 'Europe/Sofia',
    tax_jurisdiction = 'BG'
WHERE locale IS NULL OR locale = '';
```

### 4. Verify PHP intl extension

```bash
php -m | grep -i intl
# Очаквам: intl

# Ако липсва:
apt-get install -y php8.3-intl
service apache2 restart
```

---

## 🔌 Integration в existing PHP файлове

### Минимална промяна — пример

**Преди (hardcoded):**

```php
<?php
echo "<h1>Добро утро, " . htmlspecialchars($user['name']) . "</h1>";
echo "<p>Печалба: " . number_format($amount, 2, ',', ' ') . " лв</p>";
```

**След (i18n-ready):**

```php
<?php
require_once '/var/www/runmystore/lib/bootstrap.php';
$tenant = bootstrapTenant($pdo, $_SESSION['tenant_id']);

echo "<h1>" . htmlspecialchars(t('home.greeting_morning', ['name' => $user['name']])) . "</h1>";
echo "<p>" . t('home.profit') . ": " . Locale::priceFormat($amount, $tenant) . "</p>";
```

### Tax engine

```php
<?php
$tax = taxEngine($tenant, $pdo);

if ($tax->isAvailable()) {
    $estimate = $tax->estimateAnnualTax();
    echo "Данък: " . Locale::priceFormat($estimate['tax_amount'], $tenant);
    
    $vat = $tax->checkVATThreshold();
    if ($vat['status'] === 'approaching') {
        echo t('tax.vat_threshold_approaching', [
            'pct' => $vat['pct_of_threshold'],
            'threshold' => Locale::priceFormat($vat['threshold'], $tenant),
            'remaining' => Locale::priceFormat($vat['remaining'], $tenant),
        ]);
    }
} else {
    // GenericTaxEngine fallback
    echo t('tax.not_supported_country');
}
```

---

## 🎨 HTML/Mockup hydration

```html
<!-- Hardcoded текстове сложи data-i18n: -->
<h1 data-i18n="home.greeting_morning" data-i18n-vars='{"name":"Стефан"}'>
  Добро утро, Стефан
</h1>

<!-- JS hydration: -->
<script>
const T = window.__i18n || {};  // serialized from PHP

document.querySelectorAll('[data-i18n]').forEach(el => {
  const key = el.dataset.i18n;
  const vars = el.dataset.i18nVars ? JSON.parse(el.dataset.i18nVars) : {};
  let value = T[key] || el.textContent;
  for (const [k, v] of Object.entries(vars)) {
    value = value.replace(`{${k}}`, v);
  }
  el.textContent = value;
});
</script>
```

PHP serializes само ключовете използвани в страницата:

```php
$keys_needed = ['home.greeting_morning', 'home.profit', /* ... */];
$translations = [];
foreach ($keys_needed as $k) $translations[$k] = t($k);
echo '<script>window.__i18n = ' . json_encode($translations) . ';</script>';
```

---

## 🌍 Дни 1 → 365 timeline

```
DAY 1 (June 2026):
  ✓ Country: BG active
  ✓ Locale: bg-BG default
  ✓ Tax: BGTaxEngine
  ✓ Translations: bg.json (master) + en.json (draft)

MONTH 6 (Dec 2026):
  → en.json professional translation
  → US/UK активни (GenericTaxEngine)
  → ASO за EN markets
  → Pricing adaptation

MONTH 12 (Jun 2027):
  → ROTaxEngine + GRTaxEngine
  → RO, GR активни
  → Local payment methods (cards)

MONTH 18+ (Dec 2027+):
  → DE, ES, IT, FR (GenericTaxEngine first, native tax engines later)
  → 10+ езика
```

---

## ⚠️ Важно за разработчик

```
ПРАВИЛО 1: НИКАКВИ HARDCODED ТЕКСТОВЕ.
  Всеки string към user → t('key')

ПРАВИЛО 2: НИКАКВИ HARDCODED ВАЛУТНИ ЗНАЦИ.
  Не "1 437 €" → винаги Locale::priceFormat($amount, $tenant)

ПРАВИЛО 3: НИКАКВИ HARDCODED ДАТИ.
  Не "17 май 2026" → винаги Locale::dateFormat($date, $tenant)

ПРАВИЛО 4: НИКАКВИ HARDCODED TAX LOGIC.
  Не if ($country === 'BG') → винаги taxEngine($tenant, $pdo)

ПРАВИЛО 5: DB stored в UTC + EUR (base currency).
  Conversion to user locale/currency at display time only.

ПРАВИЛО 6: AI prompts с locale parameter.
  Винаги PromptBuilder::build($topic, $context, $tenant['locale'])
```

---

## 🔧 Troubleshooting

### "Missing translation" key visible в UI

```bash
tail -f /tmp/i18n_missing.log
# Виж кои ключове липсват, добави ги в bg.json
```

### Numbers/dates изглеждат странно

```bash
php -r 'echo extension_loaded("intl") ? "OK" : "MISSING";'
# Ако MISSING:
apt-get install -y php8.3-intl && service apache2 restart
```

### Stale DB overrides cache

```bash
rm /tmp/i18n_db_*.cache
```

---

## ✅ Checklist преди deploy

- [ ] DB migration изпълнена
- [ ] PHP intl extension инсталирано
- [ ] Library файлове качени
- [ ] Existing tenants updated с BG defaults
- [ ] Поне един page tested с t() и Locale::priceFormat()
- [ ] Bible v1.5 secura §49 documented
- [ ] Commit + push to GitHub

