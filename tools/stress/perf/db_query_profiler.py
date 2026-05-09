#!/usr/bin/env python3
"""
tools/stress/perf/db_query_profiler.py

Phase O2 (S130 extension). Анализира MySQL slow_query_log.

Чете slow log файл (или stdin), нормализира SQL заявките (премахва
литерали — LIMIT 1, '...', 123 → ?), групира по pattern и rank-ва по
frequency * avg_time. Извежда top 20 slow queries + recommendations.

Поддържа MySQL slow log формат:
  # Time: 2026-05-09T05:30:00.123456Z
  # User@Host: ... Id: ...
  # Query_time: 5.234567  Lock_time: 0.000123 Rows_sent: 100  Rows_examined: 50000
  SET timestamp=...;
  SELECT * FROM products WHERE ...;

ABSOLUTE GUARDS:
  * Read-only: само чете лог, нищо не променя в DB.
  * Изисква достъп до slow_query_log path или stdin.

Usage:
    python3 db_query_profiler.py /var/log/mysql/slow.log
    python3 db_query_profiler.py --input slow.log --top 30
    cat slow.log | python3 db_query_profiler.py --stdin
    python3 db_query_profiler.py slow.log --output report.json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import defaultdict
from pathlib import Path

QUERY_TIME_RE = re.compile(
    r"# Query_time:\s*([\d.]+)\s+Lock_time:\s*([\d.]+)"
    r"(?:\s+Rows_sent:\s*(\d+))?(?:\s+Rows_examined:\s*(\d+))?"
)
TIME_RE = re.compile(r"# Time:\s*(\S+)")


def normalize_sql(sql: str) -> str:
    """Заменя литерали с placeholder за групиране на сходни заявки."""
    s = sql.strip().rstrip(";")
    # multiline -> single
    s = re.sub(r"\s+", " ", s)
    # 'string literal'
    s = re.sub(r"'(?:[^'\\]|\\.)*'", "?", s)
    # "string literal"
    s = re.sub(r'"(?:[^"\\]|\\.)*"', "?", s)
    # numbers
    s = re.sub(r"\b\d+(?:\.\d+)?\b", "?", s)
    # IN (...)
    s = re.sub(r"IN\s*\([^)]+\)", "IN (?)", s, flags=re.IGNORECASE)
    return s.strip().lower()


def parse_slow_log(text: str) -> list[dict]:
    """Връща list of {query_time, lock_time, rows_examined, sql_norm, sql}."""
    entries: list[dict] = []
    lines = text.splitlines()
    i = 0
    n = len(lines)
    current = None
    sql_buf: list[str] = []

    def flush():
        nonlocal current, sql_buf
        if current and sql_buf:
            sql = "\n".join(sql_buf).strip()
            # пропускай SET timestamp / use db / административни
            sql_clean = re.sub(r"^use\s+\S+;\s*", "", sql, flags=re.IGNORECASE)
            sql_clean = re.sub(r"SET timestamp\s*=\s*\d+;", "",
                               sql_clean, flags=re.IGNORECASE).strip()
            if sql_clean:
                current["sql"] = sql_clean
                current["sql_norm"] = normalize_sql(sql_clean)
                entries.append(current)
        current = None
        sql_buf = []

    while i < n:
        line = lines[i]
        if line.startswith("# Time:"):
            flush()
            current = {"timestamp": TIME_RE.match(line).group(1)
                       if TIME_RE.match(line) else None}
        elif line.startswith("# Query_time:"):
            m = QUERY_TIME_RE.search(line)
            if m and current is not None:
                current["query_time"] = float(m.group(1))
                current["lock_time"] = float(m.group(2))
                current["rows_sent"] = int(m.group(3)) if m.group(3) else 0
                current["rows_examined"] = int(m.group(4)) if m.group(4) else 0
        elif line.startswith("# User@Host:"):
            pass  # skip
        elif line.startswith("#"):
            pass
        else:
            if current is not None:
                sql_buf.append(line)
        i += 1
    flush()
    return entries


def aggregate(entries: list[dict], top_n: int = 20) -> list[dict]:
    grouped: dict[str, dict] = defaultdict(lambda: {
        "count": 0, "total_time": 0.0, "max_time": 0.0,
        "total_rows_examined": 0, "examples": [],
    })
    for e in entries:
        if "query_time" not in e or "sql_norm" not in e:
            continue
        g = grouped[e["sql_norm"]]
        g["count"] += 1
        g["total_time"] += e["query_time"]
        g["max_time"] = max(g["max_time"], e["query_time"])
        g["total_rows_examined"] += e.get("rows_examined", 0)
        if len(g["examples"]) < 2:
            g["examples"].append(e["sql"])

    ranked = []
    for sql_norm, g in grouped.items():
        avg = g["total_time"] / g["count"]
        ranked.append({
            "sql_norm": sql_norm,
            "count": g["count"],
            "total_time_s": round(g["total_time"], 3),
            "avg_time_s": round(avg, 3),
            "max_time_s": round(g["max_time"], 3),
            "total_rows_examined": g["total_rows_examined"],
            "rank_score": round(g["count"] * avg, 3),
            "examples": g["examples"],
        })
    ranked.sort(key=lambda x: x["rank_score"], reverse=True)
    return ranked[:top_n]


def recommendations(top: list[dict]) -> list[str]:
    out: list[str] = []
    for q in top[:10]:
        sql = q["sql_norm"]
        rows = q.get("total_rows_examined", 0)
        avg = q.get("avg_time_s", 0)
        if rows / max(q["count"], 1) > 10_000:
            out.append(
                f"⚠ {q['sql_norm'][:80]}… examines avg "
                f"{rows // max(q['count'], 1):,} rows — възможен липсващ index "
                f"(виж index_advisor.py)"
            )
        if avg > 5.0:
            out.append(
                f"🐢 avg time {avg}s — кандидат за query rewrite или "
                f"materialized view"
            )
        if "select *" in sql:
            out.append(
                f"📋 SELECT * — изброй колоните за по-добра планировка "
                f"(повторено {q['count']}x)"
            )
        if "where " not in sql and " where" not in sql and "select" in sql:
            out.append(
                "❗ SELECT без WHERE — full table scan (повторено "
                f"{q['count']}x)"
            )
    if not out:
        out.append("✓ Нищо тревожно — top заявките са под threshold-овете.")
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("input", nargs="?", default=None,
                    help="Path към slow_query_log.")
    ap.add_argument("--input", dest="input2", default=None)
    ap.add_argument("--stdin", action="store_true",
                    help="Чети от stdin вместо файл.")
    ap.add_argument("--top", type=int, default=20)
    ap.add_argument("--output", default=None,
                    help="Запиши JSON отчет в този файл.")
    args = ap.parse_args()

    text = ""
    if args.stdin:
        text = sys.stdin.read()
    else:
        path = args.input or args.input2
        if not path:
            sys.exit("[USAGE] подай slow_query_log path или --stdin")
        p = Path(path)
        if not p.exists():
            sys.exit(f"[FATAL] {p} липсва")
        text = p.read_text(encoding="utf-8", errors="replace")

    entries = parse_slow_log(text)
    print(f"[INFO] Парснати {len(entries)} slow queries", file=sys.stderr)

    top = aggregate(entries, top_n=args.top)
    recs = recommendations(top)

    report = {
        "total_slow_queries": len(entries),
        "unique_patterns": len(top),
        "top": top,
        "recommendations": recs,
    }

    if args.output:
        Path(args.output).write_text(
            json.dumps(report, ensure_ascii=False, indent=2))
        print(f"[OK] Output: {args.output}", file=sys.stderr)

    print(f"\n=== TOP {len(top)} SLOW QUERIES (ranked by count*avg_time) ===\n")
    for i, q in enumerate(top, 1):
        print(f"#{i}  count={q['count']}  avg={q['avg_time_s']}s  "
              f"max={q['max_time_s']}s  rows_examined={q['total_rows_examined']:,}")
        print(f"    {q['sql_norm'][:200]}")
        print()

    print("\n=== RECOMMENDATIONS ===\n")
    for r in recs:
        print(f"  {r}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
