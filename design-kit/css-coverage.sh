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
#   css-coverage.sh <mockup_file> <rewrite_file> [rewrite_dump_file]
#
# S136 v1.2: optional 3rd arg <rewrite_dump_file> — when present, classes
# are extracted from the rendered DUMP (chromium-resolved) for the rewrite
# instead of the raw source. Required for files that compose via PHP
# includes — class attributes inside `<?php include ... ?>` are otherwise
# invisible to the source-only grep, producing false-positive missing-class
# verdicts. Mockup is always extracted from source (no PHP).
#
# Exit codes:
#   0 = coverage OK (0 или 1 missing)
#   1 = coverage FAIL (>= 2 missing)
#   2 = invalid args / files missing
# ═══════════════════════════════════════════════════════════════════════

set -u

MOCKUP="${1:-}"
REWRITE="${2:-}"
REWRITE_DUMP="${3:-}"

if [ -z "$MOCKUP" ] || [ -z "$REWRITE" ]; then
    echo "Usage: $0 <mockup_file> <rewrite_file> [rewrite_dump_file]" >&2
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

# Use dump for rewrite if provided AND non-empty; else fall back to source.
REWRITE_FOR_GREP="$REWRITE"
if [ -n "$REWRITE_DUMP" ] && [ -s "$REWRITE_DUMP" ]; then
    REWRITE_FOR_GREP="$REWRITE_DUMP"
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
extract_classes "$REWRITE_FOR_GREP" > "$TMP_R"

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
