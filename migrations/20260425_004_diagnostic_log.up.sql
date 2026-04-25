-- ═══════════════════════════════════════════════════════════════
-- 20260425_002_diagnostic_log (UP)
-- S80.DIAG.STEP1 — създава diagnostic_log таблица
-- Записва всеки run на tools/diagnostic/run_diag.py
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS diagnostic_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    trigger_type ENUM(
        'manual','cron_weekly','cron_monthly',
        'module_commit','user_command','milestone','suspicion'
    ) NOT NULL,
    module_name VARCHAR(60) NOT NULL,
    git_commit_sha VARCHAR(40) DEFAULT NULL,
    total_scenarios INT DEFAULT 0,
    passed INT DEFAULT 0,
    failed INT DEFAULT 0,
    skipped INT DEFAULT 0,
    category_a_pass_rate DECIMAL(5,2) DEFAULT NULL,
    category_b_pass_rate DECIMAL(5,2) DEFAULT NULL,
    category_c_pass_rate DECIMAL(5,2) DEFAULT NULL,
    category_d_pass_rate DECIMAL(5,2) DEFAULT NULL,
    failures_json JSON DEFAULT NULL,
    duration_seconds INT DEFAULT 0,
    notes TEXT DEFAULT NULL,
    INDEX idx_module_time (module_name, run_timestamp),
    INDEX idx_trigger (trigger_type, run_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
