<?php
/**
 * pricing-engine.php — Auto-pricing learning engine.
 *
 * Spec: AUTO_PRICING_DESIGN_LOGIC.md
 * Decisions: C1-C9, F1-F6 (бестселър protection)
 *
 * Експонира:
 *   suggestRetail(tenant_id, category_id|null, category_name|null, cost, store_id, [product_id])
 *      -> ['retail'=>..., 'confidence'=>..., 'multiplier'=>..., 'ending'=>..., 'reasoning'=>'...']
 *   learnFromCorrection(tenant_id, store_id, category_id, cost, applied_retail, learned_from)
 *   getOnboardingDefault(category_name) -> ['multiplier'=>..., 'ending'=>...]
 *   applyEndingPattern(price, ending) -> price snapped към .90/.99/.50/round_50/exact
 *   auditPriceChange(...)
 *   isBestseller(tenant_id, product_id)
 *   classifyAction(confidence, is_bestseller, variance_pct) -> 'auto'|'confirm'|'manual'
 */

require_once __DIR__ . '/../config/database.php';

// ─────────────────────────────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────────────────────────────
const PE_AUTO_THRESHOLD       = 0.85;
const PE_MANUAL_THRESHOLD     = 0.50;
const PE_BESTSELLER_PER_WEEK  = 5;
const PE_BESTSELLER_WEEKS     = 4;
const PE_VARIANCE_AUTO_HI     = 20.0;   // > 20% → винаги confirm
const PE_VARIANCE_AUTO_LO     = 10.0;   // < 10% → tih insight, no action

// ─────────────────────────────────────────────────────────────────────
// DEFAULTS (cold start)
// ─────────────────────────────────────────────────────────────────────
function getOnboardingDefault(?string $category_name): array {
    $name = mb_strtolower(trim((string)$category_name), 'UTF-8');
    $map = [
        'дрехи'    => ['multiplier' => 2.0, 'ending' => 'point_90'],
        'бельо'    => ['multiplier' => 2.5, 'ending' => 'point_99'],
        'чорапи'   => ['multiplier' => 1.8, 'ending' => 'point_50'],
        'тениски'  => ['multiplier' => 2.5, 'ending' => 'point_90'],
        'якета'    => ['multiplier' => 2.2, 'ending' => 'point_90'],
        'обувки'   => ['multiplier' => 2.3, 'ending' => 'point_90'],
        'бижута'   => ['multiplier' => 3.0, 'ending' => 'exact'],
        'аксесоари'=> ['multiplier' => 2.5, 'ending' => 'point_90'],
        'храна'    => ['multiplier' => 1.4, 'ending' => 'point_50'],
        'козметика'=> ['multiplier' => 2.0, 'ending' => 'point_90'],
        'играчки'  => ['multiplier' => 2.2, 'ending' => 'point_90'],
        'книги'    => ['multiplier' => 1.6, 'ending' => 'exact'],
    ];
    foreach ($map as $kw => $def) {
        if ($name !== '' && mb_strpos($name, $kw) !== false) return $def;
    }
    return ['multiplier' => 2.0, 'ending' => 'point_90'];
}

// ─────────────────────────────────────────────────────────────────────
// ENDING PATTERN
// ─────────────────────────────────────────────────────────────────────
function applyEndingPattern(float $price, string $ending = 'point_90'): float {
    if ($price <= 0) return 0.0;

    switch ($ending) {
        case 'point_99':
            return floor($price) + 0.99;

        case 'point_90':
            // 8.79 → 8.90, 8.30 → 8.90 (винаги към .90 или нагоре)
            $base = floor($price);
            $rounded = $base + 0.90;
            return ($price > $rounded) ? ($base + 1 + 0.90) : $rounded;

        case 'point_50':
            // 8.30 → 8.50, 8.70 → 8.50 на най-близкото
            $base = floor($price);
            $candidates = [$base + 0.50, $base + 1.00];
            $best = $candidates[0];
            $best_diff = abs($price - $best);
            foreach ($candidates as $c) {
                $d = abs($price - $c);
                if ($d < $best_diff) { $best = $c; $best_diff = $d; }
            }
            return $best;

        case 'round_50':
            // Round до най-близкото .50 ИЛИ цяло число
            return round($price * 2) / 2;

        case 'exact':
        default:
            return round($price, 2);
    }
}

// ─────────────────────────────────────────────────────────────────────
// PATTERN LOOKUP (live wins schema: pricing_patterns)
// ─────────────────────────────────────────────────────────────────────
function getPattern(int $tenant_id, ?int $store_id, ?int $category_id, ?string $category_name): ?array {
    try {
        // Tier 1 — exact category_id match
        if ($category_id) {
            $row = DB::run("
                SELECT * FROM pricing_patterns
                WHERE tenant_id = ?
                  AND category_id = ?
                  " . ($store_id ? "AND (store_id = ? OR store_id IS NULL)" : "") . "
                ORDER BY (store_id = ?) DESC, last_updated_at DESC
                LIMIT 1
            ", $store_id
                ? [$tenant_id, $category_id, $store_id, $store_id]
                : [$tenant_id, $category_id])->fetch();
            if ($row) return $row;
        }

        // Tier 2 — category_name match (fallback)
        if ($category_name) {
            $row = DB::run("
                SELECT * FROM pricing_patterns
                WHERE tenant_id = ?
                  AND category_name = ?
                ORDER BY last_updated_at DESC
                LIMIT 1
            ", [$tenant_id, $category_name])->fetch();
            if ($row) return $row;
        }
    } catch (Throwable $e) {
        error_log('pricing-engine getPattern: ' . $e->getMessage());
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────
// MAIN — suggestRetail
// ─────────────────────────────────────────────────────────────────────
function suggestRetail(
    int $tenant_id,
    ?int $category_id,
    ?string $category_name,
    float $cost,
    ?int $store_id = null,
    ?int $product_id = null
): array {
    if ($cost <= 0) {
        return [
            'retail' => 0.0,
            'confidence' => 0.0,
            'multiplier' => 0.0,
            'ending' => 'point_90',
            'reasoning' => 'cost <= 0',
            'source' => 'invalid',
            'is_bestseller' => false,
        ];
    }

    $is_bestseller = $product_id ? isBestseller($tenant_id, $product_id) : false;

    $pattern = getPattern($tenant_id, $store_id, $category_id, $category_name);

    if ($pattern) {
        $mult = (float)$pattern['multiplier'];
        $ending = (string)($pattern['ending_pattern'] ?? 'point_90');
        $conf = (float)$pattern['confidence'];
        $sample = (int)$pattern['sample_count'];
        $source = 'learned_pattern';
        $reasoning = "×{$mult} от " . $sample . " наблюдения за категорията";
    } else {
        $def = getOnboardingDefault($category_name);
        $mult = $def['multiplier'];
        $ending = $def['ending'];
        $conf = 0.50;
        $source = 'onboarding_default';
        $reasoning = "×{$mult} (нова категория, baseline)";
    }

    $raw_retail = $cost * $mult;
    $retail = applyEndingPattern($raw_retail, $ending);

    return [
        'retail'        => round($retail, 2),
        'confidence'    => round($conf, 3),
        'multiplier'    => round($mult, 3),
        'ending'        => $ending,
        'reasoning'     => $reasoning,
        'source'        => $source,
        'is_bestseller' => $is_bestseller,
    ];
}

// ─────────────────────────────────────────────────────────────────────
// LEARN — на ръчна корекция учим pattern-а
// ─────────────────────────────────────────────────────────────────────
function learnFromCorrection(
    int $tenant_id,
    ?int $store_id,
    ?int $category_id,
    ?string $category_name,
    float $cost,
    float $applied_retail,
    string $learned_from = 'manual_corrections'
): bool {
    if ($cost <= 0 || $applied_retail <= 0) return false;

    $observed_mult = $applied_retail / $cost;
    $observed_ending = inferEndingFromPrice($applied_retail);

    try {
        $existing = null;
        if ($category_id) {
            $existing = DB::run("
                SELECT * FROM pricing_patterns
                WHERE tenant_id = ? AND category_id = ?
                  " . ($store_id ? "AND (store_id = ? OR store_id IS NULL)" : "") . "
                ORDER BY (store_id = ?) DESC, last_updated_at DESC
                LIMIT 1
            ", $store_id
                ? [$tenant_id, $category_id, $store_id, $store_id]
                : [$tenant_id, $category_id])->fetch();
        } elseif ($category_name) {
            $existing = DB::run("
                SELECT * FROM pricing_patterns
                WHERE tenant_id = ? AND category_name = ?
                LIMIT 1
            ", [$tenant_id, $category_name])->fetch();
        }

        if ($existing) {
            $old_n = max(1, (int)$existing['sample_count']);
            $old_mult = (float)$existing['multiplier'];
            $new_mult = ($old_mult * $old_n + $observed_mult) / ($old_n + 1);
            $new_n = $old_n + 1;
            $new_conf = computeConfidence($new_n, $old_mult, $observed_mult);

            DB::run("
                UPDATE pricing_patterns
                SET multiplier = ?,
                    ending_pattern = ?,
                    confidence = ?,
                    sample_count = ?,
                    learned_from = ?,
                    last_updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [
                round($new_mult, 3),
                $observed_ending,
                round($new_conf, 2),
                $new_n,
                $learned_from,
                (int)$existing['id'],
            ]);
        } else {
            DB::run("
                INSERT INTO pricing_patterns
                  (tenant_id, store_id, category_id, category_name, multiplier, ending_pattern, confidence, sample_count, learned_from)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
            ", [
                $tenant_id,
                $store_id,
                $category_id,
                $category_name,
                round($observed_mult, 3),
                $observed_ending,
                0.50,
                $learned_from,
            ]);
        }
        return true;
    } catch (Throwable $e) {
        error_log('pricing-engine learnFromCorrection: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────
// AUDIT
// ─────────────────────────────────────────────────────────────────────
function auditPriceChange(
    int $tenant_id,
    int $store_id,
    int $product_id,
    ?float $old_cost, ?float $new_cost,
    ?float $old_retail, ?float $new_retail,
    string $change_source = 'manual',
    ?float $confidence = null,
    bool $auto_applied = false,
    ?int $delivery_id = null,
    ?int $delivery_item_id = null,
    ?int $user_id = null,
    ?string $note = null,
    string $currency_code = 'EUR'
): bool {
    $variance_pct = null;
    if ($old_cost && $old_cost > 0 && $new_cost !== null) {
        $variance_pct = round((($new_cost - $old_cost) / $old_cost) * 100, 2);
    }

    try {
        DB::run("
            INSERT INTO price_change_log
              (tenant_id, store_id, product_id, delivery_id, delivery_item_id,
               old_cost, new_cost, old_retail, new_retail, cost_variance_pct,
               currency_code, change_source, confidence, auto_applied, user_id, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $tenant_id, $store_id, $product_id, $delivery_id, $delivery_item_id,
            $old_cost, $new_cost, $old_retail, $new_retail, $variance_pct,
            $currency_code, $change_source, $confidence, $auto_applied ? 1 : 0, $user_id, $note,
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('pricing-engine auditPriceChange: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────
function isBestseller(int $tenant_id, int $product_id): bool {
    $weeks = PE_BESTSELLER_WEEKS;
    $thresh = PE_BESTSELLER_PER_WEEK * $weeks;

    try {
        $count = (int)DB::run("
            SELECT COALESCE(SUM(si.quantity), 0)
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE s.tenant_id = ?
              AND si.product_id = ?
              AND s.status = 'completed'
              AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
        ", [$tenant_id, $product_id, $weeks])->fetchColumn();
        return $count >= $thresh;
    } catch (Throwable $e) {
        return false;
    }
}

function classifyAction(float $confidence, bool $is_bestseller, ?float $cost_variance_pct = null): string {
    if ($is_bestseller) return 'confirm';
    if ($cost_variance_pct !== null && abs($cost_variance_pct) > PE_VARIANCE_AUTO_HI) return 'confirm';
    if ($confidence >= PE_AUTO_THRESHOLD) return 'auto';
    if ($confidence >= PE_MANUAL_THRESHOLD) return 'confirm';
    return 'manual';
}

function inferEndingFromPrice(float $price): string {
    $cents = round(($price - floor($price)) * 100);
    if ($cents === 99.0 || $cents === 99) return 'point_99';
    if ($cents === 90.0 || $cents === 90) return 'point_90';
    if ($cents === 50.0 || $cents === 50) return 'point_50';
    if ($cents === 0.0 || $cents === 0) return 'exact';
    return 'point_90';
}

function computeConfidence(int $sample_count, float $old_mult, float $observed_mult): float {
    $variance = abs($observed_mult - $old_mult) / max(0.01, $old_mult);
    $consistency = max(0.0, 1.0 - $variance);
    $sample_richness = tanh($sample_count / 20.0);
    $conf = 0.30 + 0.40 * $sample_richness + 0.30 * $consistency;
    return min(1.0, max(0.0, $conf));
}
