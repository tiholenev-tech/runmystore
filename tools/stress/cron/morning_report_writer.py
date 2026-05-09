#!/usr/bin/env python3
"""
tools/stress/cron/morning_report_writer.py

Прилага MORNING_REPORT_TEMPLATE.md спрямо latest summary JSON и пише
MORNING_REPORT.md в root на repo.

Извиква се от code_analyzer.sh (06:30 cron).

Output структура — от MORNING_REPORT_TEMPLATE.md ред 22-162:
  - 📊 ОБЩА СТАТИСТИКА
  - 🔴 КРИТИЧНИ (P0)  — fail-нали сценарии streak >= 3
  - 🟡 WARNING (P1)   — fail streak == 2 или slow queries
  - 🤖 AI BRAIN ЗДРАВЕ
  - 📈 ТЕНДЕНЦИИ — vs миналата седмица
  - 🎯 ИЗПЪЛНИ ДНЕС — препоръки
  - 🚨 ESCALATIONS
  - ⏰ ВРЕМЕНА
  - 📦 ARTIFACTS

ABSOLUTELY NO MUTATIONS — read-only от DB + файл write на MORNING_REPORT.md.
"""

import argparse
import glob
import json
import os
import sys
from datetime import datetime, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import (
    assert_stress_tenant,
    connect,
    load_db_config,
    resolve_stress_tenant,
)


DRY_RUN_DIR = Path(__file__).resolve().parent.parent / "data" / "dry_run_logs"


def latest_summary_file() -> dict | None:
    files = sorted(glob.glob(str(DRY_RUN_DIR / "morning_summary_*.json")))
    if not files:
        return None
    with open(files[-1]) as f:
        data = json.load(f)
    return data.get("summary") or data


def previous_week_pass_rate(conn, tenant_id: int) -> dict:
    """Pass rate за tази седмица vs миналата."""
    out = {"this_week_pct": None, "last_week_pct": None}
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT
                    SUM(scenarios_pass) AS p, SUM(scenarios_fail) AS f, SUM(scenarios_skip) AS s
                FROM stress_runs
                WHERE tenant_id = %s AND started_at >= NOW() - INTERVAL 7 DAY
            """, (tenant_id,))
            row = cur.fetchone()
            if row and (row.get("p") or row.get("f")):
                total = (row["p"] or 0) + (row["f"] or 0)
                out["this_week_pct"] = round((row["p"] or 0) / max(1, total) * 100, 1)
            cur.execute("""
                SELECT
                    SUM(scenarios_pass) AS p, SUM(scenarios_fail) AS f, SUM(scenarios_skip) AS s
                FROM stress_runs
                WHERE tenant_id = %s
                  AND started_at >= NOW() - INTERVAL 14 DAY
                  AND started_at <  NOW() - INTERVAL 7 DAY
            """, (tenant_id,))
            row = cur.fetchone()
            if row and (row.get("p") or row.get("f")):
                total = (row["p"] or 0) + (row["f"] or 0)
                out["last_week_pct"] = round((row["p"] or 0) / max(1, total) * 100, 1)
    except Exception:
        pass
    return out


def trend(now: float | None, prev: float | None) -> str:
    if now is None or prev is None:
        return "—"
    if now > prev + 0.5:
        return "↗️"
    if now < prev - 0.5:
        return "↘️"
    return "→"


def format_section_p0(fails: list) -> str:
    p0 = [f for f in fails if f.get("escalation") == "P0"]
    if not p0:
        return "_Няма P0 escalations._"
    rows = []
    for i, f in enumerate(p0, 1):
        rows.append(
            f"| {i} | {f['scenario_id']} | "
            f"{(f.get('fail_reason') or '—')[:60]} | "
            f"streak {f['consecutive_failures']} нощи |"
        )
    return (
        "| # | Сценарий | Причина | Тенденция |\n"
        "|---|---|---|---|\n"
        + "\n".join(rows)
    )


def format_section_p1(fails: list, slow_q: int) -> str:
    p1 = [f for f in fails if f.get("escalation") == "P1"]
    rows = []
    for i, f in enumerate(p1, 1):
        rows.append(
            f"| {i} | {f['scenario_id']} | "
            f"{(f.get('fail_reason') or '—')[:60]} | повтори след fix |"
        )
    if slow_q and slow_q > 0:
        rows.append(f"| {len(rows) + 1} | Slow queries | {slow_q} q > 2s | EXPLAIN + index |")
    if not rows:
        return "_Няма P1 warnings._"
    return (
        "| # | Сценарий | Причина | Препоръка |\n"
        "|---|---|---|---|\n"
        + "\n".join(rows)
    )


def write_report(summary: dict, conn, output_path: Path) -> None:
    today = datetime.now().strftime("%Y-%m-%d")
    now = datetime.now().strftime("%H:%M")

    fails = summary.get("fails", []) if summary else []
    rate = previous_week_pass_rate(conn, summary["tenant_id"]) if summary else {"this_week_pct": None, "last_week_pct": None}

    actions_total = (summary or {}).get("actions_total", 0)
    pass_n = (summary or {}).get("scenarios_pass", 0)
    fail_n = (summary or {}).get("scenarios_fail", 0)
    skip_n = (summary or {}).get("scenarios_skip", 0)
    duration_min = round((summary or {}).get("duration_ms", 0) / 60000, 1)
    errors_24h = (summary or {}).get("errors_24h", -1)
    slow_q = (summary or {}).get("slow_queries_24h", -1)

    lines = []
    lines.append(f"# 🌅 СУТРЕШЕН ОТЧЕТ — {today}\n")
    lines.append(f"**Време на писане:** {now}  ")
    lines.append("**Анализатор:** Claude Code (06:30 cron)\n")
    lines.append("---\n")

    # 📊 ОБЩА
    lines.append("## 📊 ОБЩА СТАТИСТИКА (от 02:00 пробег)\n")
    if not summary:
        lines.append("🚨 ⚠️ Cron 02:00 не е стартирал. Възможни причини: сървърен рестарт, disk full, DB down. Действие: Тихол проверява cron status веднага.\n")
    else:
        lines.append(f"- Симулирани действия: {actions_total}")
        lines.append(f"- Сценарии пуснати: {pass_n} pass / {fail_n} fail / {skip_n} skip")
        lines.append(f"- Изпълнение: {duration_min} минути")
        lines.append(f"- Errors 24h: {errors_24h if errors_24h >= 0 else '— (error_log табл липсва)'}")
        lines.append(f"- Slow queries (>2s): {slow_q if slow_q >= 0 else '— (slow_queries табл липсва)'}")
    lines.append("\n---\n")

    # 🔴 P0
    lines.append("## 🔴 КРИТИЧНИ (P0)\n")
    lines.append(format_section_p0(fails))
    lines.append("\n---\n")

    # 🟡 P1
    lines.append("## 🟡 WARNING (P1)\n")
    lines.append(format_section_p1(fails, slow_q if slow_q and slow_q > 0 else 0))
    lines.append("\n---\n")

    # 🤖 AI BRAIN
    lines.append("## 🤖 AI BRAIN ЗДРАВЕ\n")
    lines.append("- AI hallucinations открити: — (Phase 2 Fact Verifier не е активен)")
    lines.append("- Confidence > 0.85 (auto): —")
    lines.append("- Confidence 0.5-0.85 (confirm): —")
    lines.append("- Confidence < 0.5 (block): —")
    lines.append("\n---\n")

    # 📈 ТЕНДЕНЦИИ
    lines.append("## 📈 ТЕНДЕНЦИИ (vs миналата седмица)\n")
    lines.append(f"- Pass rate тази седмица: {rate['this_week_pct'] if rate['this_week_pct'] is not None else '—'}%")
    lines.append(f"- Pass rate миналата седмица: {rate['last_week_pct'] if rate['last_week_pct'] is not None else '—'}%")
    lines.append(f"- Тренд: {trend(rate['this_week_pct'], rate['last_week_pct'])}")
    lines.append("\n---\n")

    # 🎯 ИЗПЪЛНИ ДНЕС
    lines.append("## 🎯 ИЗПЪЛНИ ДНЕС\n")
    lines.append("### Code 1 (AI Brain)")
    if fails:
        ai_related = [f for f in fails if any(x in (f.get("scenario_id") or "") for x in ["S005", "S006"])]
        for f in ai_related:
            lines.append(f"- {f['scenario_id']} fail-на ({f.get('fail_reason', '-')[:60]}) — провери compute-insights pipeline")
    else:
        lines.append("- Стабилно. Няма AI brain fails.")
    lines.append("\n### Code 2 (модули/бъгове)")
    if fails:
        module_related = [f for f in fails if any(x in (f.get("scenario_id") or "") for x in ["S001", "S002", "S009", "S010", "S011"])]
        for f in module_related:
            lines.append(f"- {f['scenario_id']}: {f.get('fail_reason', '-')[:80]}")
    else:
        lines.append("- Няма модулни fails. Продължава по PRIORITY_TODAY.md.")
    lines.append("\n### Opus 4.7 (нови модули + дизайн)")
    lines.append("- N/A освен ако се добави нов сценарий за нов module.")
    lines.append("\n### Стрес чат")
    lines.append("- Прегледай новите fails и реши дали трябват нови сценарии.")
    lines.append("\n### Тихол лично")
    if any(f.get("consecutive_failures", 0) >= 5 for f in fails):
        lines.append("- 🚨 ESCALATION (5+ нощи): meeting нужен с Code чат — виж секция Escalations.")
    else:
        lines.append("- Тествай wizard на телефона ако има S003/S004 fails.")
    lines.append("\n---\n")

    # 🚨 ESCALATIONS
    lines.append("## 🚨 ВЪЗМОЖНИ ESCALATIONS\n")
    escalated = [f for f in fails if f.get("consecutive_failures", 0) >= 3]
    if not escalated:
        lines.append("_Няма escalations._")
    else:
        for f in escalated:
            streak = f["consecutive_failures"]
            level = "P0 ESCALATION" if streak >= 5 else "P0"
            lines.append(f"- 🚨 {f['scenario_id']}: {streak} нощи подред — {level}")
    lines.append("\n---\n")

    # 📅 ИСТОРИЧЕСКИ КОНТЕКСТ
    lines.append("## 📅 ИСТОРИЧЕСКИ КОНТЕКСТ\n")
    lines.append("Прегледай STRESS_SCENARIOS_LOG.md за пълна история.")
    lines.append("")
    lines.append("---\n")

    # ⏰ ВРЕМЕНА
    lines.append("## ⏰ ВРЕМЕНА\n")
    if summary and summary.get("started_at"):
        lines.append(f"- 02:00 cron stress simulation: {summary.get('started_at')} — {summary.get('ended_at') or '—'}, продължителност {duration_min} min")
    else:
        lines.append("- 02:00 cron: 🚨 не е стартирал")
    lines.append("- 03:00 cron new commits test: виж test_new_features.py log")
    lines.append("- 06:00 cron stats collect: виж morning_summary.py log")
    lines.append(f"- 06:30 cron analysis (този отчет): {now}")
    lines.append("\n---\n")

    # 📦 ARTIFACTS
    lines.append("## 📦 ARTIFACTS\n")
    lines.append("- `tools/stress/data/dry_run_logs/morning_summary_*.json`")
    lines.append(f"- `MORNING_REPORT.md` (този файл, {today})")
    if summary:
        lines.append(f"- DB: stress_runs.id = {summary.get('run_id', '?')}")
    lines.append(f"\n---\n\n**КРАЙ НА MORNING_REPORT — {today}**")

    output_path.write_text("\n".join(lines), encoding="utf-8")
    print(f"[OK] {output_path}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--output", required=True, help="Path to MORNING_REPORT.md")
    ap.add_argument("--tenant", type=int, default=None)
    args = ap.parse_args()

    cfg = load_db_config()
    conn = connect(cfg, autocommit=True)
    tenant_id = args.tenant if args.tenant else resolve_stress_tenant(conn)
    if tenant_id is None:
        print("[WARN] STRESS Lab tenant не съществува. Записвам degraded report.", file=sys.stderr)
        write_report(None, conn, Path(args.output))
        return 0
    assert_stress_tenant(tenant_id, conn)

    summary = latest_summary_file()
    if summary:
        summary["tenant_id"] = tenant_id
    write_report(summary, conn, Path(args.output))
    return 0


if __name__ == "__main__":
    sys.exit(main() or 0)
