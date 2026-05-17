#!/bin/bash
set -e

echo "═══ 5× VERIFICATION ═══"

# 1
echo -n "1. PHP syntax wizard-v6.php ... "
php -l wizard-v6.php > /tmp/php_lint.out 2>&1 && echo "OK" || { cat /tmp/php_lint.out; exit 1; }

# 2
echo -n "2. Sacred SHA check ... "
sha256sum -c sacred_files.sha256 > /tmp/sha.out 2>&1 && echo "OK" || { cat /tmp/sha.out; exit 1; }

# 3
echo -n "3. products.php line count ... "
LC=$(wc -l < products.php)
[ "$LC" = "15529" ] && echo "OK ($LC)" || { echo "FAIL ($LC, expected 15529)"; exit 1; }

# 4
echo -n "4. wizard-v6.php sections ... "
SEC=$(grep -c 'data-section=' wizard-v6.php 2>/dev/null || echo 0)
[ "$SEC" -ge "4" ] && echo "OK ($SEC)" || { echo "FAIL ($SEC, expected ≥4)"; exit 1; }

# 5
echo -n "5. wizard-v6.php HTTP 200 ... "
HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/wizard-v6.php 2>/dev/null || echo "000")
[ "$HTTP" = "200" ] && echo "OK" || { echo "FAIL ($HTTP)"; exit 1; }

echo "✅ ALL 5 PASSED — safe to commit"
