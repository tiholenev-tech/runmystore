# Phase 2 part B — Apache Slow URLs Analysis

**Session:** S115.PERFORMANCE_AUDIT
**Status:** ⚠ **Could not run** — `/var/log/apache2/access.log` is `Permission denied` for the `tihol` user.

---

## Why this section is empty

The audit harness denied:
- `tail -10000 /var/log/apache2/access.log` → not readable by `tihol`
- `sudo -n true` → password required
- `ls /var/log/apache2/` → permission denied

This is **correct sysadmin posture** — `access.log` typically has `0640 root:adm` ownership; user-level processes should not need it. To run this analysis, Тихол must run the commands in §3 below as a privileged user.

---

## 1. What this analysis would have produced

If access were available, the deliverable would be:

```
TOP 20 SLOW URLs (last 24h, response time > 500ms)

  Avg ms    Hits     URL
  ─────────────────────────
   1240    1456    /products_fetch.php?action=search
    890     312    /sale-search.php?q=…
    730     201    /products.php?filter=zombie
    640     98     /stats.php?range=30d
    510     312    /chat-send.php
   …

TOP 10 MOST-HIT URLs (any latency)

  Hits      URL
  ──────────────────
  18234     /life-board.php
  12876     /chat-send.php
   9821     /products_fetch.php
   …
```

These two lists tell us:
- **Where users actually spend time** (priority of perf wins).
- **Which slow URLs are also frequently hit** (multiplier effect — a 500ms URL hit 10k×/day = real user pain).

---

## 2. Cross-reference plan (when log access lands)

For each URL in TOP 20 slow:
1. Map to PHP file (most are `<basename>.php`; some are AJAX endpoints behind `?action=`).
2. Cross-ref to findings in `01_static_findings.md`.
3. Confirm or refute: is this URL slow because of a finding, or for another reason (cold cache, large payload, external API)?

This matrix becomes the priority input for `04_recommended_fixes.md`.

---

## 3. Commands for Тихол to run with sudo

```bash
# TOP 20 slow URLs — last 24h, average ms desc
# Assumes apache LogFormat exposes "%D" (microseconds) at end of line.
# Adjust -F if format is different.

sudo tail -n 100000 /var/log/apache2/access.log \
  | awk '{
      url = $7;             # 7th field = URL path
      ms  = $NF / 1000;     # last field = microseconds → ms
      if (ms > 500) {
        sum[url] += ms;
        cnt[url] += 1;
      }
    }
    END {
      for (u in cnt) printf "%6d  %5d  %s\n", sum[u]/cnt[u], cnt[u], u;
    }' \
  | sort -rn \
  | head -20

# TOP 10 most-hit URLs (any latency)
sudo tail -n 100000 /var/log/apache2/access.log \
  | awk '{print $7}' \
  | sort | uniq -c | sort -rn | head -10

# TOP 5 URLs by total time spent (avg × hits)
sudo tail -n 100000 /var/log/apache2/access.log \
  | awk '{ url=$7; ms=$NF/1000; sum[url] += ms; cnt[url]++; }
         END { for (u in cnt) printf "%9d  %5d  %s\n", sum[u], cnt[u], u; }' \
  | sort -rn | head -5
```

**Save output to:** `/tmp/perf_audit/03_apache_slow_urls_results.txt` (after Тихол runs).

---

## 4. Alternative without log access — measure live with curl

If sysadmin access is blocked but app-level perf hooks exist, profile production endpoints from the loopback:

```bash
# Time a representative set of endpoints (cookies/auth required for real flow)
for url in \
  "https://runmystore.local/life-board.php" \
  "https://runmystore.local/chat.php" \
  "https://runmystore.local/products_fetch.php?action=search&q=foo" \
  "https://runmystore.local/sale-search.php?q=foo" \
  "https://runmystore.local/stats.php?range=30d" \
  "https://runmystore.local/deliveries.php" \
  "https://runmystore.local/orders.php" \
  "https://runmystore.local/warehouse.php"; do
  echo -n "$url  "
  curl -sb /tmp/cookies.txt -o /dev/null -w "%{time_total}s\n" "$url"
done
```

Run from a host with prod cookies; ≥3 runs each, take median.

---

## 5. Cron jobs to investigate (orthogonal)

`cron-insights.php` runs every 15 min and writes its own heartbeat (per S114 audit). Inspect:

```sql
SELECT job_name, last_run_at, last_status, last_duration_ms, expected_interval_minutes
  FROM cron_heartbeats
 ORDER BY last_run_at DESC;
```

- `last_duration_ms > 5000`: cron is taking > 5s per cycle → tenants iterated serially, growing slow.
- `last_status='error'`: cron failure during last run.

This is **not** an apache log analysis but is the closest equivalent for background performance and accessible without sysadmin elevation (just app credentials).

---

## 6. Audit note

Phase 2 of this session was the most blocked by sandbox access. Static findings (Phase 1) are the load-bearing output of this audit. Once Тихол runs the EXPLAIN battery and the apache awk commands, the cross-reference can complete the picture.
