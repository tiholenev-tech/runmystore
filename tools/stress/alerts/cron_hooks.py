#!/usr/bin/env python3
"""
tools/stress/alerts/cron_hooks.py

Phase M2 (S130 extension). Готови wrapper-и за интеграция с existing
cron-овете, без да се пипат самите cron файлове.

Защо отделен файл:
  Cron файловете (nightly_robot.py, sanity_checker.py, code_analyzer.sh)
  имат конкуриращи се промени в други branches (s128-stress-full).
  За да не създаваме merge конфликти, помощниците живеят тук, а
  cron-ите ги import-ват само когато branch-ът се merge-не.

Всеки helper:
  - НИКОГА не raises (try/except wrapper).
  - Респектира severity escalation (warning -> critical при тежки нива).
  - Използва конкретни topics за rate limiting (per-cron channel).

Integration инструкции — виж README.md секция "🔌 Integration patches".
"""

from __future__ import annotations

import sys
import traceback
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

try:
    from telegram_bot import send_alert
except Exception:
    send_alert = None  # type: ignore


def _safe_send(severity: str, message: str, **kw) -> None:
    if send_alert is None:
        return
    try:
        send_alert(severity, message, **kw)
    except Exception:
        pass


def alert_balance(failures_count: int, period_h: int, tenant_id: int,
                  sample: list | None = None) -> None:
    """
    Hook за sanity_checker.py. Извиква се след fetch_balance_data.

    Threshold:
      - 0       → quiet (no alert)
      - 1-50    → warning
      - >50     → critical (escalation, bypass rate limit)
    """
    if failures_count <= 0:
        return
    severity = "critical" if failures_count > 50 else "warning"
    sample = sample or []
    sample_short = ", ".join(
        f"prod={f.get('product_id')}@store={f.get('store_id')}"
        f"(Δ={f.get('delta')})" for f in sample[:3]
    )
    _safe_send(
        severity,
        f"sanity_checker: {failures_count} balance failures",
        topic="balance",
        context={
            "tenant_id": tenant_id,
            "period_h": period_h,
            "sample": sample_short or "n/a",
        },
    )


def alert_nightly_outcome(run_id: int | None, pass_n: int, fail_n: int,
                          skip_n: int, duration_ms: int) -> None:
    """
    Hook за nightly_robot.py края на run. Извиква се след
    UPDATE stress_runs с финалните метрики.

    Threshold:
      - fail_n == 0 → quiet
      - fail_n 1-5  → warning
      - fail_n > 5  → critical
    """
    if fail_n <= 0:
        return
    severity = "critical" if fail_n > 5 else "warning"
    _safe_send(
        severity,
        f"nightly_robot: {fail_n} scenario failures",
        topic="nightly_robot",
        context={
            "run_id": run_id,
            "pass": pass_n,
            "fail": fail_n,
            "skip": skip_n,
            "duration_ms": duration_ms,
        },
    )


def alert_nightly_crash(exc: BaseException) -> None:
    """
    Hook за nightly_robot.py __main__ exception handler.

    Винаги critical — нощният робот не трябва да крашва тихо.
    """
    tb = "".join(traceback.format_exception_only(type(exc), exc))
    _safe_send(
        "critical",
        f"nightly_robot CRASH: {type(exc).__name__}",
        topic="nightly_robot",
        context={"error": tb.strip()[:300]},
    )


def alert_p0_escalation(headline: str, source: str = "MORNING_REPORT") -> None:
    """
    Hook за code_analyzer.sh когато grep засече P0 ESCALATION
    в MORNING_REPORT.md. Винаги critical.

    Извиква се през CLI: telegram_bot.py --severity critical \
        --topic morning_report_p0 --message "P0: ${headline}"
    Този Python helper е alternative за случаите когато cron-ът е
    Python (не bash).
    """
    _safe_send(
        "critical",
        f"P0 escalation: {headline}",
        topic="morning_report_p0",
        context={"source": source},
    )


def alert_cron_skipped(cron_name: str, reason: str) -> None:
    """
    Generic helper — cron skip-нат заради липсваща config / DB.

    Severity = warning (не critical, защото skipping е по дизайн).
    """
    _safe_send(
        "warning",
        f"{cron_name} skipped: {reason}",
        topic=f"skip_{cron_name}",
        context={"cron": cron_name, "reason": reason},
    )


__all__ = [
    "alert_balance",
    "alert_nightly_outcome",
    "alert_nightly_crash",
    "alert_p0_escalation",
    "alert_cron_skipped",
]
