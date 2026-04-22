<?php
/**
 * compute-insights.php
 * S79.INSIGHTS — 19 функции по 6-те фундаментални въпроса (Products module)
 *
 * Употреба:
 *   CLI: php compute-insights.php [tenant_id]   (default: всички активни tenants)
 *   AJAX: GET /compute-insights.php?ajax=compute_insights&tenant_id=N
 *   Cron: /usr/bin/php /var/www/runmystore/compute-insights.php
 *
 * Архитектура:
 *   - 6 фундаментални въпроса × 19 pf*() функции
 *   - Idempotent UPSERT в ai_insights (topic_id + product_id + fundamental_question)
 *   - WHERE tenant_id=? навсякъде, parametrized queries
 *   - role_gate per insight type
 *   - expires_at per въпрос (loss=3д, loss_cause=5д, gain/gain_cause=7д, order=5д, anti_order=14д)
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
if (file_exists(__DIR__ . '/config/helpers.php')) {
    require_once __DIR__ . '/config/helpers.php';
}

// =============================================================
// SECTION 1 — HELPERS
// =============================================================

function pfDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (function_exists('getDB')) {
            $pdo = getDB();
        } elseif (class_exists('DB')) {
            $pdo = DB::get();
        } else {
            throw new RuntimeException('Не намирам DB connection (getDB() или DB::pdo())');
        }
    }
    return $pdo;
}

function pfTableExists(string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        pfDB()->query("SELECT 1 FROM `$table` LIMIT 0");
        return $cache[$table] = true;
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function pfColumnExists(string $table, string $column): bool {
    static $cache = [];
    $key = "$table.$column";
    if (isset($cache[$key])) return $cache[$key];
    try {
        $st = pfDB()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS 
                               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $column]);
        return $cache[$key] = ((int)$st->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function pfDefaultStoreId(int $tenant_id): ?int {
    static $cache = [];
    if (isset($cache[$tenant_id])) return $cache[$tenant_id];
    try {
        $st = pfDB()->prepare("SELECT id FROM stores WHERE tenant_id = ? ORDER BY id LIMIT 1");
        $st->execute([$tenant_id]);
        $v = $st->fetchColumn();
        return $cache[$tenant_id] = ($v ? (int)$v : null);
    } catch (Throwable $e) {
        return $cache[$tenant_id] = null;
    }
}

function pfCategoryFor(string $topic_id): string {
    static $map = [
        'zero_stock_with_sales'  => 'inventory',
        'below_min_urgent'       => 'inventory',
        'running_out_today'      => 'inventory',
        'bestseller_low_stock'   => 'inventory',
        'selling_at_loss'        => 'pricing',
        'no_cost_price'          => 'pricing',
        'margin_below_15'        => 'pricing',
        'highest_margin'         => 'pricing',
        'top_profit_30d'         => 'biz_revenue',
        'profit_growth'          => 'biz_revenue',
        'trending_up'            => 'trend',
        'declining_trend'        => 'trend',
        'basket_driver'          => 'product_mix',
        'size_leader'            => 'product_mix',
        'zombie_45d'             => 'cash',
        'high_return_rate'       => 'quality',
        'lost_demand_match'      => 'demand',
    ];
    if (isset($map[$topic_id])) return $map[$topic_id];
    foreach ($map as $key => $cat) {
        if (strpos($topic_id, $key) === 0) return $cat;
    }
    if (strpos($topic_id, 'seller_discount_killer') === 0) return 'staff';
    if (strpos($topic_id, 'loyal_customer') === 0) return 'customer';
    if (strpos($topic_id, 'lost_demand') === 0) return 'demand';
    return 'product';
}

function pfPlanGateFor(string $fq): string {
    return 'start';
}

function pfExpiresAt(string $fq): string {
    $map = [
        'loss'        => '+3 days',
        'loss_cause'  => '+5 days',
        'gain'        => '+7 days',
        'gain_cause'  => '+7 days',
        'order'       => '+5 days',
        'anti_order'  => '+14 days',
    ];
    return date('Y-m-d H:i:s', strtotime($map[$fq] ?? '+3 days'));
}

function pfRoleGateFor(string $fq, string $topic): string {
    // Owner-only: всичко за пари (loss, loss_cause, gain, gain_cause)
    // Manager-also: операционни (order, anti_order)
    // Seller: засега нищо
    if (in_array($fq, ['loss','loss_cause','gain','gain_cause'], true)) return 'owner';
    if (in_array($fq, ['order','anti_order'], true)) return 'owner,manager';
    return 'owner';
}

/**
 * Idempotent UPSERT в ai_insights.
 * Dedup ключ: tenant_id + topic_id + product_id (NULL-safe) + fundamental_question
 */
function pfUpsert(int $tenant_id, array $i): array {
    // Map към реалната ai_insights схема
    $store_id    = $i['store_id'] ?? pfDefaultStoreId($tenant_id);
    if ($store_id === null) return ['skipped' => 'no_store'];
    
    $topic_id    = $i['topic_id'];
    $product_id  = $i['product_id']  ?? null;
    $supplier_id = $i['supplier_id'] ?? null;
    $fq          = $i['fundamental_question'];
    $category    = $i['category'] ?? pfCategoryFor($topic_id);
    
    // Map urgency: 'opportunity' не съществува в schema → 'info'
    $urgency_in  = $i['urgency'] ?? 'info';
    $valid_urg   = ['critical','warning','info','passive'];
    $urgency     = in_array($urgency_in, $valid_urg, true) ? $urgency_in : 'info';
    if ($urgency_in === 'opportunity') $urgency = 'info';
    
    // Map action_type: 'url' → 'deeplink'
    $atype_in    = $i['action_type'] ?? 'none';
    $atype_map   = ['url' => 'deeplink', 'chat' => 'chat', 'order_draft' => 'order_draft', 'deeplink' => 'deeplink', 'none' => 'none'];
    $action_typ  = $atype_map[$atype_in] ?? 'none';
    
    $title       = mb_substr($i['pill_text'] ?? $i['title'] ?? '', 0, 255);
    $value       = $i['value_numeric'] ?? null;
    $data_json   = isset($i['detail']) ? json_encode($i['detail'], JSON_UNESCAPED_UNICODE) : null;
    $action_lbl  = $i['action_label'] ?? null;
    $action_url  = $i['action_url']   ?? null;
    $action_dat  = isset($i['action_data']) ? json_encode($i['action_data'], JSON_UNESCAPED_UNICODE) : null;
    $expires_at  = pfExpiresAt($fq);
    $role_gate   = pfRoleGateFor($fq, $topic_id);
    $plan_gate   = pfPlanGateFor($fq);

    // module enum е ('home','products','warehouse','stats','sale') — слагаме 'products'
    $module = 'products';

    // Check existing (idempotent) — match ТОЧНО UNIQUE ключа: (tenant_id, store_id, topic_id)
    $sql_chk = "SELECT id FROM ai_insights 
                WHERE tenant_id=? AND store_id=? AND topic_id=? 
                LIMIT 1";
    $st = pfDB()->prepare($sql_chk);
    $st->execute([$tenant_id, $store_id, $topic_id]);
    $existing = $st->fetchColumn();

    if ($existing) {
        $up = pfDB()->prepare("UPDATE ai_insights SET 
            category=?, module=?, urgency=?, fundamental_question=?, plan_gate=?, role_gate=?,
            title=?, data_json=?, value_numeric=?, product_id=?, product_count=?, supplier_id=?, 
            action_label=?, action_type=?, action_url=?, action_data=?, 
            expires_at=? 
            WHERE id=?");
        $up->execute([
            $category, $module, $urgency, $fq, $plan_gate, $role_gate,
            $title, $data_json, $value, $product_id, ($i['product_count'] ?? null), $supplier_id,
            $action_lbl, $action_typ, $action_url, $action_dat,
            $expires_at, (int)$existing
        ]);
        return ['updated' => (int)$existing];
    }

    $ins = pfDB()->prepare("INSERT INTO ai_insights 
        (tenant_id, store_id, topic_id, category, grp, module, urgency, 
         fundamental_question, plan_gate, role_gate,
         title, data_json, value_numeric, product_id, product_count, supplier_id, 
         action_label, action_type, action_url, action_data, 
         expires_at) 
        VALUES (?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([
        $tenant_id, $store_id, $topic_id, $category, $module, $urgency,
        $fq, $plan_gate, $role_gate,
        $title, $data_json, $value, $product_id, ($i['product_count'] ?? null), $supplier_id,
        $action_lbl, $action_typ, $action_url, $action_dat,
        $expires_at
    ]);
    return ['inserted' => (int)pfDB()->lastInsertId()];
}

// =============================================================
// SECTION 2 — LOSS (3 функции) — Какво губя?
// =============================================================

/**
 * pf01: Артикули с 0 наличност, но са продадени през последните 30 дни
 * Агрегирано: 1 insight = "N бестселъра свършиха — губиш ~X EUR/ден"
 */
function pfZeroStockWithSales(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code, p.retail_price, p.cost_price,
               COALESCE(s30.qty_sold, 0) AS sold_30d
        FROM products p
        JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        JOIN (
            SELECT si.product_id, SUM(si.quantity) AS qty_sold
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
            WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY si.product_id
        ) s30 ON s30.product_id = p.id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
          AND i.quantity = 0
        ORDER BY s30.qty_sold DESC
        LIMIT 100";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $count = count($rows);
    $lost_per_day = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $daily = (float)$r['sold_30d'] / 30.0;
        $lost_per_day += $daily * (float)$r['retail_price'];
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'code'       => $r['code'],
            'sold_30d'   => (int)$r['sold_30d'],
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'zero_stock_with_sales',
        'fundamental_question' => 'loss',
        'urgency'              => 'critical',
        'pill_text'            => sprintf('%d бестселъра на нула — губиш ~%.2f EUR/ден', $count, $lost_per_day),
        'value_numeric'        => $lost_per_day,
        'product_count'        => $count,
        'action_label'         => 'Поръчай всички',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => $count, 'lost_per_day' => round($lost_per_day, 2)],
    ]);
    return 1;
}

/**
 * pf02: Под минимум, спешно
 */
function pfBelowMinUrgent(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code, p.min_quantity,
               i.quantity AS qty
        FROM products p
        JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
          AND p.min_quantity > 0
          AND i.quantity > 0
          AND i.quantity < p.min_quantity
        ORDER BY (p.min_quantity - i.quantity) DESC
        LIMIT 100";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'code'       => $r['code'],
            'qty'        => (int)$r['qty'],
            'min'        => (int)$r['min_quantity'],
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'below_min_urgent',
        'fundamental_question' => 'loss',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d артикула под минимално количество', count($rows)),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Поръчай',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

/**
 * pf03: Свършва днес — quantity <= avg_daily
 */
function pfRunningOutToday(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code,
               i.quantity AS qty,
               (s30.qty_sold / 30.0) AS avg_daily
        FROM products p
        JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        JOIN (
            SELECT si.product_id, SUM(si.quantity) AS qty_sold
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
            WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY si.product_id
            HAVING SUM(si.quantity) >= 5
        ) s30 ON s30.product_id = p.id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
          AND i.quantity > 0
          AND i.quantity <= (s30.qty_sold / 30.0)
        ORDER BY (s30.qty_sold / 30.0) DESC
        LIMIT 50";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'qty'        => (int)$r['qty'],
            'avg_daily'  => round((float)$r['avg_daily'], 2),
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'running_out_today',
        'fundamental_question' => 'loss',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d артикула свършват днес', count($rows)),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Поръчай',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

// =============================================================
// SECTION 3 — LOSS_CAUSE (4 функции) — От какво губя?
// =============================================================

function pfSellingAtLoss(int $tenant_id): int {
    $sql = "
        SELECT id AS product_id, name, code, retail_price, cost_price,
               (cost_price - retail_price) AS loss_per_unit
        FROM products
        WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL
          AND cost_price IS NOT NULL AND cost_price > 0
          AND retail_price < cost_price
        ORDER BY (cost_price - retail_price) DESC
        LIMIT 100";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $total_loss = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $total_loss += (float)$r['loss_per_unit'];
        $items[] = [
            'product_id'    => (int)$r['product_id'],
            'name'          => $r['name'],
            'code'          => $r['code'],
            'cost'          => (float)$r['cost_price'],
            'retail'        => (float)$r['retail_price'],
            'loss_per_unit' => round((float)$r['loss_per_unit'], 2),
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'selling_at_loss',
        'fundamental_question' => 'loss_cause',
        'urgency'              => 'critical',
        'pill_text'            => sprintf('%d артикула се продават ПОД себестойност', count($rows)),
        'value_numeric'        => $total_loss,
        'product_count'        => count($rows),
        'action_label'         => 'Промени цени',
        'action_type'          => 'deeplink',
        'action_url'           => 'products.php?filter=at_loss',
        'detail'               => ['items' => $items, 'count' => count($rows), 'total_loss' => round($total_loss, 2)],
    ]);
    return 1;
}

function pfNoCostPrice(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code,
               COALESCE(s30.qty_sold, 0) AS sold_30d
        FROM products p
        LEFT JOIN (
            SELECT si.product_id, SUM(si.quantity) AS qty_sold
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
            WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY si.product_id
        ) s30 ON s30.product_id = p.id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
          AND (p.cost_price IS NULL OR p.cost_price = 0)
        ORDER BY sold_30d DESC
        LIMIT 50";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $with_sales = 0;
    $items = [];
    foreach ($rows as $r) {
        if ((int)$r['sold_30d'] > 0) $with_sales++;
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'code'       => $r['code'],
            'sold_30d'   => (int)$r['sold_30d'],
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'no_cost_price',
        'fundamental_question' => 'loss_cause',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула без себестойност (%d с продажби)', count($rows), $with_sales),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Въведи себестойности',
        'action_type'          => 'deeplink',
        'action_url'           => 'products.php?filter=no_cost',
        'detail'               => ['items' => $items, 'count' => count($rows), 'with_sales' => $with_sales],
    ]);
    return 1;
}

function pfMarginBelow15(int $tenant_id): int {
    $sql = "
        SELECT id AS product_id, name, code, retail_price, cost_price,
               ROUND(((retail_price - cost_price) / retail_price) * 100, 1) AS margin_pct
        FROM products
        WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL
          AND cost_price IS NOT NULL AND cost_price > 0
          AND retail_price > cost_price AND retail_price > 0
          AND ((retail_price - cost_price) / retail_price) < 0.15
        ORDER BY margin_pct ASC
        LIMIT 100";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'code'       => $r['code'],
            'margin_pct' => (float)$r['margin_pct'],
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'margin_below_15',
        'fundamental_question' => 'loss_cause',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d артикула с марж под 15%%', count($rows)),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Прегледай цени',
        'action_type'          => 'deeplink',
        'action_url'           => 'products.php?filter=low_margin',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

function pfSellerDiscountKiller(int $tenant_id): int {
    if (!pfColumnExists('sale_items', 'discount_pct')) return 0;
    $sql = "
        SELECT s.user_id, COALESCE(u.name, CONCAT('Продавач #', s.user_id)) AS user_name,
               AVG(si.discount_pct) AS avg_disc,
               COUNT(si.id) AS items_count,
               SUM(si.unit_price * si.quantity * (si.discount_pct / 100)) AS lost_money
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.status = 'completed' 
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND si.discount_pct IS NOT NULL AND si.discount_pct > 0
        GROUP BY s.user_id, u.name
        HAVING avg_disc > 20 AND items_count >= 10
        ORDER BY avg_disc DESC
        LIMIT 10";
    try {
        $st = pfDB()->prepare($sql);
        $st->execute([$tenant_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return 0; }
    if (empty($rows)) return 0;

    $total_lost = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $total_lost += (float)$r['lost_money'];
        $items[] = [
            'user_id'    => (int)$r['user_id'],
            'name'       => $r['user_name'],
            'avg_disc'   => round((float)$r['avg_disc'], 1),
            'items'      => (int)$r['items_count'],
            'lost_money' => round((float)$r['lost_money'], 2),
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'seller_discount_killer',
        'fundamental_question' => 'loss_cause',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d продавачи с >20%% отстъпки — загубени ~%.2f EUR', count($rows), $total_lost),
        'value_numeric'        => $total_lost,
        'action_label'         => 'Виж продавачи',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows), 'total_lost' => round($total_lost, 2)],
    ]);
    return 1;
}

// =============================================================
// SECTION 4 — GAIN (2 функции) — Какво печеля?
// =============================================================

function pfTopProfit30d(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code,
               SUM((si.unit_price - COALESCE(si.cost_price, p.cost_price, 0)) * si.quantity) AS profit
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        JOIN products p ON p.id = si.product_id AND p.tenant_id = ?
        WHERE s.status = 'completed' 
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND COALESCE(si.cost_price, p.cost_price, 0) > 0
        GROUP BY p.id, p.name, p.code
        HAVING profit > 0
        ORDER BY profit DESC
        LIMIT 10";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $total = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $total += (float)$r['profit'];
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'code'       => $r['code'],
            'profit'     => round((float)$r['profit'], 2),
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'top_profit_30d',
        'fundamental_question' => 'gain',
        'urgency'              => 'info',
        'pill_text'            => sprintf('Топ печалба: %s — %.2f EUR за 30д', $top['name'], $top['profit']),
        'value_numeric'        => $total,
        'product_count'        => count($rows),
        'action_label'         => 'Виж всички',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows), 'total_profit' => round($total, 2)],
    ]);
    return 1;
}

function pfProfitGrowth(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code,
               COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    THEN (si.unit_price - COALESCE(si.cost_price, p.cost_price, 0)) * si.quantity
                    ELSE 0 END), 0) AS profit_now,
               COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                                  AND s.created_at <  DATE_SUB(NOW(), INTERVAL 30 DAY)
                    THEN (si.unit_price - COALESCE(si.cost_price, p.cost_price, 0)) * si.quantity
                    ELSE 0 END), 0) AS profit_prev
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        JOIN products p ON p.id = si.product_id AND p.tenant_id = ?
        WHERE s.status = 'completed' 
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
          AND COALESCE(si.cost_price, p.cost_price, 0) > 0
        GROUP BY p.id, p.name, p.code
        HAVING profit_now > 0 AND profit_prev > 0 AND profit_now > profit_prev * 1.2
        ORDER BY (profit_now - profit_prev) DESC
        LIMIT 10";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $growth = (((float)$r['profit_now'] - (float)$r['profit_prev']) / (float)$r['profit_prev']) * 100;
        $items[] = [
            'product_id'  => (int)$r['product_id'],
            'name'        => $r['name'],
            'profit_now'  => round((float)$r['profit_now'], 2),
            'profit_prev' => round((float)$r['profit_prev'], 2),
            'growth_pct'  => round($growth, 1),
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'profit_growth',
        'fundamental_question' => 'gain',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула с растяща печалба (топ: %s +%.0f%%)', count($rows), $top['name'], $top['growth_pct']),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Зареди още',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

// =============================================================
// SECTION 5 — GAIN_CAUSE (5 функции) — От какво печеля?
// =============================================================

function pfHighestMargin(int $tenant_id): int {
    $sql = "
        SELECT id AS product_id, name, code, retail_price, cost_price,
               ROUND(((retail_price - cost_price) / retail_price) * 100, 1) AS margin_pct
        FROM products
        WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL
          AND cost_price IS NOT NULL AND cost_price > 0
          AND retail_price > cost_price AND retail_price > 0
        ORDER BY margin_pct DESC
        LIMIT 10";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'margin_pct' => (float)$r['margin_pct'],
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'highest_margin',
        'fundamental_question' => 'gain_cause',
        'urgency'              => 'info',
        'pill_text'            => sprintf('Топ марж: %s — %.1f%%', $top['name'], $top['margin_pct']),
        'value_numeric'        => $top['margin_pct'],
        'product_count'        => count($rows),
        'action_label'         => 'Виж всички',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

function pfTrendingUp(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code,
               COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                 THEN si.quantity ELSE 0 END), 0) / 7.0 AS avg_7d,
               COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                 THEN si.quantity ELSE 0 END), 0) / 30.0 AS avg_30d
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        JOIN products p ON p.id = si.product_id AND p.tenant_id = ?
        WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id, p.name, p.code
        HAVING avg_30d >= 0.5 AND avg_7d > avg_30d * 1.5
        ORDER BY (avg_7d - avg_30d) DESC
        LIMIT 10";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $up = (((float)$r['avg_7d'] - (float)$r['avg_30d']) / (float)$r['avg_30d']) * 100;
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'avg_7d'     => round((float)$r['avg_7d'], 2),
            'avg_30d'    => round((float)$r['avg_30d'], 2),
            'growth_pct' => round($up, 1),
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'trending_up',
        'fundamental_question' => 'gain_cause',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула в ръст (топ: %s +%.0f%%)', count($rows), $top['name'], $top['growth_pct']),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Зареди още',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

function pfLoyalCustomers(int $tenant_id): int {
    if (!pfTableExists('customers') || !pfColumnExists('sales', 'customer_id')) return 0;
    try {
        $st = pfDB()->prepare("
            SELECT c.id AS customer_id, COALESCE(c.name, CONCAT('Клиент #', c.id)) AS cname,
                   COUNT(s.id) AS purchases, COALESCE(SUM(s.total), 0) AS total_money
            FROM customers c
            JOIN sales s ON s.customer_id = c.id AND s.tenant_id = ? AND s.status = 'completed'
            WHERE c.tenant_id = ? AND s.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY c.id, c.name
            HAVING purchases >= 3
            ORDER BY total_money DESC
            LIMIT 20");
        $st->execute([$tenant_id, $tenant_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return 0; }
    if (empty($rows)) return 0;

    $total = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $total += (float)$r['total_money'];
        $items[] = [
            'customer_id' => (int)$r['customer_id'],
            'name'        => $r['cname'],
            'purchases'   => (int)$r['purchases'],
            'total'       => round((float)$r['total_money'], 2),
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'loyal_customers',
        'fundamental_question' => 'gain_cause',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d лоялни клиенти за 60д — %.2f EUR общо', count($rows), $total),
        'value_numeric'        => $total,
        'action_label'         => 'Виж клиенти',
        'action_type'          => 'deeplink',
        'action_url'           => 'customers.php',
        'detail'               => ['items' => $items, 'count' => count($rows), 'total' => round($total, 2)],
    ]);
    return 1;
}

function pfBasketDriver(int $tenant_id): int {
    $sql = "
        SELECT si.product_id, p.name, p.code,
               COUNT(DISTINCT si.sale_id) AS basket_count
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        JOIN products p ON p.id = si.product_id AND p.tenant_id = ?
        JOIN (
            SELECT sale_id FROM sale_items GROUP BY sale_id HAVING COUNT(*) >= 2
        ) multi ON multi.sale_id = si.sale_id
        WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY si.product_id, p.name, p.code
        HAVING basket_count >= 3
        ORDER BY basket_count DESC
        LIMIT 10";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'product_id'   => (int)$r['product_id'],
            'name'         => $r['name'],
            'basket_count' => (int)$r['basket_count'],
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'basket_driver',
        'fundamental_question' => 'gain_cause',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула теглят кошницата (топ: %s — %d пъти)', count($rows), $top['name'], $top['basket_count']),
        'value_numeric'        => (float)$top['basket_count'],
        'product_count'        => count($rows),
        'action_label'         => 'Сложи отпред',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

function pfSizeLeader(int $tenant_id): int {
    $sql = "
        SELECT parent.id AS parent_id, parent.name AS parent_name,
               child.id AS child_id, child.size, child.color,
               SUM(si.quantity) AS qty_sold
        FROM products parent
        JOIN products child ON child.parent_id = parent.id AND child.tenant_id = parent.tenant_id
        JOIN sale_items si ON si.product_id = child.id
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        WHERE parent.tenant_id = ? AND parent.is_active = 1 AND parent.has_variations = 1
          AND s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY parent.id, parent.name, child.id, child.size, child.color
        ORDER BY parent.id, qty_sold DESC";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $top_per_parent = [];
    foreach ($rows as $r) {
        $pid = (int)$r['parent_id'];
        if (!isset($top_per_parent[$pid])) $top_per_parent[$pid] = $r;
    }
    $items = [];
    foreach ($top_per_parent as $r) {
        if ((int)$r['qty_sold'] < 3) continue;
        $variation = trim(($r['size'] ?? '') . ' ' . ($r['color'] ?? '')) ?: ('#' . (int)$r['child_id']);
        $items[] = [
            'parent_id'   => (int)$r['parent_id'],
            'parent_name' => $r['parent_name'],
            'child_id'    => (int)$r['child_id'],
            'variation'   => $variation,
            'qty_sold'    => (int)$r['qty_sold'],
        ];
    }
    if (empty($items)) return 0;

    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'size_leader',
        'fundamental_question' => 'gain_cause',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула с лидер-вариация (%s: „%s")', count($items), $top['parent_name'], $top['variation']),
        'value_numeric'        => (float)count($items),
        'product_count'        => count($items),
        'action_label'         => 'Зареди лидерите',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($items)],
    ]);
    return 1;
}

// =============================================================
// SECTION 6 — ORDER (2 функции) — Поръчай!
// =============================================================

function pfBestsellerLowStock(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code, p.min_quantity,
               MIN(i.quantity) AS qty,
               SUM(si.quantity) AS sold_30d
        FROM products p
        JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        JOIN sale_items si ON si.product_id = p.id
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
          AND s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND i.quantity <= GREATEST(p.min_quantity * 1.5, 3)
        GROUP BY p.id, p.name, p.code, p.min_quantity
        HAVING sold_30d >= 5
        ORDER BY sold_30d DESC
        LIMIT 50";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'code'       => $r['code'],
            'qty'        => (int)$r['qty'],
            'sold_30d'   => (int)$r['sold_30d'],
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'bestseller_low_stock',
        'fundamental_question' => 'order',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d бестселъра с ниска наличност', count($rows)),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Поръчай всички',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

function pfLostDemandMatch(int $tenant_id): int {
    if (!pfTableExists('lost_demand')) return 0;
    try {
        $st = pfDB()->prepare("
            SELECT ld.id AS ld_id, ld.query_text, ld.times,
                   ld.matched_product_id, p.name AS product_name, p.code
            FROM lost_demand ld
            LEFT JOIN products p ON p.id = ld.matched_product_id AND p.tenant_id = ?
            WHERE ld.tenant_id = ?
              AND (ld.resolved_order_id IS NULL OR ld.resolved_order_id = 0)
              AND ld.matched_product_id IS NOT NULL
              AND ld.last_asked_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            ORDER BY ld.times DESC, ld.last_asked_at DESC
            LIMIT 30");
        $st->execute([$tenant_id, $tenant_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return 0; }
    if (empty($rows)) return 0;

    $total_asks = 0;
    $items = [];
    foreach ($rows as $r) {
        $total_asks += (int)$r['times'];
        $items[] = [
            'ld_id'      => (int)$r['ld_id'],
            'query'      => $r['query_text'],
            'product_id' => $r['matched_product_id'] ? (int)$r['matched_product_id'] : null,
            'name'       => $r['product_name'] ?: $r['query_text'],
            'times'      => (int)$r['times'],
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'lost_demand_match',
        'fundamental_question' => 'order',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d артикула питани %d пъти от клиенти', count($rows), $total_asks),
        'value_numeric'        => (float)$total_asks,
        'product_count'        => count($rows),
        'action_label'         => 'Поръчай',
        'action_type'          => 'order_draft',
        'detail'               => ['items' => $items, 'count' => count($rows), 'total_asks' => $total_asks],
    ]);
    return 1;
}

// =============================================================
// SECTION 7 — ANTI_ORDER (3 функции) — НЕ поръчвай!
// =============================================================

function pfZombie45d(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code, p.retail_price,
               i.quantity AS qty,
               (i.quantity * p.retail_price) AS frozen_money,
               DATEDIFF(NOW(), COALESCE(
                   (SELECT MAX(s.created_at) 
                    FROM sale_items si 
                    JOIN sales s ON s.id = si.sale_id 
                    WHERE si.product_id = p.id AND s.tenant_id = p.tenant_id AND s.status='completed'),
                   p.created_at
               )) AS days_stale
        FROM products p
        JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
          AND i.quantity > 0
        HAVING days_stale > 45
        ORDER BY frozen_money DESC
        LIMIT 100";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $total_frozen = 0.0;
    $items = [];
    foreach ($rows as $r) {
        $total_frozen += (float)$r['frozen_money'];
        $items[] = [
            'product_id'   => (int)$r['product_id'],
            'name'         => $r['name'],
            'code'         => $r['code'],
            'qty'          => (int)$r['qty'],
            'days_stale'   => (int)$r['days_stale'],
            'frozen_money' => round((float)$r['frozen_money'], 2),
        ];
    }
    pfUpsert($tenant_id, [
        'topic_id'             => 'zombie_45d',
        'fundamental_question' => 'anti_order',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула стоят 45+ дни — %.2f EUR замразени', count($rows), $total_frozen),
        'value_numeric'        => $total_frozen,
        'product_count'        => count($rows),
        'action_label'         => 'НЕ поръчвай — намали цена',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows), 'total_frozen' => round($total_frozen, 2)],
    ]);
    return 1;
}

function pfDecliningTrend(int $tenant_id): int {
    $sql = "
        SELECT p.id AS product_id, p.name, p.code,
               COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                 THEN si.quantity ELSE 0 END), 0) / 7.0 AS avg_7d,
               COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                 THEN si.quantity ELSE 0 END), 0) / 30.0 AS avg_30d
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        JOIN products p ON p.id = si.product_id AND p.tenant_id = ?
        WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id, p.name, p.code
        HAVING avg_30d >= 0.5 AND avg_7d < avg_30d * 0.5
        ORDER BY (avg_30d - avg_7d) DESC
        LIMIT 20";
    $st = pfDB()->prepare($sql);
    $st->execute([$tenant_id, $tenant_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $down = (1 - ((float)$r['avg_7d'] / (float)$r['avg_30d'])) * 100;
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'avg_7d'     => round((float)$r['avg_7d'], 2),
            'avg_30d'    => round((float)$r['avg_30d'], 2),
            'down_pct'   => round($down, 1),
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'declining_trend',
        'fundamental_question' => 'anti_order',
        'urgency'              => 'info',
        'pill_text'            => sprintf('%d артикула в спад (топ: %s -%.0f%%)', count($rows), $top['name'], $top['down_pct']),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'Изчакай — не поръчвай',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

function pfHighReturnRate(int $tenant_id): int {
    if (!pfTableExists('returns') && !pfColumnExists('sale_items', 'returned_quantity')) return 0;
    
    if (pfTableExists('returns')) {
        $sql = "
            SELECT p.id AS product_id, p.name, p.code,
                   SUM(si.quantity) AS sold,
                   COALESCE(SUM(r.quantity), 0) AS returned
            FROM products p
            JOIN sale_items si ON si.product_id = p.id
            JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
            LEFT JOIN returns r ON r.product_id = p.id AND r.tenant_id = ?
                                 AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE p.tenant_id = ? AND p.is_active = 1
              AND s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id, p.name, p.code
            HAVING sold >= 5 AND returned > sold * 0.15
            ORDER BY (returned / sold) DESC
            LIMIT 20";
        $params = [$tenant_id, $tenant_id, $tenant_id];
    } else {
        $sql = "
            SELECT p.id AS product_id, p.name, p.code,
                   SUM(si.quantity) AS sold,
                   COALESCE(SUM(si.returned_quantity), 0) AS returned
            FROM products p
            JOIN sale_items si ON si.product_id = p.id
            JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
            WHERE p.tenant_id = ? AND p.is_active = 1
              AND s.status = 'completed' AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id, p.name, p.code
            HAVING sold >= 5 AND returned > sold * 0.15
            ORDER BY (returned / sold) DESC
            LIMIT 20";
        $params = [$tenant_id, $tenant_id];
    }
    try {
        $st = pfDB()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return 0; }
    if (empty($rows)) return 0;

    $items = [];
    foreach ($rows as $r) {
        $rate = ((float)$r['returned'] / (float)$r['sold']) * 100;
        $items[] = [
            'product_id' => (int)$r['product_id'],
            'name'       => $r['name'],
            'sold'       => (int)$r['sold'],
            'returned'   => (int)$r['returned'],
            'rate'       => round($rate, 1),
        ];
    }
    $top = $items[0];
    pfUpsert($tenant_id, [
        'topic_id'             => 'high_return_rate',
        'fundamental_question' => 'anti_order',
        'urgency'              => 'warning',
        'pill_text'            => sprintf('%d артикула с висок процент връщания (топ: %s — %.0f%%)', count($rows), $top['name'], $top['rate']),
        'value_numeric'        => (float)count($rows),
        'product_count'        => count($rows),
        'action_label'         => 'НЕ поръчвай повече',
        'action_type'          => 'chat',
        'detail'               => ['items' => $items, 'count' => count($rows)],
    ]);
    return 1;
}

// =============================================================
// SECTION 8 — WRAPPER
// =============================================================

function computeProductInsights(int $tenant_id): array {
    $results = [];
    $functions = [
        // LOSS
        'zero_stock_with_sales'  => 'pfZeroStockWithSales',
        'below_min_urgent'       => 'pfBelowMinUrgent',
        'running_out_today'      => 'pfRunningOutToday',
        // LOSS_CAUSE
        'selling_at_loss'        => 'pfSellingAtLoss',
        'no_cost_price'          => 'pfNoCostPrice',
        'margin_below_15'        => 'pfMarginBelow15',
        'seller_discount_killer' => 'pfSellerDiscountKiller',
        // GAIN
        'top_profit_30d'         => 'pfTopProfit30d',
        'profit_growth'          => 'pfProfitGrowth',
        // GAIN_CAUSE
        'highest_margin'         => 'pfHighestMargin',
        'trending_up'            => 'pfTrendingUp',
        'loyal_customers'        => 'pfLoyalCustomers',
        'basket_driver'          => 'pfBasketDriver',
        'size_leader'            => 'pfSizeLeader',
        // ORDER
        'bestseller_low_stock'   => 'pfBestsellerLowStock',
        'lost_demand_match'      => 'pfLostDemandMatch',
        // ANTI_ORDER
        'zombie_45d'             => 'pfZombie45d',
        'declining_trend'        => 'pfDecliningTrend',
        'high_return_rate'       => 'pfHighReturnRate',
    ];
    foreach ($functions as $key => $fn) {
        $t0 = microtime(true);
        try {
            $count = $fn($tenant_id);
            $results[$key] = ['count' => $count, 'ms' => round((microtime(true) - $t0) * 1000, 1)];
        } catch (Throwable $e) {
            $results[$key] = ['error' => $e->getMessage(), 'ms' => round((microtime(true) - $t0) * 1000, 1)];
        }
    }
    return $results;
}

function computeAllInsights(int $tenant_id): array {
    return ['products' => computeProductInsights($tenant_id)];
}

// =============================================================
// SECTION 9 — ENTRY POINT (CLI + AJAX)
// =============================================================

if (php_sapi_name() === 'cli') {
    $tenant_id = isset($argv[1]) ? (int)$argv[1] : 0;
    if ($tenant_id > 0) {
        $r = computeAllInsights($tenant_id);
        echo "Tenant $tenant_id:\n";
        foreach ($r['products'] as $key => $info) {
            if (isset($info['error'])) {
                echo sprintf("  %-26s ERROR: %s (%.1fms)\n", $key, $info['error'], $info['ms']);
            } else {
                echo sprintf("  %-26s %d insights (%.1fms)\n", $key, $info['count'], $info['ms']);
            }
        }
    } else {
        $tenants = pfDB()->query("SELECT id FROM tenants WHERE is_active = 1 OR is_active IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tenants as $tid) {
            $r = computeAllInsights((int)$tid);
            $total = 0;
            foreach ($r['products'] as $info) $total += $info['count'] ?? 0;
            echo "Tenant $tid: $total insights generated\n";
        }
    }
    exit(0);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'compute_insights') {
    header('Content-Type: application/json; charset=utf-8');
    $tenant_id = (int)($_GET['tenant_id'] ?? 0);
    if ($tenant_id <= 0) {
        echo json_encode(['error' => 'tenant_id required']);
        exit;
    }
    echo json_encode(computeAllInsights($tenant_id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
