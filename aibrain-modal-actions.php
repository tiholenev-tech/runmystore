<?php
/**
 * S89.AIBRAIN.MODALS — modal action dispatcher
 *
 * Single endpoint with ?action= dispatch:
 *   GET  ?action=csrf              → mint/refresh CSRF token (returns {token})
 *   POST ?action=order_draft_submit → INSERT purchase_orders + items (status=draft)
 *   POST ?action=transfer_draft_submit → INSERT transfers + items (status=pending — schema has no 'draft')
 *   POST ?action=dismiss           → expire ai_insight + log dismiss in ai_shown
 *
 * Schema notes (verified against backup_s79_schema_20260424_1536.sql):
 *   - purchase_orders.status ENUM has 'draft' ✓
 *   - transfers.status ENUM is ('pending','in_transit','completed','canceled') — NO 'draft'.
 *     Drafts are stored as status='pending' and a 'AI draft (insight=…)' note for now.
 *   - ai_insights has NO dismissed_at/dismissed_reason columns. Reason is merged into
 *     existing action_data JSON; expires_at=NOW() removes it from the active fetch.
 *
 * CSRF: token is per-session, kept in $_SESSION['aibrain_csrf']. Frontend bootstraps via
 *       GET ?action=csrf, then includes it as POST field `_csrf` on every mutation.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jerr(int $http, string $code, string $msg = ''): void {
    http_response_code($http);
    echo json_encode(['ok' => false, 'err' => $code, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function jok(array $data = []): void {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    jerr(401, 'unauth');
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];
$store_id  = (int)($_SESSION['store_id'] ?? 0);

$action = $_GET['action'] ?? '';

if ($action === 'csrf') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jerr(405, 'method');
    if (empty($_SESSION['aibrain_csrf'])) {
        $_SESSION['aibrain_csrf'] = bin2hex(random_bytes(16));
    }
    jok(['token' => $_SESSION['aibrain_csrf']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr(405, 'method');

$csrf_post = (string)($_POST['_csrf'] ?? '');
$csrf_sess = (string)($_SESSION['aibrain_csrf'] ?? '');
if ($csrf_sess === '' || !hash_equals($csrf_sess, $csrf_post)) {
    jerr(403, 'csrf');
}

$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host    = $_SERVER['HTTP_HOST']    ?? '';
if ($host !== '') {
    $okOrigin  = $origin  !== '' && stripos($origin,  $host) !== false;
    $okReferer = $referer !== '' && stripos($referer, $host) !== false;
    if (!$okOrigin && !$okReferer) jerr(403, 'cross_origin');
}

try {
    switch ($action) {
        case 'order_draft_submit':
            handleOrderDraft($tenant_id, $user_id, $store_id);
            break;
        case 'transfer_draft_submit':
            handleTransferDraft($tenant_id, $user_id, $store_id);
            break;
        case 'dismiss':
            handleDismiss($tenant_id, $user_id, $store_id);
            break;
        default:
            jerr(400, 'bad_action');
    }
} catch (Throwable $e) {
    error_log('S89.AIBRAIN.MODALS: ' . $e->getMessage());
    jerr(500, 'server', $e->getMessage());
}

function handleOrderDraft(int $tenant_id, int $user_id, int $store_id): void {
    $items_raw   = $_POST['items'] ?? '[]';
    $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $insight_id  = (int)($_POST['insight_id'] ?? 0) ?: null;

    $items = json_decode((string)$items_raw, true);
    if (!is_array($items) || count($items) === 0) jerr(400, 'no_items');

    $clean = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (float)($it['qty'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $clean[] = ['product_id' => $pid, 'qty' => $qty];
        }
    }
    if (count($clean) === 0) jerr(400, 'no_valid_items');

    $note = $insight_id ? ('AI insight draft (insight=' . $insight_id . ')') : 'AI insight draft';

    $order_id = DB::tx(function ($pdo) use ($tenant_id, $user_id, $store_id, $supplier_id, $note, $clean) {
        DB::run(
            "INSERT INTO purchase_orders
               (tenant_id, supplier_id, store_id, created_by, status, notes)
             VALUES (?, ?, ?, ?, 'draft', ?)",
            [$tenant_id, $supplier_id, $store_id ?: null, $user_id, $note]
        );
        $oid = (int)DB::lastInsertId();
        foreach ($clean as $row) {
            DB::run(
                "INSERT INTO purchase_order_items
                   (purchase_order_id, product_id, qty_ordered, qty_received, cost_price)
                 VALUES (?, ?, ?, 0, 0)",
                [$oid, $row['product_id'], $row['qty']]
            );
        }
        return $oid;
    });

    jok([
        'order_id'     => $order_id,
        'redirect_url' => 'orders.php?id=' . $order_id,
    ]);
}

function handleTransferDraft(int $tenant_id, int $user_id, int $store_id): void {
    $from_store_id = (int)($_POST['from_store_id'] ?? 0);
    $to_store_id   = (int)($_POST['to_store_id'] ?? 0);
    $insight_id    = (int)($_POST['insight_id'] ?? 0) ?: null;
    $items_raw     = $_POST['items'] ?? '[]';

    if ($from_store_id <= 0 || $to_store_id <= 0 || $from_store_id === $to_store_id) {
        jerr(400, 'bad_stores');
    }

    $stores = DB::run(
        "SELECT COUNT(*) AS cnt FROM stores WHERE tenant_id=? AND id IN (?, ?)",
        [$tenant_id, $from_store_id, $to_store_id]
    )->fetch();
    if (!$stores || (int)$stores['cnt'] !== 2) jerr(403, 'store_scope');

    $items = json_decode((string)$items_raw, true);
    if (!is_array($items) || count($items) === 0) jerr(400, 'no_items');

    $clean = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (float)($it['qty'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $clean[] = ['product_id' => $pid, 'qty' => $qty];
        }
    }
    if (count($clean) === 0) jerr(400, 'no_valid_items');

    $note = 'AI draft' . ($insight_id ? ' (insight=' . $insight_id . ')' : '');

    $transfer_id = DB::tx(function ($pdo) use ($tenant_id, $user_id, $from_store_id, $to_store_id, $note, $clean) {
        DB::run(
            "INSERT INTO transfers
               (tenant_id, from_store_id, to_store_id, user_id, status, note)
             VALUES (?, ?, ?, ?, 'pending', ?)",
            [$tenant_id, $from_store_id, $to_store_id, $user_id, $note]
        );
        $tid = (int)DB::lastInsertId();
        foreach ($clean as $row) {
            DB::run(
                "INSERT INTO transfer_items (transfer_id, product_id, quantity)
                 VALUES (?, ?, ?)",
                [$tid, $row['product_id'], $row['qty']]
            );
        }
        return $tid;
    });

    jok(['transfer_id' => $transfer_id]);
}

function handleDismiss(int $tenant_id, int $user_id, int $store_id): void {
    $insight_id = (int)($_POST['insight_id'] ?? 0);
    $reason     = trim((string)($_POST['reason'] ?? ''));
    if (mb_strlen($reason) > 500) $reason = mb_substr($reason, 0, 500);

    if ($insight_id <= 0) jerr(400, 'no_insight');

    $row = DB::run(
        "SELECT id, topic_id, category, action_data
           FROM ai_insights
          WHERE id=? AND tenant_id=? LIMIT 1",
        [$insight_id, $tenant_id]
    )->fetch();
    if (!$row) jerr(404, 'not_found');

    $action_data = [];
    if (!empty($row['action_data'])) {
        $decoded = json_decode((string)$row['action_data'], true);
        if (is_array($decoded)) $action_data = $decoded;
    }
    $action_data['dismissed_at']     = date('c');
    $action_data['dismissed_by']     = $user_id;
    $action_data['dismissed_reason'] = $reason;

    DB::tx(function ($pdo) use ($insight_id, $tenant_id, $row, $user_id, $store_id, $action_data) {
        DB::run(
            "UPDATE ai_insights
                SET expires_at = NOW(),
                    action_data = ?
              WHERE id = ? AND tenant_id = ?",
            [json_encode($action_data, JSON_UNESCAPED_UNICODE), $insight_id, $tenant_id]
        );
        DB::run(
            "INSERT INTO ai_shown
               (tenant_id, user_id, store_id, topic_id, category, product_id,
                shown_at, action, action_at)
             VALUES (?, ?, ?, ?, ?, NULL, NOW(), 'dismissed', NOW())",
            [$tenant_id, $user_id, $store_id, (string)$row['topic_id'], (string)$row['category']]
        );
    });

    jok(['insight_id' => $insight_id]);
}
