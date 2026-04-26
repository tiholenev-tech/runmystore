<?php
/**
 * /admin/diagnostics.php — RunMyStore Diagnostic Dashboard
 *
 * Auth: само tenant_id=7 (Тихол). 403 за всички други.
 * Sections:
 *   1. Last 10 runs
 *   2. Per-category trend (last 14 runs)
 *   3. Cat A/D failures банер (ако има)
 *   4. Gap detector output (pf*() без oracle entries)
 *   5. Manual run button
 *   6. Human БГ summary (template-based, не AI)
 *
 * Style: ползва /css/theme.css (от паралелния chat). Не дублира theming.
 */

require_once __DIR__ . '/../config/database.php';
session_start();

if (($_SESSION['tenant_id'] ?? 0) !== 7) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Diagnostic dashboard е достъпен само за tenant=7.</p>';
    exit;
}

// ─── DATA FETCH ───────────────────────────────────────────────

function diag_last_runs(int $limit = 10): array {
    return DB::run("
        SELECT id, run_timestamp, trigger_type, module_name,
               total_scenarios, passed, failed,
               category_a_pass_rate AS rate_a,
               category_b_pass_rate AS rate_b,
               category_c_pass_rate AS rate_c,
               category_d_pass_rate AS rate_d,
               duration_seconds, git_commit_sha
        FROM diagnostic_log
        ORDER BY id DESC
        LIMIT $limit
    ")->fetchAll();
}

function diag_trend(int $n = 14): array {
    return DB::run("
        SELECT id, run_timestamp,
               category_a_pass_rate AS a,
               category_b_pass_rate AS b,
               category_c_pass_rate AS c,
               category_d_pass_rate AS d
        FROM diagnostic_log
        ORDER BY id DESC
        LIMIT $n
    ")->fetchAll();
}

function diag_oracle_status(): array {
    $rows = DB::run("
        SELECT category, COUNT(*) AS cnt
        FROM seed_oracle
        WHERE module_name = 'insights' AND COALESCE(is_active, 1) = 1
        GROUP BY category
        ORDER BY category
    ")->fetchAll();
    $total = DB::run("
        SELECT COUNT(*) AS cnt FROM seed_oracle
        WHERE module_name = 'insights' AND COALESCE(is_active, 1) = 1
    ")->fetchColumn();
    return ['by_cat' => $rows, 'total' => (int)$total];
}

$last_runs = diag_last_runs(10);
$current = $last_runs[0] ?? null;
$prev = $last_runs[1] ?? null;
$trend = diag_trend(14);
$oracle = diag_oracle_status();

// ─── HUMAN SUMMARY (PHP if/else, не AI) ──────────────────────

function human_summary(?array $cur, ?array $prev): string {
    if (!$cur) {
        return "Все още няма пуснат diagnostic. Натисни „Пусни ръчен test\" за първи run.";
    }
    $a = (float)($cur['rate_a'] ?? 0);
    $d = (float)($cur['rate_d'] ?? 0);
    $b = (float)($cur['rate_b'] ?? 0);
    $c = (float)($cur['rate_c'] ?? 0);
    $failed = (int)($cur['failed'] ?? 0);

    $parts = [];
    if ($a >= 100 && $d >= 100) {
        $parts[] = "Всички критични логики работят правилно.";
    } elseif ($a < 100) {
        $parts[] = "⚠️ Критична категория A е под 100% (" . number_format($a, 1) . "%). Нужен е rollback или fix.";
    } elseif ($d < 100) {
        $parts[] = "⚠️ Граничните тестове D са под 100% (" . number_format($d, 1) . "%). Вероятно SQL bug.";
    }
    if ($b < 80 || $c < 70) {
        $parts[] = "Второстепенните логики (B=" . number_format($b, 1) . "%, C=" . number_format($c, 1) . "%) имат пропуски, но не са спешни.";
    } else {
        $parts[] = "Второстепенните логики са в норма.";
    }
    if ($failed > 0) {
        $parts[] = "$failed теста се провалиха — виж списъка по-долу.";
    }
    $parts[] = "Следващ автоматичен тест: понеделник 03:00 (cron).";
    return implode(" ", $parts);
}

function rate_class(?float $rate, float $threshold = 100): string {
    if ($rate === null) return 'rate-na';
    if ($rate >= $threshold) return 'rate-ok';
    if ($rate >= 80) return 'rate-warn';
    return 'rate-bad';
}

function fmt_rate(?float $rate): string {
    return $rate === null ? '—' : number_format((float)$rate, 1) . '%';
}

$summary_text = human_summary($current, $prev);
$has_critical = $current && (
    ((float)($current['rate_a'] ?? 100) < 100) ||
    ((float)($current['rate_d'] ?? 100) < 100)
);
?><!DOCTYPE html>
<html lang="bg" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>RunMyStore Diagnostic Dashboard</title>
<link rel="stylesheet" href="/css/theme.css?v=<?= @filemtime(__DIR__.'/../css/theme.css') ?: 1 ?>">
<style>
body{margin:0;font-family:system-ui,-apple-system,'Montserrat',sans-serif;background:var(--bg-main);color:var(--text-primary);min-height:100vh;padding:16px}
h1{font-size:18px;margin:0 0 16px;letter-spacing:.05em;font-weight:800}
.section{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:var(--shadow-card)}
.section h2{font-size:14px;margin:0 0 12px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.1em}
.banner{background:linear-gradient(90deg,rgba(239,68,68,.15),rgba(239,68,68,.05));border:1px solid rgba(239,68,68,.4);border-radius:12px;padding:14px 16px;margin-bottom:16px;color:#fca5a5;font-size:13px;font-weight:600}
.summary-text{font-size:14px;line-height:1.6;color:var(--text-primary)}
.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px}
.metric{background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:10px;padding:12px;text-align:center}
.metric-label{font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.metric-value{font-size:22px;font-weight:800;font-variant-numeric:tabular-nums}
.rate-ok{color:#22c55e}.rate-warn{color:#f59e0b}.rate-bad{color:#ef4444}.rate-na{color:var(--text-muted)}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:8px 6px;text-align:left;border-bottom:1px solid var(--border-color)}
th{color:var(--text-muted);font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.05em}
tr:hover td{background:rgba(255,255,255,.02)}
.btn{display:inline-block;padding:10px 16px;background:var(--indigo-500);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none}
.btn:hover{background:var(--indigo-600)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;letter-spacing:.05em}
.tag-A{background:rgba(239,68,68,.15);color:#fca5a5}
.tag-B{background:rgba(245,158,11,.15);color:#fcd34d}
.tag-C{background:rgba(99,102,241,.15);color:#a5b4fc}
.tag-D{background:rgba(34,197,94,.15);color:#86efac}
.trigger-tag{font-family:monospace;font-size:10px;color:var(--text-muted)}
#runOutput{background:#000;color:#0f0;font-family:monospace;font-size:11px;padding:10px;border-radius:8px;margin-top:10px;max-height:400px;overflow:auto;white-space:pre-wrap;display:none}
.gap-list{font-family:monospace;font-size:12px;color:var(--text-secondary)}
.gap-list li{padding:3px 0}
</style>
</head>
<body>

<h1>🔍 RunMyStore Diagnostic Dashboard</h1>

<?php if ($has_critical): ?>
<div class="banner">
    🚨 КРИТИЧНО — Категория A или D под 100%. Спри commit-ите докато не fix-неш.
</div>
<?php endif; ?>

<div class="section">
    <h2>Резюме</h2>
    <p class="summary-text"><?= htmlspecialchars($summary_text) ?></p>
</div>

<?php if ($current): ?>
<div class="section">
    <h2>Последен run #<?= (int)$current['id'] ?></h2>
    <div class="metrics">
        <div class="metric">
            <div class="metric-label">A — критични</div>
            <div class="metric-value <?= rate_class($current['rate_a']) ?>"><?= fmt_rate($current['rate_a']) ?></div>
        </div>
        <div class="metric">
            <div class="metric-label">B — важни</div>
            <div class="metric-value <?= rate_class($current['rate_b'], 80) ?>"><?= fmt_rate($current['rate_b']) ?></div>
        </div>
        <div class="metric">
            <div class="metric-label">C — декорация</div>
            <div class="metric-value <?= rate_class($current['rate_c'], 70) ?>"><?= fmt_rate($current['rate_c']) ?></div>
        </div>
        <div class="metric">
            <div class="metric-label">D — граници</div>
            <div class="metric-value <?= rate_class($current['rate_d']) ?>"><?= fmt_rate($current['rate_d']) ?></div>
        </div>
        <div class="metric">
            <div class="metric-label">Total / Pass / Fail</div>
            <div class="metric-value"><?= (int)$current['total_scenarios'] ?>/<?= (int)$current['passed'] ?>/<?= (int)$current['failed'] ?></div>
        </div>
        <div class="metric">
            <div class="metric-label">Време</div>
            <div class="metric-value"><?= (int)$current['duration_seconds'] ?>s</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="section">
    <h2>Последни 10 runs</h2>
    <table>
        <thead><tr>
            <th>#</th><th>Дата</th><th>Тригер</th><th>A</th><th>B</th><th>C</th><th>D</th><th>Pass/Fail</th>
        </tr></thead>
        <tbody>
        <?php foreach ($last_runs as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['run_timestamp']) ?></td>
                <td><span class="trigger-tag"><?= htmlspecialchars($r['trigger_type']) ?></span></td>
                <td class="<?= rate_class($r['rate_a']) ?>"><?= fmt_rate($r['rate_a']) ?></td>
                <td class="<?= rate_class($r['rate_b'], 80) ?>"><?= fmt_rate($r['rate_b']) ?></td>
                <td class="<?= rate_class($r['rate_c'], 70) ?>"><?= fmt_rate($r['rate_c']) ?></td>
                <td class="<?= rate_class($r['rate_d']) ?>"><?= fmt_rate($r['rate_d']) ?></td>
                <td><?= (int)$r['passed'] ?>/<?= (int)$r['failed'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($last_runs)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:20px">Няма runs все още</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Oracle покритие</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin:0 0 10px">
        Общо <strong><?= $oracle['total'] ?></strong> активни сценария за `compute-insights`:
    </p>
    <div class="metrics">
        <?php foreach ($oracle['by_cat'] as $row): ?>
            <div class="metric">
                <div class="metric-label">Категория <?= htmlspecialchars($row['category']) ?></div>
                <div class="metric-value"><?= (int)$row['cnt'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="section">
    <h2>Ръчен test (TRIGGER 4)</h2>
    <p style="font-size:13px;color:var(--text-secondary)">
        Извиква <code>python3 tools/diagnostic/run_diag.py --module=insights --trigger=user_command --pristine</code>.
        Може да отнеме до 2 минути.
    </p>
    <button class="btn" onclick="runManual()">▶ Пусни ръчен test</button>
    <pre id="runOutput"></pre>
</div>

<script>
async function runManual() {
    const out = document.getElementById('runOutput');
    out.style.display = 'block';
    out.textContent = 'Стартирам diagnostic...\n';
    try {
        const r = await fetch('/admin/diag-run.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const text = await r.text();
        out.textContent += text + '\n\nЗавършено. Освежи страницата за нови резултати.';
    } catch (e) {
        out.textContent += 'ГРЕШКА: ' + e.message;
    }
}
</script>

</body></html>
