<?php
/**
 * duplicate-check.php — Global helper за откриване на дублирани записи.
 *
 * Spec: DELIVERY_ORDERS_DECISIONS_FINAL §F (F1-F6)
 *
 * Слой 1 (точно дублиране): supplier + invoice_number → hard block
 * Слой 2 (съмнително): content_signature hash в 24-48ч → soft warning
 * Sales-specific: 3+ артикула + еднакви бройки/цени в 5 мин → soft warning
 *
 * Hash: sha256(supplier_id + sorted(items+qtys+prices))
 */

require_once __DIR__ . '/../config/database.php';

const DUP_LEVEL_NONE       = 'none';
const DUP_LEVEL_HARD       = 'hard';   // точно дублиране — блокира
const DUP_LEVEL_SOFT       = 'soft';   // съмнително — предупреждение

/**
 * Главна функция — checkDuplicate(type, tenant, payload).
 *
 * @param string $type — 'delivery' | 'sale' | 'transfer' | 'payment'
 * @param int    $tenant_id
 * @param array  $payload — type-specific (виж по-долу всеки case)
 *
 * @return array{
 *   is_duplicate: bool,
 *   level: string,            // 'none'|'hard'|'soft'
 *   existing_record_id: ?int,
 *   message: string,
 *   meta: array
 * }
 */
function checkDuplicate(string $type, int $tenant_id, array $payload): array {
    switch ($type) {
        case 'delivery':
            return checkDeliveryDuplicate($tenant_id, $payload);
        case 'sale':
            return checkSaleDuplicate($tenant_id, $payload);
        case 'transfer':
            return checkTransferDuplicate($tenant_id, $payload);
        case 'payment':
            return checkPaymentDuplicate($tenant_id, $payload);
        default:
            return dupResult(false, DUP_LEVEL_NONE, null, 'unknown type', []);
    }
}

/**
 * Compute content_signature hash.
 * Stable: sort items by product_id (or name) ASC, concat product+qty+price, hash.
 */
function computeContentSignature(?int $supplier_id, array $items): string {
    $rows = [];
    foreach ($items as $it) {
        $pid   = (string)($it['product_id'] ?? $it['name'] ?? '');
        $qty   = number_format((float)($it['quantity'] ?? 0), 4, '.', '');
        $price = number_format((float)($it['unit_price'] ?? $it['cost_price'] ?? $it['price'] ?? 0), 4, '.', '');
        $rows[] = $pid . '|' . $qty . '|' . $price;
    }
    sort($rows, SORT_STRING);
    $base = ($supplier_id ?? 0) . ';' . implode(';', $rows);
    return hash('sha256', $base);
}

// ─────────────────────────────────────────────────────────────────────
// DELIVERY
// payload: ['supplier_id'=>?, 'invoice_number'=>?, 'items'=>[{product_id,quantity,cost_price}], 'exclude_id'=>?]
// ─────────────────────────────────────────────────────────────────────
function checkDeliveryDuplicate(int $tenant_id, array $payload): array {
    $supplier_id    = isset($payload['supplier_id']) ? (int)$payload['supplier_id'] : null;
    $invoice_number = trim((string)($payload['invoice_number'] ?? ''));
    $items          = (array)($payload['items'] ?? []);
    $exclude_id     = isset($payload['exclude_id']) ? (int)$payload['exclude_id'] : null;

    // Слой 1 — supplier + invoice_number → HARD
    if ($supplier_id && $invoice_number !== '') {
        $sql = "
            SELECT id, total, currency_code, created_at
            FROM deliveries
            WHERE tenant_id = ?
              AND supplier_id = ?
              AND invoice_number = ?
              AND status NOT IN ('voided','superseded')
              " . ($exclude_id ? "AND id <> ?" : "") . "
            ORDER BY created_at DESC
            LIMIT 1
        ";
        $params = [$tenant_id, $supplier_id, $invoice_number];
        if ($exclude_id) $params[] = $exclude_id;

        try {
            $row = DB::run($sql, $params)->fetch();
        } catch (Throwable $e) {
            error_log('duplicate-check delivery L1: ' . $e->getMessage());
            $row = false;
        }

        if ($row) {
            return dupResult(
                true,
                DUP_LEVEL_HARD,
                (int)$row['id'],
                'Тази фактура вече е заприходена (фактура №' . $invoice_number . ', ' . substr($row['created_at'], 0, 10) . ').',
                ['existing' => $row, 'reason' => 'supplier+invoice_number_exact']
            );
        }
    }

    // Слой 2 — content_signature в последните 48ч → SOFT
    if (!empty($items)) {
        $signature = computeContentSignature($supplier_id, $items);
        $sql = "
            SELECT id, supplier_id, invoice_number, total, created_at
            FROM deliveries
            WHERE tenant_id = ?
              AND content_signature = ?
              AND status NOT IN ('voided','superseded')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
              " . ($exclude_id ? "AND id <> ?" : "") . "
            ORDER BY created_at DESC
            LIMIT 1
        ";
        $params = [$tenant_id, $signature];
        if ($exclude_id) $params[] = $exclude_id;

        try {
            $row = DB::run($sql, $params)->fetch();
        } catch (Throwable $e) {
            error_log('duplicate-check delivery L2: ' . $e->getMessage());
            $row = false;
        }

        if ($row) {
            return dupResult(
                true,
                DUP_LEVEL_SOFT,
                (int)$row['id'],
                'Тази доставка изглежда вече е въведена ' . humanRelTime((string)$row['created_at']) . '. Потвърди само ако е друга.',
                ['existing' => $row, 'reason' => 'content_signature_match', 'signature' => $signature]
            );
        }
    }

    return dupResult(false, DUP_LEVEL_NONE, null, '', []);
}

// ─────────────────────────────────────────────────────────────────────
// SALE
// payload: ['store_id'=>?, 'items'=>[{product_id,quantity,unit_price}], 'total'=>?]
// Правило F4: 1-2 артикула — НЕ предупреждаваме. 3+ — soft warning ако в 5 мин има същото.
// ─────────────────────────────────────────────────────────────────────
function checkSaleDuplicate(int $tenant_id, array $payload): array {
    $items    = (array)($payload['items'] ?? []);
    $store_id = isset($payload['store_id']) ? (int)$payload['store_id'] : null;

    if (count($items) < 3) {
        return dupResult(false, DUP_LEVEL_NONE, null, '', ['reason' => 'fewer_than_3_items']);
    }

    $signature = computeContentSignature($store_id, $items);

    // Прескачаме: има ли продажба със същия signature в последните 5 мин?
    $sql = "
        SELECT s.id, s.total, s.created_at
        FROM sales s
        WHERE s.tenant_id = ?
          AND s.status = 'completed'
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY s.created_at DESC
        LIMIT 20
    ";
    try {
        $candidates = DB::run($sql, [$tenant_id])->fetchAll();
    } catch (Throwable $e) {
        error_log('duplicate-check sale: ' . $e->getMessage());
        return dupResult(false, DUP_LEVEL_NONE, null, '', []);
    }

    foreach ($candidates as $sale) {
        $sale_id = (int)$sale['id'];
        try {
            $rows = DB::run("
                SELECT product_id, quantity, unit_price
                FROM sale_items WHERE sale_id = ?
            ", [$sale_id])->fetchAll();
        } catch (Throwable $e) {
            continue;
        }
        if (count($rows) < 3) continue;

        $sig = computeContentSignature($store_id, array_map(function ($r) {
            return [
                'product_id' => $r['product_id'],
                'quantity'   => $r['quantity'],
                'unit_price' => $r['unit_price'],
            ];
        }, $rows));

        if ($sig === $signature) {
            return dupResult(
                true,
                DUP_LEVEL_SOFT,
                $sale_id,
                'Същата продажба беше регистрирана преди ' . humanRelTime((string)$sale['created_at']) . '. Сигурен ли си, че не е дубликат?',
                ['existing' => $sale, 'reason' => 'sale_signature_match_5min', 'signature' => $signature]
            );
        }
    }

    return dupResult(false, DUP_LEVEL_NONE, null, '', []);
}

// ─────────────────────────────────────────────────────────────────────
// TRANSFER
// payload: ['from_store_id'=>?, 'to_store_id'=>?, 'items'=>[...]]
// Правило: same from→to + same items в 10 мин → soft warning
// ─────────────────────────────────────────────────────────────────────
function checkTransferDuplicate(int $tenant_id, array $payload): array {
    $from_id = isset($payload['from_store_id']) ? (int)$payload['from_store_id'] : null;
    $to_id   = isset($payload['to_store_id'])   ? (int)$payload['to_store_id']   : null;
    $items   = (array)($payload['items'] ?? []);

    if (!$from_id || !$to_id || empty($items)) {
        return dupResult(false, DUP_LEVEL_NONE, null, '', []);
    }

    $signature = computeContentSignature($from_id * 1000 + $to_id, $items);

    try {
        $candidates = DB::run("
            SELECT id, created_at
            FROM transfers
            WHERE tenant_id = ?
              AND from_store_id = ?
              AND to_store_id = ?
              AND status <> 'canceled'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ORDER BY created_at DESC
            LIMIT 10
        ", [$tenant_id, $from_id, $to_id])->fetchAll();
    } catch (Throwable $e) {
        error_log('duplicate-check transfer: ' . $e->getMessage());
        return dupResult(false, DUP_LEVEL_NONE, null, '', []);
    }

    foreach ($candidates as $t) {
        try {
            $rows = DB::run("SELECT product_id, quantity FROM transfer_items WHERE transfer_id = ?", [$t['id']])->fetchAll();
        } catch (Throwable $e) {
            continue;
        }
        $sig = computeContentSignature($from_id * 1000 + $to_id, array_map(function ($r) {
            return ['product_id' => $r['product_id'], 'quantity' => $r['quantity'], 'unit_price' => 0];
        }, $rows));

        if ($sig === $signature) {
            return dupResult(
                true,
                DUP_LEVEL_SOFT,
                (int)$t['id'],
                'Такъв трансфер беше регистриран преди ' . humanRelTime((string)$t['created_at']) . '. Дубликат?',
                ['existing' => $t, 'signature' => $signature]
            );
        }
    }

    return dupResult(false, DUP_LEVEL_NONE, null, '', []);
}

// ─────────────────────────────────────────────────────────────────────
// PAYMENT (бъдеща таблица supplier_payments — checking by amount+supplier+date)
// payload: ['supplier_id'=>?, 'amount'=>?, 'paid_at'=>'YYYY-MM-DD']
// ─────────────────────────────────────────────────────────────────────
function checkPaymentDuplicate(int $tenant_id, array $payload): array {
    $supplier_id = isset($payload['supplier_id']) ? (int)$payload['supplier_id'] : null;
    $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;
    $paid_at = (string)($payload['paid_at'] ?? date('Y-m-d'));

    if (!$supplier_id || $amount <= 0) {
        return dupResult(false, DUP_LEVEL_NONE, null, '', []);
    }

    try {
        $row = DB::run("
            SELECT id, amount, paid_at
            FROM supplier_payments
            WHERE tenant_id = ?
              AND supplier_id = ?
              AND ABS(amount - ?) < 0.01
              AND paid_at = ?
            ORDER BY id DESC
            LIMIT 1
        ", [$tenant_id, $supplier_id, $amount, $paid_at])->fetch();
    } catch (Throwable $e) {
        // Таблицата може все още да не съществува за някои tenants
        return dupResult(false, DUP_LEVEL_NONE, null, '', []);
    }

    if ($row) {
        return dupResult(
            true,
            DUP_LEVEL_SOFT,
            (int)$row['id'],
            'Имаш плащане за същата сума на същия доставчик днес. Сигурен ли си, че е друго?',
            ['existing' => $row]
        );
    }

    return dupResult(false, DUP_LEVEL_NONE, null, '', []);
}

// ─────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────
function dupResult(bool $is_dup, string $level, ?int $existing_id, string $message, array $meta): array {
    return [
        'is_duplicate'       => $is_dup,
        'level'              => $level,
        'existing_record_id' => $existing_id,
        'message'            => $message,
        'meta'               => $meta,
    ];
}

function humanRelTime(string $iso): string {
    try {
        $then = new DateTime($iso);
        $diff = (new DateTime())->getTimestamp() - $then->getTimestamp();
        if ($diff < 60)    return 'преди по-малко от минута';
        if ($diff < 3600)  return 'преди ' . intval($diff / 60) . ' мин';
        if ($diff < 86400) return 'преди ' . intval($diff / 3600) . ' часа';
        return 'преди ' . intval($diff / 86400) . ' дни';
    } catch (Throwable $e) {
        return $iso;
    }
}
