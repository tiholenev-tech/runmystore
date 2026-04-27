# INSIGHTS_REPORT — S83.INSIGHTS

**Date:** 2026-04-27
**Session:** S83 (real product entry blocker)
**Author:** Claude Code (Opus 4.7 1M)
**Scope:** populate `ai_insights` for tenant=7 so `products.php` 6 sections render with content.

---

## State BEFORE

| Source                                | Value |
|---------------------------------------|-------|
| Cron schedule (`crontab -l`)          | `*/15 * * * * php /var/www/runmystore/cron-insights.php` — healthy |
| Last cron run for tenant=7 (log)      | `tenant 7 (Ени Тихолов): 16 insights, 909ms` (≤ 15 min ago) |
| Live `ai_insights` rows (tenant=7, module=products, `expires_at > NOW()`) | 17 |
| Distribution by `fundamental_question` | loss=2 · loss_cause=4 · gain=2 · gain_cause=4 · order=2 · anti_order=3 |
| Stale rows / cron broken              | No — `created_at = 2026-04-24 19:30…19:33`, kept alive by `pfUpsert` (refreshes `expires_at` only) |
| `ai_insights_shown` cooldown table    | Does not exist; nothing is hiding rows |

All 6 `fundamental_question` buckets are populated, but loss / gain / order are at **2 each** — below the DoD floor of `≥18 total` and the per-bucket target of 5.

---

## Decision

**Case A — DATA SHORTAGE** (per brief).

Cron and `compute-insights.php` are healthy; the catalog of tenant=7 simply does not generate enough organic signal in three of the six buckets. Per the brief's protocol, fixing pf*() functions would require Rule #21 diagnostic protocol (forbidden zone). The right move is to top-up via seed.

Case B (compute-insights fix) was rejected — no bug to fix, and the brief lists `tools/diagnostic/*` as forbidden so a Rule #21 round trip is not viable in this session.

---

## What changed

### New files
- `tools/seed/insights_populate.py` — idempotent top-up seeder.
  - Reads live counts per `fundamental_question`.
  - For each bucket, inserts a curated set of realistic templates (`stockout_risk_72h`, `expiry_window_7d`, `deadstock_value`, `weekend_revenue_lift`, `new_customer_share`, `average_basket_growth`, `reorder_window_open`, `supplier_minimum_alert`, `seasonal_lead_time`, `overstock_supplier`, `low_velocity_category`, `competitor_price_undercut`, `bundle_synergy`).
  - Pulls real `product_id`s from `products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL` so `data_json.items[]` is wired to live SKUs.
  - `topic_id` namespaced as `seed_s83_<fq>_<seq>` so re-runs upsert (refresh `expires_at`) instead of duplicating.
  - Guard: refuses to run for any tenant outside `{7, 99}`.
  - DB credentials from `/etc/runmystore/db.env` (same convention as `tools/diagnostic/`).

### Untouched
- `compute-insights.php` — read only, no changes (no bug found).
- `cron-insights.php`, `products.php`, `ai-studio*.php`, `tools/diagnostic/*` — not modified.

---

## State AFTER

```
=== final tenant=7 module=products live counts ===
  loss         5
  loss_cause   5
  gain         5
  gain_cause   5
  order        5
  anti_order   5
  ---- total: 30

=== seed-vs-organic split ===
  seed:    13
  organic: 17
  total:   30
```

- ✅ Total live ≥ 18 (actually 30).
- ✅ All 6 `fundamental_question` buckets populated (5 each, exceeds the "≥3 per bucket" target).
- ✅ Re-running the script is a no-op except `expires_at` refresh.

`expires_at` for seed rows: `NOW() + INTERVAL 7 DAY` — covers the live demo window. Cron will continue refreshing organic rows; seeds need to be re-run weekly (or daily as a cron) until tenant=7 has enough real catalog to push the under-represented buckets above 5 organically.

---

## How to re-run

```bash
cd /var/www/runmystore
python3 tools/seed/insights_populate.py --tenant 7              # live
python3 tools/seed/insights_populate.py --tenant 7 --dry-run    # plan only
python3 tools/seed/insights_populate.py --tenant 7 --target 8   # higher per-bucket target
```

Exit code is `0` only when DoD passes (`total ≥ 18` AND `min_per_fq ≥ 2`), so this is safe to wrap in a cron later.

---

## Follow-ups (not in this session)

1. If `products.php` *still* renders empty after this run, the bug is on the read side (likely a `role_gate`/`plan_gate` filter or a missing join). That falls outside this session's scope (`products.php` is forbidden).
2. Three under-represented buckets (loss/gain/order) point at gaps in `compute-insights.php` coverage for small catalogs — worth a future S84+ ticket to add cron-side fallbacks (e.g. weekend revenue trend, basket-size momentum) so seeding becomes unnecessary.
3. Add a weekly/daily seed-cron entry once Tihol confirms the visual is right.
