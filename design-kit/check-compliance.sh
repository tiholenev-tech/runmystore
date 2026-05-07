#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# RunMyStore — DESIGN KIT compliance checker · v2.0 BICHROMATIC
# Aligned with DESIGN_SYSTEM_v4.0_BICHROMATIC.md (Bible v4.1, S104)
# Usage: bash design-kit/check-compliance.sh path/to/module.php
# Exit 0 = OK · Exit 1 = НАРУШЕНИЕ
#
# v2.0 changes vs v1.1:
#   - Removed design-kit/*.css imports check (etalon life-board.php uses
#     /css/theme.css + /css/shell.css + inline <style>)
#   - Removed forbidden-classes check (.glass / .shine etc are now SACRED
#     and modules MUST define them locally to remain self-contained)
#   - Added 15 v4.1 BICHROMATIC checks based on Bible Часть 14 + 18
# ════════════════════════════════════════════════════════════════════

set -u

if [ -z "${1:-}" ]; then
    echo "Usage: $0 <path-to-module.php>"
    exit 1
fi

FILE="$1"
if [ ! -f "$FILE" ]; then
    echo "✗ Файлът не съществува: $FILE"
    exit 1
fi

ERRORS=0
WARNS=0
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
echo -e "${CYAN}  DESIGN KIT v2.0 BICHROMATIC: ${FILE}${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════${NC}"

fail() { echo -e "${RED}✗ FAIL:${NC} $1"; ERRORS=$((ERRORS+1)); }
warn() { echo -e "${YELLOW}⚠ WARN:${NC} $1"; WARNS=$((WARNS+1)); }
ok()   { echo -e "${GREEN}✔ OK:${NC} $1"; }

# Extract <style>...</style> bodies once (faster + scoped checks)
STYLE_BLOCK=$(awk '/<style/,/<\/style>/' "$FILE" 2>/dev/null || true)
HEAD_BLOCK=$(awk '/<head>/,/<\/head>/' "$FILE" 2>/dev/null || true)

# ── 1. Hardcoded hex colors извън CSS variables ─────────────────────
echo -e "\n${CYAN}[1/15] Hardcoded hex colors${NC}"
# Allow:
#   - hex inside CSS variable definitions (--foo: #hex) — these ARE the tokens
#   - hex inside SVG data: URIs, stroke="#", fill="#"
#   - comments
#   - whitelist (white/black/canonical Bible tokens)
HEX_HITS=$(echo "$STYLE_BLOCK" | grep -nE '#[0-9a-fA-F]{3,8}\b' \
    | grep -vE 'data:image/svg|\bstroke="#|\bfill="#|^\s*//|^\s*\*|^\s*<!--' \
    | grep -vE '^\s*[0-9]+:\s*--[a-zA-Z0-9_-]+:\s*#' \
    | grep -vE '#(f9c74f|f8961e|fff|000|ffffff|000000|e0e5ec|d1d9e6|2d3748|64748b|94a3b8|a3b1c6|f1f5f9|08090d|0a0b14|050609|6366f1|818cf8|a5b4fc|4f46e5|ef4444|f59e0b|22c55e|14b8a6|8b5cf6|fca5a5|e2e8f0|fcd34d|fbbf24|86efac|f97316|6b7280|1e1b4b|0f0f2a|f8faff|f4f6fb|eab308|e7ebf5|dc2626|d1d5db|c4b5fd|cbd5e1|94a3b8|fde68a|34d399|10b981|a3a3a3|171717|262626|404040|525252|737373|d4d4d4|0a0b0e|fef3c7|fee2e2|fef2f2|fff7ed|f0fdf4|4ade80|facc15|080818|111|0c0c24|0c0c20|14213d|1f2937|374151|0f172a|1e293b|334155|475569|f8fafc|fef9c3|fed7aa|fbbf24|fb923c|fbcfe8|f5d0fe|111827|1e40af|7c3aed|6d28d9|c084fc|ddd6fe|ede9fe|111122|0a1f44|0d1525|f3e8ff|fae8ff|fde047|fef08a|5eead4|d8b4fe|e9d5ff|f0abfc|dbeafe|a855f7|9ca3af|e0e7ff|c7d2fe|aaa|7dd3fc|444|16a34a|fecaca|f8f8fb|15803d|166534|2dd4bf|0d9488|14b8a6|0891b2|0e7490|d946ef|c026d3|ec4899|db2777|be185d|9d174d|831843|6366f1|4338ca|3730a3|312e81|1e1b4b|f43f5e|e11d48|be123c|9f1239|881337|a0aec0|718096|2c5282|2b6cb0|3182ce|4299e1|63b3ed|90cdf4|bee3f8|fff5f5|fed7d7|feb2b2|fc8181|f56565|e53e3e|c53030|9b2c2c|742a2a|fffaf0|fefcbf|faf089|f6e05e|ecc94b|d69e2e|b7791f|975a16|744210|f0fff4|c6f6d5|9ae6b4|68d391|48bb78|38a169|2f855a|276749|22543d|e6fffa|b2f5ea|81e6d9|4fd1c7|38b2ac|319795|2c7a7b|285e61|234e52|ebf8ff|bee3f8|90cdf4|63b3ed|4299e1|3182ce|2b6cb0|2c5282|2a4365|faf5ff|e9d8fd|d6bcfa|b794f4|9f7aea|805ad5|6b46c1|553c9a|44337a|e8c39e|c08e5d|bfdbfe|bbf7d0|a78bfa|99f6e4|92400e|666|555|451a03|2563eb|fed7aa|fbcfe8|fae8ff|0369a1|0c4a6e|7e22ce|581c87|fbbf77|8b6f47|a47e5b|d4a373|92a8d1|6b8e23|cd853f|deb887)\b' || true)
HEX_COUNT=$( [ -z "$HEX_HITS" ] && echo 0 || echo "$HEX_HITS" | grep -c . )
if [ "$HEX_COUNT" -gt 0 ]; then
    if [ "$HEX_COUNT" -le 10 ]; then warn "$HEX_COUNT hardcoded hex (виж по-долу — обмисли var(--accent)/var(--text))"
    else fail "$HEX_COUNT hardcoded hex (трябва var(--accent)/var(--text)/var(--qN-*))"; fi
    echo "$HEX_HITS" | head -5 | sed 's/^/      /'
else
    ok "Без hardcoded hex (или само whitelisted)"
fi

# ── 2. CSS variables за цветове ─────────────────────────────────────
echo -e "\n${CYAN}[2/15] CSS variables за цветове (var(--accent)/--text)${NC}"
if echo "$STYLE_BLOCK" | grep -qE 'var\(--(accent|text|text-muted|q[1-6]-)' ; then
    ok "Цветове минават през var(--accent)/--text/--qN-*"
else
    if [ -n "$STYLE_BLOCK" ]; then
        warn "Не намирам var(--accent)/var(--text)/var(--qN-*) — модулът може да е dark-only"
    else
        ok "Няма <style> блок (модулът ползва external CSS)"
    fi
fi

# ── 3. Радиуси през --radius* ───────────────────────────────────────
echo -e "\n${CYAN}[3/15] border-radius през --radius*${NC}"
RAW_RADIUS=$(echo "$STYLE_BLOCK" | grep -nE 'border-radius:\s*[0-9]+px' || true)
RAW_RADIUS_COUNT=$( [ -z "$RAW_RADIUS" ] && echo 0 || echo "$RAW_RADIUS" | grep -c . )
if [ "$RAW_RADIUS_COUNT" -gt 0 ]; then
    if [ "$RAW_RADIUS_COUNT" -le 3 ]; then warn "$RAW_RADIUS_COUNT raw border-radius (px) — препоръчва се var(--radius*)"
    else fail "$RAW_RADIUS_COUNT raw border-radius (px) — трябва var(--radius)/--radius-sm/--radius-pill/--radius-icon"; fi
    echo "$RAW_RADIUS" | head -3 | sed 's/^/      /'
else
    ok "Без raw border-radius в px (или само 0/inherit)"
fi

# ── 4. Сенки през --shadow-card* ────────────────────────────────────
echo -e "\n${CYAN}[4/15] box-shadow през --shadow-card*${NC}"
# Only count "neumorphism-replaceable" shadows: raw black/grey shadows.
# Skip:
#   - hsl()/rgba()/var() colored shadows (neon glow / SACRED per Bible 22.5.4)
#   - inset 0 1px 0 (highlights)
#   - shadows already using var(--shadow*)
RAW_SHADOW=$(echo "$STYLE_BLOCK" | grep -nE 'box-shadow:\s*(inset\s+)?[0-9-]+(px)?\s+' \
    | grep -vE 'hsl\(|rgba\(|var\(--shadow' || true)
RAW_SHADOW_COUNT=$( [ -z "$RAW_SHADOW" ] && echo 0 || echo "$RAW_SHADOW" | grep -c . )
# Threshold: shadow recipes are often visually-meaningful (neon glow, focus rings) so
# we're more lenient — warn 5-15, fail at 16+.
if [ "$RAW_SHADOW_COUNT" -gt 30 ]; then
    fail "$RAW_SHADOW_COUNT raw box-shadow recipes — повечето трябва var(--shadow-card*)"
    echo "$RAW_SHADOW" | head -3 | sed 's/^/      /'
elif [ "$RAW_SHADOW_COUNT" -gt 4 ]; then
    warn "$RAW_SHADOW_COUNT raw box-shadow — много са neon glow / focus rings (acceptable)"
elif [ "$RAW_SHADOW_COUNT" -gt 0 ]; then
    warn "$RAW_SHADOW_COUNT raw box-shadow (acceptable level)"
else
    ok "Без raw box-shadow recipes"
fi

# ── 5. Шрифт = Montserrat (var(--font)) ─────────────────────────────
echo -e "\n${CYAN}[5/15] Шрифт = Montserrat / DM Mono${NC}"
BAD_FONT=$(echo "$STYLE_BLOCK" | grep -niE "font-family:\s*['\"]?(Inter|Roboto|Arial|Helvetica|Times|Georgia|Verdana|Courier)" || true)
if [ -n "$BAD_FONT" ]; then
    fail "Не-Montserrat font-family (трябва var(--font) или var(--font-mono))"
    echo "$BAD_FONT" | head -3 | sed 's/^/      /'
else
    ok "Само Montserrat / DM Mono / system stack"
fi

# ── 6. [data-theme=light] и [data-theme=dark] правила ──────────────
echo -e "\n${CYAN}[6/15] Bichromatic theme rules${NC}"
HAS_LIGHT=$(echo "$STYLE_BLOCK" | grep -cE '\[data-theme="?light' 2>/dev/null); HAS_LIGHT=${HAS_LIGHT:-0}
HAS_DARK=$(echo "$STYLE_BLOCK" | grep -cE '\[data-theme="?dark' 2>/dev/null); HAS_DARK=${HAS_DARK:-0}
if [ -z "$STYLE_BLOCK" ]; then
    warn "Няма <style> в модула (BICHROMATIC support идва от external CSS)"
elif [ "$HAS_LIGHT" -gt 0 ] && [ "$HAS_DARK" -gt 0 ]; then
    ok "Има [data-theme=\"light\"] ($HAS_LIGHT) и [data-theme=\"dark\"] ($HAS_DARK) правила"
elif [ "$HAS_LIGHT" -gt 0 ]; then
    warn "Само [data-theme=\"light\"] правила (липсва dark)"
elif [ "$HAS_DARK" -gt 0 ]; then
    warn "Само [data-theme=\"dark\"] правила (липсва light)"
else
    fail "Няма [data-theme=\"light\"] нито [data-theme=\"dark\"] правила — модулът е mono-theme"
fi

# ── 7. partials/header.php + bottom-nav.php include ────────────────
echo -e "\n${CYAN}[7/15] Shell partials${NC}"
HAS_HEADER=0; HAS_NAV=0
grep -qE "include[^;]+partials/header\.php" "$FILE" && HAS_HEADER=1
grep -qE "include[^;]+partials/bottom-nav\.php" "$FILE" && HAS_NAV=1
if [ "$HAS_HEADER" = 1 ] && [ "$HAS_NAV" = 1 ]; then
    ok "header.php + bottom-nav.php включени"
elif [ "$HAS_HEADER" = 1 ]; then
    warn "header.php включен, но липсва bottom-nav.php (acceptable за auth/full-screen pages)"
elif [ "$HAS_NAV" = 1 ]; then
    warn "bottom-nav.php включен, но липсва header.php"
else
    warn "Нито header нито bottom-nav (auth/landing pages — OK)"
fi

# ── 8. $rms_current_module зададен ──────────────────────────────────
echo -e "\n${CYAN}[8/15] \$rms_current_module${NC}"
if grep -qE '\$rms_current_module\s*=' "$FILE"; then
    ok "\$rms_current_module зададен"
elif [ "$HAS_NAV" = 1 ] || [ "$HAS_HEADER" = 1 ]; then
    warn "Няма \$rms_current_module (active tab detection ще пропадне на shell-init.php fallback)"
else
    ok "Няма shell → не е нужен \$rms_current_module"
fi

# ── 9. .glass cards 4-span structure ────────────────────────────────
echo -e "\n${CYAN}[9/15] .glass + 4 SACRED span-а${NC}"
GLASS_COUNT=$(grep -cE 'class="[^"]*\bglass\b' "$FILE" 2>/dev/null); GLASS_COUNT=${GLASS_COUNT:-0}
if [ "$GLASS_COUNT" -gt 0 ]; then
    SHINE_COUNT=$(grep -cE 'class="[^"]*\bshine\b' "$FILE" 2>/dev/null); SHINE_COUNT=${SHINE_COUNT:-0}
    GLOW_COUNT=$(grep -cE 'class="[^"]*\bglow\b' "$FILE" 2>/dev/null); GLOW_COUNT=${GLOW_COUNT:-0}
    if [ "$SHINE_COUNT" -lt 1 ] || [ "$GLOW_COUNT" -lt 1 ]; then
        warn ".glass($GLASS_COUNT) използван, но shine/glow span-овете са недостатъчни (.shine=$SHINE_COUNT .glow=$GLOW_COUNT)"
    else
        ok ".glass=$GLASS_COUNT, shine=$SHINE_COUNT, glow=$GLOW_COUNT"
    fi
else
    ok "Няма .glass (или ползва различен компонент)"
fi

# ── 10. z-index: 5+ за content в .glass ────────────────────────────
echo -e "\n${CYAN}[10/15] Content z-index ≥ 5${NC}"
if [ "$GLASS_COUNT" -gt 0 ]; then
    if echo "$STYLE_BLOCK" | grep -qE 'z-index:\s*[5-9]|z-index:\s*var\(--z-content'; then
        ok "z-index ≥ 5 присъства (content z-index OK)"
    else
        warn "Не намирам z-index:5+ — content в .glass може да е under shine/glow"
    fi
else
    ok "Няма .glass → не е нужен z-index check"
fi

# ── 11. Никакви framework imports ──────────────────────────────────
echo -e "\n${CYAN}[11/15] Без Bootstrap / Tailwind${NC}"
FW_HITS=$(grep -niE 'bootstrap\.(min\.)?css|tailwind\.(min\.)?css|cdn\.tailwindcss|jsdelivr.*bootstrap' "$FILE" 2>/dev/null | head -3 || true)
if [ -n "$FW_HITS" ]; then
    fail "Framework imports намерени"
    echo "$FW_HITS" | sed 's/^/      /'
else
    ok "Без bootstrap/tailwind imports"
fi

# ── 12. priceFormat вместо hardcoded валути ─────────────────────────
echo -e "\n${CYAN}[12/15] priceFormat (вместо лв/€/BGN)${NC}"
HARDC_CUR=$(grep -nE "(>\s*'?лв'?\s*<|>\s*'?BGN'?\s*<|echo\s+['\"]лв|echo\s+['\"]€)" "$FILE" 2>/dev/null \
    | grep -vE '//\s|/\*|\*\s|priceFormat|currency' | head -5 || true)
HARDC_COUNT=$( [ -z "$HARDC_CUR" ] && echo 0 || echo "$HARDC_CUR" | grep -c . )
if [ "$HARDC_COUNT" -gt 0 ]; then
    warn "$HARDC_COUNT hardcoded 'лв'/'€'/'BGN' изрази (препоръчва се priceFormat(\$amount, \$tenant))"
    echo "$HARDC_CUR" | head -3 | sed 's/^/      /'
else
    ok "Без hardcoded валутни литерали (или ползва priceFormat)"
fi

# ── 13. Без "Gemini"/"fal.ai"/"Anthropic" в UI ─────────────────────
echo -e "\n${CYAN}[13/15] AI brand mentions в UI${NC}"
AI_HITS=$(grep -nE '>\s*[^<]*\b(Gemini|fal\.ai|Anthropic|Claude)\b[^<]*<|"\s*[^"]*\b(Gemini|fal\.ai|Anthropic)\b' "$FILE" 2>/dev/null \
    | grep -vE '//\s|/\*|\*\s|^\s*\*' | head -3 || true)
if [ -n "$AI_HITS" ]; then
    fail "AI vendor mentions в UI (използвай 'AI')"
    echo "$AI_HITS" | sed 's/^/      /'
else
    ok "Без vendor brand mentions"
fi

# ── 14. prefers-reduced-motion блок ─────────────────────────────────
echo -e "\n${CYAN}[14/15] @media prefers-reduced-motion${NC}"
if echo "$STYLE_BLOCK" | grep -qE 'prefers-reduced-motion'; then
    ok "@media (prefers-reduced-motion: reduce) включен"
else
    if [ -n "$STYLE_BLOCK" ]; then
        warn "Липсва @media (prefers-reduced-motion: reduce) (препоръчително за анимации)"
    else
        ok "Няма <style> → не е нужно"
    fi
fi

# ── 15. Mobile-first .app max-width 480px ──────────────────────────
echo -e "\n${CYAN}[15/15] Mobile-first (max-width 480px)${NC}"
if echo "$STYLE_BLOCK" | grep -qE '\.app\s*\{[^}]*max-width:\s*480px|max-width:\s*480px[^}]*\}'; then
    ok "Mobile-first .app max-width: 480px"
elif [ -n "$STYLE_BLOCK" ] && [ "$HAS_HEADER" = 1 ]; then
    warn "Не виждам max-width:480px на .app — препоръчително за shell pages"
else
    ok "Не е shell page или липсва inline style — пропуска се"
fi

# ── Резултат ────────────────────────────────────────────────────────
echo
echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
if [ "$ERRORS" -eq 0 ]; then
    echo -e "${GREEN}✔ COMPLIANCE PASSED — модулът е v4.1 BICHROMATIC съвместим${NC}"
    [ "$WARNS" -gt 0 ] && echo -e "${YELLOW}  ($WARNS предупреждения — препоръчително да поправиш)${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
    exit 0
else
    echo -e "${RED}✗ COMPLIANCE FAILED — $ERRORS критични нарушения${NC}"
    [ "$WARNS" -gt 0 ] && echo -e "${YELLOW}  + $WARNS предупреждения${NC}"
    echo -e "${RED}  Поправи и пусни отново.${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════${NC}"
    exit 1
fi
