#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# VISUAL GATE v1.0 — escalating-tolerance auto-retry за design rewrites
# ═══════════════════════════════════════════════════════════════════════
# Реализира 4-те check-а от VISUAL_GATE_SPEC §1:
#   1. DOM structure diff (dom-extract.py)
#   2. CSS classes coverage (css-coverage.sh)
#   3. Pixel diff (chromium screenshot + ImageMagick compare)
#   4. Element position diff (chromium + element-positions.js)
#
# Loop с escalating tolerances:
#   Iter 1: DOM 1% / Pixel 3% / Position 20px
#   Iter 2: DOM 2% / Pixel 4% / Position 20px
#   Iter 3: DOM 2% / Pixel 4% / Position 25px
#   Iter 4: DOM 3% / Pixel 5% / Position 30px
#   Iter 5: DOM 3% / Pixel 5% / Position 30px
#
# Pass = ALL 4 checks pass на даден iter.
# CSS липсващи class threshold: >=2 → FAIL (виж css-coverage.sh).
#
# Usage:
#   visual-gate.sh [--auth=admin|seller|none] <mockup_path> <rewrite_path> <session_dir> [backup_path]
#
#   --auth=admin   priming за login-protected .php (вижте AUTH FIXTURE по-долу)
#   --auth=seller  same, но role=seller (за simple-mode rendering паttern-и)
#   --auth=none    default; backwards compatible — без auth fixture
#
#   mockup_path: .html файл (mockup; ground truth)
#   rewrite_path: .php или .html (rewrite; testee)
#   session_dir: directory за артефакти (positions, screenshots, logs)
#   backup_path: optional .bak файл за auto-rollback при iter 5 fail
#
# AUTH FIXTURE (v1.1):
#   Когато --auth != none, php -S се стартира с visual-gate-router.php като
#   router script + VG_AUTH=1, така че auth-fixture.php се require-ва ПРЕДИ
#   target-ния .php файл. $_SESSION се set-ва само в isolated php -S процеса
#   (own session storage). Production session storage не се пипа.
#   Defaults: admin=user_id=1/role=admin/tenant=1/store=1;
#             seller=user_id=2/role=seller/tenant=1/store=1.
#   Override чрез env vars: VG_USER_ID, VG_ROLE, VG_TENANT_ID, VG_STORE_ID.
#
# Exit codes:
#   0 = PASS на някой iter (1-5)
#   1 = bad args / missing deps
#   2 = ALL 5 iters FAIL (auto-rollback fired ако backup_path е даден)
# ═══════════════════════════════════════════════════════════════════════

set -u

# ─── config ────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="${SCRIPT_DIR}/visual-gate-log.json"

# S136 PHASE A — source persistent VG_* defaults (e.g. fixtures DB targeting).
# `set -a` auto-exports any vars defined in the file so php -S inherits them.
# Already-set env vars from the parent shell take precedence (set -a is idempotent
# but `.` honors prior exports). File is optional for backwards compatibility.
ENV_FILE="${SCRIPT_DIR}/visual-gate.env"
if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$ENV_FILE"
    set +a
fi

# Snap chromium wrapper не работи в non-systemd-user сесии
# (cgroup mismatch). Ползвай direct binary path.
CHROMIUM_BIN="/snap/chromium/current/usr/lib/chromium-browser/chrome"
[ ! -x "$CHROMIUM_BIN" ] && CHROMIUM_BIN="$(command -v chromium-browser || command -v chromium || true)"

VIEWPORT_W=375
VIEWPORT_H=812

# Tolerances per iter (DOM_PCT PIX_PCT POS_PX)
ITER_TOL=(
    "1 1 3 20"
    "2 2 4 20"
    "3 2 4 25"
    "4 3 5 30"
    "5 3 5 30"
)

# PHP server port (за rendering на .php файлове)
PHP_PORT=8765
PHP_PID=""

# ─── colors ─────────────────────────────────────────────────────────────
R='\033[0;31m'; G='\033[0;32m'; Y='\033[1;33m'; B='\033[0;34m'; N='\033[0m'

# ─── args ───────────────────────────────────────────────────────────────
AUTH_MODE="none"
POSITIONAL=()
for arg in "$@"; do
    case "$arg" in
        --auth=*)
            AUTH_MODE="${arg#--auth=}"
            ;;
        --auth)
            echo "FATAL: --auth requires =value (e.g. --auth=admin)" >&2
            exit 1
            ;;
        *)
            POSITIONAL+=("$arg")
            ;;
    esac
done
set -- "${POSITIONAL[@]}"

MOCKUP="${1:-}"
REWRITE="${2:-}"
SESSION_DIR="${3:-}"
BACKUP_PATH="${4:-}"

case "$AUTH_MODE" in
    none|admin|seller) ;;
    *) echo "FATAL: --auth must be one of: none, admin, seller (got: $AUTH_MODE)" >&2; exit 1 ;;
esac

if [ -z "$MOCKUP" ] || [ -z "$REWRITE" ] || [ -z "$SESSION_DIR" ]; then
    echo "Usage: $0 [--auth=admin|seller|none] <mockup_path> <rewrite_path> <session_dir> [backup_path]" >&2
    exit 1
fi
[ ! -f "$MOCKUP" ] && { echo "FATAL: mockup not found: $MOCKUP" >&2; exit 1; }
[ ! -f "$REWRITE" ] && { echo "FATAL: rewrite not found: $REWRITE" >&2; exit 1; }
mkdir -p "$SESSION_DIR"

# ─── deps check ─────────────────────────────────────────────────────────
need() { command -v "$1" >/dev/null 2>&1 || { echo "FATAL: missing dep: $1" >&2; exit 1; }; }
need python3
need compare
need convert
[ -x "$CHROMIUM_BIN" ] || { echo "FATAL: chromium binary not executable: $CHROMIUM_BIN" >&2; exit 1; }
python3 -c "import bs4" 2>/dev/null || { echo "FATAL: python3-bs4 not installed" >&2; exit 1; }

# ─── cleanup ────────────────────────────────────────────────────────────
cleanup() {
    [ -n "$PHP_PID" ] && kill "$PHP_PID" 2>/dev/null || true
}
trap cleanup EXIT

# ─── helpers ────────────────────────────────────────────────────────────

# Стартира PHP вграден сървър (само ако не работи вече) за рендериране на .php
# Когато AUTH_MODE != none, ползва visual-gate-router.php като router script
# и ENV_AUTH_FLAGS като env (set от apply_auth_mode по-горе).
ensure_php_server() {
    local doc_root="$1"
    if [ -n "$PHP_PID" ] && kill -0 "$PHP_PID" 2>/dev/null; then return 0; fi
    local router_args=()
    if [ "$AUTH_MODE" != "none" ]; then
        local router="${SCRIPT_DIR}/visual-gate-router.php"
        [ -f "$router" ] || { echo "FATAL: router missing: $router" >&2; return 1; }
        router_args=("$router")
    fi
    # env е вече exported от apply_auth_mode (VG_AUTH, VG_USER_ID, ...)
    php -S "127.0.0.1:${PHP_PORT}" -t "$doc_root" "${router_args[@]}" >/dev/null 2>&1 &
    PHP_PID=$!
    # дай малко време да стартира
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        sleep 0.2
        curl -sf -o /dev/null "http://127.0.0.1:${PHP_PORT}/" 2>/dev/null && return 0
        # 404 на /, но сървърът е up
        curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${PHP_PORT}/" 2>/dev/null | grep -qE '^[0-9]+$' && return 0
    done
    return 1
}

# Set-ва VG_* env vars според AUTH_MODE. Викнат еднократно от main преди loop.
apply_auth_mode() {
    case "$AUTH_MODE" in
        none)
            unset VG_AUTH VG_USER_ID VG_ROLE VG_TENANT_ID VG_STORE_ID
            return
            ;;
        admin)
            export VG_AUTH=1
            export VG_USER_ID="${VG_USER_ID:-1}"
            export VG_ROLE="${VG_ROLE:-admin}"
            export VG_TENANT_ID="${VG_TENANT_ID:-1}"
            export VG_STORE_ID="${VG_STORE_ID:-1}"
            ;;
        seller)
            export VG_AUTH=1
            export VG_USER_ID="${VG_USER_ID:-2}"
            export VG_ROLE="${VG_ROLE:-seller}"
            export VG_TENANT_ID="${VG_TENANT_ID:-1}"
            export VG_STORE_ID="${VG_STORE_ID:-1}"
            ;;
    esac
    echo "auth fixture: VG_AUTH=1 user_id=${VG_USER_ID} role=${VG_ROLE} tenant=${VG_TENANT_ID} store=${VG_STORE_ID}"
}

# Връща URL за рендериране на даден файл
url_for_file() {
    local path="$1"
    local abs; abs=$(readlink -f "$path")
    case "$path" in
        *.php)
            local doc_root; doc_root=$(dirname "$abs")
            # ако файлът е в sub-dir, използвай първи родителски ancestor който има пълно дърво
            # за prosto: doc_root е directory-то на файла; за по-добра поддръжка inject пълния worktree
            doc_root="${PHP_DOC_ROOT:-$doc_root}"
            ensure_php_server "$doc_root" || { echo "" ; return 1; }
            local rel="${abs#$doc_root/}"
            echo "http://127.0.0.1:${PHP_PORT}/${rel}"
            ;;
        *)
            echo "file://${abs}"
            ;;
    esac
}

# Inject element-positions.js преди </body> и запиши във $2
inject_positions_script() {
    local src="$1" dst="$2"
    python3 - "$src" "$dst" "${SCRIPT_DIR}/element-positions.js" <<'PYEOF'
import sys
src, dst, js_path = sys.argv[1], sys.argv[2], sys.argv[3]
content = open(src, 'r', encoding='utf-8', errors='replace').read()
js = open(js_path, 'r', encoding='utf-8').read()
inj = '<script>\n' + js + '\n</script>'
if '</body>' in content:
    content = content.replace('</body>', inj + '</body>', 1)
else:
    content += inj
open(dst, 'w', encoding='utf-8').write(content)
PYEOF
}

# Извлече JSON масив от <pre id="__visual_gate_positions__"> в dump file
extract_positions() {
    local dump="$1" out="$2"
    python3 - "$dump" "$out" <<'PYEOF'
import re, sys, json
data = open(sys.argv[1], 'r', encoding='utf-8', errors='replace').read()
m = re.search(r'<pre id="__visual_gate_positions__"[^>]*>([^<]*)</pre>', data, re.DOTALL)
if not m:
    open(sys.argv[2], 'w').write('[]')
    sys.exit(2)
try:
    arr = json.loads(m.group(1))
except Exception as e:
    print("ERR parse positions:", e, file=sys.stderr)
    open(sys.argv[2], 'w').write('[]')
    sys.exit(2)
open(sys.argv[2], 'w').write(json.dumps(arr))
PYEOF
}

# Render с chromium → screenshot + dump
render_target() {
    local label="$1" target_path="$2" out_dir="$3"
    local instr="${out_dir}/${label}_instr.html"
    local dump="${out_dir}/${label}_dump.html"
    local png="${out_dir}/${label}.png"
    local positions="${out_dir}/${label}_positions.json"

    # Inject positions script (само за .html / .php source)
    inject_positions_script "$target_path" "$instr"

    # Ако e .php → стартирай php -S и render през HTTP
    # Иначе file:// URL
    local url
    case "$target_path" in
        *.php)
            local abs; abs=$(readlink -f "$target_path")
            local doc_root="${PHP_DOC_ROOT:-$(dirname "$abs")}"
            # копирай instrumented в doc_root за да се рендерира заедно с includes
            local rel="${abs#$doc_root/}"
            local instr_in_root="${doc_root}/__visual_gate_${label}_instr.php"
            cp "$instr" "$instr_in_root"
            ensure_php_server "$doc_root" || { rm -f "$instr_in_root"; echo "RENDER_FAIL"; return 1; }
            url="http://127.0.0.1:${PHP_PORT}/$(basename "$instr_in_root")"
            ;;
        *)
            url="file://$(readlink -f "$instr")"
            ;;
    esac

    local user_data; user_data=$(mktemp -d)
    # dump-dom за DOM + positions
    "$CHROMIUM_BIN" --headless --no-sandbox --disable-gpu --disable-dev-shm-usage \
        --user-data-dir="$user_data" --window-size=${VIEWPORT_W},${VIEWPORT_H} \
        --hide-scrollbars --virtual-time-budget=5000 --run-all-compositor-stages-before-draw \
        --dump-dom "$url" 2>/dev/null > "$dump" || true
    # screenshot отделен run (--screenshot не може едновременно с --dump-dom)
    "$CHROMIUM_BIN" --headless --no-sandbox --disable-gpu --disable-dev-shm-usage \
        --user-data-dir="$user_data" --window-size=${VIEWPORT_W},${VIEWPORT_H} \
        --hide-scrollbars --virtual-time-budget=5000 --run-all-compositor-stages-before-draw \
        --screenshot="$png" "$url" 2>/dev/null || true
    rm -rf "$user_data"

    # cleanup на instrumented копие в php doc_root
    case "$target_path" in
        *.php)
            local abs; abs=$(readlink -f "$target_path")
            local doc_root="${PHP_DOC_ROOT:-$(dirname "$abs")}"
            rm -f "${doc_root}/__visual_gate_${label}_instr.php"
            ;;
    esac

    extract_positions "$dump" "$positions" || true

    # върни succes ако имаме png
    [ -s "$png" ] && return 0 || return 1
}

# Compute DOM diff % между два JSON файла
dom_diff_pct() {
    local a="$1" b="$2"
    python3 - "$a" "$b" <<'PYEOF'
import json, sys
a = json.load(open(sys.argv[1]))
b = json.load(open(sys.argv[2]))
def sig(e):
    return (e['tag'], tuple(e['classes_sorted']), e['id'], e['parent_selector'])
sa = [sig(e) for e in a]
sb = [sig(e) for e in b]
from collections import Counter
ca, cb = Counter(sa), Counter(sb)
added = sum((cb - ca).values())
removed = sum((ca - cb).values())
total = max(len(sa), len(sb), 1)
pct = min(100.0, (added + removed) / total * 100)
print(f"{pct:.2f}")
PYEOF
}

# Pixel diff % между две PNG
pixel_diff_pct() {
    local a="$1" b="$2" diff_out="$3"
    if [ ! -s "$a" ] || [ ! -s "$b" ]; then echo "100.00"; return; fi
    # ImageMagick compare → AE (absolute error) за брой различни pixel-и
    local ae; ae=$(compare -metric AE -fuzz 10% "$a" "$b" "$diff_out" 2>&1 | tail -1 | awk '{print $1}')
    [ -z "$ae" ] && ae=999999
    # Брой total pixels
    local total; total=$(identify -format "%w*%h\n" "$a" 2>/dev/null | head -1 | bc)
    [ -z "$total" ] || [ "$total" -eq 0 ] && { echo "100.00"; return; }
    python3 -c "print(f'{$ae / $total * 100:.2f}')"
}

# Position diff: брой elements преместени > threshold_px
position_diff_count() {
    local a="$1" b="$2" threshold="$3"
    python3 - "$a" "$b" "$threshold" <<'PYEOF'
import json, sys
a = json.load(open(sys.argv[1]))
b = json.load(open(sys.argv[2]))
threshold = float(sys.argv[3])
# index по selector → list of (x,y)
from collections import defaultdict
idx_a, idx_b = defaultdict(list), defaultdict(list)
for e in a: idx_a[e['selector']].append((e['x'], e['y']))
for e in b: idx_b[e['selector']].append((e['x'], e['y']))
moved = 0
for sel, pos_a_list in idx_a.items():
    pos_b_list = idx_b.get(sel, [])
    for i, (xa, ya) in enumerate(pos_a_list):
        if i >= len(pos_b_list):
            moved += 1  # елемент липсва в rewrite
            continue
        xb, yb = pos_b_list[i]
        d = ((xa-xb)**2 + (ya-yb)**2) ** 0.5
        if d > threshold:
            moved += 1
print(moved)
PYEOF
}

# Append entry в visual-gate-log.json
log_append() {
    local entry="$1"
    if [ ! -s "$LOG_FILE" ]; then echo "[]" > "$LOG_FILE"; fi
    python3 - "$LOG_FILE" "$entry" <<'PYEOF'
import json, sys
log_file, entry_json = sys.argv[1], sys.argv[2]
try:
    arr = json.load(open(log_file))
    if not isinstance(arr, list): arr = []
except Exception:
    arr = []
arr.append(json.loads(entry_json))
open(log_file, 'w').write(json.dumps(arr, indent=2, ensure_ascii=False))
PYEOF
}

# ─── main loop ──────────────────────────────────────────────────────────

echo -e "${B}═══════════════════════════════════════════════════════════════${N}"
echo -e "${B}  VISUAL GATE v1.0${N}"
echo -e "${B}═══════════════════════════════════════════════════════════════${N}"
echo "Mockup:  $MOCKUP"
echo "Rewrite: $REWRITE"
echo "Session: $SESSION_DIR"
echo ""

# Auto-detect PHP doc root: ако rewrite е .php, ползвай родителското дърво.
if [ -z "${PHP_DOC_ROOT:-}" ]; then
    case "$REWRITE" in
        *.php)
            export PHP_DOC_ROOT="$(cd "$(dirname "$REWRITE")" && pwd)"
            ;;
    esac
fi

apply_auth_mode

# S136.ALIGN: derive VG_MODULE from rewrite filename so the included partials
# (shell-init.php) can resolve $rms_current_module correctly even though
# the rewrite is rendered through an instrumented temp file. Without this,
# bottom-nav active-tab detection misfires (no .active class), causing
# DOM diff false positives.
if [ -z "${VG_MODULE:-}" ]; then
    case "$REWRITE" in
        *.php)
            VG_MODULE_DERIVED=$(basename "$REWRITE" .php)
            export VG_MODULE="$VG_MODULE_DERIVED"
            ;;
    esac
fi

START_TS=$(date +%s)

# Render mockup само веднъж (не се променя между iters)
echo -e "${B}→${N} Rendering mockup..."
render_target "mockup" "$MOCKUP" "$SESSION_DIR" || echo -e "${Y}⚠${N} mockup render incomplete"
MOCKUP_DOM="${SESSION_DIR}/mockup_dom.json"
# DOM diff се прави срещу chromium dump (rendered output, не raw PHP/HTML).
# Ако dump-ът е празен, fallback към raw source.
MOCKUP_DUMP="${SESSION_DIR}/mockup_dump.html"
if [ -s "$MOCKUP_DUMP" ]; then
    python3 "${SCRIPT_DIR}/dom-extract.py" --html="$MOCKUP_DUMP" --output="$MOCKUP_DOM" >/dev/null
else
    python3 "${SCRIPT_DIR}/dom-extract.py" --html="$MOCKUP" --output="$MOCKUP_DOM" >/dev/null
fi

GATE_PASS=0
PASS_ITER=0

for tol in "${ITER_TOL[@]}"; do
    read -r ITER DOM_T PIX_T POS_T <<< "$tol"
    echo ""
    echo -e "${B}─── ITER $ITER ───${N} (DOM ≤ ${DOM_T}% / Pixel ≤ ${PIX_T}% / Position moved 0 elements > ${POS_T}px)"

    ITER_DIR="${SESSION_DIR}/iter${ITER}"
    mkdir -p "$ITER_DIR"

    # Render rewrite (фреш всеки iter защото се очаква CC да го е patch-нал)
    render_target "rewrite" "$REWRITE" "$ITER_DIR" || echo -e "${Y}⚠${N} rewrite render incomplete"

    # CHECK 1 — DOM diff (на rendered dump, не на raw source)
    REWRITE_DOM="${ITER_DIR}/rewrite_dom.json"
    REWRITE_DUMP="${ITER_DIR}/rewrite_dump.html"
    if [ -s "$REWRITE_DUMP" ]; then
        python3 "${SCRIPT_DIR}/dom-extract.py" --html="$REWRITE_DUMP" --output="$REWRITE_DOM" >/dev/null
    else
        python3 "${SCRIPT_DIR}/dom-extract.py" --html="$REWRITE" --output="$REWRITE_DOM" >/dev/null
    fi
    DOM_PCT=$(dom_diff_pct "$MOCKUP_DOM" "$REWRITE_DOM")
    DOM_PASS=$(python3 -c "print(1 if $DOM_PCT <= $DOM_T else 0)")
    echo -e "  CHECK 1 DOM diff:        ${DOM_PCT}%  $([ "$DOM_PASS" = "1" ] && echo -e "${G}PASS${N}" || echo -e "${R}FAIL${N}")"

    # CHECK 2 — CSS coverage. S136 v1.2: pass rewrite_dump as 3rd arg so
    # PHP-included partial classes are visible (previously raw source-only
    # grep missed any class inside `<?php include ... ?>` blocks).
    if "${SCRIPT_DIR}/css-coverage.sh" "$MOCKUP" "$REWRITE" "$REWRITE_DUMP" > "${ITER_DIR}/css_coverage.log" 2>&1; then
        CSS_PASS=1; CSS_MSG="PASS"
    else
        CSS_PASS=0; CSS_MSG="FAIL"
    fi
    MISSING_CLASSES=$(grep -E '^  - ' "${ITER_DIR}/css_coverage.log" | sed 's/^  - //' | head -20 | python3 -c "import sys,json; print(json.dumps([l.strip() for l in sys.stdin if l.strip()]))")
    echo -e "  CHECK 2 CSS coverage:    $([ "$CSS_PASS" = "1" ] && echo -e "${G}PASS${N}" || echo -e "${R}FAIL${N}")"

    # CHECK 3 — Pixel diff
    MOCKUP_PNG="${SESSION_DIR}/mockup.png"
    REWRITE_PNG="${ITER_DIR}/rewrite.png"
    DIFF_PNG="${ITER_DIR}/diff.png"
    if [ -s "$MOCKUP_PNG" ] && [ -s "$REWRITE_PNG" ]; then
        PIX_PCT=$(pixel_diff_pct "$MOCKUP_PNG" "$REWRITE_PNG" "$DIFF_PNG")
        PIX_PASS=$(python3 -c "print(1 if $PIX_PCT <= $PIX_T else 0)")
        echo -e "  CHECK 3 Pixel diff:      ${PIX_PCT}%  $([ "$PIX_PASS" = "1" ] && echo -e "${G}PASS${N}" || echo -e "${R}FAIL${N}")"
    else
        PIX_PCT=100; PIX_PASS=0
        echo -e "  CHECK 3 Pixel diff:      ${R}FAIL${N} (render missing)"
    fi

    # CHECK 4 — Position diff
    MOCKUP_POS="${SESSION_DIR}/mockup_positions.json"
    REWRITE_POS="${ITER_DIR}/rewrite_positions.json"
    if [ -s "$MOCKUP_POS" ] && [ -s "$REWRITE_POS" ]; then
        POS_MOVED=$(position_diff_count "$MOCKUP_POS" "$REWRITE_POS" "$POS_T")
        POS_PASS=$(python3 -c "print(1 if $POS_MOVED == 0 else 0)")
        echo -e "  CHECK 4 Position diff:   ${POS_MOVED} elements moved > ${POS_T}px  $([ "$POS_PASS" = "1" ] && echo -e "${G}PASS${N}" || echo -e "${R}FAIL${N}")"
    else
        POS_MOVED=999; POS_PASS=0
        echo -e "  CHECK 4 Position diff:   ${R}FAIL${N} (positions missing)"
    fi

    # Aggregate
    if [ "$DOM_PASS" = "1" ] && [ "$CSS_PASS" = "1" ] && [ "$PIX_PASS" = "1" ] && [ "$POS_PASS" = "1" ]; then
        GATE_PASS=1
        PASS_ITER=$ITER
        echo -e "  ${G}═══ ITER $ITER PASS ═══${N}"
        break
    else
        echo -e "  ${R}═══ ITER $ITER FAIL ═══${N}"
    fi
done

END_TS=$(date +%s)
DURATION=$((END_TS - START_TS))

# ─── log entry ──────────────────────────────────────────────────────────
SESSION_NAME=$(basename "$SESSION_DIR")
LOG_ENTRY=$(python3 - <<PYEOF
import json
print(json.dumps({
    "session": "$SESSION_NAME",
    "file": "$REWRITE",
    "mockup": "$MOCKUP",
    "iter_pass": $PASS_ITER,
    "dom_diff_pct": float("$DOM_PCT"),
    "pixel_diff_pct": float("$PIX_PCT"),
    "missing_classes": $MISSING_CLASSES,
    "displaced_elements_count": $POS_MOVED,
    "timestamp": __import__("datetime").datetime.now(__import__("datetime").timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "duration_seconds": $DURATION
}))
PYEOF
)
log_append "$LOG_ENTRY"

# ─── exit ───────────────────────────────────────────────────────────────
if [ "$GATE_PASS" = "1" ]; then
    echo ""
    echo -e "${G}╔════════════════════════════════════╗${N}"
    echo -e "${G}║  VISUAL GATE PASS @ iter $PASS_ITER  ║${N}"
    echo -e "${G}╚════════════════════════════════════╝${N}"
    exit 0
fi

# ALL 5 iters failed → ROLLBACK ако backup_path е даден
echo ""
echo -e "${R}╔════════════════════════════════════╗${N}"
echo -e "${R}║  VISUAL GATE FAIL — all 5 iters     ║${N}"
echo -e "${R}╚════════════════════════════════════╝${N}"

FAIL_REPORT="${SESSION_DIR}/VISUAL_GATE_FAIL.md"
cat > "$FAIL_REPORT" <<EOF
# VISUAL_GATE_FAIL — iter 5 не премина

Mockup:  $MOCKUP
Rewrite: $REWRITE
Session: $SESSION_DIR
Date:    $(date -u +%Y-%m-%dT%H:%M:%SZ)

## Last iter results

DOM diff:        ${DOM_PCT}% (target ≤ ${DOM_T}%)
CSS coverage:    $CSS_MSG
Pixel diff:      ${PIX_PCT}% (target ≤ ${PIX_T}%)
Position diff:   $POS_MOVED elements moved > ${POS_T}px (target 0)

## Препоръка

Rewrite-ът се отклонява от mockup-а на ниво което escalating loop не може да covered.
Препоръчителни действия:
1. Прегледай ${SESSION_DIR}/iter5/diff.png за визуални разлики
2. Прегледай ${SESSION_DIR}/iter5/css_coverage.log за липсващи класове
3. Сравни DOM JSON: $MOCKUP_DOM vs ${SESSION_DIR}/iter5/rewrite_dom.json
4. Manual rewrite или handoff към Тихол.
EOF

if [ -n "$BACKUP_PATH" ] && [ -f "$BACKUP_PATH" ]; then
    echo "Auto-rollback: cp $BACKUP_PATH → $REWRITE"
    cp -p "$BACKUP_PATH" "$REWRITE"
    echo "ROLLBACK_FIRED" >> "$FAIL_REPORT"
fi

exit 2
