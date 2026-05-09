# tools/stress/regression_tests/ — bugfix verification (S130).
#
# Всеки тест е независим скрипт, който:
#   1. Установява връзка със sandbox DB (НЕ production)
#   2. Сетва STRESS Lab tenant
#   3. Изпълнява pre-condition assertion
#   4. Apply-ва patch (по референция или вече apply-ван) — read-only check
#   5. Изпълнява post-condition assertion
#   6. Връща {fix_id, status, evidence}
#
# Run: python3 -m tools.stress.regression_tests.runner
