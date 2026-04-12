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
    // 1/4 отваряния = тишина (Addictive UX правило)
    if (rand(1, 4) === 1) {
        return [];
    }
    
    $all = DB::run(
        "SELECT * FROM ai_insights 
         WHERE tenant_id=? AND store_id=? AND module=? 
         AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY FIELD(urgency,'critical','warning','info','passive'), value_numeric DESC",
        [$tenantId, $storeId, $module]
    )->fetchAll();
    
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
    
    // Проверка: имало ли е ghost pill скоро
    $recent = DB::run(
        "SELECT COUNT(*) FROM ai_shown 
         WHERE tenant_id=? AND user_id=? AND topic_id LIKE 'ghost:%' 
         AND shown_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$tenantId, $userId, $cooldownHours]
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
 * - AI confidence (30%): средна confidence на всички продукти
 * 
 * @return int 0-100
 */
function storeHealth(int $tenantId, int $storeId): int {
    try {
        $totalProducts = DB::run(
            "SELECT COUNT(*) FROM products WHERE store_id=? AND is_active=1",
            [$storeId]
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
            // inventory_checks може да няма тези колони още
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
        
        // 3. AI confidence: средна стойност
        $confidence = 0;
        try {
            $avgConf = DB::run(
                "SELECT AVG(COALESCE(confidence, 0)) FROM products WHERE store_id=? AND is_active=1",
                [$storeId]
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
