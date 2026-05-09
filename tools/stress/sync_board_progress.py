#!/usr/bin/env python3
"""
tools/stress/sync_board_progress.py

Phase N3 (S130 extension). Авто-обновяване на STRESS_BOARD.md ГРАФА 7
("Прогрес по етапите") въз основа на:
  - Файлове STRESS_HANDOFF_*.md в репото
  - Последните N commits с префикс S{NN}.STRESS.

Стратегия (същата като sync_registries):
  Manual content в STRESS_BOARD.md се ЗАПАЗВА. ГРАФА 7 таблицата е
  опакована между маркери:

    <!-- STRESS-BOARD-AUTO:graph7:start -->
    | Етап | Какво | Статус | Цел |
    ...
    <!-- STRESS-BOARD-AUTO:graph7:end -->

  При първи run, маркерите ОБВИВАТ съществуващата ГРАФА 7 таблица
  (script я детектира по reda с "## 🔄 ГРАФА 7"). При следващите —
  само съдържанието вътре се обновява.

Status detection:
  - Етап 1 "Подготовка на свят" — ✅ ако `setup_stress_tenant.py` се появи
    в commits log или в repo (тестове, не production); ⏳ ако само seed_*.py
  - Етап 2 "/admin/stress-board.php" — статус от health.php / admin/stress-*
  - Етап 3 "Нощен робот" — ✅ ако nightly_robot.py + action_simulators.py
  - Етап 4 "Авто-ловец на бъгове" — ✅ ако sanity_checker.py + balance_validator.py
  - Етап 5 "Онлайн магазин симулатор" — ✅ ако ecwid_simulator/ съществува

Detection е heuristic — view-only от commits, без production проверки.

Usage:
    python3 sync_board_progress.py
    python3 sync_board_progress.py --update
    python3 sync_board_progress.py --check
"""

import argparse
import difflib
import re
import subprocess
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
BOARD_MD = REPO_ROOT / "STRESS_BOARD.md"
TOOLS_STRESS = REPO_ROOT / "tools" / "stress"

MARK_START = "<!-- STRESS-BOARD-AUTO:graph7:start (do not edit between these markers) -->"
MARK_END = "<!-- STRESS-BOARD-AUTO:graph7:end -->"

GRAPH7_HEADER = "## 🔄 ГРАФА 7 — ПРОГРЕС ПО ЕТАПИТЕ"

STAGES = [
    {
        "n": 1,
        "name": "Подготовка на свят (STRESS Lab tenant + 7 магазина + 90 дни история)",
        "target": "Юни 2026",
        "evidence": [
            "tools/stress/setup_stress_tenant.py",
            "tools/stress/seed_history_90days.py",
            "tools/stress/seed_stores.py",
        ],
    },
    {
        "n": 2,
        "name": "/admin/stress-board.php — admin отчет",
        "target": "След beta (16-22 май)",
        "evidence": [
            "admin/stress-board.php",
            "admin/health.php",
        ],
    },
    {
        "n": 3,
        "name": "Нощен робот (cron 02:00 пълна симулация)",
        "target": "След модулите",
        "evidence": [
            "tools/stress/cron/nightly_robot.py",
            "tools/stress/cron/action_simulators.py",
        ],
    },
    {
        "n": 4,
        "name": "Авто-ловец на бъгове (sanity checks)",
        "target": "След Етап 3",
        "evidence": [
            "tools/stress/cron/sanity_checker.py",
            "tools/stress/cron/balance_validator.py",
        ],
    },
    {
        "n": 5,
        "name": "Онлайн магазин симулатор (Ecwid orders)",
        "target": "След Ecwid интеграция",
        "evidence": [
            "tools/stress/ecwid_simulator/ecwid_simulator.py",
            "tools/stress/ecwid_simulator/ecwid_to_runmystore_sync.py",
        ],
    },
]


def detect_status(stage: dict) -> tuple[str, list[str]]:
    """Връща (emoji_status, found_evidence)."""
    found: list[str] = []
    for path_str in stage["evidence"]:
        p = REPO_ROOT / path_str
        if p.exists():
            found.append(path_str)

    n_evidence = len(stage["evidence"])
    if not found:
        return "⬜ не е почнат", found
    if len(found) == n_evidence:
        return "✅ готов", found
    return "🟡 частично", found


def recent_stress_commits(n: int = 30) -> list[str]:
    """Чете последните N commits с префикс S<digit><digit>."""
    try:
        out = subprocess.check_output(
            ["git", "-C", str(REPO_ROOT), "log",
             "--oneline", f"-n{n}",
             "--grep=^S[0-9]\\+\\.STRESS"],
            stderr=subprocess.DEVNULL,
        ).decode("utf-8", "replace")
        return [line.strip() for line in out.splitlines() if line.strip()]
    except Exception:
        return []


def find_handoffs() -> list[Path]:
    return sorted(REPO_ROOT.glob("STRESS_HANDOFF_*.md"))


def render_graph7_block() -> str:
    parts: list[str] = []
    parts.append("")
    parts.append("**Авто-генерирано** от `tools/stress/sync_board_progress.py`. "
                 "Не редактирай ръчно.")
    parts.append("")
    parts.append("| # | Етап | Статус | Evidence (файлове) | Цел |")
    parts.append("|---|---|---|---|---|")

    for stage in STAGES:
        status, found = detect_status(stage)
        evidence_str = ", ".join(f"`{p}`" for p in found) if found else "—"
        parts.append(
            f"| {stage['n']} | {stage['name']} | {status} | "
            f"{evidence_str} | {stage['target']} |"
        )

    parts.append("")
    handoffs = find_handoffs()
    if handoffs:
        parts.append("### Handoff документи")
        parts.append("")
        for h in handoffs:
            parts.append(f"- `{h.relative_to(REPO_ROOT)}`")
        parts.append("")

    commits = recent_stress_commits(20)
    if commits:
        parts.append("### Последни STRESS commits (last 20)")
        parts.append("")
        for c in commits:
            parts.append(f"- {c}")
        parts.append("")
    return "\n".join(parts)


def find_graph7_section(content: str) -> tuple[int, int] | None:
    """Намира началния и крайния индекс на ГРАФА 7 секцията.

    Връща (start_line_idx, end_line_idx) или None ако липсва.
    """
    lines = content.splitlines()
    start_idx = None
    for i, line in enumerate(lines):
        if line.strip().startswith(GRAPH7_HEADER):
            start_idx = i
            break
    if start_idx is None:
        return None
    end_idx = len(lines)
    for j in range(start_idx + 1, len(lines)):
        if lines[j].startswith("## "):
            end_idx = j
            break
    return start_idx, end_idx


def replace_or_inject(content: str, auto_block: str) -> str:
    """Замества content между маркерите. Ако маркерите липсват, ги
    инжектира в края на ГРАФА 7 (преди следващата #section)."""
    if MARK_START in content and MARK_END in content:
        before = content.split(MARK_START)[0]
        after = content.split(MARK_END, 1)[1]
        return f"{before}{MARK_START}\n{auto_block}\n{MARK_END}{after}"

    sect = find_graph7_section(content)
    if sect is None:
        sep = "\n\n" if not content.endswith("\n\n") else ""
        return (f"{content.rstrip()}\n\n{GRAPH7_HEADER}\n\n"
                f"{MARK_START}\n{auto_block}\n{MARK_END}\n")

    start_idx, end_idx = sect
    lines = content.splitlines()
    head = "\n".join(lines[: start_idx + 1])  # include header
    tail = "\n".join(lines[end_idx:])
    middle = f"\n{MARK_START}\n{auto_block}\n{MARK_END}\n"
    if not tail or not tail.startswith("\n"):
        tail = "\n" + tail
    return f"{head}\n{middle.lstrip()}\n{tail}"


def show_diff(old: str, new: str) -> None:
    diff = difflib.unified_diff(
        old.splitlines(keepends=True),
        new.splitlines(keepends=True),
        fromfile="STRESS_BOARD.md (current)",
        tofile="STRESS_BOARD.md (after sync)",
        n=2,
    )
    sys.stdout.writelines(diff)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--update", action="store_true",
                    help="Запиши промените в STRESS_BOARD.md.")
    ap.add_argument("--check", action="store_true",
                    help="CI mode: exit 1 ако ГРАФА 7 не е up-to-date.")
    args = ap.parse_args()

    if not BOARD_MD.exists():
        print(f"[FATAL] {BOARD_MD} липсва", file=sys.stderr)
        return 2

    current = BOARD_MD.read_text(encoding="utf-8")
    auto_block = render_graph7_block()
    new = replace_or_inject(current, auto_block)

    if current == new:
        print(f"[OK] {BOARD_MD.name} ГРАФА 7 е up-to-date.")
        return 0

    if args.check:
        print(f"[FAIL] {BOARD_MD.name} ГРАФА 7 НЕ е up-to-date — пусни "
              f"`python3 tools/stress/sync_board_progress.py --update`",
              file=sys.stderr)
        show_diff(current, new)
        return 1

    if args.update:
        BOARD_MD.write_text(new, encoding="utf-8")
        print(f"[OK] {BOARD_MD.name} ГРАФА 7 обновен — "
              f"{len(STAGES)} етапа.")
        return 0

    show_diff(current, new)
    print(f"\n[DRY-RUN] За запис: --update", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
