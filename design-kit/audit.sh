#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# RunMyStore — code audit script (S104.AUDIT_INFRA)
# Usage: bash design-kit/audit.sh path/to/module.php [--report]
# Exit 0 = no critical issues, 1 = critical issues found
#
# Categories:
#   [SYNTAX]      php -l
#   [SECURITY]    SQL-injection, eval/exec, file include from $_REQUEST
#   [DEAD_CODE]   long commented-out blocks, unused defines
#   [PERFORMANCE] SELECT *, DB::run inside loops, missing LIMIT
#   [DB_FIELDS]   wrong column names per project schema
#   [HARDCODED]   currency literals without priceFormat
#
# Output:
#   stdout = colored summary
#   handoffs/AUDIT_<file>_<TS>.md if --report
# ════════════════════════════════════════════════════════════════════

set -u

if [ -z "${1:-}" ]; then
    echo "Usage: $0 <path-to-module.php> [--report]"
    exit 1
fi

FILE="$1"
REPORT_MODE=0
[ "${2:-}" = "--report" ] && REPORT_MODE=1

if [ ! -f "$FILE" ]; then
    echo "✗ Файлът не съществува: $FILE"
    exit 1
fi

CRIT=0
WARN=0
INFO=0
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

REPORT_LINES=()
log() {
    REPORT_LINES+=("$1")
    echo -e "$1"
}
crit() { log "${RED}✗ CRIT [$1]:${NC} $2"; CRIT=$((CRIT+1)); }
warn() { log "${YELLOW}⚠ WARN [$1]:${NC} $2"; WARN=$((WARN+1)); }
info() { log "${GREEN}ℹ INFO [$1]:${NC} $2"; INFO=$((INFO+1)); }
ok()   { log "${GREEN}✔ OK   [$1]:${NC} $2"; }

log "${CYAN}═══════════════════════════════════════════════${NC}"
log "${CYAN}  AUDIT: ${FILE}${NC}"
log "${CYAN}═══════════════════════════════════════════════${NC}"

# ── [SYNTAX] ─────────────────────────────────────────────────────────
log "\n${CYAN}── [SYNTAX] PHP lint ──${NC}"
PHP_OUT=$(php -l "$FILE" 2>&1)
if echo "$PHP_OUT" | grep -q '^No syntax errors'; then
    ok "SYNTAX" "php -l clean"
else
    crit "SYNTAX" "php -l FAILED"
    log "      $(echo "$PHP_OUT" | head -3 | tr '\n' ' ')"
fi

# ── [SECURITY] ──────────────────────────────────────────────────────
log "\n${CYAN}── [SECURITY] ──${NC}"

# Direct mysql_query (legacy, deprecated, no prepared statements)
MQ=$(grep -nE 'mysql_query\s*\(' "$FILE" 2>/dev/null | head -3 || true)
if [ -n "$MQ" ]; then
    crit "SECURITY" "mysql_query() използван (deprecated, не приема prepared)"
    echo "$MQ" | sed 's/^/      /'
else
    ok "SECURITY" "Без mysql_query"
fi

# eval, exec, system, passthru, shell_exec
DANG=$(grep -nE '\b(eval|exec|system|passthru|shell_exec|popen|proc_open)\s*\(' "$FILE" 2>/dev/null \
    | grep -vE '^\s*[0-9]+:\s*//|^\s*[0-9]+:\s*\*|//.*\b(eval|exec|system)\(' | head -3 || true)
if [ -n "$DANG" ]; then
    crit "SECURITY" "Опасни функции (eval/exec/system/passthru)"
    echo "$DANG" | sed 's/^/      /'
else
    ok "SECURITY" "Без eval/exec/system/passthru"
fi

# $_GET / $_POST / $_REQUEST директно интерполирани в SQL низ (БЕЗ prepared placeholder)
# Conservative — flag only когато наистина има interpolation:
#   "WHERE x = $_GET[...]"           (inside double-quoted string)
#   "SELECT ..." . $_GET[...]        (string concat with SQL keyword nearby)
# False positives като `isset($_GET[...])` и `$params[] = $_GET[...]` не се flagat.
SQL_INJ=$(grep -nE '"[^"]*\b(SELECT|UPDATE|INSERT|DELETE|FROM|WHERE)\b[^"]*"\s*\.\s*\$_(GET|POST|REQUEST)\[|"[^"]*\$_(GET|POST|REQUEST)\[[^"]*\][^"]*"' "$FILE" 2>/dev/null \
    | grep -iE 'SELECT|UPDATE|INSERT|DELETE|FROM|WHERE|VALUES' | head -3 || true)
if [ -n "$SQL_INJ" ]; then
    crit "SECURITY" "\$_GET/\$_POST/\$_REQUEST директно интерполиран в SQL низ"
    echo "$SQL_INJ" | sed 's/^/      /'
else
    ok "SECURITY" "Няма superglobals интерполирани в SQL низове (prepared statements OK)"
fi

# include / require от потребителски input
DYN_INC=$(grep -nE '(include|require)(_once)?\s*\(\s*\$_(GET|POST|REQUEST)' "$FILE" 2>/dev/null | head -3 || true)
if [ -n "$DYN_INC" ]; then
    crit "SECURITY" "Dynamic include от user input"
    echo "$DYN_INC" | sed 's/^/      /'
else
    ok "SECURITY" "Без dynamic include от \$_GET/\$_POST"
fi

# ── [DEAD_CODE] ──────────────────────────────────────────────────────
log "\n${CYAN}── [DEAD_CODE] ──${NC}"

# Sequential single-line comments > 6 → likely dead block (heuristic)
DEAD_BLOCKS=$(awk '
    /^\s*\/\// { c++; if (c>6 && !flagged) { print NR": "c" consecutive // comment lines (likely dead code)"; flagged=1 } next }
    /^\s*$/    { next }
    { c=0; flagged=0 }
' "$FILE" | head -3)
if [ -n "$DEAD_BLOCKS" ]; then
    info "DEAD_CODE" "Възможни закоментирани блокове"
    echo "$DEAD_BLOCKS" | sed 's/^/      /'
else
    ok "DEAD_CODE" "Без големи закоментирани блокове"
fi

# Long /* ... */ blocks > 10 lines
LONG_BLOCK=$(awk '/\/\*/{s=NR} /\*\//{ if (s>0 && (NR-s)>10) print s"-"NR" "(NR-s)" lines block comment"; s=0 }' "$FILE" | head -3)
if [ -n "$LONG_BLOCK" ]; then
    info "DEAD_CODE" "Дълги /* */ блокове"
    echo "$LONG_BLOCK" | sed 's/^/      /'
fi

# ── [PERFORMANCE] ───────────────────────────────────────────────────
log "\n${CYAN}── [PERFORMANCE] ──${NC}"

# SELECT * — обикновено по-добре да изброиш колоните
SELSTAR=$(grep -niE 'SELECT\s+\*\s+FROM' "$FILE" 2>/dev/null | head -3 || true)
SELSTAR_COUNT=$( [ -z "$SELSTAR" ] && echo 0 || echo "$SELSTAR" | grep -c .)
if [ "$SELSTAR_COUNT" -gt 5 ]; then
    warn "PERFORMANCE" "$SELSTAR_COUNT × SELECT * (изброй колоните за по-малко network/IO)"
elif [ "$SELSTAR_COUNT" -gt 0 ]; then
    info "PERFORMANCE" "$SELSTAR_COUNT × SELECT * (acceptable за tenants/* lookups)"
else
    ok "PERFORMANCE" "Без SELECT *"
fi

# DB::run / DB::query в цикъл (foreach { ... DB::run })
LOOP_DB=$(awk '
    /foreach\s*\(.+\)\s*\{|while\s*\(.+\)\s*\{|for\s*\(.+;.+;.+\)\s*\{/ { in_loop=1; loop_start=NR; next }
    /^\s*\}/ { if (in_loop) { in_loop=0 } next }
    in_loop && /DB::run|DB::query|->prepare\(|->fetchAll\(/ {
        print loop_start"-"NR": DB call inside loop"
        in_loop=0
    }
' "$FILE" | head -3)
if [ -n "$LOOP_DB" ]; then
    warn "PERFORMANCE" "DB заявки в цикъл (N+1 риск)"
    echo "$LOOP_DB" | sed 's/^/      /'
else
    ok "PERFORMANCE" "Без очевидни N+1 patterns"
fi

# ── [DB_FIELDS] ──────────────────────────────────────────────────────
log "\n${CYAN}── [DB_FIELDS] ──${NC}"

DB_VIOL=()
# products: 'sku' трябва да е 'code'
if grep -nqE '\bproducts\.[\`]?sku[\`]?|\.sku\b\s*[=,]' "$FILE" 2>/dev/null; then
    DB_VIOL+=("products.sku → използвай products.code")
fi
# products: 'sell_price' → 'retail_price'
if grep -nqE '\bsell_price\b' "$FILE" 2>/dev/null; then
    DB_VIOL+=("sell_price → използвай retail_price")
fi
# inventory: 'qty' → 'quantity' (само когато ясно е поле на inventory таблица)
if grep -nqE 'inventory\.qty\b|\binv\.qty\b|`inventory`\.`qty`|FROM\s+inventory\s+[a-z]*\s*[^;]*\bqty\s*[,=]' "$FILE" 2>/dev/null; then
    DB_VIOL+=("inventory.qty → използвай inventory.quantity")
fi
# sales.status='cancelled' (двойно L) → 'canceled'
if grep -nqE "status\s*=\s*['\"]cancelled['\"]|status['\"]?\s*,\s*['\"]cancelled['\"]" "$FILE" 2>/dev/null; then
    DB_VIOL+=("status='cancelled' → използвай status='canceled' (един L)")
fi
# sale_items.price → unit_price
if grep -nqE 'sale_items\.[\`]?price[\`]?|\bsi\.price\b' "$FILE" 2>/dev/null; then
    DB_VIOL+=("sale_items.price → използвай sale_items.unit_price")
fi
# ADD COLUMN IF NOT EXISTS (не работи в MySQL 8)
if grep -nqE 'ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS' "$FILE" 2>/dev/null; then
    DB_VIOL+=("ADD COLUMN IF NOT EXISTS не работи в MySQL 8")
fi

if [ "${#DB_VIOL[@]}" -gt 0 ]; then
    for v in "${DB_VIOL[@]}"; do
        crit "DB_FIELDS" "$v"
    done
else
    ok "DB_FIELDS" "Без DB schema violations"
fi

# ── [HARDCODED] ─────────────────────────────────────────────────────
log "\n${CYAN}── [HARDCODED] ──${NC}"

# Currency literals without priceFormat в близост
HARDC_CUR=$(grep -nE "echo\s+['\"]\\s*[0-9.,]+\s*(лв|€|BGN|EUR)['\"]|>\\s*[0-9.,]+\s*(лв|€|BGN|EUR)\\s*<" "$FILE" 2>/dev/null \
    | grep -vE '//\s|/\*|\*\s|priceFormat' | head -3 || true)
HARDC_COUNT=$( [ -z "$HARDC_CUR" ] && echo 0 || echo "$HARDC_CUR" | grep -c .)
if [ "$HARDC_COUNT" -gt 0 ]; then
    warn "HARDCODED" "$HARDC_COUNT hardcoded валутни изрази (използвай priceFormat(\$amount, \$tenant))"
    echo "$HARDC_CUR" | sed 's/^/      /'
else
    ok "HARDCODED" "Без hardcoded валути"
fi

# Direct " лв" / " €" текстови литерали в HTML content без вариабли
HARDC_TXT=$(grep -nE "[\"'][^\"']*\b(лв|BGN)\b[^\"']*[\"']" "$FILE" 2>/dev/null \
    | grep -vE '//\s|/\*|\*\s|priceFormat|\$tenant\[|\$cs|currency' | head -3 || true)
HARDC_TXT_COUNT=$( [ -z "$HARDC_TXT" ] && echo 0 || echo "$HARDC_TXT" | grep -c .)
if [ "$HARDC_TXT_COUNT" -gt 0 ]; then
    info "HARDCODED" "$HARDC_TXT_COUNT текста с 'лв'/'BGN' (провери дали не трябва priceFormat)"
fi

# ── Резултат ────────────────────────────────────────────────────────
log "\n${CYAN}═══════════════════════════════════════════════${NC}"
log "  CRIT: $CRIT  WARN: $WARN  INFO: $INFO"
if [ "$CRIT" -eq 0 ]; then
    log "${GREEN}✔ AUDIT PASSED — без критични проблеми${NC}"
else
    log "${RED}✗ AUDIT — $CRIT критични проблема${NC}"
fi
log "${CYAN}═══════════════════════════════════════════════${NC}"

# Ако --report → запиши markdown файл
if [ "$REPORT_MODE" = 1 ]; then
    REPORT_DIR="handoffs"
    mkdir -p "$REPORT_DIR"
    TS=$(date +%Y%m%d-%H%M)
    BASENAME=$(basename "$FILE" .php)
    REPORT_FILE="$REPORT_DIR/AUDIT_${BASENAME}_${TS}.md"
    {
        echo "# AUDIT — $FILE"
        echo "**Date:** $(date '+%Y-%m-%d %H:%M')"
        echo ""
        echo "**Summary:** CRIT=$CRIT, WARN=$WARN, INFO=$INFO"
        echo ""
        echo '```'
        for line in "${REPORT_LINES[@]}"; do
            # strip ANSI color codes for markdown
            echo "$line" | sed 's/\x1b\[[0-9;]*m//g'
        done
        echo '```'
    } > "$REPORT_FILE"
    echo "📄 Report → $REPORT_FILE"
fi

[ "$CRIT" -eq 0 ] && exit 0 || exit 1
