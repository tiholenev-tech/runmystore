#!/usr/bin/env bash
# tools/stress/sandbox_db_setup.sh — mysqldump main → runmystore_stress_sandbox.
#
# Цел: създава изолиран sandbox DB от копие на production (runmystore), изтрива
# всички tenants освен placeholder, и го оставя готов за seed-ване от
# tools/stress/setup_stress_tenant.py + 6-те seed скрипта.
#
# ABSOLUTE GUARDS:
#   - Скриптът refuse-ва ако target DB == 'runmystore' (production).
#   - mysqldump от production може да отнеме 5-15 мин — STDOUT е piped, не файлово
#     (по-малко disk overhead).
#   - DELETE на tenants е selective — пази 1 placeholder (ID=1) ако съществува.
#   - НИКОГА не изтрива master данни в schema; само ROW data.
#
# Изпълнение:
#   sudo -u www-data bash tools/stress/sandbox_db_setup.sh
#
# Време: ~10 минути за production database с 50K+ rows.

set -euo pipefail

PROD_DB="${PROD_DB:-runmystore}"
SANDBOX_DB="${SANDBOX_DB:-runmystore_stress_sandbox}"
DB_ENV="${DB_ENV:-/etc/runmystore/db.env}"
LOG_DIR="$(dirname "$0")/data/sandbox_runs"
TS=$(date +%Y%m%d_%H%M%S)
LOG="$LOG_DIR/setup_${TS}.log"

mkdir -p "$LOG_DIR"

echo "[$(date)] sandbox_db_setup начало" | tee "$LOG"

# ─── (a) Sanity: target DB не може да е production ───
if [[ "$SANDBOX_DB" == "runmystore" || "$SANDBOX_DB" == "$PROD_DB" ]]; then
    echo "[REFUSE] SANDBOX_DB == production. Прекъсване." | tee -a "$LOG"
    exit 2
fi

# ─── (b) Зареди credentials ───
if [[ ! -r "$DB_ENV" ]]; then
    echo "[FATAL] $DB_ENV не е четим. Изпълни като www-data." | tee -a "$LOG"
    exit 2
fi
# shellcheck source=/dev/null
set -a
source "$DB_ENV"
set +a

DB_USER="${DB_USER:?DB_USER missing}"
DB_PASS="${DB_PASS:?DB_PASS missing}"
DB_HOST="${DB_HOST:-127.0.0.1}"

echo "[INFO] PROD=$PROD_DB SANDBOX=$SANDBOX_DB HOST=$DB_HOST USER=$DB_USER" | tee -a "$LOG"

# ─── (c) Създай sandbox DB (DROP + CREATE) ───
echo "[STEP 1/4] Drop + Create $SANDBOX_DB" | tee -a "$LOG"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "
    DROP DATABASE IF EXISTS \`$SANDBOX_DB\`;
    CREATE DATABASE \`$SANDBOX_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
" 2>&1 | tee -a "$LOG"

# ─── (d) mysqldump production → sandbox (без routines, без events) ───
echo "[STEP 2/4] mysqldump $PROD_DB → $SANDBOX_DB (5-15 мин)" | tee -a "$LOG"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
    --single-transaction --quick --skip-lock-tables \
    --no-create-db --routines --triggers \
    "$PROD_DB" \
| mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$SANDBOX_DB" 2>&1 | tee -a "$LOG"

# ─── (e) Изтрий всички tenants освен placeholder ID=1 (ако съществува) ───
echo "[STEP 3/4] Зачисти tenants — пази placeholder + cascade транзакционни данни" | tee -a "$LOG"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$SANDBOX_DB" <<'EOSQL' 2>&1 | tee -a "$LOG"
SET FOREIGN_KEY_CHECKS = 0;

-- Списък tenants преди cleanup (одит)
SELECT 'BEFORE:' AS marker, id, email, name FROM tenants ORDER BY id;

-- Изтрий всички tenants освен ID=1 (ако exists). 1 е резервиран placeholder.
-- Setup_stress_tenant.py ще създаде нов tenant със stress@runmystore.ai.
DELETE FROM tenants WHERE id <> 1;

-- Cascade през основните транзакционни таблици — пази master records (products,
-- stores, suppliers) защото schema/UI разчита на не-празни лookups.
-- Sandbox seed скриптовете ще добавят нови records.
DELETE FROM sales WHERE tenant_id <> 1;
DELETE FROM sale_items WHERE sale_id NOT IN (SELECT id FROM sales);
DELETE FROM ai_insights WHERE tenant_id <> 1;
DELETE FROM lost_demand WHERE tenant_id <> 1;
DELETE FROM search_log WHERE tenant_id <> 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Списък след cleanup
SELECT 'AFTER:' AS marker, COUNT(*) AS tenants FROM tenants;
SELECT 'AFTER:' AS marker, COUNT(*) AS sales FROM sales;
SELECT 'AFTER:' AS marker, COUNT(*) AS insights FROM ai_insights;
EOSQL

# ─── (f) Status report ───
echo "[STEP 4/4] Готов. Виж $LOG за детайли." | tee -a "$LOG"
echo ""
echo "Следваща стъпка:"
echo "  sudo -u www-data DB_NAME=$SANDBOX_DB python3 tools/stress/setup_stress_tenant.py --apply"
echo "  sudo -u www-data DB_NAME=$SANDBOX_DB python3 tools/stress/seed_stores.py --apply"
echo "  ... (виж SANDBOX_GUIDE.md за пълен ред)"
