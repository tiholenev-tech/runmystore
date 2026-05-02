<?php
/**
 * copy-product-template.php — S93.WIZARD.V4
 *
 * Spec: PRODUCTS_WIZARD_v4_SPEC §4 + SIMPLE_MODE_BIBLE.md §7.2.8 (v1.3).
 *
 * Връща 10-полен snapshot от съществуващ product за "Копирай предния" / "Търси".
 * Snapshot, не reference — caller използва data-та като initial state на wizard,
 * не държи FK към source.
 *
 * Tenant-isolated (никога не извлича от чужд tenant).
 */

require_once __DIR__ . '/../config/database.php';

const COPY_FIELDS_INCLUDED = [
    'supplier_id',
    'category_id',
    'composition',
    'origin_country',
    'cost_price',
    'retail_price',
    'wholesale_price',
    'has_variations',
    'variations',
    'photo_url',
];

const COPY_FIELDS_EXCLUDED = [
    'name',
    'code',
    'barcode',
    'quantities',
];

/**
 * Връща 10-field copy template + null fields за name/code/barcode/quantities.
 *
 * @return array{
 *   ok:bool,
 *   source_id:int,
 *   data:array<string,mixed>,
 *   excluded:array<string>,
 *   confidence_penalty:int,
 *   error:?string
 * }
 */
function copyProductTemplate(int $source_product_id, int $tenant_id): array {
    if ($source_product_id <= 0 || $tenant_id <= 0) {
        return _ctEmpty($source_product_id, 'invalid args');
    }

    try {
        $row = DB::run(
            "SELECT id, tenant_id, supplier_id, category_id,
                    composition, origin_country,
                    cost_price, retail_price, wholesale_price,
                    photo_url, parent_id
             FROM products
             WHERE id = ? AND tenant_id = ? AND is_active = 1
             LIMIT 1",
            [$source_product_id, $tenant_id]
        )->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('copyProductTemplate fetch: ' . $e->getMessage());
        return _ctEmpty($source_product_id, 'db error');
    }

    if (!$row) {
        return _ctEmpty($source_product_id, 'not found or wrong tenant');
    }

    $parent_id = $row['parent_id'] ? (int)$row['parent_id'] : (int)$row['id'];

    $variations = ['has' => false, 'colors' => [], 'sizes' => []];
    try {
        $children = DB::run(
            "SELECT DISTINCT size, color
             FROM products
             WHERE parent_id = ? AND tenant_id = ? AND is_active = 1",
            [$parent_id, $tenant_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($children as $c) {
            if (!empty($c['size']))  $variations['sizes'][]  = $c['size'];
            if (!empty($c['color'])) $variations['colors'][] = $c['color'];
        }
        $variations['sizes']  = array_values(array_unique($variations['sizes']));
        $variations['colors'] = array_values(array_unique($variations['colors']));
        $variations['has']    = (count($variations['sizes']) > 0 || count($variations['colors']) > 0);
    } catch (Throwable $e) {
        error_log('copyProductTemplate variations: ' . $e->getMessage());
    }

    $photo_url = $row['photo_url'] ?? null;
    $confidence_penalty = $photo_url ? 10 : 0;

    return [
        'ok'        => true,
        'source_id' => (int)$row['id'],
        'data'      => [
            'supplier_id'      => $row['supplier_id'] !== null ? (int)$row['supplier_id'] : null,
            'category_id'      => $row['category_id'] !== null ? (int)$row['category_id'] : null,
            'composition'      => $row['composition'],
            'origin_country'   => $row['origin_country'],
            'cost_price'       => $row['cost_price'] !== null ? (float)$row['cost_price'] : null,
            'retail_price'     => $row['retail_price'] !== null ? (float)$row['retail_price'] : null,
            'wholesale_price'  => $row['wholesale_price'] !== null ? (float)$row['wholesale_price'] : null,
            'has_variations'   => $variations['has'],
            'variations'       => ['colors' => $variations['colors'], 'sizes' => $variations['sizes']],
            'photo_url'        => $photo_url,
            'name'             => null,
            'code'             => null,
            'barcode'          => null,
            'quantities'       => [],
        ],
        'excluded'           => COPY_FIELDS_EXCLUDED,
        'confidence_penalty' => $confidence_penalty,
        'error'              => null,
    ];
}

function _ctEmpty(int $sid, string $err): array {
    return [
        'ok'                 => false,
        'source_id'          => $sid,
        'data'               => [],
        'excluded'           => COPY_FIELDS_EXCLUDED,
        'confidence_penalty' => 0,
        'error'              => $err,
    ];
}

/**
 * Връща recent N parent products за "stрелка ⌄" dropdown.
 * Без quantities/inventory — само meta за display.
 */
function recentProductsForTemplate(int $tenant_id, int $limit = 10): array {
    if ($tenant_id <= 0) return [];
    try {
        $rows = DB::run(
            "SELECT p.id, p.name, p.retail_price, p.photo_url,
                    s.name AS supplier_name, c.name AS category_name,
                    p.updated_at
             FROM products p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.tenant_id = ? AND p.parent_id IS NULL AND p.is_active = 1
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT ?",
            [$tenant_id, max(1, min(50, $limit))]
        )->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('recentProductsForTemplate: ' . $e->getMessage());
        return [];
    }
}
