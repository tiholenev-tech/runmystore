<?php
/**
 * onboarding-save.php — Session 23 FIX
 * Записва данните от AI onboarding интервюто.
 * Frontend праща: {name, biz, segment, stores, loyalty}
 */
session_start();
if (!isset($_SESSION['tenant_id'])) { 
    http_response_code(401); 
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit; 
}

require_once 'config/database.php';
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['ok' => false, 'error' => 'No data received']);
    exit;
}

$name    = trim($data['name'] ?? '');
$biz     = trim($data['biz'] ?? '');
$segment = trim($data['segment'] ?? '');
$stores  = trim($data['stores'] ?? '1');
$loyalty = trim($data['loyalty'] ?? '');

// Бизнес тип = бизнес + сегмент
$biz_full = $biz;
if ($segment && $segment !== $biz) {
    $biz_full = $biz . ' — ' . $segment;
}

// Брой магазини
$store_count = 1;
if (preg_match('/(\d+)/', $stores, $m)) {
    $store_count = max(1, (int)$m[1]);
}

// Автоматични флагове от бизнес типа
$wholesale  = (int)preg_match('/строит|авто|едро|wholesale|метал|индустр/iu', $biz);
$perishable = (int)preg_match('/хран|цвет|аптек|фарм|месо|млечн|хлеб|пекар/iu', $biz);
$tax_group  = preg_match('/аптек|фарм|книж|детск|медиц/iu', $biz) ? 9 : 20;

try {
    // 1. Обнови tenants — основните полета
    DB::run(
        "UPDATE tenants SET 
            business_type = ?, 
            onboarding_done = 1, 
            wholesale_enabled = ?, 
            is_perishable = ?, 
            tax_group = ? 
         WHERE id = ?",
        [$biz_full, $wholesale, $perishable, $tax_group, $tenant_id]
    );

    // 2. Обнови user name ако е дадено и е празно
    if ($name) {
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $existing = DB::run("SELECT name FROM users WHERE id = ?", [$user_id])->fetch();
            if ($existing && empty($existing['name'])) {
                DB::run("UPDATE users SET name = ? WHERE id = ?", [$name, $user_id]);
                $_SESSION['user_name'] = $name;
            }
        }
    }

    // 3. AI памет — ключови факти
    $memories = [];
    if ($biz_full) {
        $memories[] = ['БИЗНЕС ТИП', $biz_full, 'core_fact'];
    }
    if ($store_count > 0) {
        $memories[] = ['МАГАЗИНИ БРОЙ', (string)$store_count, 'core_fact'];
    }
    if ($segment) {
        $memories[] = ['СЕГМЕНТ', $segment, 'core_fact'];
    }
    if ($loyalty) {
        $memories[] = ['ЛОЯЛНОСТ ИЗБОР', $loyalty, 'strategy'];
    }

    foreach ($memories as [$key, $val, $ctx]) {
        // Избягваме дублирания — update or insert
        $existing = DB::run(
            "SELECT id FROM tenant_ai_memory WHERE tenant_id = ? AND key_phrase = ?",
            [$tenant_id, $key]
        )->fetch();

        if ($existing) {
            DB::run(
                "UPDATE tenant_ai_memory SET value = ?, context = ? WHERE id = ?",
                [$val, $ctx, $existing['id']]
            );
        } else {
            DB::run(
                "INSERT INTO tenant_ai_memory (tenant_id, key_phrase, value, context) VALUES (?, ?, ?, ?)",
                [$tenant_id, $key, $val, $ctx]
            );
        }
    }

    // 4. Сетни сесията
    $_SESSION['onboarding_done'] = 1;
    $_SESSION['business_type']   = $biz_full;

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    error_log("onboarding-save.php ERROR: " . $e->getMessage());
    
    // Опитваме минимален fallback — поне да сетнем onboarding_done
    try {
        DB::run("UPDATE tenants SET onboarding_done = 1 WHERE id = ?", [$tenant_id]);
        $_SESSION['onboarding_done'] = 1;
        echo json_encode(['ok' => true, 'warning' => 'Partial save — some columns may be missing']);
    } catch (Exception $e2) {
        echo json_encode(['ok' => false, 'error' => 'Database error']);
    }
}
