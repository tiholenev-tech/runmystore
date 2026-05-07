# AUDIT REPORT FULL — S104.AUDIT_INFRA
**Date:** 2026-05-07 19:04
**Tool:** `design-kit/audit.sh` v1.0 (S104)
**Scope:** 10 owned (Code Code 1) + 12 read-only (Code Code 2) = 22 modules

## Methodology
Each module audited for: SYNTAX, SECURITY, DEAD_CODE, PERFORMANCE, DB_FIELDS, HARDCODED.
CRIT/WARN/INFO counts derived. CRIT > 0 means action needed before next session.

| # | File | Owner | CRIT | WARN | INFO | Status |
|---|------|-------|------|------|------|--------|
| 1 | `login.php` | Code 1 | 0 | 0 | 1 | ✔ pass |
| 2 | `register.php` | Code 1 | 0 | 0 | 0 | ✔ pass |
| 3 | `onboarding.php` | Code 1 | 0 | 0 | 0 | ✔ pass |
| 4 | `chat.php` | Code 1 | 0 | 0 | 2 | ✔ pass |
| 5 | `xchat.php` | Code 1 | 0 | 0 | 2 | ✔ pass |
| 6 | `store-chat.php` | Code 1 | 0 | 0 | 0 | ✔ pass |
| 7 | `ai-chat-overlay.php` | Code 1 | 0 | 0 | 0 | ✔ pass |
| 8 | `sale.php` | Code 1 | 0 | 0 | 2 | ✔ pass |
| 9 | `products.php` | Code 1 | 0 | 2 | 4 | ✔ pass |
| 10 | `ai-wizard.php` | Code 1 | 0 | 0 | 1 | ✔ pass |
| 11 | `deliveries.php` | Code 2 | 0 | 0 | 1 | ✔ pass |
| 12 | `delivery.php` | Code 2 | 0 | 1 | 2 | ✔ pass |
| 13 | `orders.php` | Code 2 | 0 | 0 | 0 | ✔ pass |
| 14 | `order.php` | Code 2 | 1 | 1 | 2 | ✗ FAIL |
| 15 | `warehouse.php` | Code 2 | 0 | 0 | 0 | ✔ pass |
| 16 | `inventory.php` | Code 2 | 0 | 1 | 1 | ✔ pass |
| 17 | `defectives.php` | Code 2 | 0 | 1 | 0 | ✔ pass |
| 18 | `stats.php` | Code 2 | 0 | 0 | 0 | ✔ pass |
| 19 | `settings.php` | Code 2 | 0 | 0 | 0 | ✔ pass |
| 20 | `ai-studio.php` | Code 2 | 0 | 0 | 1 | ✔ pass |
| 21 | `printer-setup.php` | Code 2 | 0 | 0 | 0 | ✔ pass |
| 22 | `biz-coefficients.php` | Code 2 | 0 | 0 | 1 | ✔ pass |

## Critical issues — action required

### order.php
```
✗ CRIT [DB_FIELDS]: status='cancelled' → използвай status='canceled' (един L)
```

## Per-module details (full)

### login.php

```
═══════════════════════════════════════════════
  AUDIT: login.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 2 × SELECT * (acceptable за tenants/* lookups)
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 1
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### register.php

```
═══════════════════════════════════════════════
  AUDIT: register.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### onboarding.php

```
═══════════════════════════════════════════════
  AUDIT: onboarding.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### chat.php

```
═══════════════════════════════════════════════
  AUDIT: chat.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-13 11 lines block comment

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 1 × SELECT * (acceptable за tenants/* lookups)
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 2
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### xchat.php

```
═══════════════════════════════════════════════
  AUDIT: xchat.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-13 11 lines block comment

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 1 × SELECT * (acceptable за tenants/* lookups)
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 2
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### store-chat.php

```
═══════════════════════════════════════════════
  AUDIT: store-chat.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### ai-chat-overlay.php

```
═══════════════════════════════════════════════
  AUDIT: ai-chat-overlay.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### sale.php

```
═══════════════════════════════════════════════
  AUDIT: sale.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 3 × SELECT * (acceptable за tenants/* lookups)
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути
ℹ INFO [HARDCODED]: 2 текста с 'лв'/'BGN' (провери дали не трябва priceFormat)

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 2
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### products.php

```
═══════════════════════════════════════════════
  AUDIT: products.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
ℹ INFO [DEAD_CODE]: Възможни закоментирани блокове
      5959: 7 consecutive // comment lines (likely dead code)
      6057: 7 consecutive // comment lines (likely dead code)
      8056: 7 consecutive // comment lines (likely dead code)
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      11798-13391 1593 lines block comment

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 3 × SELECT * (acceptable за tenants/* lookups)
⚠ WARN [PERFORMANCE]: DB заявки в цикъл (N+1 риск)
      942-944: DB call inside loop
      1039-1042: DB call inside loop
      1095-1098: DB call inside loop

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
⚠ WARN [HARDCODED]: 3 hardcoded валутни изрази (използвай priceFormat($amount, $tenant))
      4386:            <div class="art-bot"><div class="art-prc">120 лв</div><div class="art-stk danger">0 бр</div></div>
      4393:            <div class="art-bot"><div class="art-prc">89 лв</div><div class="art-stk danger">0 бр</div></div>
      4400:            <div class="art-bot"><div class="art-prc">24 лв</div><div class="art-stk warn">1 бр</div></div>
ℹ INFO [HARDCODED]: 3 текста с 'лв'/'BGN' (провери дали не трябва priceFormat)

═══════════════════════════════════════════════
  CRIT: 0  WARN: 2  INFO: 4
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### ai-wizard.php

```
═══════════════════════════════════════════════
  AUDIT: ai-wizard.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-19 17 lines block comment

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 1
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### deliveries.php

```
═══════════════════════════════════════════════
  AUDIT: deliveries.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 1 × SELECT * (acceptable за tenants/* lookups)
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 1
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### delivery.php

```
═══════════════════════════════════════════════
  AUDIT: delivery.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-18 16 lines block comment

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 1 × SELECT * (acceptable за tenants/* lookups)
⚠ WARN [PERFORMANCE]: DB заявки в цикъл (N+1 риск)
      224-231: DB call inside loop
      425-434: DB call inside loop

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 1  INFO: 2
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### orders.php

```
═══════════════════════════════════════════════
  AUDIT: orders.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### order.php

```
═══════════════════════════════════════════════
  AUDIT: order.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-19 17 lines block comment

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 1 × SELECT * (acceptable за tenants/* lookups)
⚠ WARN [PERFORMANCE]: DB заявки в цикъл (N+1 риск)
      100-102: DB call inside loop

── [DB_FIELDS] ──
✗ CRIT [DB_FIELDS]: status='cancelled' → използвай status='canceled' (един L)

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 1  WARN: 1  INFO: 2
✗ AUDIT — 1 критични проблема
═══════════════════════════════════════════════
```

### warehouse.php

```
═══════════════════════════════════════════════
  AUDIT: warehouse.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### inventory.php

```
═══════════════════════════════════════════════
  AUDIT: inventory.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
ℹ INFO [PERFORMANCE]: 2 × SELECT * (acceptable за tenants/* lookups)
⚠ WARN [PERFORMANCE]: DB заявки в цикъл (N+1 риск)
      48-49: DB call inside loop
      68-71: DB call inside loop

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 1  INFO: 1
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### defectives.php

```
═══════════════════════════════════════════════
  AUDIT: defectives.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
⚠ WARN [PERFORMANCE]: DB заявки в цикъл (N+1 риск)
      130-131: DB call inside loop

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 1  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### stats.php

```
═══════════════════════════════════════════════
  AUDIT: stats.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### settings.php

```
═══════════════════════════════════════════════
  AUDIT: settings.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### ai-studio.php

```
═══════════════════════════════════════════════
  AUDIT: ai-studio.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-13 11 lines block comment

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 1
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### printer-setup.php

```
═══════════════════════════════════════════════
  AUDIT: printer-setup.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 0
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

### biz-coefficients.php

```
═══════════════════════════════════════════════
  AUDIT: biz-coefficients.php
═══════════════════════════════════════════════

── [SYNTAX] PHP lint ──
✔ OK   [SYNTAX]: php -l clean

── [SECURITY] ──
✔ OK   [SECURITY]: Без mysql_query
✔ OK   [SECURITY]: Без eval/exec/system/passthru
✔ OK   [SECURITY]: Няма superglobals интерполирани в SQL низове (prepared statements OK)
✔ OK   [SECURITY]: Без dynamic include от $_GET/$_POST

── [DEAD_CODE] ──
✔ OK   [DEAD_CODE]: Без големи закоментирани блокове
ℹ INFO [DEAD_CODE]: Дълги /* */ блокове
      2-16 14 lines block comment

── [PERFORMANCE] ──
✔ OK   [PERFORMANCE]: Без SELECT *
✔ OK   [PERFORMANCE]: Без очевидни N+1 patterns

── [DB_FIELDS] ──
✔ OK   [DB_FIELDS]: Без DB schema violations

── [HARDCODED] ──
✔ OK   [HARDCODED]: Без hardcoded валути

═══════════════════════════════════════════════
  CRIT: 0  WARN: 0  INFO: 1
✔ AUDIT PASSED — без критични проблеми
═══════════════════════════════════════════════
```

