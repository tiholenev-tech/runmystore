# Visual Gate DB Fixtures (S135)

Companion to `design-kit/visual-gate.sh` (S134, v1.1). Closes the gap from
`VISUAL_GATE_SPEC.md §13`: auth-fixture.php bypasses login, but rendering
.php targets still PHP-fatals on `effectivePlan(): Argument #1 ($tenant)
must be of type array, false given` because no DB row exists for the
fixture's `tenant_id=1`.

## Files

| File                       | Purpose                                            |
|----------------------------|----------------------------------------------------|
| `seed_test_tenant.sql`     | Idempotent INSERT…ON DUPLICATE KEY UPDATE for tenant 999 (1 store, 1 owner, 5 products, 3 ai_insights). |
| `render_helper.php`        | Re-exports `VG_*` env to point at tenant 999, then defers to existing `design-kit/auth-fixture.php`. |
| `README.md`                | This file.                                         |

## Apply

⚠️  **DO NOT apply on the live runmystore production DB without DBA review.**
Use a sandbox copy of the schema, or a staging instance. Even though
`tenant_id=999` should be isolated, AUTO_INCREMENT collisions on stores /
users / products IDs are conceivable depending on live row counts.

```bash
# Sandbox apply (replace creds appropriately):
mysql -u<USER> -p<PASS> <SANDBOX_DB> < tools/visual-gate/fixtures/seed_test_tenant.sql
```

## Teardown

The bottom of `seed_test_tenant.sql` contains a commented-out teardown
block. Copy + uncomment + run:

```sql
START TRANSACTION;
DELETE FROM ai_insights WHERE tenant_id = 999;
DELETE FROM products    WHERE tenant_id = 999;
DELETE FROM categories  WHERE tenant_id = 999;
DELETE FROM users       WHERE tenant_id = 999;
DELETE FROM stores      WHERE tenant_id = 999;
DELETE FROM tenants     WHERE id        = 999;
COMMIT;
```

## Invocation order

After applying the seed, point the visual gate at tenant 999 by exporting
the `VG_*` env vars before invoking `visual-gate.sh`:

```bash
export VG_TENANT_ID=999 VG_STORE_ID=9990 VG_USER_ID=9990 VG_ROLE=owner
./design-kit/visual-gate.sh --auth=admin \
    mockups/P10_lesny_mode.html life-board.php \
    visual-gate-test-runs/$(date +%Y%m%d_%H%M%S)_lifeboard
```

The `--auth=admin` flag triggers `apply_auth_mode()` in visual-gate.sh,
which respects pre-existing `VG_TENANT_ID` / `VG_STORE_ID` / `VG_USER_ID`
via `${VAR:-default}` parameter expansion (verified: design-kit/visual-gate.sh
lines 163-176).

## Known limitations

1. **Schema columns are best-effort.** They were derived by reading
   `register.php`, `compute-insights.php`, `seed_data.sql` and grep over
   the source tree. If the live schema has additional NOT NULL columns
   without defaults, the INSERT will fail — extend the seed rather than
   weakening the constraint.

2. **No `effectivePlan()` row.** If the tenant has plan-tier-specific
   gating tables that `effectivePlan()` reads (per S134 spec §13 the
   exact failure was on this function), additional seed rows may be
   needed. Run the gate, observe the next fatal, extend the seed.

3. **password column is bcrypt-shaped placeholder.** Auth-fixture.php
   sets `$_SESSION` directly so this is never validated. If anything ever
   does call `password_verify()` against this row, it will return false.
