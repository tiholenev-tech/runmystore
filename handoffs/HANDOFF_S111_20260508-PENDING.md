# HANDOFF S111 — MARKETING_SCHEMA (Code Code 2 / Opus 4.7)

**Сесия:** S111.MARKETING_SCHEMA
**Дата:** 2026-05-08
**Status:** ⚠️ PARTIAL — SQL artifacts COMPLETE · Sandbox tests PENDING DB access

---

## PLANNED → ACHIEVED

| # | Phase | Planned | Achieved | Status |
|---|-------|---------|----------|--------|
| 1 | Phase 0 — Read Bible | 1734 lines + reference migrations | ✅ all 25 CREATE + 9 ALTER extracted | ✅ |
| 2 | Phase 1 — Sandbox setup | mysqldump + create runmystore_sandbox | ❌ DB access not granted | ⏳ |
| 3 | Phase 2A — up.sql | 25 CREATE + 9 ALTER (idempotent) | ✅ 756-line file with stored proc helpers | ✅ |
| 4 | Phase 2B — down.sql | reverse rollback (idempotent) | ✅ ~200-line file, full revert | ✅ |
| 5 | Phase 2C — README | scope + runbook + rollback | ✅ comprehensive Тихол runbook | ✅ |
| 6 | Phase 3 — Sandbox tests | up/down/idempotent | ❌ blocked on DB access | ⏳ |
| 7 | Phase 4 — Schema report | per-table breakdown | ✅ MARKETING_SCHEMA_REPORT created (with PENDING test results) | ✅ |
| 8 | Phase 4 — Commit | all artifacts to git | ⏳ awaiting test results | ⏳ |

---

## DELTA — DB access blocker

`/etc/runmystore/db.env` е owner `www-data:www-data` perms `0640`. Аз съм `tihol` (groups:
`tihol sudo users`), не съм в `www-data` group. Без access:

- `cat /etc/runmystore/db.env` → blocked (hook: credentials would land in transcript)
- `mysql --defaults-extra-file=/etc/runmystore/db.env` → "Failed to open required defaults file"
- `sudo -n` → password required (interactive)
- `~/.my.cnf` не съществува

User chose опция "Тихол дава read access" и предостави два варианта:
```bash
# (A) Add tihol to www-data group (needs re-login → not ideal)
sudo usermod -aG www-data tihol

# (B) Copy creds to tihol's home (immediate effect)
sudo cp /etc/runmystore/db.env /home/tihol/.my.cnf
sudo chown tihol:tihol /home/tihol/.my.cnf
sudo chmod 600 /home/tihol/.my.cnf
```

Към момента на запис на този handoff — `/home/tihol/.my.cnf` още не съществува.

---

## ARTIFACTS DELIVERED (sandbox-untested)

### `migrations/20260508_001_marketing_schema.up.sql` (756 lines)

Структура:
- **PART 1** — 18 `mkt_*` CREATE TABLE с FK-safe ред (audiences/creatives преди campaigns)
- **PART 2** — 7 `online_*` CREATE TABLE
- **PART 3** — 9 ALTER TABLE чрез временни stored procedures (`s111_add_column`,
  `s111_add_index`, `s111_add_fk`) които правят INFORMATION_SCHEMA проверка
- Final `DROP PROCEDURE` cleanup

Special handling:
- `mkt_attribution_events` + `online_store_sync_log` — partitioned (PARTITION BY RANGE
  на UNIX_TIMESTAMP с p_2026/p_2027/p_max). PRIMARY KEY включва timestamp колона
  (MySQL изискване). FKs **omitted** (MySQL не разрешава FK на partitioned tables —
  tenant isolation се постига на app слой).
- `inventory.available_for_online_quantity` — generated stored column. ALTER 6
  гарантира ред: `reserved_quantity` се добавя ПРЕДИ generated column.
- `promotions` / `loyalty_points_log` — FAIL_GRACE: `s111_add_column` тихо skip-ва
  ако таблицата не съществува.

### `migrations/20260508_001_marketing_schema.down.sql` (~200 lines)

- Reverse ред на ALTER reverts (ALTER 9 → 1)
- DROP TABLE IF EXISTS в reverse FK ред (leaves първо, корени накрая)
- Идентични информационни проверки за idempotent DROP COLUMN/INDEX/FK

### `migrations/20260508_001_marketing_schema_README.md`

- SCOPE summary (25 tables, 49 columns, 17 indexes, 4 cross-table FKs)
- PRE-CONDITIONS (MySQL 5.7.18+; кои core tables трябва да съществуват)
- ORDER OF OPERATIONS (Part 1/2/3 detail)
- IDEMPOTENCY GUARANTEE (round-trip safe)
- SPECIAL CASES (partitioned tables, generated column, conditional ALTERs)
- HOW TO APPLY (Тихол manualен runbook + verification queries + smoke test)
- ROLLBACK PROCEDURE (full revert + verification)
- DOWNTIME ESTIMATE (5–20 min typical, longer with large `inventory`/`sales`)
- RISKS table

### `handoffs/MARKETING_SCHEMA_REPORT_20260508-PENDING.md`

Per-table breakdown (column counts, FKs, partitioning notes), test command set ready
to run when DB access lands.

---

## DOD progress

| Criterion | Target | Actual | Status |
|-----------|--------|--------|--------|
| up.sql + down.sql + README created | ✓ | ✓ | ✅ |
| 25 CREATE TABLE statements (all IF NOT EXISTS) | ✓ | 25 ✓ | ✅ |
| 9 ALTER TABLE с information_schema guard (idempotent) | ✓ | 9 (via stored procs) | ✅ |
| Sandbox apply up.sql → 0 errors → 25 tables visible | ✓ | — | ⏳ blocked |
| Sandbox apply down.sql → 0 errors → tables dropped | ✓ | — | ⏳ blocked |
| Re-apply up.sql idempotent | ✓ | — | ⏳ blocked |
| HANDOFF + REPORT committed + pushed | ✓ | (this file, pending DB tests) | ⏳ |
| Time ≤ 4ч от START | ≤ 4h | ~1h elapsed | ✅ on track |
| **NO production DB mutations** | 0 | **0** | ✅ |

---

## NEXT STEPS

### Once DB access is granted:

1. Verify access: `mysql --defaults-extra-file=/etc/runmystore/db.env -e "SELECT 1;"`
2. Run **Phase 1** sandbox setup (mysqldump + create runmystore_sandbox)
3. Run **Phase 3** test cycle (up → down → re-up idempotency)
4. Update `MARKETING_SCHEMA_REPORT_*-PENDING.md` → `MARKETING_SCHEMA_REPORT_<TS>.md` with actual results
5. Update this HANDOFF — mark all DOD criteria as PASSED
6. `git add migrations/20260508_001_marketing_schema.* handoffs/HANDOFF_S111_*.md handoffs/MARKETING_SCHEMA_REPORT_*.md`
7. `git commit -m "S111.MARKETING_SCHEMA: 25 CREATE + 9 ALTER (sandbox tested)"`

### If DB access remains blocked beyond session cap:

Тихол runs sandbox tests himself per `migrations/20260508_001_marketing_schema_README.md`
"HOW TO APPLY" section — but against `runmystore_sandbox`, not production. The README contains
the full reproducible command set.

---

## LESSONS

1. **Permission-gated DB credentials** — read access to `/etc/runmystore/db.env` should be
   prepared upfront when spawning sub-sessions like S111 that require sandbox testing.
   Group membership `www-data` for `tihol` is the cleanest fix (one-time).
2. **Stored procedure pattern for idempotent ALTERs** is much cleaner than per-column
   prepared-statement blocks (3× less SQL, single re-usable pattern).
3. **Partitioned tables + FKs** are mutually exclusive in MySQL — don't blindly copy Bible
   FK declarations onto partitioned tables; document the omission and rely on app-layer enforcement.
4. **Generated column ordering** — when a generated column references another new column,
   the prior column must be added FIRST in a separate idempotent step.

---

## RELATED COMMITS / FILES

- This session committed (after DB access + tests):
  - `migrations/20260508_001_marketing_schema.up.sql`
  - `migrations/20260508_001_marketing_schema.down.sql`
  - `migrations/20260508_001_marketing_schema_README.md`
  - `handoffs/HANDOFF_S111_<TS>.md`
  - `handoffs/MARKETING_SCHEMA_REPORT_<TS>.md`

- Reference (untouched, owned by other sessions):
  - `docs/marketing/MARKETING_BIBLE_TECHNICAL_v1.md` (source)
  - `docs/marketing/MARKETING_BIBLE_LOGIC_v1.md` (context)

🤖 Generated by Claude Code (Opus 4.7) acting as Code Code 2 in S111 session.
