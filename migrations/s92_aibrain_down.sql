-- ════════════════════════════════════════════════════════════
-- S92.AIBRAIN.PHASE1 — ai_brain_queue DOWN migration
-- Round-trip safe: re-applying UP after DOWN should recreate identical schema.
-- ════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS ai_brain_queue;

DELETE FROM schema_migrations WHERE version = 's92_aibrain';
