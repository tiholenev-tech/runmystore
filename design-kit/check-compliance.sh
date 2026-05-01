#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════
# RunMyStore — DESIGN KIT compliance checker · v1.1 (01.05.2026)
# Usage: bash /design-kit/check-compliance.sh path/to/module.php
# Exit 0 = OK · Exit 1 = НАРУШЕНИЕ (модулът се отказва)
#
# v1.1 нови проверки (S89 GAP REPORT fix):
#   [9/10] theme-toggle.js включен
#   [10/10] <html> няма hardcoded data-theme="dark"
# ════════════════════════════════════════════════════════════

set -e

if [ -z "$1" ]; then
    echo "Usage: $0 <path-to-module.php>"
    exit 1
fi

FILE="$1"
if [ ! -f "$FILE" ]; then
    echo "✗ Файлът не съществува: $FILE"
    exit 1
fi

ERRORS=0
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
echo -e "${CYAN}  DESIGN KIT v1.1 compliance: $FILE${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════${NC}"

fail() {
    echo -e "${RED}✗ FAIL:${NC} $1"
    ERRORS=$((ERRORS+1))
}
warn() {
    echo -e "${YELLOW}⚠ WARN:${NC} $1"
}
ok() {
    echo -e "${GREEN}✔ OK:${NC} $1"
}

# ════════════════════════════════════════════════════════════
# 1. Задължителни импорти
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[1/10] Задължителни CSS импорти от /design-kit/${NC}"
for f in tokens.css components-base.css components.css light-theme.css header-palette.css; do
    if grep -q "design-kit/$f" "$FILE"; then
        ok "import $f"
    else
        fail "Липсва: <link rel=\"stylesheet\" href=\"/design-kit/$f\">"
    fi
done

if grep -q "design-kit/palette.js" "$FILE"; then
    ok "include palette.js"
else
    fail "Липсва: <script src=\"/design-kit/palette.js\">"
fi

# ════════════════════════════════════════════════════════════
# 2. Забранени собствени дефиниции на компоненти
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[2/10] Забранени собствени дефиниции${NC}"
FORBIDDEN_CLASSES="\.glass\s*[{,]|\.shine\s*[{,]|\.glow\s*[{,]|\.glow-bright\s*[{,]|\.qcard\s*[{,]|\.btn-iri\s*[{,]|\.lb-card\s*[{,]|\.s82-dash\s*[{,]|\.briefing-section\s*[{,]|\.ai-studio-row\s*[{,]|\.health\s*[{,]|\.cb-mode-toggle\s*[{,]|\.rms-icon-btn\s*[{,]|\.rms-fab\s*[{,]|\.rms-header\s*[{,]|\.rms-bottom-nav\s*[{,]|\.rms-input-bar\s*[{,]|\.rms-brand\s*[{,]|\.pill\s*[{,]|\.top-pill\s*[{,]|\.rev-pill\s*[{,]"

VIOLATIONS=$(grep -nE "$FORBIDDEN_CLASSES" "$FILE" 2>/dev/null || true)
if [ -n "$VIOLATIONS" ]; then
    fail "Преписваш съществуващи класове:"
    echo "$VIOLATIONS" | head -10 | sed 's/^/      /'
else
    ok "Не преписваш съществуващи класове"
fi

# ════════════════════════════════════════════════════════════
# 3. Забранени hue-overrides
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[3/10] Забранени inline hue-overrides${NC}"
INLINE_HUE=$(grep -nE 'style="[^"]*--hue[12][^"]*"' "$FILE" 2>/dev/null || true)
if [ -n "$INLINE_HUE" ]; then
    fail "Inline --hue1/--hue2 в style=\"\""
    echo "$INLINE_HUE" | head -5 | sed 's/^/      /'
else
    ok "Няма inline --hue1/--hue2"
fi

ROOT_HUE=$(grep -nE ':root\s*\{[^}]*--hue[12]' "$FILE" 2>/dev/null || true)
if [ -n "$ROOT_HUE" ]; then
    fail ":root override на --hue1/--hue2 в модула"
    echo "$ROOT_HUE" | head -5 | sed 's/^/      /'
else
    ok "Няма :root override на hue tokens"
fi

# ════════════════════════════════════════════════════════════
# 4. Забранени patterns
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[4/10] Забранени design-kit patterns${NC}"
INLINE_STYLE=$(awk '/<style/,/<\/style>/' "$FILE" 2>/dev/null)

check_pattern() {
    local pattern="$1"
    local name="$2"
    if echo "$INLINE_STYLE" | grep -qE "$pattern"; then
        fail "$name извън design-kit"
    else
        ok "$name е само в design-kit"
    fi
}

check_pattern 'backdrop-filter\s*:' 'backdrop-filter'
check_pattern 'conic-gradient' 'conic-gradient'
check_pattern 'mix-blend-mode\s*:\s*plus-lighter' 'mix-blend-mode: plus-lighter'
check_pattern 'mask:\s*linear-gradient.*linear-gradient' 'mask composite (двоен linear-gradient)'

# ════════════════════════════════════════════════════════════
# 5. Шрифт
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[5/10] Шрифт = Montserrat${NC}"
if grep -qE "font-family:\s*['\"]?(?!Montserrat)" <(echo "$INLINE_STYLE") 2>/dev/null; then
    OTHER_FONTS=$(echo "$INLINE_STYLE" | grep -E "font-family:\s*['\"]?[A-Z]" | grep -vE "Montserrat|monospace|inherit|sans-serif" || true)
    if [ -n "$OTHER_FONTS" ]; then
        fail "Друг шрифт освен Montserrat:"
        echo "$OTHER_FONTS" | head -3 | sed 's/^/      /'
    else
        ok "Само Montserrat"
    fi
else
    ok "Само Montserrat"
fi

# ════════════════════════════════════════════════════════════
# 6. Emoji в UI
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[6/10] Emoji в UI${NC}"
EMOJI_LINES=$(grep -nP '[\x{1F300}-\x{1FAFF}]|[\x{2600}-\x{27BF}]' "$FILE" 2>/dev/null | grep -v '^\s*\*\|^\s*\/\/\|^\s*<!--' || true)
if [ -n "$EMOJI_LINES" ]; then
    warn "Emoji намерени (може да са в коментари — провери ръчно):"
    echo "$EMOJI_LINES" | head -5 | sed 's/^/      /'
else
    ok "Няма emoji"
fi

# ════════════════════════════════════════════════════════════
# 7. Shell partials
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[7/10] Shell partials${NC}"
if grep -qE "include\s+__DIR__\s*\.\s*['\"]/(design-kit/partial-header|partials/header)\.(html|php)" "$FILE"; then
    ok "Header partial включен"
else
    fail "Header partial липсва (трябва include на /design-kit/partial-header.html)"
fi

if grep -qE "include\s+__DIR__\s*\.\s*['\"]/(design-kit/partial-bottom-nav|partials/bottom-nav)\.(html|php)" "$FILE"; then
    ok "Bottom nav partial включен"
else
    fail "Bottom nav partial липсва"
fi

# ════════════════════════════════════════════════════════════
# 8. body class="has-rms-shell"
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[8/10] body class${NC}"
if grep -qE '<body[^>]*class="[^"]*has-rms-shell' "$FILE"; then
    ok 'body има class="has-rms-shell"'
else
    fail '<body> трябва да има class="has-rms-shell"'
fi

# ════════════════════════════════════════════════════════════
# 9. theme-toggle.js включен (v1.1 НОВО)
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[9/10] theme-toggle.js (v1.1)${NC}"
if grep -q "design-kit/theme-toggle.js" "$FILE"; then
    ok "include theme-toggle.js"
    # Проверка дали е ПРЕДИ palette.js
    TOGGLE_LINE=$(grep -n "design-kit/theme-toggle.js" "$FILE" | head -1 | cut -d: -f1)
    PALETTE_LINE=$(grep -n "design-kit/palette.js" "$FILE" | head -1 | cut -d: -f1)
    if [ -n "$TOGGLE_LINE" ] && [ -n "$PALETTE_LINE" ]; then
        if [ "$TOGGLE_LINE" -gt "$PALETTE_LINE" ]; then
            fail "theme-toggle.js трябва да е ПРЕДИ palette.js (toggle:$TOGGLE_LINE > palette:$PALETTE_LINE)"
        else
            ok "theme-toggle.js е преди palette.js"
        fi
    fi
else
    fail "Липсва: <script src=\"/design-kit/theme-toggle.js\"> (без него theme бутонът е мъртъв)"
fi

# ════════════════════════════════════════════════════════════
# 10. <html> няма hardcoded data-theme (v1.1 НОВО)
# ════════════════════════════════════════════════════════════
echo -e "\n${CYAN}[10/10] <html> tag без data-theme (v1.1)${NC}"
HTML_THEME=$(grep -nE '<html[^>]*data-theme=' "$FILE" 2>/dev/null || true)
if [ -n "$HTML_THEME" ]; then
    fail '<html> има hardcoded data-theme — трябва да е САМО <html lang="bg">'
    echo "$HTML_THEME" | head -3 | sed 's/^/      /'
    echo -e "      ${YELLOW}Защо:${NC} hardcoded data-theme=\"dark\" override-ва bootstrap script-а,"
    echo -e "      ${YELLOW}      и localStorage rms_theme=light не се прилага на reload.${NC}"
else
    ok '<html> няма hardcoded data-theme'
fi

# Bootstrap script проверка
if grep -q "localStorage.getItem('rms_theme')" "$FILE"; then
    ok "Bootstrap script за theme присъства"
else
    warn "Липсва inline bootstrap: <script>try{if(localStorage.getItem('rms_theme')==='light')...}</script>"
fi

# ════════════════════════════════════════════════════════════
# Резултат
# ════════════════════════════════════════════════════════════
echo
echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
if [ "$ERRORS" -eq 0 ]; then
    echo -e "${GREEN}✔ COMPLIANCE PASSED — модулът е в design-kit v1.1 стандарта${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
    exit 0
else
    echo -e "${RED}✗ COMPLIANCE FAILED — $ERRORS нарушения${NC}"
    echo -e "${RED}  Модулът се отказва. Поправи и пусни отново.${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
    exit 1
fi
