#!/usr/bin/env python3
"""
sales_pulse.py — nightly random sales pulse for tenant=7 (live).

v2 (S92.STRESS.DEPLOY) — wrapper around tools/seed/sales_populate.py.

Replaces v1's broken `DATE_ADD(DATE(NOW()), INTERVAL X HOUR)` clumping
(all sales landed on HH:00:00 of today) and `GREATEST(quantity-X, 0)`
(stock never depleted) with the realistic generator already implemented
by sales_populate.main():
    - peak hours 11-13 + 17-19 (60% share)
    - per-second timestamps within business hours 09-21
    - basket size + return rate + payment + discount distributions
    - atomic "skip when stock=0" (no over-sell, no negative inventory)
    - --dry-run support

Usage:
    python3 sales_pulse.py             # 5-15 sales on tenant=7
    python3 sales_pulse.py --dry-run   # plan only, no INSERTs
    python3 sales_pulse.py --count 30  # override sale count
"""
import argparse
import random
import sys

sys.path.insert(0, '/var/www/runmystore')
from tools.seed.sales_populate import main as populate_main

TENANT_ID = 7
MIN_SALES = 5
MAX_SALES = 15


def parse_args(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[1])
    ap.add_argument('--dry-run', action='store_true',
                    help='Plan only — no INSERTs, no inventory updates')
    ap.add_argument('--count', type=int, default=None,
                    help=f'Override sale count (default random {MIN_SALES}-{MAX_SALES})')
    return ap.parse_args(argv)


def main(argv=None):
    args = parse_args(argv)
    n = args.count if args.count is not None else random.randint(MIN_SALES, MAX_SALES)
    inner = ['--tenant', str(TENANT_ID), '--count', str(n), '--backfill-days', '1', '--confirm']
    if args.dry_run:
        inner.append('--dry-run')
    return populate_main(inner)


if __name__ == '__main__':
    sys.exit(main())
