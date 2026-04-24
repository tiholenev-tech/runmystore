<?php
/**
 * S79.SELECTION_ENGINE — MMR-based AI topic rotation
 * Избира най-релевантните теми за tenant с diversity по категория.
 *
 * Зависи от: ai_topics_catalog, ai_topic_rotation, tenants
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ai_topics.php';

/**
 * Select top N topics for tenant using MMR (Maximal Marginal Relevance).
 *
 * @param int    $tenant_id
 * @param string $module  'home' | 'products' | 'stats' | ...
 * @param int    $max     Max topics to return
 * @param float  $lambda  Relevance vs diversity weight (0=diversity, 1=pure relevance)
 * @return array<array>   List of topic rows
 */
function selectTopicsForTenant(int $tenant_id, string $module = 'home', int $max = 5, float $lambda = 0.75): array {
    // 1. Tenant context — използваме plan_effective (реалния plan по време на trial)
    $tenant = DB::run(
        "SELECT plan_effective, country, language FROM tenants WHERE id=?",
        [$tenant_id]
    )->fetch();
    if (!$tenant) return [];

    $plan    = $tenant['plan_effective'] ?? 'free';
    $country = $tenant['country']        ?? 'BG';

    // 2. Candidate topics:
    //    - module: текущ или 'home' (home = универсален)
    //    - country_codes='*' универсален, иначе FIND_IN_SET ('BG,RO,GR')
    //    - plan: 'business' или 'free' = универсален; tenant.plan_effective = специфично
    $candidates = DB::run(
        "SELECT * FROM ai_topics_catalog
         WHERE is_active = 1
           AND module IN (?, 'home')
           AND (country_codes = '*' OR FIND_IN_SET(?, country_codes) > 0)
           AND (plan IN ('business','free') OR plan = ?)",
        [$module, $country, $plan]
    )->fetchAll();

    if (!$candidates) return [];

    // 3. Rotation state
    $rotation = [];
    foreach (DB::run(
        "SELECT topic_id, last_shown_at, shown_count, suppressed_until
         FROM ai_topic_rotation WHERE tenant_id = ?",
        [$tenant_id]
    )->fetchAll() as $r) {
        $rotation[$r['topic_id']] = $r;
    }

    // 4. Score candidates
    $scored = [];
    $now = time();
    foreach ($candidates as $c) {
        $rot = $rotation[$c['id']] ?? null;

        // Suppression
        if ($rot && !empty($rot['suppressed_until']) && strtotime($rot['suppressed_until']) > $now) {
            continue;
        }

        // Relevance: priority 1 → 1.0 ; priority 8 → 0.125
        $relevance = (9 - (int)$c['priority']) / 8.0;

        // Freshness: 0 = току-що показана, 1 = 72+ часа не е показана
        $hours_since = $rot ? ($now - strtotime($rot['last_shown_at'])) / 3600 : 999;
        $freshness   = min($hours_since / 24.0, 3.0) / 3.0;

        // Trigger placeholder (S79) — compute-insights ще оцени реалните условия по-късно
        $trigger_match = 1.0;

        $score = $relevance * 0.4 + $freshness * 0.3 + $trigger_match * 0.3;

        $scored[] = ['topic' => $c, 'score' => $score];
    }

    if (!$scored) return [];

    // 5. MMR: greedy selection, penalise repeated categories
    $selected = [];
    $categories_used = [];

    while (count($selected) < $max && count($scored) > 0) {
        $best_idx = -1;
        $best_mmr = -INF;

        foreach ($scored as $idx => $s) {
            $div_penalty = in_array($s['topic']['category'], $categories_used, true) ? 0.4 : 0.0;
            $mmr = $lambda * $s['score'] - (1.0 - $lambda) * $div_penalty;
            if ($mmr > $best_mmr) {
                $best_mmr = $mmr;
                $best_idx = $idx;
            }
        }

        if ($best_idx === -1) break;

        $selected[]        = $scored[$best_idx]['topic'];
        $categories_used[] = $scored[$best_idx]['topic']['category'];
        array_splice($scored, $best_idx, 1);
    }

    return $selected;
}

/**
 * Record that a topic was shown; sets suppression window (UPSERT).
 */
function recordTopicShown(int $tenant_id, string $topic_id, string $module = 'home', int $suppress_hours = 6): bool {
    $suppress_until = date('Y-m-d H:i:s', time() + $suppress_hours * 3600);
    DB::run(
        "INSERT INTO ai_topic_rotation
           (tenant_id, topic_id, last_shown_at, shown_count, last_module, suppressed_until)
         VALUES (?, ?, NOW(), 1, ?, ?)
         ON DUPLICATE KEY UPDATE
           last_shown_at    = NOW(),
           shown_count      = shown_count + 1,
           last_module      = VALUES(last_module),
           suppressed_until = VALUES(suppressed_until)",
        [$tenant_id, $topic_id, substr($module, 0, 30), $suppress_until]
    );
    return true;
}

/**
 * Stats за rotation-a на tenant.
 */
function getTopicStats(int $tenant_id): array {
    $row = DB::run(
        "SELECT
           COUNT(DISTINCT topic_id)                                    AS unique_shown,
           COALESCE(SUM(shown_count), 0)                                AS total_impressions,
           SUM(CASE WHEN suppressed_until > NOW() THEN 1 ELSE 0 END)    AS currently_suppressed,
           MAX(last_shown_at)                                           AS last_activity
         FROM ai_topic_rotation WHERE tenant_id = ?",
        [$tenant_id]
    )->fetch();
    return $row ?: [
        'unique_shown'         => 0,
        'total_impressions'    => 0,
        'currently_suppressed' => 0,
        'last_activity'        => null,
    ];
}

/**
 * Reset за дебъг — изтрива цялата rotation за tenant.
 */
function resetTenantRotation(int $tenant_id): int {
    return DB::run("DELETE FROM ai_topic_rotation WHERE tenant_id = ?", [$tenant_id])->rowCount();
}
