#!/usr/bin/env bash
# tools/stress/cron/code_analyzer.sh
#
# Cron 06:30 — пише MORNING_REPORT.md по шаблона MORNING_REPORT_TEMPLATE.md.
#
# Стъпки:
#   1. Чете latest summary JSON от tools/stress/data/dry_run_logs/
#   2. Извиква morning_report_writer.py което прилага темплейта
#   3. Commit-ва MORNING_REPORT.md в repo (ако е ROOT git)
#   4. Telegram alert при P0 escalation
#   5. POST heartbeat към admin/health.php
#
# Без auth: cron tab трябва да има CRON_HEALTH_TOKEN + TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID в env.
#
# Линк: MORNING_REPORT_TEMPLATE.md ред 168-196 (ИНСТРУКЦИИ ЗА CLAUDE CODE).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS_STRESS="$(dirname "$SCRIPT_DIR")"
REPO_ROOT="$(dirname "$(dirname "$TOOLS_STRESS")")"
DATE_STAMP=$(date +%Y-%m-%d)
LOG_FILE="/var/log/runmystore/code_analyzer_${DATE_STAMP}.log"
mkdir -p "$(dirname "$LOG_FILE")"

# ─── (a) Heartbeat helper ───
hb() {
    local status="$1"; local msg="${2:-}"
    if [[ -n "${CRON_HEALTH_TOKEN:-}" && -n "${CRON_HEALTH_URL:-}" ]]; then
        curl -sS -X POST "$CRON_HEALTH_URL" \
            -H "Authorization: Bearer $CRON_HEALTH_TOKEN" \
            --data-urlencode "cron=code_analyzer" \
            --data-urlencode "status=$status" \
            --data-urlencode "message=$msg" \
            >/dev/null 2>&1 || true
    fi
}

trap 'hb FAIL "unexpected exit at line $LINENO"' ERR

START=$(date +%s)

echo "[$(date)] code_analyzer.sh starting" | tee -a "$LOG_FILE"

# ─── (b) Извикваме morning_report_writer.py ───
PYTHON=$(command -v python3 || command -v python)
if [[ -z "$PYTHON" ]]; then
    echo "[FATAL] python3 не е намерен" | tee -a "$LOG_FILE"
    hb FAIL "python3 missing"
    exit 2
fi

OUTPUT_MD="${REPO_ROOT}/MORNING_REPORT.md"

"$PYTHON" "${TOOLS_STRESS}/cron/morning_report_writer.py" \
    --output "$OUTPUT_MD" 2>&1 | tee -a "$LOG_FILE"

# ─── (c) Commit + push (best-effort) ───
if [[ -d "${REPO_ROOT}/.git" ]]; then
    cd "$REPO_ROOT"
    if [[ -n "$(git status --short MORNING_REPORT.md 2>/dev/null)" ]]; then
        git add MORNING_REPORT.md
        git commit -m "MORNING_REPORT: ${DATE_STAMP}" >>"$LOG_FILE" 2>&1 || true
        # push e best-effort — auth може да липсва, не fail-ваме
        git push origin main >>"$LOG_FILE" 2>&1 || \
            echo "[WARN] git push fail (auth?)" | tee -a "$LOG_FILE"
    else
        echo "[INFO] MORNING_REPORT.md няма промени." | tee -a "$LOG_FILE"
    fi
fi

# ─── (d) Telegram alert при P0 escalation (Phase M2) ───
# Делегира на tools/stress/alerts/telegram_bot.py за централно rate limiting,
# severity levels и state. Fallback към inline curl ако скриптът липсва.
if grep -qE "P0 ESCALATION|3-та нощ подред|🚨" "$OUTPUT_MD" 2>/dev/null; then
    head_line=$(grep -m1 "🚨" "$OUTPUT_MD" || echo "P0 escalation")
    TG_BOT_PY="${TOOLS_STRESS}/alerts/telegram_bot.py"
    if [[ -x "$TG_BOT_PY" || -f "$TG_BOT_PY" ]]; then
        "$PYTHON" "$TG_BOT_PY" \
            --severity critical \
            --topic morning_report_p0 \
            --message "P0 escalation: ${head_line}" \
            >>"$LOG_FILE" 2>&1 || true
    elif [[ -n "${TELEGRAM_BOT_TOKEN:-}" && -n "${TELEGRAM_CHAT_ID:-}" ]]; then
        curl -sS -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
            --data-urlencode "chat_id=${TELEGRAM_CHAT_ID}" \
            --data-urlencode "text=🚨 STRESS P0: ${head_line}" \
            >>"$LOG_FILE" 2>&1 || true
    fi
fi

DUR=$(( $(date +%s) - START ))
echo "[$(date)] code_analyzer.sh done in ${DUR}s" | tee -a "$LOG_FILE"
hb OK "wrote MORNING_REPORT.md duration=${DUR}s"

exit 0
