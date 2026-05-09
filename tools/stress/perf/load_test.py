#!/usr/bin/env python3
"""
tools/stress/perf/load_test.py

Phase O1 (S130 extension). Симулира concurrent users срещу sale.php.

Записва p50 / p95 / p99 latency + error rate. Сравнява срещу
последния baseline в `last_baseline.json`.

ABSOLUTE GUARDS:
  * НЕ удря production sale.php — само STRESS Lab tenant (X-STRESS-TENANT
    header или query param tenant_id, ако endpoint-ът поддържа).
  * --dry-run по default — печата план, не прави HTTP заявки.
  * Random seed = 42.

Usage:
    python3 load_test.py --dry-run
    python3 load_test.py --apply --concurrent 10 --requests 100
    python3 load_test.py --apply --concurrent 50 --duration 30
    python3 load_test.py --baseline   # запиши текущия резултат като baseline
"""

from __future__ import annotations

import argparse
import json
import random
import statistics
import sys
import threading
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from _db import dry_run_log, seed_rng

PERF_DIR = Path(__file__).resolve().parent
BASELINE_FILE = PERF_DIR / "last_baseline.json"

DEFAULT_BASE_URL = "https://stress-lab.runmystore.ai"
DEFAULT_ENDPOINT = "/sale.php"


def fake_payload(req_index: int) -> dict:
    """Symbolic payload — sale.php-style POST data."""
    return {
        "tenant_id": "stress",
        "store_id": str(random.choice([1, 2, 3, 4, 5, 6, 7])),
        "items[0][product_id]": str(random.randint(10_000, 10_100)),
        "items[0][quantity]": str(random.choice([1, 1, 1, 2, 3])),
        "items[0][unit_price]": f"{random.uniform(5.0, 99.99):.2f}",
        "payment_method": random.choice(["cash", "card"]),
        "synthetic": "1",
        "_seq": str(req_index),
    }


def do_request(url: str, payload: dict, timeout: int = 10,
               extra_headers: dict | None = None) -> dict:
    body = urllib.parse.urlencode(payload).encode()
    req = urllib.request.Request(url, data=body, method="POST")
    req.add_header("X-STRESS-PERF", "1")
    if extra_headers:
        for k, v in extra_headers.items():
            req.add_header(k, v)
    t0 = time.perf_counter()
    error = None
    status = 0
    body_len = 0
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            status = resp.status
            body_len = len(resp.read())
    except urllib.error.HTTPError as e:
        status = e.code
        error = f"HTTP {e.code}"
    except Exception as e:
        error = f"{type(e).__name__}: {e}"
    elapsed_ms = (time.perf_counter() - t0) * 1000.0
    return {
        "elapsed_ms": elapsed_ms,
        "status": status,
        "body_len": body_len,
        "error": error,
    }


def run_load(url: str, total_requests: int, concurrent: int,
             timeout: int = 10, extra_headers: dict | None = None) -> dict:
    """Реално прави заявките и връща статистика."""
    samples: list[dict] = []
    lock = threading.Lock()

    def worker(i: int):
        result = do_request(url, fake_payload(i), timeout=timeout,
                            extra_headers=extra_headers)
        with lock:
            samples.append(result)

    t_start = time.perf_counter()
    with ThreadPoolExecutor(max_workers=concurrent) as ex:
        futures = [ex.submit(worker, i) for i in range(total_requests)]
        for f in as_completed(futures):
            f.result()
    duration_s = time.perf_counter() - t_start

    return summarize(samples, duration_s, concurrent, url)


def summarize(samples: list[dict], duration_s: float,
              concurrent: int, url: str) -> dict:
    if not samples:
        return {"error": "no samples"}
    elapsed = sorted(s["elapsed_ms"] for s in samples)
    errors = [s for s in samples if s["error"]]
    bad_status = [s for s in samples
                  if not s["error"] and s["status"] >= 500]
    return {
        "url": url,
        "concurrent": concurrent,
        "total_requests": len(samples),
        "duration_s": round(duration_s, 3),
        "rps": round(len(samples) / max(duration_s, 1e-3), 2),
        "p50_ms": round(percentile(elapsed, 50), 2),
        "p95_ms": round(percentile(elapsed, 95), 2),
        "p99_ms": round(percentile(elapsed, 99), 2),
        "min_ms": round(elapsed[0], 2),
        "max_ms": round(elapsed[-1], 2),
        "mean_ms": round(statistics.mean(elapsed), 2),
        "stdev_ms": round(statistics.pstdev(elapsed), 2),
        "errors_count": len(errors),
        "errors_rate_pct": round(100 * len(errors) / len(samples), 2),
        "5xx_count": len(bad_status),
        "5xx_rate_pct": round(100 * len(bad_status) / len(samples), 2),
        "timestamp": datetime.now().isoformat(timespec="seconds"),
    }


def percentile(sorted_values: list[float], p: float) -> float:
    if not sorted_values:
        return 0.0
    if len(sorted_values) == 1:
        return sorted_values[0]
    k = (len(sorted_values) - 1) * (p / 100.0)
    f = int(k)
    c = min(f + 1, len(sorted_values) - 1)
    return sorted_values[f] + (sorted_values[c] - sorted_values[f]) * (k - f)


def load_baseline() -> dict | None:
    if not BASELINE_FILE.exists():
        return None
    try:
        with open(BASELINE_FILE) as f:
            return json.load(f)
    except Exception:
        return None


def save_baseline(summary: dict) -> Path:
    BASELINE_FILE.write_text(json.dumps(summary, ensure_ascii=False,
                                        indent=2))
    return BASELINE_FILE


def compare(current: dict, baseline: dict) -> dict:
    """Връща { metric: {current, baseline, delta_pct, regression} }."""
    out = {}
    for k in ("p50_ms", "p95_ms", "p99_ms", "errors_rate_pct", "rps"):
        c = current.get(k)
        b = baseline.get(k)
        if c is None or b is None:
            continue
        delta = c - b
        delta_pct = (delta / b * 100) if b > 0 else 0.0
        # rps regression = current < baseline; latency regression = current > baseline
        if k == "rps":
            regression = c < b * 0.95  # >5% drop in throughput
        elif k == "errors_rate_pct":
            regression = c > max(b + 1.0, b * 1.5)
        else:
            regression = c > b * 1.20  # >20% latency increase
        out[k] = {
            "current": c, "baseline": b,
            "delta": round(delta, 2),
            "delta_pct": round(delta_pct, 2),
            "regression": regression,
        }
    return out


def main():
    ap = argparse.ArgumentParser(description="STRESS load test")
    ap.add_argument("--apply", action="store_true",
                    help="Реално прави HTTP заявки. Default = dry-run.")
    ap.add_argument("--base-url", default=DEFAULT_BASE_URL)
    ap.add_argument("--endpoint", default=DEFAULT_ENDPOINT)
    ap.add_argument("--concurrent", type=int, default=10,
                    help="Брой едновременни workers (5-50).")
    ap.add_argument("--requests", type=int, default=100,
                    help="Общ брой заявки.")
    ap.add_argument("--timeout", type=int, default=10)
    ap.add_argument("--baseline", action="store_true",
                    help="Запиши резултата в last_baseline.json.")
    ap.add_argument("--no-compare", action="store_true",
                    help="Пропусни сравнение с baseline.")
    args = ap.parse_args()
    seed_rng()

    if args.concurrent < 1 or args.concurrent > 50:
        sys.exit("[REFUSE] --concurrent трябва да е 1-50.")
    if args.requests < 1:
        sys.exit("[REFUSE] --requests >= 1.")

    url = args.base_url.rstrip("/") + args.endpoint

    if not args.apply:
        plan = {
            "url": url,
            "concurrent": args.concurrent,
            "total_requests": args.requests,
            "timeout_s": args.timeout,
        }
        out = dry_run_log("load_test", {"action": "dry-run", "plan": plan})
        print(f"[DRY-RUN] План: {out}")
        print(f"[DRY-RUN] Ще пусне {args.requests} заявки c "
              f"{args.concurrent} concurrent workers към {url}")
        return 0

    print(f"[RUN] {args.requests} заявки x {args.concurrent} concurrent "
          f"към {url}")
    summary = run_load(url, args.requests, args.concurrent,
                       timeout=args.timeout)

    print(json.dumps(summary, ensure_ascii=False, indent=2))

    baseline = None if args.no_compare else load_baseline()
    cmp = None
    regressions = []
    if baseline:
        cmp = compare(summary, baseline)
        for metric, info in cmp.items():
            tag = "🔴 REGRESSION" if info["regression"] else "✓"
            print(f"  {tag} {metric}: {info['baseline']} -> {info['current']} "
                  f"(Δ {info['delta_pct']:+.1f}%)")
            if info["regression"]:
                regressions.append(metric)

    if args.baseline:
        path = save_baseline(summary)
        print(f"[OK] Baseline saved: {path}")

    dry_run_log("load_test", {
        "action": "applied",
        "summary": summary,
        "compare": cmp,
        "regressions": regressions,
    })

    return 1 if regressions else 0


if __name__ == "__main__":
    sys.exit(main())
