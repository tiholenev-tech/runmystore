# /admin/ — Internal Owner Dashboards

Single-tenant ops dashboards. Достъпни **само за tenant=7 (Тихол), role=owner**.
Няма публични линкове — bookmark-вай в браузъра.

| Файл | URL | Какво прави |
|---|---|---|
| `beta-readiness.php` | `/admin/beta-readiness.php` | **BETA READINESS DASHBOARD** — 1 страница, 6 секции, real DB queries, auto-refresh 60s, mobile-friendly. Отговаря на въпроса „готови ли сме за ENI 14 май и какво ни блокира?". |
| `diagnostics.php`    | `/admin/diagnostics.php`    | Diagnostic Cat A/B/C/D pass-rate trend (последни 14 runs) + manual run бутон. |
| `diag-run.php`       | POST                        | Streaming endpoint за ръчен `tools/diagnostic/run_diag.py` run. |

## beta-readiness.php — секции

1. **Product Catalog** — total / photo / ai_category / cost_price / min_quantity (target 50+).
2. **Sales (last 30d)** — sales count, items, revenue, days with ≥1 sale.
3. **AI Insights (live)** — total + breakdown по 6 fundamental_question + last created.
4. **AI Studio Health** — credit balance bg/desc/magic, spend last 7d, refunds, anti-abuse triggers.
5. **Infrastructure** — `cron_heartbeats.compute_insights_15min`, DB latency, disk `/var/www`, latest `diagnostic_log` Cat A/D.
6. **Beta Blockers** — parse-ва `## ⚠️ KNOWN ISSUES` таблицата от `STATE_OF_THE_PROJECT.md`. Header banner е 🔴 ако има P0, иначе 🟢 READY.

Цветен код: 🟢 OK · 🟡 WARN · 🔴 BAD · ⚪ idle/N/A.

## Auth

```php
$_SESSION['user_id']   != 0
$_SESSION['tenant_id'] === 7
$_SESSION['role']      === 'owner'
```

Anonymous → `Location: /login.php` (302).
Logged-in без owner@tenant=7 → 403 plain page.

## Read-only гаранция

Дашбордовете **не пишат в БД**. Само `SELECT` queries и `df -h` shell call.
Безопасно за повторно зареждане / auto-refresh.
