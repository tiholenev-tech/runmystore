<?php
/**
 * WooCommerce Integration — Config, Endpoints, Payload Builders
 *
 * Session: S81.WOO_API
 * See: docs/WOOCOMMERCE_API_SPEC.md
 */

// ═══════════════════════════════════════════════════════════════════
// 1. ENDPOINT DEFINITIONS
// ═══════════════════════════════════════════════════════════════════

$WOO_ENDPOINTS = [
    'index'              => ['GET',    ''],
    'system_status'      => ['GET',    'system_status'],
    'products_list'      => ['GET',    'products'],
    'products_create'    => ['POST',   'products'],
    'products_get'       => ['GET',    'products/{id}'],
    'products_update'    => ['PUT',    'products/{id}'],
    'products_delete'    => ['DELETE', 'products/{id}?force=true'],
    'products_batch'     => ['POST',   'products/batch'],
    'variations_list'    => ['GET',    'products/{parent_id}/variations'],
    'variations_create'  => ['POST',   'products/{parent_id}/variations'],
    'variations_update'  => ['PUT',    'products/{parent_id}/variations/{id}'],
    'variations_delete'  => ['DELETE', 'products/{parent_id}/variations/{id}?force=true'],
    'variations_batch'   => ['POST',   'products/{parent_id}/variations/batch'],
    'categories_list'    => ['GET',    'products/categories'],
    'categories_create'  => ['POST',   'products/categories'],
    'categories_update'  => ['PUT',    'products/categories/{id}'],
    'attributes_list'    => ['GET',    'products/attributes'],
    'attributes_create'  => ['POST',   'products/attributes'],
    'attributes_terms'   => ['GET',    'products/attributes/{id}/terms'],
];

// ═══════════════════════════════════════════════════════════════════
// 2. DEFAULTS / CONSTANTS
// ═══════════════════════════════════════════════════════════════════

// Unified attribute naming across all RunMyStore tenants.
// Преди push, уверявай се че global attribute-ите съществуват в WC.
define('WOO_ATTR_SIZE_NAME',  'Размер');
define('WOO_ATTR_COLOR_NAME', 'Цвят');

// Default stock behavior for pushed products.
define('WOO_DEFAULT_MANAGE_STOCK', true);
define('WOO_DEFAULT_STOCK_STATUS', 'instock');

// Batch size limit (WC hard cap = 100).
define('WOO_BATCH_MAX', 100);

// ═══════════════════════════════════════════════════════════════════
// 3. PAYLOAD BUILDERS
// ═══════════════════════════════════════════════════════════════════

/**
 * Build product JSON payload for POST /products.
 *
 * Cents-to-euro conversion: predpoлагаме products.retail_price е в cents.
 * ПРОВЕРИ със CHAT 1 schema когато CSV export-ът е готов (може да е direct euros).
 *
 * @param int $product_id
 * @param int $tenant_id
 * @return array  JSON-encodable payload
 * @throws Exception if product not found
 */
function wooProductPayload($product_id, $tenant_id) {
    $row = DB::run(
        "SELECT p.id, p.code, p.name, p.description, p.retail_price, p.sale_price,
                p.cost_price, p.barcode, p.image_url, p.is_active, p.category_id,
                p.season, p.created_at,
                COALESCE(SUM(i.quantity), 0) AS total_stock,
                MIN(i.min_quantity) AS min_stock
         FROM products p
         LEFT JOIN inventory i ON i.product_id = p.id
         WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
         GROUP BY p.id",
        [$product_id, $tenant_id]
    )->fetch();

    if (!$row) {
        throw new Exception("Product $product_id not found for tenant $tenant_id");
    }

    // Load variations (if any)
    $variations = DB::run(
        "SELECT id, code, size, color, retail_price, sale_price, image_url
         FROM product_variations
         WHERE product_id = ? AND deleted_at IS NULL",
        [$product_id]
    )->fetchAll();

    $has_variations = !empty($variations);

    // Base payload
    $payload = [
        'name'               => $row['name'],
        'sku'                => $row['code'],
        'type'               => $has_variations ? 'variable' : 'simple',
        'status'             => $row['is_active'] ? 'publish' : 'draft',
        'catalog_visibility' => 'visible',
        'description'        => $row['description'] ?? '',
        'short_description'  => '',
    ];

    // Price (само за simple products — variable-ите получават цени на ниво variation)
    if (!$has_variations) {
        $payload['regular_price'] = wooPrice($row['retail_price']);
        if (!empty($row['sale_price'])) {
            $payload['sale_price'] = wooPrice($row['sale_price']);
        }
    }

    // Stock (само за simple)
    if (!$has_variations) {
        $payload['manage_stock']   = WOO_DEFAULT_MANAGE_STOCK;
        $payload['stock_quantity'] = (int)$row['total_stock'];
        $payload['stock_status']   = ((int)$row['total_stock'] > 0) ? 'instock' : 'outofstock';
        if (!empty($row['min_stock'])) {
            $payload['low_stock_amount'] = (int)$row['min_stock'];
        }
    }

    // Images
    if (!empty($row['image_url'])) {
        $payload['images'] = [
            ['src' => $row['image_url'], 'position' => 0]
        ];
    }

    // Categories
    if (!empty($row['category_id'])) {
        $cat = DB::run(
            "SELECT woo_category_id FROM categories WHERE id = ?",
            [$row['category_id']]
        )->fetch();
        if (!empty($cat['woo_category_id'])) {
            $payload['categories'] = [['id' => (int)$cat['woo_category_id']]];
        }
        // TODO (S90): если woo_category_id IS NULL → create category first (syncCategories)
    }

    // Attributes for variable products
    if ($has_variations) {
        $sizes  = array_unique(array_filter(array_column($variations, 'size')));
        $colors = array_unique(array_filter(array_column($variations, 'color')));
        $attrs  = [];

        if (!empty($sizes)) {
            $attrs[] = [
                'name'      => WOO_ATTR_SIZE_NAME,
                'position'  => 0,
                'visible'   => true,
                'variation' => true,
                'options'   => array_values($sizes),
            ];
        }
        if (!empty($colors)) {
            $attrs[] = [
                'name'      => WOO_ATTR_COLOR_NAME,
                'position'  => 1,
                'visible'   => true,
                'variation' => true,
                'options'   => array_values($colors),
            ];
        }
        if (!empty($attrs)) {
            $payload['attributes'] = $attrs;
        }
    }

    // Custom meta (internal IDs, barcode)
    $meta = [
        ['key' => '_runmystore_product_id', 'value' => (string)$row['id']],
        ['key' => '_runmystore_tenant_id',  'value' => (string)$tenant_id],
    ];
    if (!empty($row['barcode'])) {
        $meta[] = ['key' => '_barcode', 'value' => $row['barcode']];
    }
    $payload['meta_data'] = $meta;

    return $payload;
}

/**
 * Build variation JSON payload for POST /products/{parent_id}/variations.
 *
 * @param array $variation  Row from product_variations
 * @return array
 */
function wooVariationPayload(array $variation) {
    $payload = [
        'regular_price' => wooPrice($variation['retail_price']),
        'sku'           => $variation['code'],
        'manage_stock'  => WOO_DEFAULT_MANAGE_STOCK,
    ];

    if (!empty($variation['sale_price'])) {
        $payload['sale_price'] = wooPrice($variation['sale_price']);
    }

    if (!empty($variation['image_url'])) {
        $payload['image'] = ['src' => $variation['image_url']];
    }

    // TODO (S90): добави stock_quantity от inventory таблица
    // $payload['stock_quantity'] = ...;

    // Attributes (variation ID NOT used here — WC matches by name or ID at variation level)
    $attrs = [];
    if (!empty($variation['size'])) {
        $attrs[] = ['name' => WOO_ATTR_SIZE_NAME, 'option' => $variation['size']];
    }
    if (!empty($variation['color'])) {
        $attrs[] = ['name' => WOO_ATTR_COLOR_NAME, 'option' => $variation['color']];
    }
    if (!empty($attrs)) {
        $payload['attributes'] = $attrs;
    }

    return $payload;
}

/**
 * Build category payload.
 */
function wooCategoryPayload(array $category) {
    $payload = [
        'name' => $category['name'],
    ];
    if (!empty($category['parent_woo_id'])) {
        $payload['parent'] = (int)$category['parent_woo_id'];
    }
    if (!empty($category['description'])) {
        $payload['description'] = $category['description'];
    }
    return $payload;
}

// ═══════════════════════════════════════════════════════════════════
// 4. HELPERS
// ═══════════════════════════════════════════════════════════════════

/**
 * Format price for WC API.
 * WC очаква STRING с точка като decimal separator.
 *
 * Assumption: RunMyStore продуктовите цени са в cents (integer).
 * ПРОВЕРИ със CHAT 1 когато CSV export работи — може да се наложи корекция.
 *
 * @param mixed $cents  Cents (int) or raw euros (string/float)
 * @return string
 */
function wooPrice($cents) {
    if ($cents === null || $cents === '') {
        return '';
    }
    // Heuristic: ако е цяло число > 100 → вероятно cents
    // NOTE (S90): замени с direct schema знание
    if (is_int($cents) || (is_string($cents) && ctype_digit($cents))) {
        return number_format((int)$cents / 100, 2, '.', '');
    }
    return number_format((float)$cents, 2, '.', '');
}

/**
 * Resolve endpoint URL with path params.
 *
 * Usage: wooEndpoint('products_update', ['id' => 42])
 *        → ['PUT', 'products/42']
 */
function wooEndpoint($key, array $params = []) {
    global $WOO_ENDPOINTS;
    if (!isset($WOO_ENDPOINTS[$key])) {
        throw new Exception("Unknown Woo endpoint: $key");
    }
    [$method, $path] = $WOO_ENDPOINTS[$key];
    foreach ($params as $k => $v) {
        $path = str_replace('{' . $k . '}', (string)$v, $path);
    }
    return [$method, $path];
}

/**
 * Convert category path ('Parent > Child > Leaf') to WC format.
 * Used в CSV export (CHAT 1) и REST API (S90).
 */
function wooCategoryPath(array $category_row, $categories_indexed_by_id) {
    $path  = [];
    $cur   = $category_row;
    $guard = 0;
    while ($cur && $guard++ < 10) {
        array_unshift($path, $cur['name']);
        if (empty($cur['parent_id']) || !isset($categories_indexed_by_id[$cur['parent_id']])) {
            break;
        }
        $cur = $categories_indexed_by_id[$cur['parent_id']];
    }
    return implode(' > ', $path);
}
