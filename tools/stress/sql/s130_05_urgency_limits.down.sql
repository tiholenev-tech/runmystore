-- Rollback за s130_05_urgency_limits.up.sql
-- ВАЖНО: НЕ изтрива самата tenant_settings таблица — друг код може да я ползва.

DELETE FROM tenant_settings WHERE key_name = 'insight_limits';
