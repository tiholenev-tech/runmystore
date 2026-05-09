"""tools/stress/perf — Phase O (S130 extension).

Performance harness за STRESS Lab tenant.

Модули:
  - load_test.py          — concurrent users срещу sale.php (p50/p95/p99)
  - db_query_profiler.py  — анализ на slow_query_log
  - index_advisor.py      — препоръки за CREATE INDEX

Random seed = 42 (deterministic).
Никога не пише върху ENI tenant_id=7 (assert_stress_tenant guard).
"""
