# 🚨 TESTING_LOOP — ANOMALY LOG

Append-only лог. Записва се само при 🟡 warning / 🔴 critical статус след
`snapshot_diff.py`. 🟢 healthy дни не се записват (тишина = добро).

Records са в обратен chronological ред (newest at bottom — append-only).
Шеф-чат при boot преглежда последните 5 записа.

---
