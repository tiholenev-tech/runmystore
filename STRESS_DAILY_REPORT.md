# 🌙 STRESS DAILY REPORT

**Статус:** ✅ OK
**Run id:** 1
**Tenant:** 7 (Тихол пробен — per FACT_TENANT_7.md)
**Started:** 2026-05-16 03:32:53  •  Duration: 3150 ms
**Report generated:** 2026-05-16 06:34 EEST

## 🎯 Target scenarios (S001, S002, S007, S009)

| ID | Outcome | Duration | Note |
|---|---|---|---|
| S001 | ✅ pass | 38 ms | — |
| S002 | ✅ pass | 7 ms | — |
| S007 | 🟡 skip | 0 ms | no smoke_sql defined (waiting for module) |
| S009 | ✅ pass | 10 ms | — |

## Aggregate

- Total scenarios: 75
- Pass: **52**  •  Fail: **22**  •  Skip: **1**

<details><summary>All 22 fails (non-target scenarios) — click</summary>

| ID | Reason |
|---|---|
| S005 | OperationalError: (1054, "Unknown column 'status' in 'where clause'") |
| S015 | OperationalError: (1054, "Unknown column 'si.created_at' in 'where clause'") |
| S016 | ProgrammingError: (1146, "Table 'runmystore.sale_payments' doesn't exist") |
| S019 | OperationalError: (1054, "Unknown column 'c.type' in 'where clause'") |
| S021 | OperationalError: (1054, "Unknown column 'discount_percent' in 'where clause'") |
| S024 | OperationalError: (1054, "Unknown column 'status' in 'where clause'") |
| S025 | OperationalError: (1054, "Unknown column 'confidence' in 'where clause'") |
| S028 | OperationalError: (1054, "Unknown column 'created_at_bucket' in 'field list'") |
| S029 | OperationalError: (1054, "Unknown column 'status' in 'where clause'") |
| S030 | expected >= 1 rows, got 0 |
| S034 | OperationalError: (1054, "Unknown column 'movement_type' in 'where clause'") |
| S035 | OperationalError: (1054, "Unknown column 'movement_type' in 'where clause'") |
| S046 | ProgrammingError: (1146, "Table 'runmystore.voice_log' doesn't exist") |
| S047 | OperationalError: (1054, "Unknown column 'cron_name' in 'field list'") |
| S061 | OperationalError: (1054, "Unknown column 'notes' in 'where clause'") |
| S062 | OperationalError: (1054, "Unknown column 'notes' in 'where clause'") |
| S063 | OperationalError: (1054, "Unknown column 'notes' in 'where clause'") |
| S064 | OperationalError: (1054, "Unknown column 'customer_email' in 'where clause'") |
| S065 | OperationalError: (1054, "Unknown column 'ie.ref_id' in 'on clause'") |
| S067 | OperationalError: (1054, "Unknown column 'notes' in 'where clause'") |
| S068 | OperationalError: (1054, "Unknown column 'notes' in 'where clause'") |
| S070 | OperationalError: (1054, "Unknown column 'notes' in 'where clause'") |

</details>
