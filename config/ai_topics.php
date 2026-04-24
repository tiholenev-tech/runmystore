<?php
/**
 * S79.SELECTION_ENGINE — AI Topics catalog bootstrap + helpers
 * Зарежда ai-topics-catalog.json в ai_topics_catalog (idempotent UPSERT).
 */

require_once __DIR__ . '/database.php';

/**
 * Bootstrap/refresh catalog from JSON file.
 * Idempotent — safe to re-run.
 */
function bootstrapTopicsFromJson(?string $json_path = null): array {
    $json_path = $json_path ?? __DIR__ . '/../ai-topics-catalog.json';
    if (!file_exists($json_path)) {
        throw new RuntimeException('Topics catalog JSON missing: ' . $json_path);
    }
    $raw = file_get_contents($json_path);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('Cannot read JSON: ' . $json_path);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON in: ' . $json_path);
    }

    $processed = 0;
    $skipped   = 0;
    $errors    = [];

    foreach ($data as $i => $t) {
        // Required fields check
        if (empty($t['id']) || empty($t['cat']) || empty($t['name']) || !isset($t['what'])) {
            $skipped++;
            $errors[] = "row #$i: missing id/cat/name/what";
            continue;
        }

        // ENUM safety
        $topic_type = $t['type'] ?? 'fact';
        if (!in_array($topic_type, ['fact','reminder','discovery','comparison'], true)) {
            $topic_type = 'fact';
        }

        try {
            DB::run(
                "INSERT INTO ai_topics_catalog
                   (id, category, name, what, trigger_condition, data_source, topic_type,
                    country_codes, roles, plan, priority, module)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   category=VALUES(category), name=VALUES(name), what=VALUES(what),
                   trigger_condition=VALUES(trigger_condition), data_source=VALUES(data_source),
                   topic_type=VALUES(topic_type), country_codes=VALUES(country_codes),
                   roles=VALUES(roles), plan=VALUES(plan), priority=VALUES(priority),
                   module=VALUES(module)",
                [
                    substr((string)$t['id'], 0, 50),
                    substr((string)$t['cat'], 0, 30),
                    substr((string)$t['name'], 0, 200),
                    (string)$t['what'],
                    isset($t['when']) ? substr((string)$t['when'], 0, 200) : null,
                    isset($t['data']) ? substr((string)$t['data'], 0, 50)  : null,
                    $topic_type,
                    isset($t['cc'])    ? substr((string)$t['cc'], 0, 100)  : '*',
                    isset($t['roles']) ? substr((string)$t['roles'], 0, 100) : 'owner',
                    isset($t['plan'])  ? substr((string)$t['plan'], 0, 50) : 'business',
                    max(1, min(8, (int)($t['p'] ?? 5))),
                    isset($t['module']) ? substr((string)$t['module'], 0, 30) : 'home',
                ]
            );
            $processed++;
        } catch (Throwable $e) {
            $skipped++;
            $errors[] = "row #$i ({$t['id']}): " . $e->getMessage();
        }
    }

    return [
        'total_in_json'       => count($data),
        'inserted_or_updated' => $processed,
        'skipped'             => $skipped,
        'errors'              => array_slice($errors, 0, 10),
    ];
}

/** Fetch single topic by ID. Returns null if not found. */
function getTopicById(string $topic_id): ?array {
    $row = DB::run("SELECT * FROM ai_topics_catalog WHERE id=?", [$topic_id])->fetch();
    return $row ?: null;
}

/** Fetch all topics in a category, ordered by priority. */
function getTopicsByCategory(string $category, bool $active_only = true): array {
    $sql = "SELECT * FROM ai_topics_catalog WHERE category=?";
    if ($active_only) $sql .= " AND is_active=1";
    $sql .= " ORDER BY priority ASC, id ASC";
    return DB::run($sql, [$category])->fetchAll();
}
