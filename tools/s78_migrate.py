#!/usr/bin/env python3
"""
S78 DB MIGRATION — RunMyStore.ai
================================
Създава всички S77 таблици + idempotency_log + user_devices.
Идемпотентен: безопасен за повторно пускане.

Последователност:
  1. Парсва credentials от /var/www/runmystore/config/database.php
  2. Backup (mysqldump → /root/backup_s78_YYYYMMDD_HHMM.sql)
  3. Schema check + CREATE/ALTER
  4. Verify
  5. Log

Чете se като 'python3 /tmp/s78_migrate.py'.
"""
import os
import re
import sys
import subprocess
import datetime

CONFIG_PATH = "/var/www/runmystore/config/database.php"
BACKUP_DIR = "/root"
LOG_PATH = "/var/log/runmystore_migrations.log"


# ─────────────────────────────────────────────────────────────
# 1. CREDENTIALS
# ─────────────────────────────────────────────────────────────
def parse_db_config(path):
    """Извлича host, dbname, user, pass от config/database.php."""
    if not os.path.exists(path):
        sys.exit(f"[FATAL] {path} липсва.")
    txt = open(path).read()

    def grab(key):
        # Търси 'key' => 'value' или "key" => "value"
        m = re.search(
            rf"['\"]{key}['\"]\s*=>\s*['\"]([^'\"]+)['\"]", txt
        )
        if not m:
            # Алтернатива: define('DB_KEY', 'value')
            m = re.search(
                rf"define\(\s*['\"]DB_{key.upper()}['\"]\s*,\s*['\"]([^'\"]+)['\"]",
                txt,
            )
        return m.group(1) if m else None

    cfg = {
        "host": grab("host") or "localhost",
        "dbname": grab("dbname") or grab("database") or grab("name"),
        "user": grab("username") or grab("user"),
        "pass": grab("password") or grab("pass"),
    }
    missing = [k for k, v in cfg.items() if not v]
    if missing:
        sys.exit(f"[FATAL] Не намерих в config: {missing}")
    return cfg


# ─────────────────────────────────────────────────────────────
# 2. BACKUP
# ─────────────────────────────────────────────────────────────
def backup(cfg):
    ts = datetime.datetime.now().strftime("%Y%m%d_%H%M")
    out = f"{BACKUP_DIR}/backup_s78_{ts}.sql"
    print(f"[BACKUP] → {out}")
    env = os.environ.copy()
    env["MYSQL_PWD"] = cfg["pass"]
    with open(out, "w") as f:
        r = subprocess.run(
            [
                "mysqldump",
                "-h", cfg["host"],
                "-u", cfg["user"],
                "--single-transaction",
                "--routines",
                "--triggers",
                cfg["dbname"],
            ],
            stdout=f,
            stderr=subprocess.PIPE,
            env=env,
        )
    if r.returncode != 0:
        sys.exit(f"[FATAL] mysqldump failed: {r.stderr.decode()}")
    size_mb = os.path.getsize(out) / (1024 * 1024)
    print(f"[BACKUP] OK ({size_mb:.1f} MB)")
    return out


# ─────────────────────────────────────────────────────────────
# 3. MYSQL HELPERS
# ─────────────────────────────────────────────────────────────
def mysql_run(cfg, sql, fetch=False):
    """Пуска SQL чрез mysql CLI. Връща stdout ако fetch=True."""
    env = os.environ.copy()
    env["MYSQL_PWD"] = cfg["pass"]
    r = subprocess.run(
        [
            "mysql",
            "-h", cfg["host"],
            "-u", cfg["user"],
            "-N",
            "-B",
            cfg["dbname"],
        ],
        input=sql,
        text=True,
        capture_output=True,
        env=env,
    )
    if r.returncode != 0:
        raise RuntimeError(f"SQL error: {r.stderr.strip()}\nSQL: {sql[:200]}")
    return r.stdout.strip() if fetch else None


def table_exists(cfg, table):
    out = mysql_run(
        cfg,
        f"SELECT COUNT(*) FROM information_schema.tables "
        f"WHERE table_schema=DATABASE() AND table_name='{table}';",
        fetch=True,
    )
    return out == "1"


def column_exists(cfg, table, column):
    out = mysql_run(
        cfg,
        f"SELECT COUNT(*) FROM information_schema.columns "
        f"WHERE table_schema=DATABASE() AND table_name='{table}' AND column_name='{column}';",
        fetch=True,
    )
    return out == "1"


# ─────────────────────────────────────────────────────────────
# 4. DDL (BIBLE v3 APPENDIX §11)
# ─────────────────────────────────────────────────────────────
DDL_AI_INSIGHTS = """
CREATE TABLE IF NOT EXISTS ai_insights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NULL,
  topic_id VARCHAR(50) NOT NULL,
  module ENUM('home','products','warehouse','stats','sale','orders','deliveries',
              'transfers','inventory','loyalty') NOT NULL,
  urgency ENUM('critical','warning','info','opportunity') NOT NULL,
  fundamental_question ENUM('loss','loss_cause','gain','gain_cause',
                            'order','anti_order') DEFAULT NULL,
  pill_text VARCHAR(255) NOT NULL,
  detail_json JSON NULL,
  value_numeric DECIMAL(12,2) NULL,
  product_id INT NULL,
  supplier_id INT NULL,
  action_label VARCHAR(100) NULL,
  action_type ENUM('chat','url','order_draft','inline') DEFAULT 'chat',
  action_url VARCHAR(255) NULL,
  action_data JSON NULL,
  is_active BOOLEAN DEFAULT TRUE,
  computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  INDEX idx_tenant_module (tenant_id, module, urgency),
  INDEX idx_question (fundamental_question),
  INDEX idx_product (product_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

DDL_AI_SHOWN = """
CREATE TABLE IF NOT EXISTS ai_shown (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  insight_id INT NOT NULL,
  shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  tapped BOOLEAN DEFAULT FALSE,
  tapped_at TIMESTAMP NULL,
  INDEX idx_tenant_user (tenant_id, user_id),
  INDEX idx_insight (insight_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

DDL_SEARCH_LOG = """
CREATE TABLE IF NOT EXISTS search_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  user_id INT NOT NULL,
  query VARCHAR(255) NOT NULL,
  results_count INT DEFAULT 0,
  source ENUM('products','sale','warehouse','chat') DEFAULT 'products',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id, created_at),
  INDEX idx_zero (tenant_id, results_count, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

DDL_LOST_DEMAND = """
CREATE TABLE IF NOT EXISTS lost_demand (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  user_id INT NULL,
  query_text VARCHAR(500) NOT NULL,
  suggested_supplier_id INT NULL,
  matched_product_id INT NULL,
  resolved_order_id INT NULL,
  source ENUM('search','voice','barcode_miss','ai_chat','manual') DEFAULT 'search',
  resolved TINYINT(1) DEFAULT 0,
  times INT DEFAULT 1,
  first_asked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_asked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_resolved (tenant_id, resolved, last_asked_at),
  INDEX idx_supplier (suggested_supplier_id),
  INDEX idx_matched (matched_product_id),
  INDEX idx_resolved_order (resolved_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

DDL_SUPPLIER_ORDERS = """
CREATE TABLE IF NOT EXISTS supplier_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  store_id INT NOT NULL,
  supplier_id INT NOT NULL,
  order_type ENUM('min','partial','full','combined','urgent','seasonal',
                  'replen','blind','rebuy','bundle','basket') DEFAULT 'partial',
  status ENUM('draft','confirmed','sent','acked','partial','received',
              'cancelled','overdue') DEFAULT 'draft',
  priority TINYINT DEFAULT 5,
  total_items INT DEFAULT 0,
  total_cost DECIMAL(12,2) DEFAULT 0,
  expected_profit DECIMAL(12,2) DEFAULT 0,
  expected_delivery DATE NULL,
  actual_delivery DATE NULL,
  notes TEXT,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  received_at TIMESTAMP NULL,
  INDEX idx_tenant_status (tenant_id, status),
  INDEX idx_supplier (supplier_id, status),
  INDEX idx_overdue (expected_delivery, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

DDL_SUPPLIER_ORDER_ITEMS = """
CREATE TABLE IF NOT EXISTS supplier_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  variation_id INT NULL,
  qty_ordered INT NOT NULL,
  qty_received INT DEFAULT 0,
  unit_cost DECIMAL(10,2) NOT NULL,
  fundamental_question ENUM('loss','loss_cause','gain','gain_cause',
                            'order','anti_order') DEFAULT 'order',
  source ENUM('products','chat','home','sale','delivery','inventory',
              'warehouse','voice','lost_demand','basket','manual') DEFAULT 'manual',
  source_ref INT NULL,
  ai_reasoning TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order (order_id),
  INDEX idx_product (product_id),
  CONSTRAINT fk_soi_order FOREIGN KEY (order_id)
    REFERENCES supplier_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

DDL_SUPPLIER_ORDER_EVENTS = """
CREATE TABLE IF NOT EXISTS supplier_order_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  event_type ENUM('created','edited','status_change','item_added',
                  'item_removed','item_qty_change','note_added','sent',
                  'acked','partial_received','fully_received','cancelled'),
  old_value TEXT,
  new_value TEXT,
  user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_time (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

# ─── idempotency_log (multi-device race prevention) ──────────
# Проектирано в S78. Базирано на ai_actions_log pattern от BIBLE TECH §14.2
# + device_id и user_id за multi-device tracking.
DDL_IDEMPOTENCY_LOG = """
CREATE TABLE IF NOT EXISTS idempotency_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(128) NOT NULL,
  tenant_id INT NOT NULL,
  user_id INT NULL,
  device_id VARCHAR(64) NULL,
  action_type VARCHAR(50) NOT NULL,
  payload_json JSON NULL,
  result_json JSON NULL,
  status ENUM('processing','done','failed') DEFAULT 'processing',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  UNIQUE KEY uk_idem_key (idempotency_key),
  INDEX idx_tenant_created (tenant_id, created_at),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

# ─── user_devices (multi-device tracking) ────────────────────
# Проектирано в S78. Тракира кое устройство на кой потребител е активно.
DDL_USER_DEVICES = """
CREATE TABLE IF NOT EXISTS user_devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  device_id VARCHAR(64) NOT NULL,
  device_name VARCHAR(100) NULL,
  platform ENUM('web','ios','android','desktop') DEFAULT 'web',
  user_agent VARCHAR(500) NULL,
  last_ip VARCHAR(45) NULL,
  push_token VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_device (user_id, device_id),
  INDEX idx_tenant_user (tenant_id, user_id),
  INDEX idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""


# ─────────────────────────────────────────────────────────────
# 5. MIGRATIONS
# ─────────────────────────────────────────────────────────────
TABLES = [
    ("ai_insights", DDL_AI_INSIGHTS),
    ("ai_shown", DDL_AI_SHOWN),
    ("search_log", DDL_SEARCH_LOG),
    ("lost_demand", DDL_LOST_DEMAND),
    ("supplier_orders", DDL_SUPPLIER_ORDERS),
    ("supplier_order_items", DDL_SUPPLIER_ORDER_ITEMS),
    ("supplier_order_events", DDL_SUPPLIER_ORDER_EVENTS),
    ("idempotency_log", DDL_IDEMPOTENCY_LOG),
    ("user_devices", DDL_USER_DEVICES),
]

# Колони които може да трябва да се добавят към СЪЩЕСТВУВАЩИ таблици.
# (Ако таблицата е създадена от DDL по-горе — IF NOT EXISTS — тези ADD също
#  могат да се нуждаят когато има стара версия.)
ALTERS = {
    "ai_insights": [
        ("fundamental_question",
         "ALTER TABLE ai_insights ADD COLUMN fundamental_question "
         "ENUM('loss','loss_cause','gain','gain_cause','order','anti_order') "
         "DEFAULT NULL AFTER urgency, ADD INDEX idx_question (fundamental_question);"),
    ],
    "lost_demand": [
        ("suggested_supplier_id",
         "ALTER TABLE lost_demand ADD COLUMN suggested_supplier_id INT NULL AFTER query_text, "
         "ADD INDEX idx_supplier (suggested_supplier_id);"),
        ("matched_product_id",
         "ALTER TABLE lost_demand ADD COLUMN matched_product_id INT NULL AFTER suggested_supplier_id, "
         "ADD INDEX idx_matched (matched_product_id);"),
        ("resolved_order_id",
         "ALTER TABLE lost_demand ADD COLUMN resolved_order_id INT NULL AFTER matched_product_id, "
         "ADD INDEX idx_resolved_order (resolved_order_id);"),
        ("times",
         "ALTER TABLE lost_demand ADD COLUMN times INT DEFAULT 1 AFTER resolved;"),
    ],
}


def run_migration(cfg):
    created = []
    altered = []
    skipped = []

    # 1. CREATE TABLE IF NOT EXISTS
    for name, ddl in TABLES:
        existed = table_exists(cfg, name)
        mysql_run(cfg, ddl)
        if existed:
            skipped.append(name)
        else:
            created.append(name)
        print(f"  [{'+' if not existed else '=' }] {name}")

    # 2. ALTER TABLE ADD COLUMN (conditional)
    for tbl, col_specs in ALTERS.items():
        if not table_exists(cfg, tbl):
            continue
        for col, sql in col_specs:
            if column_exists(cfg, tbl, col):
                print(f"  [=] {tbl}.{col}")
                continue
            mysql_run(cfg, sql)
            altered.append(f"{tbl}.{col}")
            print(f"  [+] {tbl}.{col}")

    return created, altered, skipped


# ─────────────────────────────────────────────────────────────
# 6. VERIFY
# ─────────────────────────────────────────────────────────────
def verify(cfg):
    print("\n[VERIFY]")
    all_ok = True
    for name, _ in TABLES:
        ok = table_exists(cfg, name)
        print(f"  {'✓' if ok else '✗'} {name}")
        if not ok:
            all_ok = False
    # Колонни проверки
    checks = [
        ("ai_insights", "fundamental_question"),
        ("lost_demand", "suggested_supplier_id"),
        ("lost_demand", "matched_product_id"),
        ("lost_demand", "resolved_order_id"),
        ("lost_demand", "times"),
        ("supplier_order_items", "fundamental_question"),
    ]
    for tbl, col in checks:
        ok = column_exists(cfg, tbl, col)
        print(f"  {'✓' if ok else '✗'} {tbl}.{col}")
        if not ok:
            all_ok = False
    return all_ok


# ─────────────────────────────────────────────────────────────
# 7. LOG
# ─────────────────────────────────────────────────────────────
def log_result(backup_path, created, altered, skipped, ok):
    ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = (
        f"[{ts}] S78 migration | backup={backup_path} | "
        f"created={len(created)} | altered={len(altered)} | "
        f"skipped={len(skipped)} | verify={'OK' if ok else 'FAIL'}\n"
    )
    try:
        with open(LOG_PATH, "a") as f:
            f.write(line)
    except PermissionError:
        print(f"[WARN] Не мога да пиша {LOG_PATH} (без права). Прескачам.")


# ─────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────
def main():
    print("=" * 60)
    print("S78 DB MIGRATION — RunMyStore.ai")
    print("=" * 60)

    cfg = parse_db_config(CONFIG_PATH)
    print(f"[CONFIG] db={cfg['dbname']} host={cfg['host']} user={cfg['user']}")

    backup_path = backup(cfg)

    print("\n[MIGRATE]")
    created, altered, skipped = run_migration(cfg)

    print(f"\n  Създадени:  {len(created)}")
    print(f"  Променени:  {len(altered)}")
    print(f"  Прескочени: {len(skipped)}")

    ok = verify(cfg)
    log_result(backup_path, created, altered, skipped, ok)

    print("\n" + "=" * 60)
    if ok:
        print("✓ S78 MIGRATION УСПЕШНА")
        print(f"✓ Backup: {backup_path}")
    else:
        print("✗ VERIFY FAIL — виж изхода по-горе")
        print(f"  Rollback: mysql -u {cfg['user']} {cfg['dbname']} < {backup_path}")
    print("=" * 60)
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()
