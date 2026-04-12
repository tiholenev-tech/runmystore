<?php
/**
 * ai-topics.php — AI Topic Selector for RunMyStore.ai
 * Session 38/42 — 500 topics in 22 categories
 *
 * Loads ai-topics-catalog.json and selects 5-8 relevant topics
 * based on: country, role, plan, hour, month, data availability.
 *
 * Usage in build-prompt.php:
 *   require_once __DIR__ . '/ai-topics.php';
 *   $topicsBlock = selectRelevantTopics($tenant_id, $store_id, $role, $plan, $country, $dataStats);
 *   // $topicsBlock = formatted string to inject in Gemini system prompt
 */

/**
 * Load all 500 topics from JSON catalog (cached in static var)
 */
function loadTopicsCatalog(): array {
    static $catalog = null;
    if ($catalog !== null) return $catalog;

    $path = __DIR__ . '/ai-topics-catalog.json';
    if (!file_exists($path)) return [];

    $raw = file_get_contents($path);
    $catalog = json_decode($raw, true) ?? [];
    return $catalog;
}

/**
 * Select 5-8 relevant topics for the current context
 *
 * @param int    $tenant_id
 * @param int    $store_id
 * @param string $role      owner|manager|seller
 * @param string $plan      free|business|pro|enterprise
 * @param string $country   2-letter ISO (BG, RO, GR, DE, etc.)
 * @param array  $dataStats Associative array with data availability flags:
 *   'days_of_data'     => int (how many days since first sale)
 *   'total_products'   => int
 *   'total_sales'      => int
 *   'total_customers'  => int
 *   'has_cost_price'   => bool (>50% products with cost_price)
 *   'has_wholesale'    => bool
 *   'has_returns'      => bool
 *   'has_invoices'     => bool
 *   'has_deliveries'   => bool
 *   'has_multi_store'  => bool
 *   'sellers_count'    => int
 *   'has_variations'   => bool
 * @return string Formatted block for Gemini system prompt
 */
function selectRelevantTopics(
    int $tenant_id,
    int $store_id,
    string $role,
    string $plan,
    string $country,
    array $dataStats
): string {
    $catalog = loadTopicsCatalog();
    if (empty($catalog)) return '';

    $month = (int)date('n');
    $day   = (int)date('j');
    $hour  = (int)date('G');
    $dow   = (int)date('w'); // 0=Sun

    // Plan hierarchy: free < business < pro < enterprise
    $planRank = ['free' => 0, 'business' => 1, 'pro' => 2, 'enterprise' => 3];
    $myPlanRank = $planRank[strtolower($plan)] ?? 0;

    $scored = [];

    foreach ($catalog as $topic) {
        // ── FILTER: Role ──
        $allowedRoles = explode(',', $topic['roles'] ?? 'owner');
        $roleMatch = false;
        foreach ($allowedRoles as $r) {
            $r = trim($r);
            if ($r === $role || ($r === 'mgr' && $role === 'manager')) {
                $roleMatch = true;
                break;
            }
        }
        if (!$roleMatch) continue;

        // ── FILTER: Plan ──
        $topicPlanRank = $planRank[$topic['plan'] ?? 'free'] ?? 0;
        if ($myPlanRank < $topicPlanRank) continue;

        // ── FILTER: Country ──
        $cc = $topic['cc'] ?? '*';
        if ($cc !== '*') {
            $countries = array_map('trim', explode(',', $cc));
            if (!in_array($country, $countries)) continue;
        }

        // ── FILTER: Data availability ──
        $dataReq = $topic['data'] ?? '';
        if (!checkDataAvailability($dataReq, $dataStats)) continue;

        // ── SCORE: Priority (lower = more important) ──
        $score = 100 - (($topic['p'] ?? 5) * 10); // priority 1 → score 90, priority 10 → score 0

        // ── SCORE: Time relevance bonus ──
        $when = $topic['when'] ?? '';
        $timeBonus = scoreTimeRelevance($when, $month, $day, $hour, $dow);
        $score += $timeBonus;

        // ── SCORE: Category variety bonus (handled after scoring) ──
        $scored[] = [
            'topic' => $topic,
            'score' => $score,
            'cat'   => $topic['cat'] ?? 'other',
        ];
    }

    // Sort by score descending
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    // Pick top 8 with category diversity (max 2 per category)
    $selected = [];
    $catCount = [];
    foreach ($scored as $item) {
        $cat = $item['cat'];
        $catCount[$cat] = ($catCount[$cat] ?? 0) + 1;
        if ($catCount[$cat] > 2) continue; // max 2 per category
        $selected[] = $item['topic'];
        if (count($selected) >= 8) break;
    }

    if (empty($selected)) return '';

    // Format for Gemini prompt
    $lines = [];
    foreach ($selected as $i => $t) {
        $n = $i + 1;
        $lines[] = "{$n}. [{$t['cat']}] {$t['name']}: {$t['what']}";
    }

    $block = implode("\n", $lines);

    return <<<TOPICS

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LAYER 7 — RELEVANT TOPICS FOR TODAY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
These are insights relevant RIGHT NOW for this store. Use them proactively
when the owner asks general questions like "какво ново?" or "как е бизнесът?".
Each topic is a FACT you can check against the data above.
Do NOT mention all of them — pick 2-3 most relevant to the conversation.

{$block}
TOPICS;
}

/**
 * Check if tenant has enough data for this topic
 */
function checkDataAvailability(string $dataReq, array $stats): bool {
    $days      = $stats['days_of_data'] ?? 0;
    $products  = $stats['total_products'] ?? 0;
    $sales     = $stats['total_sales'] ?? 0;
    $customers = $stats['total_customers'] ?? 0;
    $hasCost   = $stats['has_cost_price'] ?? false;
    $hasWS     = $stats['has_wholesale'] ?? false;
    $hasRet    = $stats['has_returns'] ?? false;
    $hasInv    = $stats['has_invoices'] ?? false;
    $hasDel    = $stats['has_deliveries'] ?? false;
    $hasMulti  = $stats['has_multi_store'] ?? false;
    $sellers   = $stats['sellers_count'] ?? 1;

    // Quick checks based on data requirement string
    if (str_contains($dataReq, '365d') && $days < 365) return false;
    if (str_contains($dataReq, '90d') && $days < 90) return false;
    if (str_contains($dataReq, '60d') && $days < 60) return false;
    if (str_contains($dataReq, '30d') && $days < 30) return false;
    if (str_contains($dataReq, '14d') && $days < 14) return false;
    if (str_contains($dataReq, '7d') && $days < 7) return false;

    if (str_contains($dataReq, 'cost') && !$hasCost) return false;
    if (str_contains($dataReq, 'ws') && !$hasWS) return false;
    if (str_contains($dataReq, 'ret') && !$hasRet) return false;
    if (str_contains($dataReq, 'inv') && !$hasInv) return false;
    if (str_contains($dataReq, 'del') && !$hasDel) return false;
    if (str_contains($dataReq, 'multi') && !$hasMulti) return false;
    if (str_contains($dataReq, 'sellers>=2') && $sellers < 2) return false;

    if (str_contains($dataReq, 'sales>=50') && $sales < 50) return false;
    if (str_contains($dataReq, 'sales>=30') && $sales < 30) return false;
    if (str_contains($dataReq, 'sales>=20') && $sales < 20) return false;

    if (str_contains($dataReq, 'cust>=50') && $customers < 50) return false;
    if (str_contains($dataReq, 'cust>=20') && $customers < 20) return false;

    return true;
}

/**
 * Score how relevant a topic is right now based on time conditions
 * Returns 0-30 bonus points
 */
function scoreTimeRelevance(string $when, int $month, int $day, int $hour, int $dow): int {
    if (empty($when)) return 0;

    $score = 0;

    // Month-based conditions
    if (preg_match('/month[=_](\d+)/', $when, $m) && (int)$m[1] === $month) $score += 20;
    if (str_contains($when, 'jan') && $month === 1) $score += 15;
    if (str_contains($when, 'feb') && $month === 2) $score += 15;
    if (str_contains($when, 'mar') && $month === 3) $score += 15;
    if (str_contains($when, 'apr') && $month === 4) $score += 15;
    if (str_contains($when, 'may') && $month === 5) $score += 15;
    if (str_contains($when, 'jun') && $month === 6) $score += 15;
    if (str_contains($when, 'jul') && $month === 7) $score += 15;
    if (str_contains($when, 'aug') && $month === 8) $score += 15;
    if (str_contains($when, 'sep') && $month === 9) $score += 15;
    if (str_contains($when, 'oct') && $month === 10) $score += 15;
    if (str_contains($when, 'nov') && $month === 11) $score += 15;
    if (str_contains($when, 'dec') && $month === 12) $score += 15;

    // Day-based
    if (str_contains($when, 'day>=20') && $day >= 20) $score += 10;
    if (str_contains($when, 'end_month') && $day >= 25) $score += 10;
    if (str_contains($when, 'quarter_end') && in_array($month, [3,6,9,12]) && $day >= 15) $score += 10;

    // Always relevant (low bonus, just don't filter out)
    if (str_contains($when, 'always') || str_contains($when, 'daily') || str_contains($when, 'weekly')) $score += 5;

    // High urgency keywords
    if (str_contains($when, '<=7') || str_contains($when, '<=5')) $score += 15;
    if (str_contains($when, '>0') || str_contains($when, '>=1')) $score += 10;

    return min($score, 30); // cap at 30
}
