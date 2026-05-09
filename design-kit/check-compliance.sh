#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# DESIGN COMPLIANCE CHECK v4.1 BICHROMATIC
# ═══════════════════════════════════════════════════════════════════════
# Usage: ./check-compliance.sh [file1.php] [file2.css] ...
#        Or: ./check-compliance.sh  (auto-checks all .php in current dir)
#
# Exit codes:
#   0 = всички файлове compliant
#   1 = поне 1 файл с нарушения (CI/pre-commit blocks)
# ═══════════════════════════════════════════════════════════════════════

set -u

# Colors
R='\033[0;31m'   # Red
G='\033[0;32m'   # Green
Y='\033[1;33m'   # Yellow
B='\033[0;34m'   # Blue
N='\033[0m'      # No color
BOLD='\033[1m'

# Counters
ERRORS=0
WARNINGS=0
FILES_CHECKED=0
FILES_FAILED=0

# Settings
BIBLE='/var/www/runmystore/DESIGN_SYSTEM.md'
SKIP_PATTERNS='\.bak\.|/\.git/|/node_modules/|/vendor/|/docs/archived/|\.min\.|/\.backups/'

# ═══════════════════════════════════════════════════════════════════════
# HELPER FUNCTIONS
# ═══════════════════════════════════════════════════════════════════════

print_header() {
    echo -e "${BOLD}${B}═══════════════════════════════════════════════════════════════${N}"
    echo -e "${BOLD}${B}  DESIGN COMPLIANCE v4.1 BICHROMATIC${N}"
    echo -e "${BOLD}${B}═══════════════════════════════════════════════════════════════${N}"
    echo ""
}

err() {
    local file="$1"
    local line="$2"
    local rule="$3"
    local msg="$4"
    echo -e "  ${R}✗ ERROR${N} ${file}:${line} [${rule}]"
    echo -e "    ${msg}"
    ERRORS=$((ERRORS + 1))
}

warn() {
    local file="$1"
    local line="$2"
    local rule="$3"
    local msg="$4"
    echo -e "  ${Y}⚠ WARN${N}  ${file}:${line} [${rule}]"
    echo -e "    ${msg}"
    WARNINGS=$((WARNINGS + 1))
}

# Check if pattern matches and report
check_pattern() {
    local file="$1"
    local pattern="$2"
    local rule="$3"
    local msg="$4"
    local severity="${5:-error}"  # error or warn

    while IFS=: read -r linenum match; do
        [ -z "$linenum" ] && continue
        if [ "$severity" = "warn" ]; then
            warn "$file" "$linenum" "$rule" "$msg"
        else
            err "$file" "$linenum" "$rule" "$msg"
        fi
    done < <(grep -nE "$pattern" "$file" 2>/dev/null || true)
}

# Check if pattern is MISSING (required pattern not found)
check_required() {
    local file="$1"
    local pattern="$2"
    local rule="$3"
    local msg="$4"

    if ! grep -qE "$pattern" "$file" 2>/dev/null; then
        err "$file" "0" "$rule" "$msg"
    fi
}

# ═══════════════════════════════════════════════════════════════════════
# CHECK A SINGLE FILE
# ═══════════════════════════════════════════════════════════════════════

check_file() {
    local file="$1"
    local before_errors=$ERRORS
    local before_warnings=$WARNINGS

    # Skip if matches skip patterns
    if echo "$file" | grep -qE "$SKIP_PATTERNS"; then
        return 0
    fi

    # Skip if doesn't exist
    [ ! -f "$file" ] && return 0

    FILES_CHECKED=$((FILES_CHECKED + 1))

    echo -e "${B}→${N} Checking: ${BOLD}${file}${N}"

    local ext="${file##*.}"
    local is_php=false
    local is_css=false
    local is_html=false

    case "$ext" in
        php) is_php=true ;;
        css) is_css=true ;;
        html|htm) is_html=true ;;
        *) return 0 ;;
    esac

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 1: HARDCODED VALUES (no CSS variables)
    # ═══════════════════════════════════════════════════════════════

    # 1.1 Hardcoded hex colors извън коментари
    # Allow #fff, #000, transparent, currentColor; allow в SVG fill/stroke; allow в rgba/hsla calls
    check_pattern "$file" \
        ':\s*#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b(?![^/]*\*/)(?![^<]*</style)' \
        '1.1-hardcoded-hex' \
        'Hardcoded hex цвят. Използвай var(--accent), var(--text), var(--qN-*) или hsl(var(--hueN) ...)'

    # 1.2 Hardcoded border-radius
    check_pattern "$file" \
        'border-radius:\s*[0-9]+(\.[0-9]+)?(px|rem|em)\s*[;}]' \
        '1.2-hardcoded-radius' \
        'Hardcoded border-radius. Използвай var(--radius), var(--radius-sm), var(--radius-pill), var(--radius-icon)'

    # 1.3 Hardcoded box-shadow (allow само ако започва с inset 0 0 или 0 0 0)
    check_pattern "$file" \
        'box-shadow:\s*0\s+[0-9]+px\s+[0-9]+px\s+(rgba|hsla|#)' \
        '1.3-hardcoded-shadow' \
        'Hardcoded box-shadow. Използвай var(--shadow-card), var(--shadow-card-sm), var(--shadow-pressed)' \
        'warn'

    # 1.4 Forbidden font families
    check_pattern "$file" \
        "font-family:\s*['\"]?(Inter|Roboto|Arial|system-ui|-apple-system|Helvetica|Times|Georgia|Verdana|Tahoma)" \
        '1.4-forbidden-font' \
        'Forbidden font. Използвай var(--font) (Montserrat) или var(--font-mono) (DM Mono)'

    # 1.5 Forbidden Bootstrap/Tailwind classes
    # S136 fix: require class name to start at attribute boundary (after `"`) or
    # whitespace. Old `\b` matched mid-name (e.g. project's `sig-btn-primary`
    # tripped on the `btn-primary` substring after `-`). Project classes that
    # legitimately contain `btn-primary` as a suffix segment are no longer false-
    # flagged. True framework classes still caught (always begin at attr-boundary).
    check_pattern "$file" \
        'class="([^"]*\s)?(btn-primary|btn-secondary|d-flex|col-md-|p-[0-9]|m-[0-9]|text-center|bg-white|bg-dark|text-muted|rounded-pill)\b' \
        '1.5-forbidden-framework' \
        'Bootstrap/Tailwind class. Project използва vanilla CSS — никакви frameworks.'

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 2: SACRED ELEMENTS
    # ═══════════════════════════════════════════════════════════════

    # 2.1 .glass без shine/glow spans (само за HTML/PHP)
    if [ "$is_php" = true ] || [ "$is_html" = true ]; then
        # Find lines with class="glass" but check if next 4 lines have all 4 spans
        while IFS=: read -r linenum content; do
            [ -z "$linenum" ] && continue
            # Skip CSS/comments
            local context_start=$linenum
            local context_end=$((linenum + 4))
            local context=$(sed -n "${context_start},${context_end}p" "$file")
            if ! echo "$context" | grep -q '<span class="shine">'; then
                err "$file" "$linenum" '2.1-glass-no-shine' \
                    '.glass без <span class="shine"></span><span class="shine shine-bottom"></span><span class="glow"></span><span class="glow glow-bottom"></span>'
            fi
        done < <(grep -nE 'class="[^"]*\bglass\b[^"]*"' "$file" 2>/dev/null | grep -v 'glass\.|glass\s*{|\.glass-' || true)
    fi

    # 2.2 removeAttribute('data-theme') — BUG (никога не работи в dark)
    check_pattern "$file" \
        "removeAttribute\s*\(\s*['\"]data-theme['\"]\s*\)" \
        '2.2-removeAttribute-bug' \
        'rmsToggleTheme трябва да ползва setAttribute(data-theme, X), НЕ removeAttribute. Иначе dark mode НЕ работи.'

    # 2.3 .shine или .glow с display: block в light mode (грешка — трябва none)
    if grep -q 'data-theme="light"' "$file" && grep -q '\.shine\|\.glow' "$file"; then
        check_pattern "$file" \
            '\[data-theme="light"\]\s+\.glass\s+\.(shine|glow)\s*{[^}]*display:\s*(block|inline|flex)' \
            '2.3-light-shine-glow-visible' \
            'В light mode .shine/.glow трябва да са display:none (plus-lighter не работи на светъл фон)'
    fi

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 3: BICHROMATIC RULES
    # ═══════════════════════════════════════════════════════════════

    # 3.1 Body transition (anti-flicker)
    check_pattern "$file" \
        '^\s*body\s*\{[^}]*transition:\s*[^;]*background' \
        '3.1-body-transition' \
        'Body НЕ трябва да има transition: background — премигва при theme switch'

    # 3.2 Missing data-theme attribute
    if [ "$is_php" = true ]; then
        if grep -q '<html lang' "$file"; then
            if ! grep -qE 'data-theme=' "$file"; then
                err "$file" "0" '3.2-missing-data-theme' \
                    '<html> tag трябва да има data-theme="<?= ... ?>" attribute'
            fi
        fi
    fi

    # 3.3 plus-lighter blend в light context
    if grep -qE '\[data-theme="light"\]|root:not\(\[data-theme\]' "$file"; then
        # Find plus-lighter inside light theme blocks (multi-line aware would be hard in pure bash)
        check_pattern "$file" \
            'mix-blend-mode:\s*plus-lighter' \
            '3.3-plus-lighter-light' \
            'plus-lighter blend mode не работи на светъл фон. Използвай multiply.' \
            'warn'
    fi

    # 3.4 Mode-specific styling БЕЗ съответстваща другата
    # Ако има [data-theme="dark"] {...} но няма [data-theme="light"] еквивалент
    if grep -q '\[data-theme="dark"\]' "$file"; then
        if ! grep -qE '\[data-theme="light"\]|:root:not\(\[data-theme\]' "$file"; then
            warn "$file" "0" '3.4-dark-only' \
                'Файлът има [data-theme="dark"] но няма [data-theme="light"] (или :root:not). Дизайнът трябва да работи в двата режима.'
        fi
    fi

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 4: REQUIRED ELEMENTS (ако е PHP/HTML)
    # ═══════════════════════════════════════════════════════════════

    if [ "$is_php" = true ]; then
        # 4.1 Google Fonts link (ако ползва Montserrat/DM Mono)
        if grep -qE "var\(--font\)|font-family:\s*['\"]?(Montserrat|DM Mono)" "$file"; then
            if ! grep -q 'fonts.googleapis.com' "$file"; then
                # Check if header.php is included (which has fonts)
                if ! grep -q 'header\.php' "$file"; then
                    err "$file" "0" '4.1-missing-fonts' \
                        'Файлът ползва var(--font) но няма Google Fonts link и не include-ва header.php (който има link-а)'
                fi
            fi
        fi

        # 4.2 Aurora div (ако е main page, не partial)
        local basename_f=$(basename "$file")
        if [ "$basename_f" != "header.php" ] && [ "$basename_f" != "bottom-nav.php" ] && \
           [ "$basename_f" != "shell-init.php" ] && [ "$basename_f" != "shell-scripts.php" ] && \
           [ "$basename_f" != "chat-input-bar.php" ]; then
            if grep -q 'header\.php' "$file"; then
                if ! grep -q 'class="aurora"' "$file"; then
                    warn "$file" "0" '4.2-missing-aurora' \
                        'Main страница НЯМА <div class="aurora"> блок (Effect #1).'
                fi
            fi
        fi

        # 4.3 НЕ дефинирай custom header / bottom-nav (винаги include partials)
        if [ "$basename_f" != "header.php" ]; then
            check_pattern "$file" \
                '<header[^>]*class="rms-header"' \
                '4.3-custom-header' \
                'НЕ пиши custom header. Винаги include partials/header.php' \
                'warn'
        fi
    fi

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 5: i18n / PRICING / NAMING
    # ═══════════════════════════════════════════════════════════════

    if [ "$is_php" = true ]; then
        # 5.1 Hardcoded "лв" / "€" / "BGN"
        check_pattern "$file" \
            '>(лв|€|BGN|EUR)<\|"(лв|€|BGN|EUR)"' \
            '5.1-hardcoded-currency' \
            'Hardcoded валута. Използвай priceFormat($amount, $tenant)' \
            'warn'

        # 5.2 "Gemini" / "fal.ai" / "Anthropic" в UI текст
        check_pattern "$file" \
            'echo[^;]*[\"'\''"][^"'\'']*\b(Gemini|fal\.ai|Anthropic|Claude|GPT|OpenAI)' \
            '5.2-ai-vendor-leak' \
            'Името на AI vendor НЕ трябва да е в UI. Използвай "AI".'
    fi

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 6: TRANSITIONS / ANIMATIONS
    # ═══════════════════════════════════════════════════════════════

    # 6.1 transition: all (без easing)
    check_pattern "$file" \
        'transition:\s*all\s+[0-9.]+s\s*[;}]' \
        '6.1-transition-all-no-easing' \
        'transition: all без easing. Използвай transition: <prop> var(--dur) var(--ease)' \
        'warn'

    # 6.2 Native <select> без styling
    if [ "$is_php" = true ] || [ "$is_html" = true ]; then
        check_pattern "$file" \
            '<select(\s[^>]*)?>(?!.*class=)' \
            '6.2-native-select' \
            'Native <select> без class. Използвай styled select (виж .lb-store-picker recipe в Bible Part 22.5)' \
            'warn'
    fi

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 7: LEGACY / DEPRECATED
    # ═══════════════════════════════════════════════════════════════

    # 7.1 Стари s87v3-* класове (warning само)
    check_pattern "$file" \
        '\bs87v3-(tap|pagein|stagger|scroll-reveal)\b' \
        '7.1-legacy-s87v3' \
        'Стар S87v3 animation class. v4.1 ползва :nth-child + fadeInUp directly.' \
        'warn'

    # 7.2 Старите hue класове в HTML (q-default, q-magic, и т.н.)
    if [ "$is_php" = true ] || [ "$is_html" = true ]; then
        check_pattern "$file" \
            'class="[^"]*\b(q-default|q-magic|q-loss|q-amber|q-gain|q-jewelry)\b' \
            '7.2-legacy-hue-class' \
            'Стари hue aliases. v4.1 използва q1-q6 (или семантичните --q1-loss и т.н. в CSS).' \
            'warn'
    fi

    # ═══════════════════════════════════════════════════════════════
    # CATEGORY 8: CRITICAL FILE-SPECIFIC RULES
    # ═══════════════════════════════════════════════════════════════

    local basename_f=$(basename "$file")

    # 8.1 partials/header.php — verify 7-element order
    if [ "$basename_f" = "header.php" ]; then
        local has_brand=$(grep -c 'rms-brand' "$file")
        local has_plan=$(grep -c 'rms-plan-badge' "$file")
        local has_spacer=$(grep -c 'rms-header-spacer' "$file")
        local has_theme=$(grep -c 'themeToggle' "$file")
        if [ "$has_brand" -lt 1 ] || [ "$has_plan" -lt 1 ] || [ "$has_spacer" -lt 1 ] || [ "$has_theme" -lt 1 ]; then
            err "$file" "0" '8.1-header-elements' \
                'header.php трябва да има всички 7 елемента: brand, plan-badge, spacer, print, settings, logout, theme-toggle'
        fi
    fi

    # 8.2 partials/bottom-nav.php — verify 4 tabs
    if [ "$basename_f" = "bottom-nav.php" ]; then
        for keyword in 'isAI' 'isWh' 'isStats' 'isSale'; do
            if ! grep -q "\$$keyword" "$file"; then
                err "$file" "0" '8.2-bottom-nav-tabs' \
                    "bottom-nav.php missing \$$keyword detection"
            fi
        done
    fi

    # ═══════════════════════════════════════════════════════════════
    # SUMMARY for this file
    # ═══════════════════════════════════════════════════════════════

    local file_errors=$((ERRORS - before_errors))
    local file_warnings=$((WARNINGS - before_warnings))

    if [ $file_errors -gt 0 ]; then
        echo -e "  ${R}${BOLD}✗ FAIL${N} — $file_errors errors, $file_warnings warnings"
        FILES_FAILED=$((FILES_FAILED + 1))
    elif [ $file_warnings -gt 0 ]; then
        echo -e "  ${Y}⚠ PASS with warnings${N} — 0 errors, $file_warnings warnings"
    else
        echo -e "  ${G}${BOLD}✓ PASS${N}"
    fi
    echo ""
}

# ═══════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════

print_header

# Verify Bible exists
if [ ! -f "$BIBLE" ]; then
    echo -e "${R}${BOLD}❌ FATAL: Bible не намерен на $BIBLE${N}"
    echo -e "${R}   Не мога да валидирам compliance без Bible.${N}"
    exit 2
fi
echo -e "${G}✓${N} Bible found: $BIBLE"
echo ""

# Determine files to check
if [ $# -gt 0 ]; then
    # Use args
    FILES=("$@")
else
    # Auto-find PHP/CSS files in current dir
    mapfile -t FILES < <(find . -maxdepth 2 \( -name "*.php" -o -name "*.css" \) -type f | head -50)
fi

if [ ${#FILES[@]} -eq 0 ]; then
    echo -e "${Y}⚠${N} Няма файлове за проверка."
    exit 0
fi

# Check each file
for file in "${FILES[@]}"; do
    check_file "$file"
done

# ═══════════════════════════════════════════════════════════════════════
# FINAL REPORT
# ═══════════════════════════════════════════════════════════════════════

echo -e "${BOLD}${B}═══════════════════════════════════════════════════════════════${N}"
echo -e "${BOLD}${B}  REPORT${N}"
echo -e "${BOLD}${B}═══════════════════════════════════════════════════════════════${N}"
echo -e "  Files checked: ${BOLD}$FILES_CHECKED${N}"
echo -e "  Files failed:  ${R}${BOLD}$FILES_FAILED${N}"
echo -e "  Errors:        ${R}${BOLD}$ERRORS${N}"
echo -e "  Warnings:      ${Y}${BOLD}$WARNINGS${N}"
echo ""

if [ $ERRORS -gt 0 ]; then
    echo -e "${R}${BOLD}✗ COMPLIANCE FAILED${N}"
    echo -e "${R}   Поправи грешките преди commit. Виж DESIGN_SYSTEM.md за подробности.${N}"
    exit 1
else
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${Y}${BOLD}⚠ COMPLIANCE PASSED with warnings${N}"
        echo -e "${Y}   Препоръчително — оправи warnings когато време позволява.${N}"
    else
        echo -e "${G}${BOLD}✓ COMPLIANCE PASSED${N}"
    fi
    exit 0
fi
