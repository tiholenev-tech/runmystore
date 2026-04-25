"""
report_writer.py — генерира различни report формати от diagnostic_log row.

Формати:
  - markdown_report()  → пълен markdown за commit history / SESSION_HANDOFF
  - bg_email_body()    → 5-7 реда български текст за дневен email
  - telegram_critical() → кратко Telegram съобщение при Cat A/D fail
  - human_summary()    → за /admin/diagnostics.php Section 6 (на бг)
"""

import json
from datetime import datetime
from typing import Optional

import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from modules.insights.oracle_rules import label_for_topic  # noqa: E402


# ─────────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────────

def _pct(v) -> str:
    return f"{float(v):.1f}%" if v is not None else "—"


def _icon_for(rate, threshold=100.0) -> str:
    if rate is None:
        return "—"
    return "✅" if float(rate) >= threshold else "❌"


def _compare_to_previous(current: dict, previous: Optional[dict]) -> list:
    """Връща списък промени между current и previous run."""
    changes = []
    if not previous:
        return changes
    for key in ('category_a_pass_rate','category_b_pass_rate','category_c_pass_rate','category_d_pass_rate'):
        cur = current.get(key)
        prev = previous.get(key)
        if cur is not None and prev is not None and float(cur) != float(prev):
            label = key.replace('category_', '').replace('_pass_rate','').upper()
            arrow = "↑" if float(cur) > float(prev) else "↓"
            changes.append(f"Категория {label}: {_pct(prev)} → {_pct(cur)} {arrow}")
    return changes


# ─────────────────────────────────────────────────────────────────
# MARKDOWN REPORT (full)
# ─────────────────────────────────────────────────────────────────

def markdown_report(log_row: dict, previous: Optional[dict] = None) -> str:
    """Пълен markdown отчет — за SESSION_HANDOFF + dashboard expand."""
    lines = []
    lines.append(f"# Diagnostic Run #{log_row.get('id', '?')}")
    lines.append("")
    lines.append(f"- **Дата:** {log_row.get('run_timestamp', '?')}")
    lines.append(f"- **Тригер:** `{log_row.get('trigger_type', '?')}`")
    lines.append(f"- **Модул:** `{log_row.get('module_name', '?')}`")
    lines.append(f"- **Git commit:** `{log_row.get('git_commit_sha', '—')}`")
    lines.append(f"- **Сценарии:** {log_row.get('total_scenarios', 0)} ({log_row.get('passed', 0)} PASS, {log_row.get('failed', 0)} FAIL, {log_row.get('skipped', 0)} SKIP)")
    lines.append(f"- **Време:** {log_row.get('duration_seconds', 0)}s")
    lines.append("")
    lines.append("## Категории")
    lines.append("")
    lines.append("| Категория | Pass rate | Статус |")
    lines.append("|---|---|---|")
    for cat in ('a', 'b', 'c', 'd'):
        rate = log_row.get(f'category_{cat}_pass_rate')
        threshold = 100.0 if cat in ('a', 'd') else 60.0
        lines.append(f"| {cat.upper()} | {_pct(rate)} | {_icon_for(rate, threshold)} |")
    lines.append("")

    failures = log_row.get('failures_json')
    if failures:
        if isinstance(failures, str):
            try: failures = json.loads(failures)
            except: failures = []
        if failures:
            lines.append("## Failures")
            lines.append("")
            for f in failures[:20]:
                code = f.get('scenario_code', '?')
                topic = f.get('expected_topic', '?')
                reason = f.get('reason', f.get('error', '?'))
                lines.append(f"- `{code}` ({topic}) — {reason}")
            if len(failures) > 20:
                lines.append(f"- *... +{len(failures)-20} още*")
            lines.append("")

    changes = _compare_to_previous(log_row, previous)
    if changes:
        lines.append("## Промени от последния run")
        lines.append("")
        for c in changes:
            lines.append(f"- {c}")
        lines.append("")

    if log_row.get('notes'):
        lines.append("## Notes")
        lines.append("")
        lines.append(log_row['notes'])
        lines.append("")

    return "\n".join(lines)


# ─────────────────────────────────────────────────────────────────
# BG EMAIL BODY (08:30 daily)
# ─────────────────────────────────────────────────────────────────

def bg_email_body(log_row: dict, previous: Optional[dict] = None, sales_today: int = 0) -> str:
    """Кратък български email — 6-10 реда, simple text."""
    if not log_row:
        return (
            "RunMyStore Diag — няма нов run в последните 24 часа.\n\n"
            "Провери: https://runmystore.ai/admin/diagnostics.php\n"
        )

    a = log_row.get('category_a_pass_rate')
    b = log_row.get('category_b_pass_rate')
    c = log_row.get('category_c_pass_rate')
    d = log_row.get('category_d_pass_rate')
    ts = log_row.get('run_timestamp')
    if isinstance(ts, datetime):
        ts = ts.strftime('%Y-%m-%d %H:%M')

    L = []
    L.append(f"RunMyStore Diag — {ts}")
    L.append("")
    L.append(f"Категория A: {_pct(a)} ({log_row.get('passed_a', '?')}/{log_row.get('total_a', '?')}) {_icon_for(a, 100)}")
    L.append(f"Категория D: {_pct(d)} ({log_row.get('passed_d', '?')}/{log_row.get('total_d', '?')}) {_icon_for(d, 100)}")
    L.append(f"Категория B: {_pct(b)}")
    L.append(f"Категория C: {_pct(c)}")
    L.append("")
    changes = _compare_to_previous(log_row, previous)
    if changes:
        L.append("Промени от вчера:")
        for ch in changes:
            L.append(f"  - {ch}")
    if sales_today > 0:
        L.append(f"  - Нови симулирани продажби: {sales_today}")
    L.append("")
    L.append("Подробности: https://runmystore.ai/admin/diagnostics.php")
    return "\n".join(L)


# ─────────────────────────────────────────────────────────────────
# TELEGRAM CRITICAL ALERT
# ─────────────────────────────────────────────────────────────────

def telegram_critical(log_row: dict) -> str:
    """Кратко съобщение при Cat A или D < 100%. HTML mode (Telegram parse_mode=HTML)."""
    a = log_row.get('category_a_pass_rate')
    d = log_row.get('category_d_pass_rate')
    failures = log_row.get('failures_json') or []
    if isinstance(failures, str):
        try: failures = json.loads(failures)
        except: failures = []
    failed_codes = [f.get('scenario_code', '?') for f in failures[:5]]

    lines = []
    lines.append("🚨 <b>RunMyStore CRITICAL</b>")
    lines.append(f"Категория A: <b>{_pct(a)}</b>")
    lines.append(f"Категория D: <b>{_pct(d)}</b>")
    if failed_codes:
        lines.append(f"Failed: <code>{', '.join(failed_codes)}</code>")
        if len(failures) > 5:
            lines.append(f"... +{len(failures)-5} още")
    lines.append(f"Тригер: <code>{log_row.get('trigger_type', '?')}</code>")
    lines.append("Виж: https://runmystore.ai/admin/diagnostics.php")
    return "\n".join(lines)


# ─────────────────────────────────────────────────────────────────
# HUMAN SUMMARY (за dashboard Section 6 — БГ, не AI, само PHP if/else logic)
# ─────────────────────────────────────────────────────────────────

def human_summary(log_row: dict, previous: Optional[dict] = None) -> str:
    """3-4 изречения на български. Template-based (не AI). Закон №2 — PHP смята, AI говори, тук PHP стига."""
    if not log_row:
        return "Все още няма пуснат diagnostic. Натисни бутона за първи run."

    a = float(log_row.get('category_a_pass_rate') or 0)
    d = float(log_row.get('category_d_pass_rate') or 0)
    b = float(log_row.get('category_b_pass_rate') or 0)
    c = float(log_row.get('category_c_pass_rate') or 0)
    failed = int(log_row.get('failed') or 0)

    parts = []

    # Главна оценка
    if a >= 100 and d >= 100:
        parts.append("Всички критични логики работят правилно.")
    elif a < 100:
        parts.append(f"⚠️ Критична категория A е под 100% ({_pct(a)}). Нужен е rollback или fix.")
    elif d < 100:
        parts.append(f"⚠️ Граничните тестове D са под 100% ({_pct(d)}). Вероятно SQL bug.")

    # Второстепенни
    if b < 80 or c < 70:
        parts.append(f"Второстепенните логики (B={_pct(b)}, C={_pct(c)}) имат пропуски, но не са спешни.")
    else:
        parts.append("Второстепенните логики са в норма.")

    # Failures pointer
    if failed > 0:
        parts.append(f"{failed} тест{'а' if failed != 1 else ''} се провали{'ха' if failed != 1 else ''} — виж списъка по-долу.")

    # Next scheduled run
    parts.append("Следващ автоматичен тест: понеделник 03:00 (cron weekly).")

    return " ".join(parts)
