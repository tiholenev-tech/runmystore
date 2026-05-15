<?php
/**
 * RunMyStore.ai — Phase 0 Helpers
 * S52 | 12.04.2026
 * 
 * Включвай с: require_once __DIR__ . '/helpers.php';
 * (от config/ директорията)
 * Или: require_once __DIR__ . '/config/helpers.php';
 * (от root директорията)
 */

// ══════════════════════════════════════
// 1. PLAN HELPERS
// ══════════════════════════════════════

/**
 * Връща ефективния план на tenant-а.
 * Ако trial_ends_at не е изтекъл → plan_effective (обикновено 'pro').
 * Иначе → plan (реалния платен план).
 */
function effectivePlan(array $tenant): string {
    if (!empty($tenant['trial_ends_at']) && $tenant['trial_ends_at'] >= date('Y-m-d H:i:s')) {
        return $tenant['plan_effective'] ?? 'pro';
    }
    return $tenant['plan'] ?? 'free';
}

/**
 * Проверява дали план X >= план Y.
 * free < start < pro
 */
function planAtLeast(string $current, string $required): bool {
    $order = ['free' => 0, 'start' => 1, 'pro' => 2];
    return ($order[$current] ?? 0) >= ($order[$required] ?? 0);
}

/**
 * Проверява дали потребител с дадена роля има достъп.
 * role_gate = 'owner' / 'owner,manager' / 'all'
 */
function roleAllowed(string $userRole, string $roleGate): bool {
    if ($roleGate === 'all') return true;
    $allowed = array_map('trim', explode(',', $roleGate));
    return in_array($userRole, $allowed);
}

/**
 * Проверява дали даден insight е видим за потребителя.
 * Комбинира plan gate + role gate.
 */
function insightVisible(array $insight, string $effectivePlan, string $userRole): bool {
    if (!planAtLeast($effectivePlan, $insight['plan_gate'])) return false;
    if (!roleAllowed($userRole, $insight['role_gate'])) return false;
    return true;
}

// ══════════════════════════════════════
// 2. INSIGHT COOLDOWN
// ══════════════════════════════════════

/**
 * Cooldown по тип (от S51):
 * - 48ч за PRO (конкретен topic)
 * - 7д за повтарящ се тип (категория, макс 5 показвания)
 * - 30д ако "dismissed" 3+ пъти
 */
function shouldShowInsight(int $tenantId, int $userId, string $topicId, string $category): bool {
    // Проверка: dismissed 3+ пъти за тази категория → 30 дни cooldown
    $dismissCount = DB::run(
        "SELECT COUNT(*) FROM ai_shown 
         WHERE tenant_id=? AND user_id=? AND category=? AND action='dismissed' 
         AND shown_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        [$tenantId, $userId, $category]
    )->fetchColumn();
    
    if ($dismissCount >= 3) {
        $lastDismiss = DB::run(
            "SELECT shown_at FROM ai_shown 
             WHERE tenant_id=? AND user_id=? AND category=? AND action='dismissed' 
             ORDER BY shown_at DESC LIMIT 1",
            [$tenantId, $userId, $category]
        )->fetchColumn();
        if ($lastDismiss && (time() - strtotime($lastDismiss)) < 30 * 86400) {
            return false;
        }
    }
    
    // Проверка: конкретен topic — 48ч cooldown
    $lastShown = DB::run(
        "SELECT shown_at FROM ai_shown 
         WHERE tenant_id=? AND user_id=? AND topic_id=? 
         ORDER BY shown_at DESC LIMIT 1",
        [$tenantId, $userId, $topicId]
    )->fetchColumn();
    
    if ($lastShown && (time() - strtotime($lastShown)) < 48 * 3600) {
        return false;
    }
    
    // Проверка: категория — макс 5 показвания за 7 дни
    $categoryCount = DB::run(
        "SELECT COUNT(*) FROM ai_shown 
         WHERE tenant_id=? AND user_id=? AND category=? 
         AND shown_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        [$tenantId, $userId, $category]
    )->fetchColumn();
    
    if ($categoryCount >= 5) {
        return false;
    }
    
    return true;
}

/**
 * Записва че insight е бил показан.
 */
function markInsightShown(int $tenantId, int $userId, int $storeId, string $topicId, string $category, ?int $productId = null): void {
    DB::run(
        "INSERT INTO ai_shown (tenant_id, user_id, store_id, topic_id, category, product_id) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$tenantId, $userId, $storeId, $topicId, $category, $productId]
    );
}

/**
 * Записва че GHOST pill е бил показан.
 * Ползва prefix 'ghost:' за да се различава от реални insights при cooldown.
 */
function markGhostShown(int $tenantId, int $userId, int $storeId, string $topicId, string $category): void {
    DB::run(
        "INSERT INTO ai_shown (tenant_id, user_id, store_id, topic_id, category) 
         VALUES (?, ?, ?, ?, ?)",
        [$tenantId, $userId, $storeId, 'ghost:' . $topicId, $category]
    );
}

/**
 * Обновява действието на потребителя (tapped/dismissed/snoozed).
 */
function updateInsightAction(int $shownId, string $action): void {
    DB::run(
        "UPDATE ai_shown SET action=?, action_at=NOW() WHERE id=?",
        [$action, $shownId]
    );
}

// ══════════════════════════════════════
// 3. INSIGHT FETCHING
// ══════════════════════════════════════

/**
 * Взима активни insights за модул, филтрирани по plan + role + cooldown.
 * Urgency gating: макс 2 critical, 3 warning, 3 info. 1/4 = тишина.
 * 
 * @return array Масив от insights, готови за показване
 */
function getInsightsForModule(int $tenantId, int $storeId, int $userId, string $module, string $effectivePlan, string $userRole): array {
    // S58: 1/4 silence rule REMOVED — Пешо иска сигнали винаги
    // S144: store_id=0 (Всички магазини) → не филтрирай по store_id (вземи от всички)
    
    if ($storeId > 0) {
        $all = DB::run(
            "SELECT * FROM ai_insights 
             WHERE tenant_id=? AND store_id=? AND module=? 
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY FIELD(urgency,'critical','warning','info','passive'), value_numeric DESC",
            [$tenantId, $storeId, $module]
        )->fetchAll();
    } else {
        // Всички магазини — без store_id филтър
        $all = DB::run(
            "SELECT * FROM ai_insights 
             WHERE tenant_id=? AND module=? 
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY FIELD(urgency,'critical','warning','info','passive'), value_numeric DESC",
            [$tenantId, $module]
        )->fetchAll();
    }
    
    $result = [];
    $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
    $limits = ['critical' => 2, 'warning' => 3, 'info' => 3];
    
    foreach ($all as $row) {
        // passive никога на екрана
        if ($row['urgency'] === 'passive') continue;
        
        // Plan + role gate
        if (!insightVisible($row, $effectivePlan, $userRole)) continue;
        
        // Cooldown
        if (!shouldShowInsight($tenantId, $userId, $row['topic_id'], $row['category'])) continue;
        
        // Urgency лимит
        $u = $row['urgency'];
        if ($counts[$u] >= $limits[$u]) continue;
        
        // Ако има critical/warning → не показвай info
        if ($u === 'info' && ($counts['critical'] > 0 || $counts['warning'] > 0)) continue;
        
        $counts[$u]++;
        $result[] = $row;
    }
    
    return $result;
}

/**
 * Взима ghost pills за START потребители (1/ден макс).
 * Или за FREE (1/седмица макс).
 */
function getGhostPills(int $tenantId, int $storeId, int $userId, string $plan): array {
    // FREE = 1 на седмица, START = 1 на ден
    $cooldownHours = ($plan === 'free') ? 168 : 24;
    $cutoff = date('Y-m-d H:i:s', time() - $cooldownHours * 3600);
    
    // Проверка: имало ли е ghost pill скоро
    $recent = DB::run(
        "SELECT COUNT(*) FROM ai_shown 
         WHERE tenant_id=? AND user_id=? AND topic_id LIKE 'ghost:%' 
         AND shown_at >= ?",
        [$tenantId, $userId, $cutoff]
    )->fetchColumn();
    
    if ($recent > 0) return [];
    
    // Взимаме 1 PRO insight за ghost pill
    $insight = DB::run(
        "SELECT * FROM ai_insights 
         WHERE tenant_id=? AND store_id=? AND plan_gate='pro' AND urgency IN ('critical','warning')
         AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY FIELD(urgency,'critical','warning'), value_numeric DESC 
         LIMIT 1",
        [$tenantId, $storeId]
    )->fetch();
    
    if (!$insight) return [];
    
    return [$insight];
}

// ══════════════════════════════════════
// 4. STORE HEALTH
// ══════════════════════════════════════

/**
 * Изчислява Store Health %.
 * 
 * 3 компонента:
 * - Stock accuracy (40%): % потвърдени артикули в последните 30 дни
 * - Data freshness (30%): обратно на avg дни от последен zone walk
 * - AI confidence (30%): средна confidence_score на всички продукти
 * 
 * @return int 0-100
 */
function storeHealth(int $tenantId, int $storeId): int {
    try {
        $totalProducts = DB::run(
            "SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1",
            [$tenantId]
        )->fetchColumn();
        
        if ($totalProducts == 0) return 0;
        
        // 1. Stock accuracy: % артикули проверени в последните 30 дни
        $accuracy = 0;
        try {
            $checkedRecently = DB::run(
                "SELECT COUNT(DISTINCT product_id) 
                 FROM inventory_checks 
                 WHERE store_id=? AND checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$storeId]
            )->fetchColumn();
            $accuracy = min(100, ($checkedRecently / $totalProducts) * 100);
        } catch (PDOException $e) {
            $accuracy = 0;
        }
        
        // 2. Data freshness: последен zone walk
        $freshness = 0;
        try {
            $lastWalk = DB::run(
                "SELECT MAX(walked_at) FROM zone_walks WHERE store_id=?",
                [$storeId]
            )->fetchColumn();
            if ($lastWalk) {
                $daysSinceWalk = (time() - strtotime($lastWalk)) / 86400;
                $freshness = max(0, 100 - ($daysSinceWalk * 100 / 30));
            }
        } catch (PDOException $e) {
            $freshness = 0;
        }
        
        // 3. AI confidence: средна стойност на confidence_score (0-100)
        $confidence = 0;
        try {
            $avgConf = DB::run(
                "SELECT AVG(COALESCE(confidence_score, 0)) FROM products WHERE tenant_id=? AND is_active=1",
                [$tenantId]
            )->fetchColumn();
            $confidence = $avgConf ?: 0;
        } catch (PDOException $e) {
            $confidence = 0;
        }
        
        // Weighted sum
        $health = round(($accuracy * 0.4) + ($freshness * 0.3) + ($confidence * 0.3));
        return max(0, min(100, (int)$health));
        
    } catch (PDOException $e) {
        return 0;
    }
}

// ══════════════════════════════════════
// 5. SEARCH LOG & LOST DEMAND
// ══════════════════════════════════════

/**
 * Записва търсене в search_log.
 * Ако results_count=0 → автоматично записва и в lost_demand.
 */
function logSearch(int $tenantId, int $storeId, int $userId, string $query, int $resultsCount, string $source = 'products'): void {
    $query = trim($query);
    if (empty($query) || mb_strlen($query) < 2) return;
    
    DB::run(
        "INSERT INTO search_log (tenant_id, store_id, user_id, query, results_count, source) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$tenantId, $storeId, $userId, $query, $resultsCount, $source]
    );
    
    // Zero results → lost demand
    if ($resultsCount === 0) {
        recordLostDemand($tenantId, $storeId, $userId, $query, 'search');
    }
}

/**
 * Записва lost demand. Ако вече съществува → увеличава times.
 */
function recordLostDemand(int $tenantId, int $storeId, ?int $userId, string $queryText, string $source = 'search'): void {
    $queryText = trim($queryText);
    if (empty($queryText)) return;
    
    // Проверка за съществуващ запис (същия текст, същия магазин, неresolved)
    $existing = DB::run(
        "SELECT id FROM lost_demand 
         WHERE tenant_id=? AND store_id=? AND query_text=? AND resolved=0 
         LIMIT 1",
        [$tenantId, $storeId, $queryText]
    )->fetch();
    
    if ($existing) {
        DB::run(
            "UPDATE lost_demand SET times=times+1, last_asked_at=NOW() WHERE id=?",
            [$existing['id']]
        );
    } else {
        DB::run(
            "INSERT INTO lost_demand (tenant_id, store_id, user_id, query_text, source) 
             VALUES (?, ?, ?, ?, ?)",
            [$tenantId, $storeId, $userId, $queryText, $source]
        );
    }
}

// ══════════════════════════════════════
// 6. NUMBER FORMATTING
// ══════════════════════════════════════

/**
 * EU форматиране: 1.234,50 €
 */
function fmtMoney(float $amount, string $currency = '€'): string {
    $formatted = number_format($amount, 2, ',', '.');
    // Махни ,00 ако е цяло число
    if (str_ends_with($formatted, ',00')) {
        $formatted = substr($formatted, 0, -3);
    }
    return $formatted . ' ' . $currency;
}

/**
 * Форматиране на количество (без trailing zeros).
 */
function fmtQty(float $qty): string {
    return rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
}

// ═══════════════════════════════════════
// S53: Auto-geolocate store from visitor IP (silent, no user interaction)
// ═══════════════════════════════════════
function autoGeolocateStore(int $storeId): void {
    $check = DB::run("SELECT latitude FROM stores WHERE id = ? AND latitude IS NOT NULL", [$storeId])->fetch();
    if ($check) return; // already has coordinates

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$ip || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.')) return;

    $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,lat,lon", false,
        stream_context_create(['http' => ['timeout' => 3]]));
    if (!$json) return;

    $geo = json_decode($json, true);
    if (($geo['status'] ?? '') !== 'success') return;

    DB::run("UPDATE stores SET latitude = ?, longitude = ?, city = COALESCE(NULLIF(city,''), ?) WHERE id = ?",
        [$geo['lat'], $geo['lon'], $geo['city'], $storeId]);
}


// ══════════════════════════════════════
// 7. AUDIT LOG (S79.DB)
// ══════════════════════════════════════

/**
 * Записва промяна в audit_log.
 * Адаптирано към реалната структура (S78).
 *
 * ОГРАНИЧЕНИЯ (към 22.04.2026):
 *   - action MUST IN ('create','update','delete') — ENUM ограничение
 *   - audit_log няма store_id / source / source_detail / user_agent колони
 *     → DOC_05 §6.2 разширения чакат S79.AUDIT.EXT
 *
 * НИКОГА не хвърля exception — audit fail не трябва да чупи бизнес транзакция.
 * Логва грешки през error_log() и продължава.
 *
 * @param array      $user      Текущ user — нужни tenant_id (задължително) + id (optional)
 * @param string     $action    'create' | 'update' | 'delete'
 * @param string     $table     Име на засегнатата таблица (напр. 'products')
 * @param int        $recordId  ID на засегнатия запис
 * @param array|null $old       Старите стойности (за update/delete)
 * @param array|null $new       Новите стойности (за create/update)
 */
/**
 * S97.HARDEN.PH4 — generic per-session CSRF token for app-wide POST endpoints.
 * The aibrain helper (config/i18n_aibrain.php:48) uses its own session key for the
 * voice flow; this lives under $_SESSION['app_csrf'] so the two are independent.
 *
 * Usage:
 *   $token = csrfToken();                 // mint or reuse
 *   if (!csrfCheck($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) jerr(403, 'csrf');
 */
function csrfToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // No session = no token. Caller is responsible for ensuring session is started.
        return '';
    }
    if (empty($_SESSION['app_csrf'])) {
        $_SESSION['app_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['app_csrf'];
}

function csrfCheck(string $token): bool {
    if ($token === '' || empty($_SESSION['app_csrf'])) return false;
    return hash_equals($_SESSION['app_csrf'], $token);
}

function auditLog(
    array $user,
    string $action,
    string $table,
    int $recordId,
    ?array $old = null,
    ?array $new = null,
    string $source = 'ui',
    ?string $sourceDetail = null
): void {
    static $validActions = ['create', 'update', 'delete', 'cron_run', 'ai_action', 'system_event'];
    static $validSources = ['ui', 'ai', 'api', 'cron', 'system'];

    if (!in_array($action, $validActions, true)) {
        error_log("auditLog: invalid action '$action' — skipped");
        return;
    }
    if (!in_array($source, $validSources, true)) {
        error_log("auditLog: invalid source '$source' — defaulting to 'ui'");
        $source = 'ui';
    }

    $tenantId = $user['tenant_id'] ?? null;
    if (!$tenantId) {
        error_log("auditLog: missing tenant_id — skipped (action=$action, table=$table, id=$recordId)");
        return;
    }

    try {
        DB::run(
            "INSERT INTO audit_log
              (tenant_id, user_id, store_id, table_name, record_id, action, source, source_detail,
               old_values, new_values, ip_address, user_agent, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int) $tenantId,
                isset($user['id']) ? (int) $user['id'] : null,
                isset($user['store_id']) ? (int) $user['store_id'] : null,
                $table,
                $recordId,
                $action,
                $source,
                $sourceDetail,
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null
            ]
        );
    } catch (Throwable $e) {
        error_log("auditLog: INSERT failed: " . $e->getMessage()
            . " (action=$action, table=$table, id=$recordId)");
    }
}

// ══════════════════════════════════════
// 8. PRODUCT COUNT — single source of truth (S101)
// ══════════════════════════════════════

/**
 * Returns int product count under explicit scope. Always tenant-guarded.
 *
 * Scopes:
 *  - 'masters'             — active masters: tenant_id + is_active=1 + parent_id IS NULL
 *  - 'all_active'          — every active product (incl variants): tenant_id + is_active=1
 *  - 'all_with_variants'   — every product (active + inactive): tenant_id only
 *  - 'per_store_masters'   — active masters that have an inventory row in the given store
 *  - 'variants_of'         — active variants for a parent: $filters['parent_id']
 *  - 'search'              — active masters matching $filters['q'] (name/code/barcode)
 *  - 'filtered'            — caller-supplied $filters['where_sql'] + $filters['params'];
 *                            optional $filters['having_sql'] + $filters['days_stale_expr']
 *                            for HAVING-on-derived-column queries.
 */
function getProductCount(int $tenant_id, ?int $store_id, string $scope, array $filters = []): int
{
    if ($tenant_id <= 0) return 0;

    switch ($scope) {
        case 'masters':
            return (int) DB::run(
                "SELECT COUNT(*) FROM products
                 WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL",
                [$tenant_id]
            )->fetchColumn();

        case 'all_active':
            return (int) DB::run(
                "SELECT COUNT(*) FROM products
                 WHERE tenant_id=? AND is_active=1",
                [$tenant_id]
            )->fetchColumn();

        case 'all_with_variants':
            return (int) DB::run(
                "SELECT COUNT(*) FROM products WHERE tenant_id=?",
                [$tenant_id]
            )->fetchColumn();

        case 'per_store_masters':
            if ($store_id === null || $store_id <= 0) return 0;
            return (int) DB::run(
                "SELECT COUNT(*) FROM products p
                 JOIN inventory i ON i.product_id=p.id AND i.store_id=?
                 WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL",
                [$store_id, $tenant_id]
            )->fetchColumn();

        case 'variants_of':
            $parent_id = (int)($filters['parent_id'] ?? 0);
            if ($parent_id <= 0) return 0;
            return (int) DB::run(
                "SELECT COUNT(*) FROM products
                 WHERE tenant_id=? AND parent_id=? AND is_active=1",
                [$tenant_id, $parent_id]
            )->fetchColumn();

        case 'search':
            $q = trim((string)($filters['q'] ?? ''));
            if ($q === '') return 0;
            $like = '%' . $q . '%';
            return (int) DB::run(
                "SELECT COUNT(DISTINCT p.id) FROM products p
                 WHERE p.tenant_id=? AND p.is_active=1 AND p.parent_id IS NULL
                   AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)",
                [$tenant_id, $like, $like, $like]
            )->fetchColumn();

        case 'filtered':
            $where_sql      = (string)($filters['where_sql'] ?? 'p.tenant_id=?');
            $where_params   = (array) ($filters['params']    ?? [$tenant_id]);
            $sid            = $filters['store_id'] ?? $store_id;
            $sid            = $sid === null ? 0 : (int)$sid;
            $having_sql     = $filters['having_sql']       ?? null;
            $days_stale_exp = $filters['days_stale_expr']  ?? null;

            if (strpos($where_sql, 'tenant_id') === false) {
                error_log("getProductCount(filtered): where_sql missing tenant_id guard — refusing.");
                return 0;
            }

            if ($having_sql !== null && $days_stale_exp !== null) {
                $sql = "SELECT COUNT(*) FROM (
                            SELECT p.id, {$days_stale_exp} AS days_stale
                            FROM products p
                            LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=?
                            WHERE {$where_sql} HAVING {$having_sql}
                        ) sub";
            } else {
                $sql = "SELECT COUNT(DISTINCT p.id) FROM products p
                        LEFT JOIN inventory i ON i.product_id=p.id AND i.store_id=?
                        WHERE {$where_sql}";
            }
            return (int) DB::run($sql, array_merge([$sid], $where_params))->fetchColumn();

        default:
            error_log("getProductCount: unknown scope '{$scope}'");
            return 0;
    }
}
