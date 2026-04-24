<?php
/**
 * WooCommerce Integration — RunMyStore
 *
 * Session: S81.WOO_API
 * Status: SKELETON (S90 ще имплементира pushProduct/pullInventoryUpdates/syncCategories)
 *
 * See: docs/WOOCOMMERCE_API_SPEC.md
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/woo.php';

class WooSync {
    private $tenant_id;
    private $store_url;
    private $consumer_key;
    private $consumer_secret;
    private $timeout = 30;

    public function __construct($tenant_id) {
        $this->tenant_id = (int)$tenant_id;

        // NOTE (S90): tenants table трябва да има woo_store_url, woo_consumer_key,
        // woo_consumer_secret колони. В S81 skeleton: ако missing → exception.
        // DB schema промените са документирани в docs/WOOCOMMERCE_API_SPEC.md §6.
        try {
            $creds = DB::run(
                "SELECT woo_store_url, woo_consumer_key, woo_consumer_secret
                 FROM tenants WHERE id=?",
                [$this->tenant_id]
            )->fetch();
        } catch (Exception $e) {
            // Unknown column → schema not yet migrated (expected в S81)
            throw new Exception(
                'WooCommerce schema not yet migrated. Run S90.DB migration first.'
            );
        }

        if (!$creds || empty($creds['woo_store_url'])) {
            throw new Exception('WooCommerce not configured for tenant ' . $this->tenant_id);
        }

        $this->store_url       = rtrim($creds['woo_store_url'], '/');
        $this->consumer_key    = $creds['woo_consumer_key'];
        $this->consumer_secret = $creds['woo_consumer_secret'];
    }

    /**
     * Base API call. Handles Basic Auth, JSON encoding, error mapping.
     * Retry with exponential backoff on 429/5xx — TODO в S90.
     */
    private function apiCall($method, $endpoint, $payload = null) {
        $url  = $this->store_url . '/wp-json/wc/v3/' . ltrim($endpoint, '/');
        $auth = base64_encode($this->consumer_key . ':' . $this->consumer_secret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Woo API connection error: $curl_err");
        }

        $decoded = json_decode($response, true);

        if ($http_code >= 400) {
            $msg = is_array($decoded) && isset($decoded['message'])
                ? $decoded['message']
                : substr($response, 0, 500);
            throw new Exception("Woo API $http_code: $msg");
        }

        return $decoded;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PUBLIC API — SKELETON STUBS (S90 ще имплементира)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Test connection to WC store. Returns system_status (includes WC version).
     * ЕДИНСТВЕНАТА работеща функция в S81 skeleton — за verifикация на credentials.
     */
    public function testConnection() {
        return $this->apiCall('GET', 'system_status');
    }

    /**
     * Push a single RunMyStore product to WooCommerce.
     *
     * TODO (S90):
     *  1. Load product + variations + category + images from DB
     *  2. Build payload via wooProductPayload() (config/woo.php)
     *  3. If products.woo_product_id IS NULL → POST /products (new)
     *     else → PUT /products/{id} (update)
     *  4. For each variation → POST /products/{id}/variations (or PUT if woo_variation_id set)
     *  5. Update products.woo_product_id, products.woo_sync_status='synced', woo_last_sync_at
     *  6. Log to woo_sync_log table
     */
    public function pushProduct($product_id) {
        throw new Exception('pushProduct: not implemented — S90 stub');
    }

    /**
     * Pull inventory updates from WC (when online sale reduces stock).
     * Alternative to webhooks — polling variant.
     *
     * TODO (S90):
     *  1. GET /products?modified_after={$since} (ISO8601)
     *  2. For each product: find by woo_product_id → update inventory.quantity
     *  3. Log changes
     */
    public function pullInventoryUpdates($since = null) {
        throw new Exception('pullInventoryUpdates: not implemented — S90 stub');
    }

    /**
     * Two-way category sync.
     *
     * TODO (S90):
     *  1. Pull WC categories via GET /products/categories
     *  2. Match by name → set categories.woo_category_id
     *  3. For RunMyStore categories without woo_category_id → POST /products/categories
     */
    public function syncCategories() {
        throw new Exception('syncCategories: not implemented — S90 stub');
    }

    /**
     * Batch push (до 100 products наведнъж).
     *
     * TODO (S90):
     *  1. Build batch payload {create: [...], update: [...]}
     *  2. POST /products/batch
     *  3. Parse response, update local DB refs
     */
    public function batchPush(array $product_ids) {
        throw new Exception('batchPush: not implemented — S90 stub');
    }

    /**
     * Handle incoming webhook from WC (product.updated, order.created).
     * Called by /woo-webhook.php endpoint.
     *
     * TODO (S90):
     *  1. Verify HMAC-SHA256 signature from X-WC-Webhook-Signature header
     *  2. Dispatch by topic ($payload['topic'] или URL param)
     *  3. Update local DB accordingly
     */
    public function handleWebhook($topic, $payload, $signature) {
        throw new Exception('handleWebhook: not implemented — S90 stub');
    }
}

// ═══════════════════════════════════════════════════════════════════
// PUBLIC HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════

/**
 * Cron entry-point за periodic sync.
 * Използва се от cron-woo-sync.php (S90).
 *
 * @param int $tenant_id
 * @return array ['status' => 'success'|'error'|'stub', 'message' => string, 'synced' => int]
 */
function wooSyncTenant($tenant_id) {
    try {
        $sync = new WooSync($tenant_id);

        // TODO (S90): 
        //   1. SELECT id FROM products WHERE tenant_id=? AND woo_sync_status IN ('dirty','never')
        //   2. For each: $sync->pushProduct($id)
        //   3. Аггрегирай резултатите

        return [
            'status'  => 'stub',
            'message' => 'S90 ще реализира реалния push loop',
            'synced'  => 0,
        ];
    } catch (Exception $e) {
        error_log("wooSyncTenant failed tenant $tenant_id: " . $e->getMessage());
        return [
            'status'  => 'error',
            'message' => $e->getMessage(),
            'synced'  => 0,
        ];
    }
}

/**
 * Quick credentials test (за settings.php "Test connection" бутон в S90).
 */
function wooTestTenantConnection($tenant_id) {
    try {
        $sync   = new WooSync($tenant_id);
        $status = $sync->testConnection();
        return [
            'ok'      => true,
            'message' => 'Connected to WC ' . ($status['environment']['version'] ?? 'unknown'),
        ];
    } catch (Exception $e) {
        return [
            'ok'      => false,
            'message' => $e->getMessage(),
        ];
    }
}
