#!/usr/bin/env python3
"""
tools/stress/perf/index_advisor.py

Phase O3 (S130 extension). Анализира top slow queries и предлага
CREATE INDEX statements.

Чете JSON output от db_query_profiler.py (--output report.json) или
парсва slow log директно. За всяка top заявка опитва:
  - Извлича таблиците от FROM / JOIN
  - Извлича WHERE колоните
  - Извлича ORDER BY / GROUP BY колоните
  - Предлага композитен индекс (col_a, col_b) ако заявката ползва
    и WHERE и ORDER BY

ABSOLUTE GUARDS:
  * НЕ apply-ва. Само пише suggested_indexes.sql за човек да review-не.
  * Read-only анализ.

Usage:
    python3 index_advisor.py --report top_slow.json
    python3 index_advisor.py --slow-log /var/log/mysql/slow.log
    python3 index_advisor.py --report top.json --output suggested_indexes.sql
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

# Парсваме обикновени MySQL заявки (best-effort, не AST)
TABLES_RE = re.compile(
    r"\bfrom\s+([a-z_][a-z0-9_]*)|"
    r"\bjoin\s+([a-z_][a-z0-9_]*)",
    re.IGNORECASE,
)
WHERE_COL_RE = re.compile(
    r"\bwhere\b(.+?)(?:\bgroup\s+by\b|\border\s+by\b|\blimit\b|$)",
    re.IGNORECASE | re.DOTALL,
)
COL_REF_RE = re.compile(
    r"(?:\b([a-z_][a-z0-9_]*)\.)?([a-z_][a-z0-9_]*)\s*(?:=|<|>|in)",
    re.IGNORECASE,
)
ORDER_RE = re.compile(
    r"\border\s+by\s+(.+?)(?:\blimit\b|$)",
    re.IGNORECASE | re.DOTALL,
)
GROUP_RE = re.compile(
    r"\bgroup\s+by\s+(.+?)(?:\border\s+by\b|\blimit\b|$)",
    re.IGNORECASE | re.DOTALL,
)

# Не предлагаме индекси за тези често псувани колони
NOISE_COLS = {"created_at", "updated_at", "id"}


def extract_tables(sql: str) -> list[str]:
    out: list[str] = []
    for m in TABLES_RE.finditer(sql):
        t = m.group(1) or m.group(2)
        if t and t.lower() not in {"select"}:
            out.append(t.lower())
    # dedup keeping order
    seen = set()
    return [t for t in out if not (t in seen or seen.add(t))]


def extract_columns(text: str, regex: re.Pattern,
                    col_pattern: re.Pattern = COL_REF_RE) -> list[str]:
    m = regex.search(text)
    if not m:
        return []
    block = m.group(1)
    cols: list[str] = []
    for cm in col_pattern.finditer(block):
        col = cm.group(2)
        if col and col.lower() not in {"and", "or", "in", "between", "like",
                                       "not", "is", "null", "where", "from"}:
            cols.append(col.lower())
    seen = set()
    return [c for c in cols if not (c in seen or seen.add(c))]


def extract_simple_columns(block: str) -> list[str]:
    out = []
    for token in re.split(r"[,\s]+", block):
        token = token.strip()
        if not token:
            continue
        m = re.match(r"(?:[a-z_]\w*\.)?([a-z_]\w*)", token, re.IGNORECASE)
        if m:
            col = m.group(1).lower()
            if col not in {"asc", "desc"}:
                out.append(col)
    seen = set()
    return [c for c in out if not (c in seen or seen.add(c))]


def suggest_for_query(sql: str, count: int, avg_time_s: float) -> list[dict]:
    sql_low = sql.lower()
    tables = extract_tables(sql_low)
    if not tables:
        return []

    where_block = WHERE_COL_RE.search(sql_low)
    where_cols = extract_simple_columns(where_block.group(1)) if where_block else []
    # filter из noise + ones that look like values
    where_cols = [c for c in where_cols
                  if c not in NOISE_COLS and not c.isdigit() and len(c) > 1]

    order_block = ORDER_RE.search(sql_low)
    order_cols = extract_simple_columns(order_block.group(1)) if order_block else []
    group_block = GROUP_RE.search(sql_low)
    group_cols = extract_simple_columns(group_block.group(1)) if group_block else []

    suggestions = []
    primary_table = tables[0]

    if where_cols:
        idx_cols = where_cols[:3]
        if order_cols:
            for oc in order_cols[:1]:
                if oc not in idx_cols:
                    idx_cols.append(oc)
        suggestions.append({
            "table": primary_table,
            "columns": idx_cols,
            "reason": (f"WHERE filter on {', '.join(where_cols[:3])}"
                       + (f" + ORDER BY {order_cols[0]}" if order_cols else "")),
            "stmt": (f"-- count={count}  avg={avg_time_s}s\n"
                     f"CREATE INDEX idx_{primary_table}_"
                     f"{'_'.join(idx_cols)} ON {primary_table} "
                     f"({', '.join(idx_cols)});"),
        })
    elif group_cols:
        suggestions.append({
            "table": primary_table,
            "columns": group_cols[:2],
            "reason": f"GROUP BY {', '.join(group_cols[:2])}",
            "stmt": (f"-- count={count}  avg={avg_time_s}s\n"
                     f"CREATE INDEX idx_{primary_table}_"
                     f"{'_'.join(group_cols[:2])} ON {primary_table} "
                     f"({', '.join(group_cols[:2])});"),
        })

    return suggestions


def dedup_suggestions(items: list[dict]) -> list[dict]:
    seen: set[str] = set()
    out: list[dict] = []
    for s in items:
        key = f"{s['table']}::{','.join(s['columns'])}"
        if key in seen:
            continue
        seen.add(key)
        out.append(s)
    return out


def load_top_from_report(path: str) -> list[dict]:
    with open(path) as f:
        report = json.load(f)
    return report.get("top", [])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--report", default=None,
                    help="JSON output от db_query_profiler.py")
    ap.add_argument("--slow-log", default=None,
                    help="Path към slow_query_log (alternatively).")
    ap.add_argument("--top", type=int, default=20)
    ap.add_argument("--output", default=None,
                    help="SQL output file. Default: suggested_indexes.sql.")
    args = ap.parse_args()

    if not args.report and not args.slow_log:
        sys.exit("[USAGE] подай --report report.json или --slow-log path")

    if args.report:
        top = load_top_from_report(args.report)
    else:
        # дефакто включваме db_query_profiler inline
        sys.path.insert(0, str(Path(__file__).resolve().parent))
        from db_query_profiler import aggregate, parse_slow_log
        text = Path(args.slow_log).read_text(encoding="utf-8", errors="replace")
        top = aggregate(parse_slow_log(text), top_n=args.top)

    all_suggestions: list[dict] = []
    for q in top[:args.top]:
        sql = q.get("sql_norm") or (q.get("examples") or [""])[0]
        if not sql:
            continue
        sugg = suggest_for_query(sql, q.get("count", 0),
                                 q.get("avg_time_s", 0.0))
        all_suggestions.extend(sugg)

    all_suggestions = dedup_suggestions(all_suggestions)

    print(f"[INFO] {len(all_suggestions)} unique index suggestions",
          file=sys.stderr)

    out_path = (Path(args.output) if args.output
                else Path(__file__).resolve().parent / "suggested_indexes.sql")
    lines = [
        "-- tools/stress/perf/suggested_indexes.sql",
        "-- Auto-generated от tools/stress/perf/index_advisor.py",
        f"-- Total suggestions: {len(all_suggestions)}",
        "-- НЕ APPLY-ВАЙ автоматично. Прегледай всеки CREATE INDEX и тествай в STAGING.",
        "",
    ]
    for s in all_suggestions:
        lines.append(f"-- Reason: {s['reason']}")
        lines.append(s["stmt"])
        lines.append("")

    out_path.write_text("\n".join(lines), encoding="utf-8")
    print(f"[OK] {out_path}")
    for s in all_suggestions[:10]:
        print(f"  - {s['table']}({', '.join(s['columns'])})  reason: {s['reason']}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
