<?php
/**
 * services/ai-brain-cod.php — S97.INVENTORY.AI_BRAIN.BACKEND
 *
 * "Category of the Day" suggestion за inventory.php hub screen.
 * Pure SQL — без external AI APIs (deterministic за beta).
 *
 * Selection:
 *   never-counted (items>0) | >30 дни → priority high
 *   14-30 дни              → medium
 *   <14 дни                → low
 * В рамките на bucket: най-старо първо. HIGH→MEDIUM→LOW.
 * First-count mode (нула lines в store) → най-голямата категория.
 *
 * GET /services/ai-brain-cod.php
 * Returns {ok, data:{category_id, category_name, items_count,
 *                    estimated_minutes, priority,
 *                    last_counted_days_ago, reason, mode}}
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['store_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$tenant_id = (int) $_SESSION['tenant_id'];
$store_id  = (int) $_SESSION['store_id'];

try {
    // Per-category items count (root products only) + last counted_at scoped to current store.
    // COALESCE(variation_id, product_id) защото inventory_count_lines записва на ниво вариация.
    $rows = DB::run(
        "SELECT c.id AS category_id,
                c.name AS category_name,
                (SELECT COUNT(*) FROM products p
                   WHERE p.tenant_id = c.tenant_id
                     AND p.category_id = c.id
                     AND p.is_active = 1
                     AND p.parent_id IS NULL) AS items_count,
                (SELECT MAX(cl.counted_at) FROM inventory_count_lines cl
                   JOIN inventory_count_sessions s ON s.id = cl.session_id
                   JOIN products px ON px.id = COALESCE(cl.variation_id, cl.product_id)
                  WHERE s.store_id = ? AND px.category_id = c.id) AS last_counted
           FROM categories c
          WHERE c.tenant_id = ?
         HAVING items_count > 0",
        [$store_id, $tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo json_encode(['ok' => false, 'error' => 'no_categories']);
        exit;
    }

    $any_counted = false;
    foreach ($rows as $r) {
        if ($r['last_counted'] !== null) { $any_counted = true; break; }
    }

    $now = time();

    if (!$any_counted) {
        usort($rows, fn($a, $b) => (int)$b['items_count'] - (int)$a['items_count']);
        $best = $rows[0];
        $best['days_ago'] = null;
        $best['priority'] = 'high';
        $best['reason']   = 'Първо броене — започни от най-голямата категория';
        $mode = 'first_count';
    } else {
        foreach ($rows as &$r) {
            if ($r['last_counted'] === null) {
                $r['days_ago']  = null;
                $r['priority']  = 'high';
                $r['_bucket']   = 0;
                $r['_age_sort'] = PHP_INT_MAX;
            } else {
                $age = max(0, (int) floor(($now - strtotime($r['last_counted'])) / 86400));
                $r['days_ago']  = $age;
                $r['_age_sort'] = $age;
                if      ($age > 30)  { $r['priority'] = 'high';   $r['_bucket'] = 0; }
                elseif  ($age >= 14) { $r['priority'] = 'medium'; $r['_bucket'] = 1; }
                else                 { $r['priority'] = 'low';    $r['_bucket'] = 2; }
            }
        }
        unset($r);

        usort($rows, function ($a, $b) {
            if ($a['_bucket'] !== $b['_bucket']) return $a['_bucket'] - $b['_bucket'];
            return $b['_age_sort'] - $a['_age_sort'];
        });
        $best = $rows[0];

        if ($best['days_ago'] === null) {
            $best['reason'] = 'Никога не е броена';
        } elseif ($best['priority'] === 'high') {
            $best['reason'] = 'Не е броена ' . $best['days_ago'] . ' дни';
        } elseif ($best['priority'] === 'medium') {
            $best['reason'] = 'Броена преди ' . $best['days_ago'] . ' дни';
        } else {
            $best['reason'] = 'Скоро броена (' . $best['days_ago'] . ' дни) — рутинна проверка';
        }
        $mode = 'normal';
    }

    $items = (int) $best['items_count'];
    $estimated_minutes = max(1, (int) ceil($items * 15 / 60));

    echo json_encode([
        'ok'   => true,
        'data' => [
            'category_id'           => (int) $best['category_id'],
            'category_name'         => (string) $best['category_name'],
            'items_count'           => $items,
            'estimated_minutes'     => $estimated_minutes,
            'priority'              => $best['priority'],
            'last_counted_days_ago' => $best['days_ago'],
            'reason'                => $best['reason'],
            'mode'                  => $mode,
        ],
    ]);

} catch (Throwable $e) {
    error_log('ai-brain-cod: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
