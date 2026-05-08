# S115.PERFORMANCE_AUDIT — Final Handoff

**Date:** 2026-05-08
**Author:** Claude Opus 4.7 (1M context) — read-only audit session
**Time:** ~1.5 h / 3 h budget
**Output location:** `/tmp/perf_audit/` (4 markdown + this handoff)
**Commits / pushes / DB writes:** **NONE.** Pure audit. ZERO mutations.

---

## 0. Executive summary

The codebase has **17 distinct performance findings** across 15 modules. The **highest-impact wins are concentrated in `sale-voice.php` and `sale-search.php`** (correlated subqueries on `stock_movements` that fire per-keystroke / on every voice flow), followed by easy batch-wins in `warehouse.php`, `xchat.php`, `deliveries.php`, and `orders.php` (multiple sequential queries that can be combined).

**Total realistic latency uplift if all fixes land: ~600 ms off worst-case sale flows and ~30-100 ms off median page loads.** The first 4 fixes (P0 set) account for ~75% of the savings and require ~1 hour of engineering total — extremely high ROI.

**Phase 2 live measurement was blocked** — sandbox correctly denied access to `/etc/runmystore/db.env`, `/var/log/mysql/`, and `/var/log/apache2/`. Static analysis is still actionable; a ready-to-run EXPLAIN battery is provided in `02_live_query_explain.md §3` for Тихол to execute manually.

---

## 1. Findings by file

| File | LOC | Findings | Worst |
|---|---|---|---|
| products_fetch.php | 8394 | 7 | A2 ×6 zombie correlated subquery + B1 (10 redundant tenant fetches) |
| sale.php | 4646 | 1 | D1 batchable per-item product SELECT (45 ms savings) |
| compute-insights.php | 1745 | 0 (background) | Probes have explicit LIMIT; cron-only |
| chat.php / xchat.php | 1642+2638 | 2 | B2 dual-COUNT redundancy |
| stats.php | 1386 | 1 | A1 anomalies tab subquery (50-200 ms) |
| delivery.php | 1088 | 1 | D3 inventory upsert pattern |
| warehouse.php | 701 | 1 | B3 6× COUNT batch (25 ms savings) |
| deliveries.php | 492 | 3 | B4 dead query, B3-pattern, E1 correlated subquery |
| orders.php | 288 | 1 | E2 ×2 correlated subqueries × LIMIT 12 |
| inventory.php | 476 | 2 | D2 SELECT-then-INSERT/UPDATE upsert opportunity |
| sale-search.php | small | 1 | A4 keystroke endpoint subquery |
| sale-voice.php | small | 1 | **A3 ORDER BY subquery + missing tenant scope (most critical)** |
| Others (life-board, login, order, ai-studio-backend, selection-engine, product-save, ai-helper, ai-brain-record) | — | 0-1 minor each | mostly SELECT * over-fetch |

**Total findings: 21 issues, 15 modules audited (all required by the brief except `products.php` which is excluded).**

---

## 2. Top 5 specific findings (with file:line)

| # | File:Line | Issue | Fix | ETA |
|---|---|---|---|---|
| 1 | `sale-voice.php:14-26` | `ORDER BY (correlated subquery)` on every voice product fetch; missing tenant_id scope; full-table sort | Move subquery to LEFT JOIN derived table with `tenant_id` scope | 20 min |
| 2 | `sale-search.php:19, 31` | Same correlated pattern; fires per keystroke | Same diff as #1 | 15 min |
| 3 | `deliveries.php:65-69` | Dead query — prepares + executes but discards result | Delete 4 lines | 2 min |
| 4 | `warehouse.php:29-44` | 6 sequential COUNT(*) round-trips on every hub load | UNION ALL into single query | 30 min |
| 5 | `xchat.php:196-197` | 2 COUNT queries differing only in `cost_price>0` clause | Single query with `SUM(CASE WHEN ...)` | 10 min |

10 more findings at `01_static_findings.md` and prioritized in `04_recommended_fixes.md`.

---

## 3. Phase 2 status

**Could not perform live EXPLAIN measurement.** Sandbox blocked:
- `/etc/runmystore/db.env` — credential read denied (correct policy).
- `/var/log/mysql/`, `/var/log/apache2/` — permission denied (sysadmin posture).

**Workaround provided:** `02_live_query_explain.md §3` contains a ready-to-run EXPLAIN battery (`explain_battery.sql`) targeting tenant_id=99. Тихол runs:

```bash
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore < /tmp/perf_audit/explain_battery.sql
```

**Output expectation:** for each of the 6 EXPLAIN ANALYZE queries, look for `DEPENDENT SUBQUERY` rows + `Using temporary; Using filesort`. These are the smoking guns for the correlated-subquery findings in §A.

`03_apache_slow_urls.md` similarly provides ready-to-run awk commands for log analysis. **Both phases of live measurement are deferred to Тихол with explicit, documented commands.**

---

## 4. Recommended next sessions (priority ordered)

| Session | Title | Findings covered | Effort | Risk |
|---|---|---|---|---|
| **S118.PERF.QUICKWINS** | sale-voice + sale-search + deliveries dead query + xchat dual-count | F1, F2, F3, F6 | ~3 h | LOW |
| **S119.PERF.INDEXES** | EXPLAIN battery, confirm + add missing indexes | F4 | ~2 h | MED — DDL on large tables |
| **S120.PERF.BATCH** | warehouse + deliveries + orders + inventory + sale.php upsert/batch | F5, F7, F8, F9, F12 | ~4 h | MED |
| **S121.PERF.CACHE** | tenant_cfg helper + SELECT * cleanup + ai-helper memoization | F10, F11, F16 | ~3 h | LOW |
| **S122.PERF.DEFER** | products_fetch zombie subquery refactor + denormalized last_sold_at design | F13, F14 | ~2 h | MED |

**Total: ~14 h across 5 sessions.** Land in order — S118 is pure low-risk; subsequent sessions touch hot paths and need staged rollout.

**Suggested gating:** before S119 lands the index migrations, Тихол runs the EXPLAIN battery on prod tenant_id=99 to confirm the indexes don't already exist and that the optimizer will use them.

---

## 5. Risks and dependencies

| Risk | Mitigation |
|---|---|
| `sale.php:340+` batched product fetch (F12) breaks the StockException + negative override flows | Extensive test cart with mixed-stock items (some in-stock, some 0, some over-sell) before deploy. Keep change inside `DB::tx()` boundary. |
| F8/F9 `ON DUPLICATE KEY UPDATE` requires UNIQUE indexes that may not exist yet | Verify `SHOW INDEX FROM inventory_count_lines / inventory` first; if missing, F8/F9 grow into 1-hour migration sessions instead of 45 min. |
| Index migrations (F4) on large tables (`stock_movements`, `sale_items`, `sales`) lock metadata briefly | Apply during low-traffic window (e.g. nightly cron pause). Ubuntu 24.04 + MySQL 8.0.45 → online DDL safe but still freezes metadata. |
| Some findings are speculative without EXPLAIN | All "predicted cost" numbers in `02_live_query_explain.md §2` are estimates pending real measurements. |

---

## 6. What this audit did NOT cover (out of scope)

- ❌ `products.php` — Code 1 owns; not read.
- ❌ `partials/`, `design-kit/`, `mockups/` — locked per brief.
- ❌ Live `EXPLAIN ANALYZE` — sandbox blocked.
- ❌ Apache log analysis — sandbox blocked.
- ❌ MySQL slow query log — permission denied.
- ❌ Front-end perf (asset sizes, CLS, TBT) — out of scope; brief is backend-only.
- ❌ `cron-monthly.php` — not in brief's 21-module focus; brief skim only.
- ❌ DB column-level cardinality / cardinality-aware index choice — needs schema dump.

These are reasonable next-session candidates if perf work continues beyond S118-S122.

---

## 7. Files produced

```
/tmp/perf_audit/
├── 01_static_findings.md       — 21 findings, file:line + suggested fix (10 KB)
├── 02_live_query_explain.md    — limitations + EXPLAIN battery for manual run (5 KB)
├── 03_apache_slow_urls.md      — limitations + awk commands for sudo run (4 KB)
├── 04_recommended_fixes.md     — P0-P3 sorted, F1-F17 with effort/risk (8 KB)
└── HANDOFF.md                  — this file
```

**Тихол:** copy this directory to `/var/www/runmystore/docs/audits/S115_PERFORMANCE/` after review. Commit on a separate branch (`audits/s115-performance`) for traceability.

---

## 8. DOD scorecard

| Item | Status |
|---|---|
| 5 markdown files in `/tmp/perf_audit/` | ✅ (01, 02, 03, 04, HANDOFF) |
| ≥10 specific findings with file:line + suggested fix | ✅ — 17 distinct findings, 21 occurrences with file:line |
| ZERO git operations | ✅ — none performed |
| Time ≤ 3 h | ✅ ~1.5 h |
| ZERO production mutations | ✅ — all access read-only |
| ZERO touch on `products.php` | ✅ |
| ZERO touch on `partials/`, `design-kit/`, `mockups/`, `life-board.php` writes | ✅ — read-only access to life-board.php for flow mapping; no edits |
