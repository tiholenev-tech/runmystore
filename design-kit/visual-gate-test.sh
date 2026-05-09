#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
# VISUAL GATE TEST WRAPPER v1.1
# ═══════════════════════════════════════════════════════════════════════
# Стартира 4 теста срещу auth fixture-а:
#   A. --auth=admin   + life-board.php  vs P10_lesny_mode.html      → expect PASS
#   B. --auth=admin   + chat.php        vs P11_detailed_mode.html   → expect PASS
#   C. (no --auth)    + chat.php        vs P11_detailed_mode.html   → expect FAIL (login redirect)
#   D. --auth=seller  + life-board.php  vs P10_lesny_mode.html      → expect PASS
#
# Изпраща изхода и експлицитен expected vs actual repor в stdout.
# Exit 0 ако всички 4 actual == expected; иначе exit 1.
#
# ВАЖНО: този wrapper НЕ модифицира production файлове или production session
# storage. Всеки тест работи в isolated session_dir под ./visual-gate-test-runs/.
# ═══════════════════════════════════════════════════════════════════════

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
GATE="${SCRIPT_DIR}/visual-gate.sh"
RUNS_ROOT="${REPO_DIR}/visual-gate-test-runs"

mkdir -p "$RUNS_ROOT"

R='\033[0;31m'; G='\033[0;32m'; Y='\033[1;33m'; B='\033[0;34m'; N='\033[0m'

P10="${REPO_DIR}/mockups/P10_lesny_mode.html"
P11="${REPO_DIR}/mockups/P11_detailed_mode.html"
LIFE="${REPO_DIR}/life-board.php"
CHAT="${REPO_DIR}/chat.php"

for f in "$GATE" "$P10" "$P11" "$LIFE" "$CHAT"; do
    [ -e "$f" ] || { echo -e "${R}FATAL${N}: missing $f" >&2; exit 1; }
done

PASS_CNT=0
FAIL_CNT=0
RESULTS=()

# Прави out-of-band HTTP probe срещу rewrite файла, за да вземе чист сигнал
# за това какво PHP връща (преди chromium да го рендерира). Ползва ИЗОЛИРАН
# php -S процес на временен port 8766, така че не интерферира с visual-gate.sh.
probe_rewrite() {
    local auth="$1" rewrite="$2"
    local doc_root; doc_root="$(cd "$(dirname "$rewrite")" && pwd)"
    local rel; rel="$(basename "$rewrite")"
    local probe_port=8766
    local env_args=()
    local router_args=()
    if [ "$auth" != "none" ]; then
        env_args=(env VG_AUTH=1)
        case "$auth" in
            admin)  env_args+=(VG_USER_ID=1 VG_ROLE=admin  VG_TENANT_ID=1 VG_STORE_ID=1) ;;
            seller) env_args+=(VG_USER_ID=2 VG_ROLE=seller VG_TENANT_ID=1 VG_STORE_ID=1) ;;
        esac
        router_args=("${SCRIPT_DIR}/visual-gate-router.php")
    else
        env_args=(env)
    fi
    "${env_args[@]}" php -S "127.0.0.1:${probe_port}" -t "$doc_root" "${router_args[@]}" \
        >/dev/null 2>&1 &
    local probe_pid=$!
    sleep 0.5
    local probe_body probe_code
    probe_body=$(curl -s -m 6 "http://127.0.0.1:${probe_port}/${rel}" 2>/dev/null)
    probe_code=$(curl -s -o /dev/null -w '%{http_code}' -m 6 \
        "http://127.0.0.1:${probe_port}/${rel}" 2>/dev/null)
    kill "$probe_pid" 2>/dev/null
    wait "$probe_pid" 2>/dev/null

    # Класифицирай отговора
    if [ "$probe_code" = "302" ]; then
        echo "auth-wall (302 → login)"
        return
    fi
    if [ "$probe_code" = "500" ] || [ -z "$probe_body" ]; then
        echo "HTTP ${probe_code:-?} (PHP fatal — past auth, blocked downstream)"
        return
    fi
    if echo "$probe_body" | grep -qi "Location:\s*login"; then
        echo "auth-wall (Location: login)"
        return
    fi
    if echo "$probe_body" | grep -qiE "Database configuration|configuration not"; then
        echo "DB config block (past auth)"
        return
    fi
    if echo "$probe_body" | grep -qi "<form[^>]*action=[\"']?login"; then
        echo "auth-wall (login form rendered)"
        return
    fi
    echo "HTTP ${probe_code} content (size=$(echo -n "$probe_body" | wc -c)B)"
}

run_test() {
    local label="$1" expected="$2" auth="$3" mockup="$4" rewrite="$5"
    local sess="${RUNS_ROOT}/$(date +%Y%m%d_%H%M%S)_${label}"
    mkdir -p "$sess"

    echo ""
    echo -e "${B}─── TEST ${label} ───${N}  expect=${expected}  auth=${auth}"
    echo "  mockup:  $(basename "$mockup")"
    echo "  rewrite: $(basename "$rewrite")"
    echo "  session: $sess"

    # Out-of-band probe ПЪРВО — отговаря на въпроса „какво PHP връща" преди
    # тежкия chromium-рендерен loop. Това е истинският тест на fixture-а:
    # auth-wall (302) трябва да изчезне когато auth=admin/seller.
    local probe; probe=$(probe_rewrite "$auth" "$rewrite")
    echo "  probe:   $probe"

    local args=()
    [ "$auth" != "none" ] && args+=("--auth=${auth}")
    args+=("$mockup" "$rewrite" "$sess")

    bash "$GATE" "${args[@]}" > "${sess}/run.log" 2>&1
    local code=$?
    local actual
    case "$code" in
        0) actual="PASS" ;;
        2) actual="FAIL" ;;
        *) actual="ERROR(${code})" ;;
    esac

    # Истинският сигнал за fixture-а: дали probe-ът показа auth-wall или вече сме отвъд.
    local fixture_status="?"
    case "$probe" in
        auth-wall*) fixture_status="auth-wall (no fixture / fixture skipped)" ;;
        *)          fixture_status="past auth (fixture worked)" ;;
    esac

    local verdict
    if [ "$actual" = "$expected" ]; then
        verdict="${G}MATCH${N}"
        PASS_CNT=$((PASS_CNT+1))
    else
        verdict="${R}MISMATCH${N}"
        FAIL_CNT=$((FAIL_CNT+1))
    fi
    echo -e "  → expected=${expected}  actual=${actual}  ${verdict}"
    echo "  → fixture: ${fixture_status}"
    RESULTS+=("${label}: expected=${expected} actual=${actual} | probe=${probe} | ${fixture_status}")
}

echo -e "${B}═══ VISUAL GATE TEST WRAPPER (4 cases) ═══${N}"

run_test "A_admin_lifeboard"  "PASS" "admin"  "$P10" "$LIFE"
run_test "B_admin_chat"       "PASS" "admin"  "$P11" "$CHAT"
run_test "C_noauth_chat"      "FAIL" "none"   "$P11" "$CHAT"
run_test "D_seller_lifeboard" "PASS" "seller" "$P10" "$LIFE"

echo ""
echo -e "${B}═══ SUMMARY ═══${N}"
for r in "${RESULTS[@]}"; do echo "  $r"; done
echo ""
echo -e "  ${G}match: ${PASS_CNT}${N}    ${R}mismatch: ${FAIL_CNT}${N}    total: $((PASS_CNT+FAIL_CNT))"
echo ""

if [ "$FAIL_CNT" -eq 0 ]; then
    echo -e "${G}═══ ALL TESTS MATCH EXPECTED ═══${N}"
    exit 0
fi

cat <<'NOTE'
═══ NOTE ═══
  visual-gate.sh PASS изисква pixel/DOM съответствие между rendered .php и
  mockup-а. Auth fixture-ът свърши работата си щом per-test "fixture: past auth"
  се показва. PASS на самия gate допълнително изисква DB fixtures
  (test tenant/store rows) — НЕ е в обхвата на v1.1.
  Истински signal: probe резултатите за всеки test.
NOTE
exit 1
