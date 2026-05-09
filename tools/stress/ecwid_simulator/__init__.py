"""tools/stress/ecwid_simulator — Phase L (S130 extension).

Симулатор на онлайн магазин (Ecwid-style) върху STRESS Lab tenant.

Модули:
  - ecwid_simulator.py            — генерира fake online поръчки
  - ecwid_to_runmystore_sync.py   — конвертира в sales + inventory_events

ABSOLUTE GUARDS:
  * Само върху STRESS Lab (assert_stress_tenant)
  * --dry-run по default
  * Random seed = 42 (deterministic)
"""
