#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# CSS COVERAGE v1.0 — visual-gate CHECK 2 helper
# ═══════════════════════════════════════════════════════════════════════
# Сравнява всички CSS класове от mockup срещу rewrite-а.
# 0 missing → OK
# 1 missing → tolerated (PHP-conditional render)
# 2+ missing → FAIL
#
# Usage:
#   css-coverage.sh <mockup_file> <rewrite_file>
#
# Exit codes:
#   0 = coverage OK (0 или 1 missing)
#   1 = coverage FAIL (>= 2 missing)
#   2 = invalid args / files missing
# ═══════════════════════════════════════════════════════════════════════

set -u

MOCKUP="${1:-}"
REWRITE="${2:-}"

if [ -z "$MOCKUP" ] || [ -z "$REWRITE" ]; then
    echo "Usage: $0 <mockup_file> <rewrite_file>" >&2
    exit 2
fi

if [ ! -f "$MOCKUP" ]; then
    echo "FATAL: mockup not found: $MOCKUP" >&2
    exit 2
fi
if [ ! -f "$REWRITE" ]; then
    echo "FATAL: rewrite not found: $REWRITE" >&2
    exit 2
fi

# Извлечи всички уникални class-tokens (split по whitespace вътре в class="...").
extract_classes() {
    local file="$1"
    grep -hoE 'class="[^"]+"' "$file" \
        | sed -E 's/^class="([^"]+)"$/\1/' \
        | tr ' ' '\n' \
        | sed -E 's/^\s+|\s+$//g' \
        | grep -v '^$' \
        | grep -vE '^<\?' \
        | sort -u
}

TMP_M=$(mktemp)
TMP_R=$(mktemp)
trap 'rm -f "$TMP_M" "$TMP_R"' EXIT

extract_classes "$MOCKUP" > "$TMP_M"
extract_classes "$REWRITE" > "$TMP_R"

# missing = в mockup, но липсва в rewrite
MISSING=$(comm -23 "$TMP_M" "$TMP_R")
MISSING_COUNT=$(printf '%s\n' "$MISSING" | grep -c . || true)

TOTAL_M=$(wc -l < "$TMP_M")
TOTAL_R=$(wc -l < "$TMP_R")

echo "css-coverage: mockup=$TOTAL_M classes, rewrite=$TOTAL_R classes, missing=$MISSING_COUNT"

if [ "$MISSING_COUNT" -ge 2 ]; then
    echo "FAIL: липсващи класове ($MISSING_COUNT >= 2):"
    printf '  - %s\n' $MISSING
    exit 1
fi

if [ "$MISSING_COUNT" -eq 1 ]; then
    echo "PASS (tolerated): 1 липсващ клас:"
    printf '  - %s\n' $MISSING
fi

exit 0
