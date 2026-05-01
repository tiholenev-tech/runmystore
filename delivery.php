<?php
/**
 * delivery.php — Single delivery wizard (Simple + Detailed Mode).
 *
 * Spec: DELIVERY_ORDERS_DECISIONS_FINAL §K (Simple) + §L (Detailed)
 * Design: spazva /design-kit/ 1:1 — без свой .glass / .lb-card / .briefing / .pill / .rms-*
 *
 * URLs:
 *   delivery.php?action=new           — нова доставка (camera/voice entry)
 *   delivery.php?id=N                 — преглед/редакция на съществуваща
 *
 * AJAX endpoints (POST):
 *   ?api=ocr_upload    — multipart файл(ове) → OCRRouter → draft delivery
 *   ?api=update_item   — qty/cost/retail edit на ред
 *   ?api=approve_item  — маркира ред като одобрен
 *   ?api=add_defective — премества ред в supplier_defectives
 *   ?api=commit        — финализира доставката (commit + inventory + audit)
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/services/duplicate-check.php';
require_once __DIR__ . '/services/pricing-engine.php';
require_once __DIR__ . '/services/ocr-router.php';

$pdo = DB::get();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$tenant_id = (int)$_SESSION['tenant_id'];

$stmt = $pdo->prepare("
    SELECT u.name, u.role, u.store_id,
           t.supato_mode, t.currency, t.language, t.ui_mode, t.country, t.plan, t.plan_effective, t.trial_ends_at
    FROM users u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1
");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: logout.php'); exit; }

$role     = $user['role'];
$lang     = $user['language'] ?? 'bg';
$currency = $user['currency'] ?? 'EUR';
$store_id = (int)($user['store_id'] ?? 0);

// Mode: seller винаги Simple. Owner/manager — t.ui_mode (defaults 'simple').
$mode = ($role === 'seller') ? 'simple' : ($user['ui_mode'] ?: 'simple');

$plan_effective = effectivePlan($user);

// ─────────────────────────────────────────────────────────────────────
// AJAX HANDLERS (POST + ?api=...)
// ─────────────────────────────────────────────────────────────────────
$api = $_GET['api'] ?? '';
if ($api && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($api) {
            case 'ocr_upload':       echo json_encode(api_ocr_upload($tenant_id, $store_id, $user_id)); break;
            case 'update_item':      echo json_encode(api_update_item($tenant_id, $user_id)); break;
            case 'approve_item':     echo json_encode(api_approve_item($tenant_id, $user_id)); break;
            case 'add_defective':    echo json_encode(api_add_defective($tenant_id, $store_id, $user_id)); break;
            case 'commit':           echo json_encode(api_commit($tenant_id, $store_id, $user_id)); break;
            case 'voice_capture':    echo json_encode(api_voice_capture($tenant_id, $user_id)); break;
            case 'manual_create':    echo json_encode(api_manual_create($tenant_id, $store_id, $user_id)); break;
            default:
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'unknown api']);
        }
    } catch (Throwable $e) {
        error_log('delivery.php api ' . $api . ': ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'server error: ' . $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────
// VIEW LOAD — съществуваща доставка
// ─────────────────────────────────────────────────────────────────────
$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action      = $_GET['action'] ?? '';
$delivery    = null;
$items       = [];

if ($delivery_id > 0) {
    $delivery = $pdo->prepare("
        SELECT d.*, s.name AS supplier_name
        FROM deliveries d
        LEFT JOIN suppliers s ON s.id = d.supplier_id
        WHERE d.id = ? AND d.tenant_id = ?
    ");
    $delivery->execute([$delivery_id, $tenant_id]);
    $delivery = $delivery->fetch(PDO::FETCH_ASSOC);
    if (!$delivery) {
        http_response_code(404);
        echo 'Delivery not found';
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT di.*, p.name AS product_name, p.has_variations
        FROM delivery_items di
        LEFT JOIN products p ON p.id = di.product_id
        WHERE di.delivery_id = ?
        ORDER BY di.line_number, di.id
    ");
    $stmt->execute([$delivery_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Suppliers list (за voice/manual flow)
$suppliers = $pdo->prepare("SELECT id, name FROM suppliers WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
$suppliers->execute([$tenant_id]);
$suppliers = $suppliers->fetchAll(PDO::FETCH_ASSOC);

// ─────────────────────────────────────────────────────────────────────
// API IMPLEMENTATIONS
// ─────────────────────────────────────────────────────────────────────
function api_ocr_upload(int $tenant_id, int $store_id, int $user_id): array {
    if (empty($_FILES['file'])) return ['ok' => false, 'error' => 'no file'];

    $files = [];
    $f = $_FILES['file'];
    if (is_array($f['name'])) {
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_OK) {
                $files[] = ['path' => $f['tmp_name'][$i], 'mime' => $f['type'][$i]];
            }
        }
    } elseif ($f['error'] === UPLOAD_ERR_OK) {
        $files[] = ['path' => $f['tmp_name'], 'mime' => $f['type']];
    }
    if (empty($files)) return ['ok' => false, 'error' => 'no valid files'];

    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    $router = new OCRRouter();
    $ocr = $router->process($files, $tenant_id, [
        'supplier_id'       => $supplier_id,
        'expected_currency' => 'EUR',
    ]);

    if ($ocr['status'] === 'REJECTED') {
        return [
            'ok'                     => false,
            'error'                  => implode('; ', (array)$ocr['errors']),
            'suggest_voice_fallback' => $ocr['suggest_voice_fallback'] ?? false,
        ];
    }

    // Resolve supplier по име ако не е подаден
    if (!$supplier_id && !empty($ocr['header']['supplier_name'])) {
        $sup = DB::run("
            SELECT id FROM suppliers
            WHERE tenant_id = ? AND name LIKE ? AND is_active = 1
            ORDER BY id DESC LIMIT 1
        ", [$tenant_id, '%' . trim($ocr['header']['supplier_name']) . '%'])->fetch();
        if ($sup) $supplier_id = (int)$sup['id'];
    }

    // Duplicate check (Закон F1-F2)
    $invoice_number = $ocr['header']['invoice_number'] ?? null;
    if ($invoice_number) {
        $items_for_dup = array_map(function ($it) {
            return [
                'product_id' => $it['name'] ?? '',
                'quantity'   => $it['qty'] ?? 0,
                'unit_price' => $it['unit_cost'] ?? 0,
            ];
        }, $ocr['items']);

        $dup = checkDuplicate('delivery', $tenant_id, [
            'supplier_id'    => $supplier_id,
            'invoice_number' => $invoice_number,
            'items'          => $items_for_dup,
        ]);
        if ($dup['is_duplicate'] && $dup['level'] === 'hard') {
            return [
                'ok'         => false,
                'error'      => $dup['message'],
                'duplicate'  => $dup,
            ];
        }
    }

    // Создаваме draft delivery + items
    $delivery_id = DB::tx(function (PDO $pdo) use ($tenant_id, $store_id, $user_id, $supplier_id, $ocr, $invoice_number) {
        $h = $ocr['header'];

        $signature = computeContentSignature($supplier_id, array_map(function ($it) {
            return [
                'product_id' => $it['name'] ?? '',
                'quantity'   => $it['qty'] ?? 0,
                'unit_price' => $it['unit_cost'] ?? 0,
            ];
        }, $ocr['items']));

        $stmt = $pdo->prepare("
            INSERT INTO deliveries
              (tenant_id, store_id, supplier_id, user_id, invoice_number, total, currency_code,
               status, invoice_type, ocr_raw_json, content_signature, delivered_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'reviewing', ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenant_id, $store_id, $supplier_id, $user_id,
            $invoice_number,
            (float)($h['total_amount'] ?? 0),
            (string)($h['currency'] ?? 'EUR'),
            $ocr['invoice_type'] ?? 'clean',
            json_encode($ocr['raw_ocr_json'], JSON_UNESCAPED_UNICODE),
            $signature,
        ]);
        $delivery_id = (int)$pdo->lastInsertId();

        $line_no = 1;
        foreach ($ocr['items'] as $it) {
            $product_id = resolveOrCreateProduct($pdo, $tenant_id, $store_id, $supplier_id, $it);

            $qty = (float)($it['qty'] ?? 0);
            $u   = (float)($it['unit_cost'] ?? 0);
            $tot = $it['total_cost'] !== null ? (float)$it['total_cost'] : round($qty * $u, 2);

            $stmt = $pdo->prepare("
                INSERT INTO delivery_items
                  (tenant_id, store_id, supplier_id, delivery_id, product_id,
                   quantity, cost_price, total, currency_code,
                   line_number, product_name_snapshot, supplier_product_code,
                   pack_size, vat_rate_applied, original_ocr_text,
                   variation_pending)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $store_id, $supplier_id, $delivery_id, $product_id,
                $qty, $u, $tot, (string)($it['currency'] ?? 'EUR'),
                $line_no++, (string)$it['name'], $it['supplier_product_code'] ?? null,
                1, $it['vat_rate'] ?? null, $it['original_ocr_text'] ?? null,
                !empty($it['variation_pending']) ? 1 : 0,
            ]);
        }

        $pdo->prepare("INSERT INTO delivery_events (tenant_id, store_id, delivery_id, user_id, event_type, payload)
                       VALUES (?, ?, ?, ?, 'ocr_imported', ?)")
            ->execute([$tenant_id, $store_id, $delivery_id, $user_id,
                       json_encode(['confidence' => $ocr['confidence']])]);

        return $delivery_id;
    });

    return [
        'ok'           => true,
        'delivery_id'  => $delivery_id,
        'redirect_to'  => '/delivery.php?id=' . $delivery_id,
        'ocr'          => [
            'status'           => $ocr['status'],
            'confidence'       => $ocr['confidence'],
            'invoice_type'     => $ocr['invoice_type'],
            'uncertain_fields' => $ocr['uncertain_fields'],
        ],
    ];
}

function resolveOrCreateProduct(PDO $pdo, int $tenant_id, int $store_id, ?int $supplier_id, array $item): int {
    $name = trim((string)($item['name'] ?? ''));
    if ($name === '') $name = 'Непознат продукт';

    if (!empty($item['supplier_product_code']) && $supplier_id) {
        $stmt = $pdo->prepare("
            SELECT product_id FROM delivery_items
            WHERE tenant_id = ? AND supplier_id = ? AND supplier_product_code = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$tenant_id, $supplier_id, $item['supplier_product_code']]);
        $pid = $stmt->fetchColumn();
        if ($pid) return (int)$pid;
    }

    $stmt = $pdo->prepare("
        SELECT id FROM products
        WHERE tenant_id = ? AND name = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $name]);
    $pid = $stmt->fetchColumn();
    if ($pid) return (int)$pid;

    $hv = !empty($item['has_variations_hint']) ? 'unknown' : 'unknown';
    $stmt = $pdo->prepare("
        INSERT INTO products (tenant_id, supplier_id, name, cost_price, retail_price, has_variations, is_active, created_at)
        VALUES (?, ?, ?, ?, 0.00, ?, 1, NOW())
    ");
    $stmt->execute([
        $tenant_id, $supplier_id, $name,
        (float)($item['unit_cost'] ?? 0),
        $hv,
    ]);
    return (int)$pdo->lastInsertId();
}

function api_update_item(int $tenant_id, int $user_id): array {
    $delivery_item_id = (int)($_POST['delivery_item_id'] ?? 0);
    if ($delivery_item_id <= 0) return ['ok' => false, 'error' => 'missing delivery_item_id'];

    $row = DB::run("
        SELECT di.*, d.tenant_id AS d_tenant
        FROM delivery_items di
        JOIN deliveries d ON d.id = di.delivery_id
        WHERE di.id = ? AND d.tenant_id = ? AND d.status NOT IN ('committed','voided','superseded')
    ", [$delivery_item_id, $tenant_id])->fetch();
    if (!$row) return ['ok' => false, 'error' => 'item not found or locked'];

    $qty = isset($_POST['quantity']) ? (float)$_POST['quantity'] : (float)$row['quantity'];
    $u   = isset($_POST['cost_price']) ? (float)$_POST['cost_price'] : (float)$row['cost_price'];
    $retail = isset($_POST['retail_price']) ? (float)$_POST['retail_price'] : null;

    $tot = round($qty * $u, 2);

    DB::tx(function (PDO $pdo) use ($delivery_item_id, $qty, $u, $tot, $retail, $row, $tenant_id, $user_id) {
        $pdo->prepare("UPDATE delivery_items SET quantity=?, cost_price=?, total=? WHERE id=?")
            ->execute([$qty, $u, $tot, $delivery_item_id]);

        if ($retail !== null && (int)$row['product_id'] > 0) {
            $existing = $pdo->prepare("SELECT cost_price, retail_price FROM products WHERE id=? AND tenant_id=?");
            $existing->execute([(int)$row['product_id'], $tenant_id]);
            $exp = $existing->fetch();

            $pdo->prepare("UPDATE products SET retail_price=?, cost_price=? WHERE id=? AND tenant_id=?")
                ->execute([$retail, $u, (int)$row['product_id'], $tenant_id]);

            if ($exp) {
                auditPriceChange(
                    $tenant_id, (int)$row['store_id'], (int)$row['product_id'],
                    (float)$exp['cost_price'], $u,
                    (float)$exp['retail_price'], $retail,
                    'manual', null, false,
                    (int)$row['delivery_id'], $delivery_item_id, $user_id,
                    'Manual edit during delivery review'
                );
            }
        }
    });

    return ['ok' => true, 'data' => ['quantity' => $qty, 'cost_price' => $u, 'total' => $tot]];
}

function api_approve_item(int $tenant_id, int $user_id): array {
    $delivery_item_id = (int)($_POST['delivery_item_id'] ?? 0);
    if ($delivery_item_id <= 0) return ['ok' => false, 'error' => 'missing'];

    DB::run("
        UPDATE delivery_items di
        JOIN deliveries d ON d.id = di.delivery_id
        SET di.received_condition = 'new'
        WHERE di.id = ? AND d.tenant_id = ?
    ", [$delivery_item_id, $tenant_id]);

    return ['ok' => true];
}

function api_add_defective(int $tenant_id, int $store_id, int $user_id): array {
    $delivery_item_id = (int)($_POST['delivery_item_id'] ?? 0);
    $reason = $_POST['reason'] ?? 'damaged';
    $allowed = ['damaged','expired','wrong_item','quality_issue','other'];
    if (!in_array($reason, $allowed, true)) $reason = 'damaged';

    $row = DB::run("
        SELECT di.*, d.supplier_id
        FROM delivery_items di
        JOIN deliveries d ON d.id = di.delivery_id
        WHERE di.id = ? AND d.tenant_id = ?
    ", [$delivery_item_id, $tenant_id])->fetch();
    if (!$row) return ['ok' => false, 'error' => 'not found'];

    DB::tx(function (PDO $pdo) use ($tenant_id, $store_id, $row, $reason, $user_id) {
        $pdo->prepare("UPDATE delivery_items SET received_condition=? WHERE id=?")
            ->execute([$reason === 'expired' ? 'expired' : ($reason === 'wrong_item' ? 'wrong_item' : 'damaged'),
                       (int)$row['id']]);

        $pdo->prepare("
            INSERT INTO supplier_defectives
              (tenant_id, store_id, supplier_id, delivery_id, delivery_item_id, product_id,
               quantity, unit_cost, total_cost, currency_code, reason, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([
            $tenant_id, $store_id, (int)$row['supplier_id'], (int)$row['delivery_id'],
            (int)$row['id'], (int)$row['product_id'],
            (float)$row['quantity'], (float)$row['cost_price'],
            (float)$row['total'], (string)$row['currency_code'],
            $reason, $user_id,
        ]);
    });

    return ['ok' => true];
}

function api_commit(int $tenant_id, int $store_id, int $user_id): array {
    $delivery_id = (int)($_POST['delivery_id'] ?? 0);
    if ($delivery_id <= 0) return ['ok' => false, 'error' => 'missing delivery_id'];

    $delivery = DB::run("SELECT * FROM deliveries WHERE id=? AND tenant_id=?",
                        [$delivery_id, $tenant_id])->fetch();
    if (!$delivery) return ['ok' => false, 'error' => 'not found'];
    if ($delivery['status'] === 'committed') {
        return ['ok' => false, 'error' => 'already committed'];
    }

    DB::tx(function (PDO $pdo) use ($delivery_id, $tenant_id, $store_id, $user_id, $delivery) {
        $items = $pdo->prepare("
            SELECT di.*, p.cost_price AS prod_cost
            FROM delivery_items di
            LEFT JOIN products p ON p.id = di.product_id
            WHERE di.delivery_id = ? AND di.received_condition = 'new'
        ");
        $items->execute([$delivery_id]);
        $items = $items->fetchAll(PDO::FETCH_ASSOC);

        $total = 0.0;
        foreach ($items as $it) {
            $qty = (float)$it['quantity'];
            $cost = (float)$it['cost_price'];
            $total += $qty * $cost;

            $pid = (int)$it['product_id'];
            if ($pid <= 0) continue;

            // Inventory upsert
            $row = $pdo->prepare("SELECT id, quantity FROM inventory WHERE tenant_id=? AND product_id=? AND store_id=? LIMIT 1");
            $row->execute([$tenant_id, $pid, $store_id]);
            $inv = $row->fetch();
            if ($inv) {
                $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$qty, (int)$inv['id']]);
            } else {
                $pdo->prepare("INSERT INTO inventory (tenant_id, store_id, product_id, quantity, min_quantity)
                               VALUES (?, ?, ?, ?, 0)")
                    ->execute([$tenant_id, $store_id, $pid, $qty]);
            }

            // stock_movements запис
            $pdo->prepare("
                INSERT INTO stock_movements (tenant_id, store_id, product_id, type, quantity, price, reference_type, reference_id, user_id)
                VALUES (?, ?, ?, 'delivery', ?, ?, 'delivery', ?, ?)
            ")->execute([$tenant_id, $store_id, $pid, $qty, $cost, $delivery_id, $user_id]);
        }

        // Compute payment_due_date
        $due_date = null;
        $sup = $pdo->prepare("SELECT payment_terms_days FROM suppliers WHERE id=?");
        $sup->execute([(int)$delivery['supplier_id']]);
        $terms = (int)($sup->fetchColumn() ?: 0);
        if ($terms > 0) {
            $due_date = date('Y-m-d', strtotime('+' . $terms . ' days'));
        }

        $pdo->prepare("
            UPDATE deliveries
            SET status='committed', committed_by=?, committed_at=NOW(), locked_at=NOW(),
                payment_due_date=?, total=?, auto_close_reason='user_committed'
            WHERE id=?
        ")->execute([$user_id, $due_date, $total, $delivery_id]);

        $pdo->prepare("INSERT INTO delivery_events (tenant_id, store_id, delivery_id, user_id, event_type, payload)
                       VALUES (?, ?, ?, ?, 'committed', ?)")
            ->execute([$tenant_id, $store_id, $delivery_id, $user_id,
                       json_encode(['total' => $total, 'item_count' => count($items)])]);
    });

    return ['ok' => true, 'redirect_to' => '/deliveries.php'];
}

function api_voice_capture(int $tenant_id, int $user_id): array {
    return ['ok' => false, 'error' => 'voice flow not implemented yet — use camera'];
}

function api_manual_create(int $tenant_id, int $store_id, int $user_id): array {
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    $delivery_id = DB::tx(function (PDO $pdo) use ($tenant_id, $store_id, $user_id, $supplier_id) {
        $stmt = $pdo->prepare("
            INSERT INTO deliveries (tenant_id, store_id, supplier_id, user_id, status, invoice_type, currency_code, created_at, delivered_at)
            VALUES (?, ?, ?, ?, 'reviewing', 'manual', 'EUR', NOW(), NOW())
        ");
        $stmt->execute([$tenant_id, $store_id, $supplier_id, $user_id]);
        return (int)$pdo->lastInsertId();
    });

    return ['ok' => true, 'delivery_id' => $delivery_id, 'redirect_to' => '/delivery.php?id=' . $delivery_id];
}

// Helpers за view
function fmtMoney(float $v, string $currency = 'EUR'): string {
    $sym = $currency === 'EUR' ? '€' : ($currency === 'BGN' ? 'лв' : $currency);
    return number_format($v, 2, '.', ' ') . ' ' . $sym;
}

function dispView(string $view): bool {
    return true;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>Доставка — RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">

<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

<style>
/* mod-del-* — само module-specific helpers, не дублира design-kit */
.mod-del-step{display:none}
.mod-del-step.active{display:block}

.mod-del-entry-cta{
    display:flex;align-items:center;gap:14px;padding:18px 16px;cursor:pointer;
    text-decoration:none;color:inherit;border:none;width:100%;font-family:inherit
}
.mod-del-entry-ico{
    width:54px;height:54px;border-radius:16px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;color:#fff;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.25)
}
.mod-del-entry-ico.cam{background:linear-gradient(135deg,hsl(38 75% 52%),hsl(28 75% 46%));box-shadow:0 0 18px hsl(38 75% 50% / .55),inset 0 1px 0 rgba(255,255,255,.25)}
.mod-del-entry-ico.mic{background:linear-gradient(135deg,hsl(280 65% 52%),hsl(310 65% 46%));box-shadow:0 0 16px hsl(280 65% 50% / .45),inset 0 1px 0 rgba(255,255,255,.25)}
.mod-del-entry-ico.man{background:linear-gradient(135deg,hsl(255 50% 50%),hsl(222 50% 44%));box-shadow:0 0 14px hsl(255 50% 50% / .40),inset 0 1px 0 rgba(255,255,255,.25)}
.mod-del-entry-ico svg{width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.mod-del-entry-text{flex:1;min-width:0;text-align:left}
.mod-del-entry-title{font-size:16px;font-weight:900;color:#f1f5f9;letter-spacing:-.01em;line-height:1.2}
.mod-del-entry-sub{font-size:11px;font-weight:600;color:rgba(255,255,255,.5);margin-top:4px}
.mod-del-entry-arr{color:hsl(38 80% 70%);font-size:20px;font-weight:900;flex-shrink:0}

.mod-del-row{
    display:flex;align-items:center;gap:10px;padding:10px 12px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);
    border-radius:12px;margin-bottom:6px;cursor:pointer
}
.mod-del-row.approved{border-color:rgba(34,197,94,.5);background:rgba(34,197,94,.05)}
.mod-del-row.uncertain{border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.06)}
.mod-del-row.defective{border-color:rgba(239,68,68,.4);background:rgba(239,68,68,.06);opacity:.7}
.mod-del-check{
    width:26px;height:26px;border-radius:8px;flex-shrink:0;
    border:2px solid rgba(255,255,255,.25);
    display:flex;align-items:center;justify-content:center;color:transparent
}
.mod-del-row.approved .mod-del-check{border-color:hsl(145 65% 50%);background:hsl(145 65% 42%);color:#fff}
.mod-del-row.approved .mod-del-check svg{display:block}
.mod-del-check svg{display:none;width:16px;height:16px;stroke:currentColor;stroke-width:3;fill:none;stroke-linecap:round;stroke-linejoin:round}
.mod-del-row-body{flex:1;min-width:0}
.mod-del-row-name{font-size:13px;font-weight:800;color:#f1f5f9;line-height:1.2;text-overflow:ellipsis;overflow:hidden;white-space:nowrap}
.mod-del-row-meta{font-size:10px;font-weight:600;color:rgba(255,255,255,.45);margin-top:3px;display:flex;gap:8px;flex-wrap:wrap}
.mod-del-row-amt{font-size:13px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums;text-align:right;flex-shrink:0;line-height:1}
.mod-del-row-amt small{display:block;font-size:8px;font-weight:700;color:rgba(255,255,255,.4);letter-spacing:.06em;margin-top:3px;text-transform:uppercase}

.mod-del-sec-label{
    font-size:9px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;
    color:hsl(255 50% 70%);margin:14px 4px 8px
}

.mod-del-totals{
    display:flex;justify-content:space-between;align-items:center;
    padding:12px 14px;border-radius:12px;margin-top:10px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07)
}
.mod-del-totals-label{font-size:10px;font-weight:700;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.06em}
.mod-del-totals-amt{font-size:18px;font-weight:900;color:#f1f5f9;font-variant-numeric:tabular-nums}

.mod-del-progress{display:flex;align-items:center;gap:10px;padding:8px 12px}
.mod-del-progress-bar{flex:1;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden}
.mod-del-progress-fill{height:100%;background:linear-gradient(90deg,hsl(145 65% 45%),hsl(160 65% 40%));transition:width .3s}
.mod-del-progress-text{font-size:11px;font-weight:700;color:rgba(255,255,255,.7);font-variant-numeric:tabular-nums}

.mod-del-action-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;
    padding:14px 20px;border-radius:14px;width:100%;
    font-size:14px;font-weight:900;letter-spacing:.02em;
    text-decoration:none;border:none;cursor:pointer;font-family:inherit;
    background:linear-gradient(135deg,hsl(145 65% 45%),hsl(160 65% 38%));
    color:#fff;box-shadow:0 0 16px hsl(145 65% 45% / .4)
}
.mod-del-action-btn:disabled{opacity:.4;cursor:not-allowed;box-shadow:none}
.mod-del-action-btn.warn{background:linear-gradient(135deg,hsl(38 75% 48%),hsl(28 75% 42%));box-shadow:0 0 16px hsl(38 75% 50% / .4)}
.mod-del-action-btn.danger{background:linear-gradient(135deg,hsl(0 70% 48%),hsl(15 70% 42%));box-shadow:0 0 14px hsl(0 75% 50% / .4)}

.mod-del-sheet{
    position:fixed;left:0;right:0;bottom:0;z-index:200;
    background:rgba(8,9,13,.95);
    border-top:1px solid rgba(255,255,255,.12);
    border-radius:18px 18px 0 0;
    padding:18px 16px calc(18px + env(safe-area-inset-bottom,0));
    transform:translateY(110%);transition:transform .25s ease-out;
    max-height:75vh;overflow-y:auto
}
.mod-del-sheet.open{transform:translateY(0)}
.mod-del-sheet-grip{
    width:48px;height:4px;border-radius:2px;background:rgba(255,255,255,.2);
    margin:-8px auto 12px
}
.mod-del-sheet h3{font-size:14px;font-weight:900;color:#f1f5f9;margin-bottom:12px}
.mod-del-sheet label{display:block;font-size:10px;font-weight:700;color:rgba(255,255,255,.55);margin:10px 0 4px;text-transform:uppercase;letter-spacing:.06em}
.mod-del-sheet input[type=number],
.mod-del-sheet input[type=text]{
    width:100%;padding:12px 14px;border-radius:10px;
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    color:#f1f5f9;font-size:15px;font-weight:700;font-family:inherit;
    font-variant-numeric:tabular-nums
}
.mod-del-sheet-actions{display:flex;gap:8px;margin-top:14px}
.mod-del-sheet-actions button{flex:1}

.mod-del-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:199;
    opacity:0;pointer-events:none;transition:opacity .2s
}
.mod-del-overlay.open{opacity:1;pointer-events:auto}

.mod-del-loading{
    text-align:center;padding:48px 16px
}
.mod-del-loading-spin{
    width:48px;height:48px;border-radius:50%;
    border:3px solid rgba(255,255,255,.1);
    border-top-color:hsl(38 75% 60%);
    margin:0 auto 16px;animation:modDelSpin .8s linear infinite
}
@keyframes modDelSpin{to{transform:rotate(360deg)}}
.mod-del-loading-title{font-size:16px;font-weight:900;color:#f1f5f9;margin-bottom:6px}
.mod-del-loading-sub{font-size:11px;font-weight:600;color:rgba(255,255,255,.5)}

.mod-del-toast{
    position:fixed;left:16px;right:16px;bottom:80px;z-index:300;
    padding:12px 16px;border-radius:12px;
    background:rgba(8,9,13,.95);border:1px solid rgba(34,197,94,.5);
    color:#86efac;font-size:13px;font-weight:800;
    transform:translateY(120%);transition:transform .3s;
    box-shadow:0 0 16px rgba(34,197,94,.3)
}
.mod-del-toast.show{transform:translateY(0)}
.mod-del-toast.warn{border-color:rgba(245,158,11,.6);color:#fbbf24}
.mod-del-toast.error{border-color:rgba(239,68,68,.6);color:#fca5a5}

.mod-del-conf-pill{
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 8px;border-radius:100px;font-size:9px;font-weight:900;
    letter-spacing:.05em;text-transform:uppercase
}
.mod-del-conf-pill.high{background:rgba(34,197,94,.14);color:#86efac;border:1px solid rgba(34,197,94,.35)}
.mod-del-conf-pill.mid{background:rgba(245,158,11,.14);color:#fbbf24;border:1px solid rgba(245,158,11,.35)}
.mod-del-conf-pill.low{background:rgba(239,68,68,.14);color:#fca5a5;border:1px solid rgba(239,68,68,.35)}

/* Detailed Mode override: показваме table view, ДДС полета, full reconciliation inline */
.mode-detailed .mod-del-row-meta{font-size:11px}
.mode-detailed .mod-del-row{padding:14px 16px}
.mode-simple .mod-del-detail-only{display:none}
.mode-detailed .mod-del-simple-only{display:none}

.mod-del-supplier-pick{
    display:block;width:100%;padding:14px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
    border-radius:12px;color:#f1f5f9;font-family:inherit;font-size:14px;font-weight:700
}
</style>
</head>
<body class="has-rms-shell mode-<?= htmlspecialchars($mode) ?>">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="app">

<?php if ($delivery): // ───── REVIEW EXISTING DELIVERY ───── ?>
    <?php
    $approved = 0;
    $defective_count = 0;
    foreach ($items as $it) {
        if (($it['received_condition'] ?? 'new') === 'new') $approved++;
        else $defective_count++;
    }
    $total_items = count($items);
    $progress = $total_items ? round(($approved / $total_items) * 100) : 0;
    $running_total = array_sum(array_map(fn($it) => (float)$it['total'], $items));
    $is_locked = in_array($delivery['status'], ['committed','voided','superseded'], true);
    ?>

    <div class="mod-del-sec-label">
        <?= htmlspecialchars($delivery['supplier_name'] ?: 'Без доставчик') ?>
        · <?= date('d.m', strtotime((string)$delivery['delivered_at'])) ?>
        · <?= htmlspecialchars($delivery['status']) ?>
    </div>

    <!-- ───── DETAILED MODE — VAT header + payment info ───── -->
    <div class="mod-del-detail-only">
        <div class="glass qd" style="padding:14px 16px;margin-bottom:10px">
            <span class="shine"></span><span class="glow"></span>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 14px">
                <div>
                    <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em">Фактура №</div>
                    <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-top:2px"><?= htmlspecialchars((string)($delivery['invoice_number'] ?: '—')) ?></div>
                </div>
                <div>
                    <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em">Дата</div>
                    <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-top:2px"><?= htmlspecialchars((string)date('d.m.Y', strtotime((string)$delivery['delivered_at']))) ?></div>
                </div>
                <div>
                    <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em">Тип</div>
                    <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-top:2px"><?= htmlspecialchars((string)($delivery['invoice_type'] ?: 'clean')) ?></div>
                </div>
                <div>
                    <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em">Плащане</div>
                    <div style="font-size:13px;font-weight:800;color:#f1f5f9;margin-top:2px">
                        <?= htmlspecialchars((string)($delivery['payment_status'])) ?>
                        <?php if ($delivery['payment_due_date']): ?>
                            <span style="font-size:10px;color:rgba(255,255,255,.5);font-weight:600">
                                · падеж <?= date('d.m', strtotime((string)$delivery['payment_due_date'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($delivery['has_mismatch'])): ?>
                <div style="margin-top:12px;padding:10px;border-radius:10px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.32);font-size:11px;color:#fbbf24;font-weight:700">
                    <?= htmlspecialchars((string)($delivery['mismatch_summary'] ?: 'Има разлика срещу поръчката или фактурата.')) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$is_locked): ?>
    <div class="mod-del-progress">
        <div class="mod-del-progress-bar"><div class="mod-del-progress-fill" id="modDelProgressFill" style="width:<?= $progress ?>%"></div></div>
        <div class="mod-del-progress-text" id="modDelProgressText"><?= $approved ?>/<?= $total_items ?></div>
    </div>
    <?php endif; ?>

    <div id="modDelRows">
    <?php foreach ($items as $it):
        $cond = $it['received_condition'] ?? 'new';
        $cls = $cond === 'new' ? 'approved' : ($cond === 'damaged' || $cond === 'expired' || $cond === 'wrong_item' ? 'defective' : '');
        $variation = !empty($it['variation_pending']);
        if ($variation && $cond === 'new') $cls = 'uncertain';
    ?>
        <div class="mod-del-row <?= $cls ?>"
             data-id="<?= (int)$it['id'] ?>"
             data-product-id="<?= (int)$it['product_id'] ?>"
             data-qty="<?= htmlspecialchars((string)$it['quantity']) ?>"
             data-cost="<?= htmlspecialchars((string)$it['cost_price']) ?>"
             data-name="<?= htmlspecialchars((string)$it['product_name_snapshot']) ?>"
             data-condition="<?= htmlspecialchars($cond) ?>"
             onclick="modDelOpenSheet(this)">
            <div class="mod-del-check">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="mod-del-row-body">
                <div class="mod-del-row-name"><?= htmlspecialchars((string)($it['product_name_snapshot'] ?: $it['product_name'])) ?></div>
                <div class="mod-del-row-meta">
                    <span><?= rtrim(rtrim(number_format((float)$it['quantity'], 2, '.', ''), '0'), '.') ?> бр</span>
                    <span>×</span>
                    <span><?= fmtMoney((float)$it['cost_price'], (string)$it['currency_code']) ?></span>
                    <?php if ($variation): ?><span style="color:hsl(38 75% 65%);display:inline-flex;align-items:center;gap:3px"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>вариации</span><?php endif; ?>
                    <?php if ($cond !== 'new'): ?><span style="color:hsl(0 70% 65%)">дефектен</span><?php endif; ?>
                </div>
            </div>
            <div class="mod-del-row-amt"><?= fmtMoney((float)$it['total'], (string)$it['currency_code']) ?><small>общо</small></div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if (empty($items)): ?>
    <div class="glass q4" style="padding:24px;text-align:center;margin:16px 0">
        <span class="shine"></span><span class="glow"></span>
        <div style="font-size:14px;font-weight:800;color:#f1f5f9;margin-bottom:8px">Няма редове</div>
        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:14px">
            Снимай фактурата отново или добави артикули ръчно.
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
    <div class="mod-del-totals">
        <div>
            <div class="mod-del-totals-label">Общо</div>
            <div class="mod-del-totals-amt"><?= fmtMoney($running_total, (string)($delivery['currency_code'] ?? 'EUR')) ?></div>
        </div>
        <div style="text-align:right">
            <div class="mod-del-totals-label">Артикули</div>
            <div class="mod-del-totals-amt" style="font-size:14px;color:rgba(255,255,255,.7)"><?= $approved ?>/<?= $total_items ?> <span style="color:hsl(145 65% 55%);display:inline-flex;align-items:center;vertical-align:middle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$is_locked): ?>
    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
        <button class="mod-del-action-btn" id="modDelCommitBtn"
                onclick="modDelCommit()"
                <?= ($approved === 0) ? 'disabled' : '' ?>>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Заприходи всичко
        </button>
        <a class="mod-del-action-btn warn" href="/deliveries.php" style="text-decoration:none">
            Запази като чернова
        </a>
    </div>
    <?php else: ?>
    <div class="glass q3" style="padding:18px;text-align:center;margin:16px 0">
        <span class="shine"></span><span class="glow"></span>
        <div style="font-size:13px;font-weight:800;color:#86efac">Доставката е заприходена</div>
        <div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:4px">
            <?= htmlspecialchars((string)($delivery['committed_at'] ?: '')) ?>
        </div>
    </div>
    <?php endif; ?>

<?php else: // ───── ENTRY SCREEN ───── ?>

    <div class="mod-del-sec-label">Нова доставка</div>

    <button class="glass q4 mod-del-entry-cta card-stagger" type="button" id="modDelCamBtn">
        <span class="shine"></span>
        <span class="shine shine-bottom"></span>
        <span class="glow"></span>
        <span class="glow glow-bottom"></span>
        <div class="mod-del-entry-ico cam">
            <svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"/><circle cx="12" cy="13" r="3"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </div>
        <div class="mod-del-entry-text">
            <div class="mod-del-entry-title">Снимай фактурата</div>
            <div class="mod-del-entry-sub">AI прочита всичко — ти само одобряваш</div>
        </div>
        <div class="mod-del-entry-arr">›</div>
    </button>

    <input type="file" id="modDelFileInput" accept="image/*,application/pdf" capture="environment" multiple style="display:none">

    <button class="glass q2 mod-del-entry-cta card-stagger" type="button" onclick="modDelStartVoice()">
        <span class="shine"></span>
        <span class="shine shine-bottom"></span>
        <span class="glow"></span>
        <span class="glow glow-bottom"></span>
        <div class="mod-del-entry-ico mic">
            <svg viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="11" rx="3"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </div>
        <div class="mod-del-entry-text">
            <div class="mod-del-entry-title">Кажи какво получи</div>
            <div class="mod-del-entry-sub">говори си с AI ред по ред</div>
        </div>
        <div class="mod-del-entry-arr">›</div>
    </button>

    <button class="glass qd mod-del-entry-cta card-stagger" type="button" onclick="modDelStartManual()">
        <span class="shine"></span>
        <span class="shine shine-bottom"></span>
        <span class="glow"></span>
        <span class="glow glow-bottom"></span>
        <div class="mod-del-entry-ico man">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div class="mod-del-entry-text">
            <div class="mod-del-entry-title">Ръчно</div>
            <div class="mod-del-entry-sub">без снимка / без глас</div>
        </div>
        <div class="mod-del-entry-arr">›</div>
    </button>

    <div class="mod-del-sec-label" style="margin-top:24px">Настройка (по желание)</div>
    <select id="modDelSupplierSel" class="mod-del-supplier-pick">
        <option value="">— автоматично от фактурата —</option>
        <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
    </select>

<?php endif; ?>

</main>

<!-- Bottom sheet за edit на ред -->
<div class="mod-del-overlay" id="modDelOverlay" onclick="modDelCloseSheet()"></div>
<div class="mod-del-sheet" id="modDelSheet">
    <div class="mod-del-sheet-grip"></div>
    <h3 id="modDelSheetTitle">Редактирай ред</h3>
    <input type="hidden" id="modDelSheetId">
    <label>Бройка</label>
    <input type="number" id="modDelSheetQty" inputmode="decimal" step="0.01">
    <label>Цена доставна</label>
    <input type="number" id="modDelSheetCost" inputmode="decimal" step="0.01">
    <label>Препоръчана продажна <span id="modDelSheetSuggest" style="color:hsl(255 60% 78%);font-weight:900"></span></label>
    <input type="number" id="modDelSheetRetail" inputmode="decimal" step="0.01">
    <div class="mod-del-sheet-actions">
        <button class="mod-del-action-btn danger" type="button" onclick="modDelMarkDefective()">Дефектен</button>
        <button class="mod-del-action-btn" type="button" onclick="modDelSaveSheet()">Запази</button>
    </div>
</div>

<div class="mod-del-toast" id="modDelToast"></div>

<!-- Loading overlay -->
<div class="mod-del-overlay" id="modDelLoading" style="display:none">
    <div class="mod-del-loading">
        <div class="mod-del-loading-spin"></div>
        <div class="mod-del-loading-title">Чета фактурата...</div>
        <div class="mod-del-loading-sub">обикновено отнема 3-5 секунди</div>
    </div>
</div>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
<script>
(function () {
    var DELIVERY_ID = <?= $delivery_id ? (int)$delivery_id : 'null' ?>;
    var SUPPLIERS = <?= json_encode($suppliers) ?>;

    function $(id) { return document.getElementById(id); }
    function toast(msg, kind) {
        var t = $('modDelToast');
        t.textContent = msg;
        t.className = 'mod-del-toast' + (kind ? ' ' + kind : '') + ' show';
        setTimeout(function () { t.classList.remove('show'); }, 3500);
    }
    function showLoading(show) { $('modDelLoading').style.display = show ? 'flex' : 'none'; $('modDelLoading').classList.toggle('open', !!show); }

    // ENTRY — camera
    var camBtn = $('modDelCamBtn');
    var fileInput = $('modDelFileInput');
    if (camBtn && fileInput) {
        camBtn.addEventListener('click', function () { fileInput.click(); });
        fileInput.addEventListener('change', function () {
            if (!fileInput.files.length) return;
            var fd = new FormData();
            for (var i = 0; i < fileInput.files.length; i++) {
                fd.append('file[]', fileInput.files[i]);
            }
            var sup = $('modDelSupplierSel');
            if (sup && sup.value) fd.append('supplier_id', sup.value);

            showLoading(true);
            fetch('/delivery.php?api=ocr_upload', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    showLoading(false);
                    if (j.ok && j.redirect_to) {
                        window.location = j.redirect_to;
                    } else {
                        toast(j.error || 'Грешка при четене', 'error');
                        if (j.suggest_voice_fallback) {
                            setTimeout(function () { modDelStartVoice(); }, 1200);
                        }
                    }
                })
                .catch(function (e) {
                    showLoading(false);
                    toast('Мрежова грешка', 'error');
                });
        });
    }

    // ENTRY — voice (placeholder — ще се завърши когато GROQ_API_KEY е добавен)
    window.modDelStartVoice = function () {
        toast('Гласовата диктовка скоро — засега снимай фактурата.', 'warn');
    };

    // ENTRY — manual
    window.modDelStartManual = function () {
        var sup = $('modDelSupplierSel');
        var fd = new FormData();
        if (sup && sup.value) fd.append('supplier_id', sup.value);
        fetch('/delivery.php?api=manual_create', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok && j.redirect_to) window.location = j.redirect_to;
                else toast(j.error || 'Грешка', 'error');
            });
    };

    // REVIEW — bottom sheet
    var sheetCur = null;

    window.modDelOpenSheet = function (rowEl) {
        if (!rowEl) return;
        sheetCur = rowEl;
        $('modDelSheetId').value = rowEl.dataset.id;
        $('modDelSheetQty').value = rowEl.dataset.qty;
        $('modDelSheetCost').value = rowEl.dataset.cost;
        $('modDelSheetTitle').textContent = rowEl.dataset.name || 'Редактирай ред';
        $('modDelSheetRetail').value = '';
        $('modDelSheetSuggest').textContent = '';
        $('modDelOverlay').classList.add('open');
        $('modDelSheet').classList.add('open');
    };
    window.modDelCloseSheet = function () {
        $('modDelOverlay').classList.remove('open');
        $('modDelSheet').classList.remove('open');
        sheetCur = null;
    };
    window.modDelSaveSheet = function () {
        if (!sheetCur) return;
        var fd = new FormData();
        fd.append('delivery_item_id', $('modDelSheetId').value);
        fd.append('quantity', $('modDelSheetQty').value || '0');
        fd.append('cost_price', $('modDelSheetCost').value || '0');
        var retail = $('modDelSheetRetail').value;
        if (retail) fd.append('retail_price', retail);

        fetch('/delivery.php?api=update_item', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) {
                    fd = new FormData();
                    fd.append('delivery_item_id', $('modDelSheetId').value);
                    fetch('/delivery.php?api=approve_item', { method: 'POST', body: fd })
                        .then(function () {
                            modDelCloseSheet();
                            window.location.reload();
                        });
                } else {
                    toast(j.error || 'Грешка', 'error');
                }
            });
    };
    window.modDelMarkDefective = function () {
        if (!sheetCur) return;
        var fd = new FormData();
        fd.append('delivery_item_id', $('modDelSheetId').value);
        fd.append('reason', 'damaged');
        fetch('/delivery.php?api=add_defective', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok) {
                    modDelCloseSheet();
                    toast('Маркиран като дефектен', 'warn');
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    toast(j.error || 'Грешка', 'error');
                }
            });
    };

    // COMMIT
    window.modDelCommit = function () {
        if (!DELIVERY_ID) return;
        var fd = new FormData();
        fd.append('delivery_id', DELIVERY_ID);
        showLoading(true);
        fetch('/delivery.php?api=commit', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                showLoading(false);
                if (j.ok) {
                    toast('Заприходено · 5 sec undo', 'success');
                    setTimeout(function () {
                        window.location = j.redirect_to || '/deliveries.php';
                    }, 1500);
                } else {
                    toast(j.error || 'Грешка при commit', 'error');
                }
            });
    };
})();
</script>
</body>
</html>
