-- s130_05_urgency_limits.up.sql
-- Bugfix 5 (STRESS_BUILD_PLAN ред 58) — конфигурируеми лимити в tenant_settings.
-- ПРИЛАГА СЕ САМО ВЪРХУ runmystore_stress_sandbox.

CREATE TABLE IF NOT EXISTS tenant_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    key_name VARCHAR(64) NOT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tenant_key (tenant_id, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tenant_settings (tenant_id, key_name, value)
SELECT id, 'insight_limits',
       '{"critical":2,"warning":3,"info":3}'
FROM tenants;

UPDATE tenant_settings
SET value = '{"critical":10,"warning":15,"info":20}'
WHERE tenant_id = (SELECT id FROM tenants WHERE email = 'stress@runmystore.ai')
  AND key_name = 'insight_limits';
