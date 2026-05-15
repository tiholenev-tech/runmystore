<?php
/**
 * sales_smart_seed.php — S144 adversarial seed за tenant=7 (пробен).
 *
 * Цел: 1000 продажби × 30 дни назад, разпределени така че
 *      compute-insights.php да генерира реални insights с реални product_ids.
 *
 * Разпределение:
 *   A) BESTSELLERS (40%) — 20 артикула × 20 продажби.
 *      → fires pf07 top_profit_30d, pf08 profit_growth, pf12 trending_up.
 *      След продажбите 10 от тях с inventory.quantity=0
 *      → fires pf01 zero_stock_with_sales (critical loss).
 *   B) NORMAL (50%) — 80 артикула × 5-8 продажби, baseline.
 *   C) SLOW MOVERS (10%) — 100 артикула × 1 продажба (>=23 дни назад).
 *      Минимална активност без да задействат bestseller логики.
 *   D) ATTACK (50 extra) — 5 продукта без cost_price + 5 с марж<15%, по 5 продажби.
 *      → засилва pf05 no_cost_price (with_sales counter) и pf06 margin_below_15.
 *
 * Маркер: всеки нов sale + sale_item получава is_test_data=1
 *         (за rollback виж края на файла).
 *
 * Usage:
 *   php /var/www/runmystore/tools/seed/sales_smart_seed.php
 *   php /var/www/runmystore/tools/seed/sales_smart_seed.php --dry-run
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';

const TENANT_ID = 7;
const SEED_NOTE = '[smart-seed-S144]';

$DRY_RUN = in_array('--dry-run', $argv ?? [], true);

$pdo = DB::get();

// ─────────────────────────────────────────────────────────────
// 0. Sanity / pre-flight
// ─────────────────────────────────────────────────────────────

$preCount = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE tenant_id=" . TENANT_ID)->fetchColumn();
$preTestCount = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE tenant_id=" . TENANT_ID . " AND is_test_data=1")->fetchColumn();

echo "Pre-seed sales за tenant=" . TENANT_ID . ": $preCount (test=$preTestCount)\n";

// Owner / store
$ownerId = (int)$pdo->query("SELECT id FROM users WHERE tenant_id=" . TENANT_ID . " AND role='owner' ORDER BY id LIMIT 1")->fetchColumn();
if ($ownerId === 0) $ownerId = 1;

$storeId = (int)$pdo->query("SELECT id FROM stores WHERE tenant_id=" . TENANT_ID . " ORDER BY id LIMIT 1")->fetchColumn();
if ($storeId === 0) {
    fwrite(STDERR, "ERROR: tenant=" . TENANT_ID . " няма stores\n");
    exit(1);
}

echo "Store: $storeId | Owner user: $ownerId | DRY_RUN=" . ($DRY_RUN ? 'YES' : 'NO') . "\n\n";

// ─────────────────────────────────────────────────────────────
// 1. Пулове от продукти
// ─────────────────────────────────────────────────────────────

/**
 * Връща inventory rows (product_id => [['store_id'=>X, 'quantity'=>Y, 'inv_id'=>Z], ...])
 * за дадения tenant. Използваме за: 1) намиране на стоки със stock,
 * 2) decrement на правилния inv row при продажба.
 */
function loadInventoryByProduct(PDO $pdo, int $tenantId): array {
    $st = $pdo->prepare("SELECT id, store_id, product_id, quantity FROM inventory WHERE tenant_id=?");
    $st->execute([$tenantId]);
    $by = [];
    while ($r = $st->fetch()) {
        $by[(int)$r['product_id']][] = [
            'inv_id'   => (int)$r['id'],
            'store_id' => (int)$r['store_id'],
            'quantity' => (float)$r['quantity'],
        ];
    }
    return $by;
}

$invByProduct = loadInventoryByProduct($pdo, TENANT_ID);

// Active parent products that have stock somewhere
$st = $pdo->prepare("
    SELECT DISTINCT p.id, p.name, p.retail_price, p.cost_price
    FROM products p
    JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
    WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL AND i.quantity > 0
");
$st->execute([TENANT_ID]);
$productsWithStock = $st->fetchAll();

echo "Pool: " . count($productsWithStock) . " active parent products with stock\n";

// A) BESTSELLERS — 20 продукта с най-висока retail_price (имат stock)
usort($productsWithStock, fn($a, $b) => ((float)$b['retail_price']) <=> ((float)$a['retail_price']));
$bestsellers = array_slice($productsWithStock, 0, 20);
$bestsellerIds = array_column($bestsellers, 'id');

// Resterende (за нормалните и slow movers)
$remaining = array_filter($productsWithStock, fn($p) => !in_array($p['id'], $bestsellerIds, true));
$remaining = array_values($remaining);

// B) NORMAL — 80 random продукта от remaining
shuffle($remaining);
$normalPool = array_slice($remaining, 0, 80);
$normalIds = array_column($normalPool, 'id');

// C) SLOW MOVERS — артикули БЕЗ продажби в последните 90 дни (за да са реално застояли).
//    Падаме на "никакви или малко" ако няма достатъчно.
$st = $pdo->prepare("
    SELECT p.id, p.name, p.retail_price, p.cost_price
    FROM products p
    JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
    LEFT JOIN (
        SELECT si.product_id
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.tenant_id = ?
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY si.product_id
    ) recent ON recent.product_id = p.id
    WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL AND i.quantity > 0
      AND recent.product_id IS NULL
    ORDER BY RAND()
    LIMIT 100
");
$st->execute([TENANT_ID, TENANT_ID]);
$slowMovers = $st->fetchAll();
echo "Slow mover pool (no sales 90d): " . count($slowMovers) . "\n";

// D-1) Без cost_price (има stock)
$st = $pdo->prepare("
    SELECT DISTINCT p.id, p.name, p.retail_price, p.cost_price
    FROM products p
    JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
    WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
      AND (p.cost_price IS NULL OR p.cost_price = 0)
      AND i.quantity > 0 AND p.retail_price > 0
    ORDER BY RAND()
    LIMIT 5
");
$st->execute([TENANT_ID]);
$noCostAttack = $st->fetchAll();

// D-2) Марж < 15% (има stock)
$st = $pdo->prepare("
    SELECT DISTINCT p.id, p.name, p.retail_price, p.cost_price
    FROM products p
    JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
    WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_id IS NULL
      AND p.cost_price > 0 AND p.retail_price > p.cost_price AND p.retail_price > 0
      AND ((p.retail_price - p.cost_price) / p.retail_price) < 0.15
      AND i.quantity > 0
    ORDER BY RAND()
    LIMIT 5
");
$st->execute([TENANT_ID]);
$lowMarginAttack = $st->fetchAll();

echo "Attack pools: no_cost=" . count($noCostAttack) . " | low_margin=" . count($lowMarginAttack) . "\n\n";

// ─────────────────────────────────────────────────────────────
// 2. Helper-и за писане
// ─────────────────────────────────────────────────────────────

/**
 * Random timestamp в [now - $maxDaysAgo, now - $minDaysAgo], часове 09:00-21:00.
 */
function randomCreatedAt(int $minDaysAgo, int $maxDaysAgo): string {
    $daysAgo = mt_rand($minDaysAgo, $maxDaysAgo);
    $hour    = mt_rand(9, 20);
    $min     = mt_rand(0, 59);
    $sec     = mt_rand(0, 59);
    $ts      = strtotime("-{$daysAgo} days");
    return date('Y-m-d', $ts) . sprintf(' %02d:%02d:%02d', $hour, $min, $sec);
}

/**
 * Decrement-ва inventory row-ове за даден product, започвайки от store_id=$preferStoreId.
 * Ако там няма достатъчно — продължава с другите rows за tenant=7.
 * Връща store_id-то от което е "продадено" (за sale.store_id), или $fallback ако нищо.
 */
function decrementInventoryForSale(PDO $pdo, array &$invByProduct, int $productId, float $qty, int $preferStoreId, int $fallback): int {
    if (!isset($invByProduct[$productId])) return $fallback;
    $rows = &$invByProduct[$productId];

    // Sort: prefer requested store, else descending qty
    usort($rows, function($a, $b) use ($preferStoreId) {
        if ($a['store_id'] === $preferStoreId && $b['store_id'] !== $preferStoreId) return -1;
        if ($b['store_id'] === $preferStoreId && $a['store_id'] !== $preferStoreId) return 1;
        return $b['quantity'] <=> $a['quantity'];
    });

    $remaining = $qty;
    $hitStore  = $fallback;
    $first     = true;
    foreach ($rows as &$r) {
        if ($remaining <= 0) break;
        if ($r['quantity'] <= 0) continue;
        $take = min($r['quantity'], $remaining);
        $r['quantity'] -= $take;
        $remaining -= $take;
        $newQ = max(0, $r['quantity']);
        $st = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $st->execute([$newQ, $r['inv_id']]);
        if ($first) { $hitStore = $r['store_id']; $first = false; }
    }
    unset($r);
    return $hitStore;
}

/**
 * Записва един sale + един sale_item (single-line basket за простота).
 * Връща [sale_id, total_amount].
 */
function writeSale(PDO $pdo, int $tenantId, int $storeId, int $userId, int $productId, float $qty, float $unitPrice, ?float $costPrice, string $createdAt, string $note): array {
    $total = round($qty * $unitPrice, 2);
    $st = $pdo->prepare("
        INSERT INTO sales (tenant_id, store_id, user_id, customer_id, type, payment_method,
                           subtotal, discount_pct, discount_amount, total, paid_amount,
                           status, is_test_data, note, created_at, updated_at)
        VALUES (?, ?, ?, NULL, 'retail', 'cash', ?, 0.00, 0.00, ?, ?, 'completed', 1, ?, ?, ?)
    ");
    $st->execute([$tenantId, $storeId, $userId, $total, $total, $total, $note, $createdAt, $createdAt]);
    $saleId = (int)$pdo->lastInsertId();

    $st = $pdo->prepare("
        INSERT INTO sale_items (sale_id, is_test_data, product_id, quantity, returned_quantity,
                                unit_price, cost_price, discount_pct, total)
        VALUES (?, 1, ?, ?, 0, ?, ?, 0.00, ?)
    ");
    $st->execute([$saleId, $productId, $qty, $unitPrice, $costPrice, $total]);

    return [$saleId, $total];
}

// ─────────────────────────────────────────────────────────────
// 3. Изпълнение
// ─────────────────────────────────────────────────────────────

if ($DRY_RUN) {
    echo "[DRY RUN] Не пиша нищо. Спирам тук.\n";
    echo "Bestsellers (" . count($bestsellers) . "), Normal (" . count($normalPool) . "), Slow (" . count($slowMovers) . "), Attack (" . (count($noCostAttack) + count($lowMarginAttack)) . ")\n";
    exit(0);
}

$pdo->beginTransaction();

try {
    $totalSales   = 0;
    $totalRevenue = 0.0;
    $touchedProducts = [];

    // ───── A) BESTSELLERS — 20 × 20 = 400 ─────
    foreach ($bestsellers as $p) {
        $pid       = (int)$p['id'];
        $unitPrice = (float)$p['retail_price'];
        $cost      = ($p['cost_price'] !== null && (float)$p['cost_price'] > 0) ? (float)$p['cost_price'] : null;
        for ($i = 0; $i < 20; $i++) {
            $qty = (float)mt_rand(1, 2);
            $createdAt = randomCreatedAt(0, 30);
            $useStore = decrementInventoryForSale($pdo, $invByProduct, $pid, $qty, $storeId, $storeId);
            [$sid, $tot] = writeSale($pdo, TENANT_ID, $useStore, $ownerId, $pid, $qty, $unitPrice, $cost, $createdAt, SEED_NOTE . ' bestseller');
            $totalSales++;
            $totalRevenue += $tot;
            $touchedProducts[$pid] = true;
        }
    }

    // Депретирай 10 от bestsellers до 0 stock (критичен loss insight)
    $depleteIds = array_slice($bestsellerIds, 0, 10);
    $depPlaceholders = implode(',', array_fill(0, count($depleteIds), '?'));
    $st = $pdo->prepare("UPDATE inventory SET quantity = 0 WHERE tenant_id = ? AND product_id IN ($depPlaceholders)");
    $st->execute(array_merge([TENANT_ID], $depleteIds));
    foreach ($depleteIds as $pid) {
        if (isset($invByProduct[$pid])) {
            foreach ($invByProduct[$pid] as &$r) $r['quantity'] = 0.0;
            unset($r);
        }
    }

    // ───── B) NORMAL — 80 × 5..8 ≈ 500 ─────
    foreach ($normalPool as $p) {
        $pid       = (int)$p['id'];
        $unitPrice = (float)$p['retail_price'];
        if ($unitPrice <= 0) $unitPrice = 10.0;
        $cost      = ($p['cost_price'] !== null && (float)$p['cost_price'] > 0) ? (float)$p['cost_price'] : null;
        $n = mt_rand(5, 8);
        for ($i = 0; $i < $n; $i++) {
            $qty = (float)mt_rand(1, 3);
            $createdAt = randomCreatedAt(0, 30);
            $useStore = decrementInventoryForSale($pdo, $invByProduct, $pid, $qty, $storeId, $storeId);
            [$sid, $tot] = writeSale($pdo, TENANT_ID, $useStore, $ownerId, $pid, $qty, $unitPrice, $cost, $createdAt, SEED_NOTE . ' normal');
            $totalSales++;
            $totalRevenue += $tot;
            $touchedProducts[$pid] = true;
        }
    }

    // ───── C) SLOW MOVERS — 100 × 1 (преди 23+ дни) ─────
    foreach ($slowMovers as $p) {
        $pid       = (int)$p['id'];
        $unitPrice = (float)$p['retail_price'];
        if ($unitPrice <= 0) $unitPrice = 10.0;
        $cost      = ($p['cost_price'] !== null && (float)$p['cost_price'] > 0) ? (float)$p['cost_price'] : null;
        $qty = 1.0;
        $createdAt = randomCreatedAt(23, 30);
        $useStore = decrementInventoryForSale($pdo, $invByProduct, $pid, $qty, $storeId, $storeId);
        [$sid, $tot] = writeSale($pdo, TENANT_ID, $useStore, $ownerId, $pid, $qty, $unitPrice, $cost, $createdAt, SEED_NOTE . ' slow');
        $totalSales++;
        $totalRevenue += $tot;
        $touchedProducts[$pid] = true;
    }

    // ───── D) ATTACK — 10 × 5 = 50 ─────
    $attackList = array_merge($noCostAttack, $lowMarginAttack);
    foreach ($attackList as $p) {
        $pid       = (int)$p['id'];
        $unitPrice = (float)$p['retail_price'];
        if ($unitPrice <= 0) $unitPrice = 10.0;
        $cost      = ($p['cost_price'] !== null && (float)$p['cost_price'] > 0) ? (float)$p['cost_price'] : null;
        for ($i = 0; $i < 5; $i++) {
            $qty = (float)mt_rand(1, 2);
            $createdAt = randomCreatedAt(0, 30);
            $useStore = decrementInventoryForSale($pdo, $invByProduct, $pid, $qty, $storeId, $storeId);
            [$sid, $tot] = writeSale($pdo, TENANT_ID, $useStore, $ownerId, $pid, $qty, $unitPrice, $cost, $createdAt, SEED_NOTE . ' attack');
            $totalSales++;
            $totalRevenue += $tot;
            $touchedProducts[$pid] = true;
        }
    }

    $pdo->commit();

    echo "✓ Записани: $totalSales продажби\n";
    echo "✓ Уникални продукти: " . count($touchedProducts) . "\n";
    echo "✓ Total revenue: " . number_format($totalRevenue, 2) . " EUR\n";
    echo "✓ Депретирани до 0: " . count($depleteIds) . " bestseller продукта\n";

    // Post-проверка
    $postCount = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE tenant_id=" . TENANT_ID . " AND is_test_data=1")->fetchColumn();
    echo "Post-seed test sales count: $postCount (delta=" . ($postCount - $preTestCount) . ")\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ROLLBACK: " . $e->getMessage() . "\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────
// ROLLBACK ПЛАН (ако нещо счупи)
// ─────────────────────────────────────────────────────────────
//   DELETE FROM sale_items WHERE sale_id IN
//     (SELECT id FROM sales WHERE tenant_id=7 AND is_test_data=1);
//   DELETE FROM sales WHERE tenant_id=7 AND is_test_data=1;
//   -- inventory няма да възстанови, но за tenant=7 (пробен) е ok
