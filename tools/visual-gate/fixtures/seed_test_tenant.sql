-- ═══════════════════════════════════════════════════════════════════════
-- Visual Gate test fixture — seed_test_tenant.sql
-- ═══════════════════════════════════════════════════════════════════════
-- Author: S135 (Claude Code, 2026-05-09)
-- Purpose: minimal DB rows so visual-gate.sh can render life-board.php /
--          chat.php / products.php without PHP fatals (per spec §13).
-- Target tenant_id: 999 (reserved test tenant — MUST NOT collide with any
--          live tenant). store_id, user_id, product_id ranges below also
--          chosen to avoid collisions; verify against your DB before APPLY.
--
-- DB:      MySQL 8.0+, utf8mb4. Same DB as runmystore production (or a
--          sandbox copy). DO NOT apply on the live production DB without
--          DBA review — even though tenant_id=999 should be isolated, a
--          stray AUTO_INCREMENT collision could cause an INSERT to overlap
--          with a real row.
--
-- APPLY (sandbox or staging, never live without review):
--   mysql -u<USER> -p<PASS> <DB_NAME> < seed_test_tenant.sql
--
-- TEARDOWN (idempotent — safe to run repeatedly):
--   See section TEARDOWN at the bottom of this file. Each statement scoped
--   to tenant_id=999 only.
--
-- WARNING: Schema columns below derived from reading register.php,
--          compute-insights.php, seed_data.sql and grep over /var/www/
--          source. If your live schema has additional NOT NULL columns
--          without defaults, INSERTs will error — extend this file rather
--          than removing the constraint.
-- ═══════════════════════════════════════════════════════════════════════

START TRANSACTION;

-- ─── 1. tenants — main account row ────────────────────────────────────
-- Mirrors register.php INSERT shape. plan='start' is safe (no PRO gating).
INSERT INTO tenants
    (id, name, email, password, plan, trial_ends_at, country, language,
     currency, timezone, supato_mode, is_active)
VALUES
    (999, 'VG Test Tenant', 'visual-gate-test@runmystore.invalid',
     '$2y$10$UFAKEHASHFAKEHASHFAKEHASHFAKEHASHFAKEHASHFAKEHASHFAK',
     'start', DATE_ADD(NOW(), INTERVAL 365 DAY),
     'BG', 'bg', 'EUR', 'Europe/Sofia', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── 2. stores ────────────────────────────────────────────────────────
INSERT INTO stores (id, tenant_id, name, is_active)
VALUES (9990, 999, 'VG Test Store', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── 3. users — one owner ─────────────────────────────────────────────
-- Password column is bcrypt-shaped placeholder; auth-fixture.php sets
-- $_SESSION directly so this is never validated.
INSERT INTO users (id, tenant_id, store_id, name, email, password, role, is_active)
VALUES
    (9990, 999, 9990, 'VG Test Owner',
     'vg-owner@runmystore.invalid',
     '$2y$10$UFAKEHASHFAKEHASHFAKEHASHFAKEHASHFAKEHASHFAKEHASHFAK',
     'owner', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── 4. categories — anchors for products ─────────────────────────────
INSERT INTO categories (id, tenant_id, name, variant_type) VALUES
    (9990, 999, 'VG Cat A', 'size_color'),
    (9991, 999, 'VG Cat B', 'none')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── 5. products — 5 rows with varied state ───────────────────────────
-- Mix of low-stock, healthy stock, zero cost (drives chat.php cost_price
-- "confidence_pct" badge to a non-edge value), high-margin.
INSERT INTO products
    (id, tenant_id, category_id, code, name, barcode, size, color,
     cost_price, wholesale_price, retail_price, min_quantity, is_active)
VALUES
    (99001, 999, 9990, 'VGP01', 'VG Product Healthy',  '5999990000001', 'M',  'Black', 10.00, 16.00, 24.99, 5, 1),
    (99002, 999, 9990, 'VGP02', 'VG Product Low-Stock', '5999990000002', 'L',  'White',  8.00, 12.00, 19.99, 5, 1),
    (99003, 999, 9991, 'VGP03', 'VG Product No-Cost',   '5999990000003', 'ONE','Red',    0.00, 10.00, 18.00, 3, 1),
    (99004, 999, 9990, 'VGP04', 'VG Product High-Margin','5999990000004','S', 'Blue',   5.00, 12.00, 35.00, 4, 1),
    (99005, 999, 9991, 'VGP05', 'VG Product Inactive',  '5999990000005', 'XL','Grey',   7.00, 11.00, 19.00, 2, 0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ─── 6. ai_insights — 3 rows for chat.php signals ──────────────────────
-- module='home' matches chat.php:228 / life-board.php:112 calls.
-- expires_at in the future so getInsightsForModule()'s expires_at gate passes.
-- topic_id values chosen high to avoid any cron-generated insight collision.
INSERT INTO ai_insights
    (tenant_id, store_id, topic_id, category, grp, module, urgency,
     fundamental_question, plan_gate, role_gate,
     title, data_json, value_numeric,
     product_id, product_count, supplier_id,
     action_label, action_type, action_url, action_data,
     expires_at, created_at)
VALUES
    (999, 9990, 990001, 'inventory', 1, 'home', 'medium',
     'Какво е в недостиг?', 'start', 'owner',
     'VG Insight: low stock test', '{"vg":true}', 1.0,
     99002, 1, NULL,
     'Виж', 'deeplink', 'products.php?filter=running_out', NULL,
     DATE_ADD(NOW(), INTERVAL 7 DAY), NOW()),
    (999, 9990, 990002, 'pricing', 1, 'home', 'low',
     'Кои продукти нямат себестойност?', 'start', 'owner',
     'VG Insight: missing cost price', '{"vg":true}', 1.0,
     99003, 1, NULL,
     'Добави цена', 'deeplink', 'products.php?filter=no_cost', NULL,
     DATE_ADD(NOW(), INTERVAL 7 DAY), NOW()),
    (999, 9990, 990003, 'profit', 1, 'home', 'low',
     'Кой е най-печелившият артикул?', 'start', 'owner',
     'VG Insight: top profit', '{"vg":true}', 1.0,
     99004, 1, NULL,
     'Поръчай още', 'deeplink', 'products.php?filter=top_profit', NULL,
     DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

COMMIT;

-- ═══════════════════════════════════════════════════════════════════════
-- TEARDOWN — paste below as a separate run when you want to remove fixtures
-- ═══════════════════════════════════════════════════════════════════════
-- START TRANSACTION;
-- DELETE FROM ai_insights WHERE tenant_id = 999;
-- DELETE FROM products    WHERE tenant_id = 999;
-- DELETE FROM categories  WHERE tenant_id = 999;
-- DELETE FROM users       WHERE tenant_id = 999;
-- DELETE FROM stores      WHERE tenant_id = 999;
-- DELETE FROM tenants     WHERE id        = 999;
-- COMMIT;
-- ═══════════════════════════════════════════════════════════════════════
