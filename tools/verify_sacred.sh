#!/bin/bash
set -e

echo "═══ 5× VERIFICATION ═══"

SKIP_WIZ=0
if [ ! -f wizard-v6.php ]; then
  echo "ℹ wizard-v6.php още не съществува — skip checks 1/4/5 (baseline mode)"
  SKIP_WIZ=1
fi

# 1
echo -n "1. PHP syntax wizard-v6.php ... "
if [ "$SKIP_WIZ" = "1" ]; then
  echo "SKIP (file absent)"
else
  php -l wizard-v6.php > /tmp/php_lint.out 2>&1 && echo "OK" || { cat /tmp/php_lint.out; exit 1; }
fi

# 2 (always runs — sacred zone integrity)
echo -n "2. Sacred SHA check ... "
sha256sum -c sacred_files.sha256 > /tmp/sha.out 2>&1 && echo "OK" || { cat /tmp/sha.out; exit 1; }

# 3 (always runs — products.php immutability)
echo -n "3. products.php line count ... "
LC=$(wc -l < products.php)
[ "$LC" = "15529" ] && echo "OK ($LC)" || { echo "FAIL ($LC, expected 15529)"; exit 1; }

# 4
echo -n "4. wizard-v6.php sections ... "
if [ "$SKIP_WIZ" = "1" ]; then
  echo "SKIP (file absent)"
else
  SEC=$(grep -c 'data-section=' wizard-v6.php 2>/dev/null || echo 0)
  [ "$SEC" -ge "4" ] && echo "OK ($SEC)" || { echo "FAIL ($SEC, expected ≥4)"; exit 1; }
fi

# 5
echo -n "5. wizard-v6.php HTTP ... "
if [ "$SKIP_WIZ" = "1" ]; then
  echo "SKIP (file absent)"
else
  HTTP=$(curl --resolve runmystore.ai:443:127.0.0.1 -sk -o /dev/null -w "%{http_code}" https://runmystore.ai/wizard-v6.php 2>/dev/null || echo "000")
  case "$HTTP" in
    200|302) echo "OK ($HTTP)" ;;
    *) echo "FAIL ($HTTP)"; exit 1 ;;
  esac
fi

if [ "$SKIP_WIZ" = "1" ]; then
  echo "✅ ALL 5 (2 OK + 3 SKIP) — baseline PASS, safe to bootstrap"
else
  echo "✅ ALL 5 PASSED — safe to commit"
fi
