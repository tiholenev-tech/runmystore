<?php
/**
 * /admin/beta-readiness.php — BETA READINESS DASHBOARD (S86)
 *
 * Цел: 1 страница, 1 поглед, "готови ли сме за ENI 14 май".
 * 6 секции с цветни статуси (🟢/🟡/🔴), реални числа от живата БД.
 *
 * Auth: tenant=7 (Тихол) + role=owner. Всички други → 403.
 * Auto-refresh: 60s (meta).
 * Mobile-friendly (375px+).
 *
 * Read-only: НЕ пише в БД. Използва само DB::run() през config/database.php
 * и помощни константи от ai-studio-backend.php (без да executes-ва тяхна логика).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// pull constants (AI_BG_PRICE, AI_DESC_PRICE, AI_MAGIC_PRICE, AI_MAX_RETRIES, AI_ABUSE_DAILY_HARD_CAP)
// NB: този файл е pure helper library — не прави HTTP I/O или session_start.
require_once __DIR__ . '/../ai-studio-backend.php';

session_start();

// ─── AUTH GATE ──────────────────────────────────────────────────────────
$session_tenant = (int)($_SESSION['tenant_id'] ?? 0);
$session_role   = (string)($_SESSION['role']    ?? '');
$session_user   = (int)($_SESSION['user_id']    ?? 0);

if ($session_user === 0) {
    header('Location: /login.php');
    exit;
}
if ($session_tenant !== 7 || $session_role !== 'owner') {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>403</title>';
    echo '<style>body{font-family:system-ui;margin:40px;color:#333}</style>';
    echo '<h1>403 Forbidden</h1>';
    echo '<p>Beta Readiness Dashboard е достъпен само за owner на tenant=7.</p>';
    exit;
}

const TENANT = 7;

// ─── HELPERS ────────────────────────────────────────────────────────────

function status_class(string $st): string {
    return ['ok'=>'ok','warn'=>'warn','bad'=>'bad','idle'=>'idle'][$st] ?? 'idle';
}
function status_dot(string $st): string {
    return ['ok'=>'🟢','warn'=>'🟡','bad'=>'🔴','idle'=>'⚪'][$st] ?? '⚪';
}
function pct(int $part, int $total): string {
    if ($total <= 0) return '0%';
    return round($part * 100 / $total) . '%';
}
function fmt_eur(float $n): string {
    $s = number_format($n, 2, ',', '.');
    if (str_ends_with($s, ',00')) $s = substr($s, 0, -3);
    return $s . ' €';
}
function fmt_num(int $n): string { return number_format($n, 0, ',', '.'); }
function ago(?string $ts): string {
    if (!$ts) return 'няма';
    $sec = max(0, time() - strtotime($ts));
    if ($sec < 60)        return $sec . 's преди';
    if ($sec < 3600)      return intdiv($sec, 60) . 'm преди';
    if ($sec < 86400)     return intdiv($sec, 3600) . 'h преди';
    return intdiv($sec, 86400) . 'd преди';
}

// ─── DATA: SECTION 1 — PRODUCT CATALOG ─────────────────────────────────
$cat_total = (int)DB::run(
    "SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL",
    [TENANT]
)->fetchColumn();

$cat_with_photo = (int)DB::run(
    "SELECT COUNT(*) FROM products
       WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL
         AND image_url IS NOT NULL AND image_url<>''",
    [TENANT]
)->fetchColumn();

$cat_with_ai_cat = (int)DB::run(
    "SELECT COUNT(*) FROM products
       WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL
         AND ai_category IS NOT NULL AND ai_category<>''",
    [TENANT]
)->fetchColumn();

$cat_with_cost = (int)DB::run(
    "SELECT COUNT(*) FROM products
       WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL
         AND cost_price IS NOT NULL AND cost_price>0",
    [TENANT]
)->fetchColumn();

$cat_with_min = (int)DB::run(
    "SELECT COUNT(*) FROM products
       WHERE tenant_id=? AND is_active=1 AND parent_id IS NULL
         AND min_quantity IS NOT NULL AND min_quantity>0",
    [TENANT]
)->fetchColumn();

$cat_status = $cat_total < 30 ? 'bad' : ($cat_total < 50 ? 'warn' : 'ok');

// ─── DATA: SECTION 2 — SALES (LAST 30D) ────────────────────────────────
$sales_row = DB::run(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS rev,
            COUNT(DISTINCT DATE(created_at)) AS days_with_sales
       FROM sales
      WHERE tenant_id=? AND status='completed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    [TENANT]
)->fetch();
$sales_count   = (int)($sales_row['cnt'] ?? 0);
$sales_revenue = (float)($sales_row['rev'] ?? 0);
$sales_days    = (int)($sales_row['days_with_sales'] ?? 0);

$items_count = (int)DB::run(
    "SELECT COUNT(*) FROM sale_items si
       JOIN sales s ON s.id = si.sale_id
      WHERE s.tenant_id=? AND s.status='completed'
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    [TENANT]
)->fetchColumn();

$items_per_sale = $sales_count > 0 ? round($items_count / $sales_count, 1) : 0;

$sales_status = $sales_count === 0 ? 'bad' : ($sales_days < 5 ? 'warn' : 'ok');

// ─── DATA: SECTION 3 — AI INSIGHTS (LIVE) ──────────────────────────────
$insights_total = (int)DB::run(
    "SELECT COUNT(*) FROM ai_insights
      WHERE tenant_id=? AND module='products'
        AND (expires_at IS NULL OR expires_at > NOW())",
    [TENANT]
)->fetchColumn();

$ins_per_q_rows = DB::run(
    "SELECT fundamental_question, COUNT(*) AS c
       FROM ai_insights
      WHERE tenant_id=? AND module='products'
        AND (expires_at IS NULL OR expires_at > NOW())
      GROUP BY fundamental_question",
    [TENANT]
)->fetchAll();
$ins_per_q = ['loss'=>0,'loss_cause'=>0,'gain'=>0,'gain_cause'=>0,'order'=>0,'anti_order'=>0];
foreach ($ins_per_q_rows as $r) {
    $k = (string)$r['fundamental_question'];
    if (isset($ins_per_q[$k])) $ins_per_q[$k] = (int)$r['c'];
}

$ins_last_created = DB::run(
    "SELECT MAX(created_at) FROM ai_insights WHERE tenant_id=?",
    [TENANT]
)->fetchColumn() ?: null;

$ins_age_min = $ins_last_created ? (int) max(0, (time() - strtotime((string)$ins_last_created)) / 60) : null;
if ($ins_age_min === null)        $ins_status = 'idle';
elseif ($ins_age_min > 24*60)     $ins_status = 'bad';
elseif ($ins_age_min > 2*60)      $ins_status = 'warn';
else                              $ins_status = 'ok';

// ─── DATA: SECTION 4 — AI STUDIO HEALTH ────────────────────────────────
$studio_balance = [
    'bg'    => get_credit_balance(TENANT, 'bg'),
    'desc'  => get_credit_balance(TENANT, 'desc'),
    'magic' => get_credit_balance(TENANT, 'magic'),
];

$spend_rows = DB::run(
    "SELECT feature, COUNT(*) AS cnt, COALESCE(SUM(cost_eur),0) AS cost
       FROM ai_spend_log
      WHERE tenant_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      GROUP BY feature",
    [TENANT]
)->fetchAll();
$spend_total = 0.0;
$spend_calls = 0;
foreach ($spend_rows as $sr) {
    $spend_total += (float)$sr['cost'];
    $spend_calls += (int)$sr['cnt'];
}

$refunds_7d = (int)DB::run(
    "SELECT COUNT(*) FROM ai_spend_log
      WHERE tenant_id=? AND status='refunded_loss'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    [TENANT]
)->fetchColumn();

// anti-abuse triggers: дневен hard cap (AI_ABUSE_DAILY_HARD_CAP) надхвърлен от потребител
// — броим (user_id, ден) комбинации с >= AI_ABUSE_DAILY_HARD_CAP attempts през последните 7 дни.
$abuse_triggers_7d = (int)DB::run(
    "SELECT COUNT(*) FROM (
       SELECT user_id, DATE(created_at) AS d, COUNT(*) AS c
         FROM ai_spend_log
        WHERE tenant_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY user_id, DATE(created_at)
       HAVING c >= ?
     ) AS t",
    [TENANT, defined('AI_ABUSE_DAILY_HARD_CAP') ? (int)AI_ABUSE_DAILY_HARD_CAP : 30]
)->fetchColumn();

$studio_status = ($studio_balance['magic']['total'] === 0) ? 'warn' : 'ok';
if ($refunds_7d > 5) $studio_status = 'warn';
if ($abuse_triggers_7d > 0) $studio_status = 'warn';

// ─── DATA: SECTION 5 — INFRASTRUCTURE ──────────────────────────────────

// DB latency
$t0 = microtime(true);
DB::run("SELECT 1")->fetch();
$db_latency_ms = (int) round((microtime(true) - $t0) * 1000);
$db_status = $db_latency_ms < 50 ? 'ok' : ($db_latency_ms < 200 ? 'warn' : 'bad');

// cron_heartbeats: compute_insights_15min
$cron = DB::run(
    "SELECT job_name, last_run_at, last_status, last_duration_ms, expected_interval_minutes
       FROM cron_heartbeats WHERE job_name='compute_insights_15min'"
)->fetch();
$cron_age_min = null;
if ($cron && !empty($cron['last_run_at'])) {
    $cron_age_min = (int) max(0, (time() - strtotime((string)$cron['last_run_at'])) / 60);
}
if ($cron_age_min === null)            $cron_status = 'bad';
elseif ($cron_age_min > 60)            $cron_status = 'bad';
elseif ($cron_age_min > 30)            $cron_status = 'warn';
else                                   $cron_status = 'ok';
if ($cron && ($cron['last_status'] ?? '') === 'error') $cron_status = 'bad';

// disk space на /var/www (best-effort, не fails ако exec е blocked)
$disk_line = '?';
$disk_status = 'idle';
$df_out = @shell_exec('df -h /var/www 2>/dev/null | tail -n 1');
if (is_string($df_out) && trim($df_out) !== '') {
    $parts = preg_split('/\s+/', trim($df_out));
    if (is_array($parts) && count($parts) >= 5) {
        $size = $parts[1] ?? '?'; $used = $parts[2] ?? '?'; $avail = $parts[3] ?? '?'; $usep = $parts[4] ?? '?';
        $disk_line = "$used / $size ($usep, $avail free)";
        $usep_n = (int) rtrim($usep, '%');
        $disk_status = $usep_n >= 90 ? 'bad' : ($usep_n >= 75 ? 'warn' : 'ok');
    }
}

// Latest diagnostic Cat A / D
$diag = DB::run(
    "SELECT id, run_timestamp, total_scenarios, passed, failed,
            category_a_pass_rate AS a, category_d_pass_rate AS d
       FROM diagnostic_log
      WHERE module_name='insights'
      ORDER BY id DESC LIMIT 1"
)->fetch();
if ($diag) {
    $a_rate = (float)$diag['a']; $d_rate = (float)$diag['d'];
    $diag_status = ($a_rate >= 100 && $d_rate >= 100) ? 'ok'
                 : (($a_rate >= 95 && $d_rate >= 95) ? 'warn' : 'bad');
} else {
    $diag_status = 'idle';
}

// Bluetooth printer log — таблицата не съществува в schema-та; показваме „N/A"
// (когато бъде въведена, тук ще добавим SELECT).
$printer_status = 'idle';
$printer_note   = 'log table N/A (printer events идат от мобилен Capacitor bridge, без server log)';

// ─── DATA: SECTION 6 — BETA BLOCKERS (parse STATE_OF_THE_PROJECT.md) ────
$state_md_path = realpath(__DIR__ . '/../STATE_OF_THE_PROJECT.md');
$blockers = [];
$blockers_parse_ok = false;
if ($state_md_path && is_readable($state_md_path)) {
    $md = (string) file_get_contents($state_md_path);
    // Слайс между „## ⚠️ KNOWN ISSUES" и следващия „---" / „## "
    if (preg_match('/##\s*⚠️?\s*KNOWN ISSUES.*?\n(.*?)(?:\n---|\n##\s)/su', $md, $m)) {
        $blockers_parse_ok = true;
        $body = $m[1];
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') !== 0) continue;
            $cells = array_map('trim', array_slice(explode('|', $line), 1, -1));
            if (count($cells) < 4) continue;
            // Скип header / separator
            if (preg_match('/^[-\s|:]+$/', implode(' ', $cells))) continue;
            if (strcasecmp($cells[0], '#') === 0) continue;
            if (!is_numeric($cells[0])) continue;
            $blockers[] = [
                'num'      => $cells[0],
                'issue'    => $cells[1],
                'severity' => $cells[2],
                'when'     => $cells[3],
            ];
        }
    }
}
$has_p0 = false;
foreach ($blockers as $b) {
    if (stripos($b['severity'], 'P0') !== false && stripos($b['severity'], 'RESOLVED') === false) {
        $has_p0 = true; break;
    }
}
$header_ready = !$has_p0;

// ─── DATA: HEADER OVERALL READINESS ─────────────────────────────────────
$days_to_eni = (int) ceil((strtotime('2026-05-14 00:00:00') - time()) / 86400);

// Rolled-up status (worst of sections, but "idle" doesn't drag down)
$section_statuses = [
    $cat_status, $sales_status, $ins_status, $studio_status,
    $db_status, $cron_status, $disk_status, $diag_status,
    $header_ready ? 'ok' : 'bad',
];
$has_bad = in_array('bad', $section_statuses, true);
$has_warn = in_array('warn', $section_statuses, true);
$overall = $has_bad ? 'bad' : ($has_warn ? 'warn' : 'ok');
$overall_label = $has_bad ? 'BLOCKED' : ($has_warn ? 'AT RISK' : 'READY');

?><!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta http-equiv="refresh" content="60">
<title>Beta Readiness — ENI 14 май</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: #0f1115; color: #e8ebf0; line-height: 1.45;
    -webkit-font-smoothing: antialiased; padding: 12px; padding-bottom: 60px;
  }
  a { color: #6fb3ff; text-decoration: none; }
  .top {
    display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: baseline;
    padding: 12px 14px; border-radius: 12px;
    background: linear-gradient(135deg, #1a1f2b, #11141b);
    border: 1px solid #232733; margin-bottom: 14px;
  }
  .top h1 { font-size: 17px; margin: 0; font-weight: 700; letter-spacing: .2px; }
  .top .meta { font-size: 12px; color: #8a93a6; }
  .pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 12px;
    background: #232733; color: #cbd2e0;
  }
  .pill.ok   { background: #14351f; color: #5be38b; border: 1px solid #1f5b35; }
  .pill.warn { background: #3a2c11; color: #ffb84d; border: 1px solid #6a4d18; }
  .pill.bad  { background: #3a1414; color: #ff6f6f; border: 1px solid #6a1d1d; }
  .pill.idle { background: #1a1d24; color: #8a93a6; border: 1px solid #2a2f3a; }

  .grid { display: grid; gap: 12px; grid-template-columns: 1fr; }
  @media (min-width: 720px)  { .grid { grid-template-columns: 1fr 1fr; } }
  @media (min-width: 1100px) { .grid { grid-template-columns: 1fr 1fr 1fr; } }

  .card {
    background: #161a23; border: 1px solid #232733; border-radius: 12px;
    padding: 14px 14px 12px; min-width: 0;
  }
  .card h2 { font-size: 14px; margin: 0 0 10px; display: flex; gap: 8px; align-items: center; }
  .card h2 .num { color: #8a93a6; font-weight: 600; }
  .row {
    display: flex; justify-content: space-between; align-items: baseline;
    padding: 6px 0; border-top: 1px dashed #232733; gap: 10px;
  }
  .row:first-of-type { border-top: 0; }
  .row .k { color: #c0c7d4; font-size: 13px; }
  .row .v { color: #fff; font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
  .row .v.dim { color: #8a93a6; font-weight: 400; }

  table.blk { width: 100%; border-collapse: collapse; font-size: 13px; }
  table.blk th, table.blk td {
    text-align: left; padding: 6px 6px; border-bottom: 1px dashed #232733;
    vertical-align: top;
  }
  table.blk th { color: #8a93a6; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
  .sev-p0 { color: #ff6f6f; font-weight: 700; }
  .sev-p1 { color: #ffb84d; font-weight: 700; }
  .sev-p2 { color: #5be38b; font-weight: 700; }
  .sev-resolved { color: #5be38b; }

  .footer { color: #6c7488; font-size: 11px; margin-top: 14px; text-align: center; }
  .nav { font-size: 12px; }
  .nav a { margin-right: 12px; }
</style>
</head>
<body>

<div class="top">
  <h1>🎯 Beta Readiness — ENI 14 май</h1>
  <span class="pill <?= status_class($overall) ?>"><?= status_dot($overall) ?> <?= htmlspecialchars($overall_label) ?></span>
  <span class="meta">
    <?= $days_to_eni > 0 ? $days_to_eni . ' дни до launch' : ($days_to_eni === 0 ? 'launch днес' : abs($days_to_eni) . ' дни ПОСЛЕ launch') ?>
    · tenant=7 · обновено <?= date('H:i:s') ?> · auto-refresh 60s
  </span>
  <span class="nav" style="margin-left:auto">
    <a href="diagnostics.php">diagnostics →</a>
  </span>
</div>

<div class="grid">

  <!-- 1. PRODUCT CATALOG -->
  <div class="card">
    <h2><span class="num">1.</span> Product Catalog
      <span class="pill <?= status_class($cat_status) ?>" style="margin-left:auto">
        <?= status_dot($cat_status) ?> <?= fmt_num($cat_total) ?>
      </span>
    </h2>
    <div class="row"><span class="k">Total products (target 50+)</span>
      <span class="v"><?= fmt_num($cat_total) ?></span></div>
    <div class="row"><span class="k">With photo</span>
      <span class="v"><?= fmt_num($cat_with_photo) ?> · <?= pct($cat_with_photo, $cat_total) ?></span></div>
    <div class="row"><span class="k">With ai_category</span>
      <span class="v"><?= fmt_num($cat_with_ai_cat) ?> · <?= pct($cat_with_ai_cat, $cat_total) ?></span></div>
    <div class="row"><span class="k">With cost_price</span>
      <span class="v"><?= fmt_num($cat_with_cost) ?> · <?= pct($cat_with_cost, $cat_total) ?></span></div>
    <div class="row"><span class="k">With min_quantity</span>
      <span class="v"><?= fmt_num($cat_with_min) ?> · <?= pct($cat_with_min, $cat_total) ?></span></div>
  </div>

  <!-- 2. SALES DATA -->
  <div class="card">
    <h2><span class="num">2.</span> Sales (last 30d)
      <span class="pill <?= status_class($sales_status) ?>" style="margin-left:auto">
        <?= status_dot($sales_status) ?> <?= fmt_num($sales_count) ?>
      </span>
    </h2>
    <div class="row"><span class="k">Total sales</span>
      <span class="v"><?= fmt_num($sales_count) ?></span></div>
    <div class="row"><span class="k">Sale items</span>
      <span class="v"><?= fmt_num($items_count) ?>
        <span class="dim"> · avg <?= $items_per_sale ?>/sale</span></span></div>
    <div class="row"><span class="k">Revenue</span>
      <span class="v"><?= fmt_eur($sales_revenue) ?></span></div>
    <div class="row"><span class="k">Days with ≥1 sale</span>
      <span class="v"><?= fmt_num($sales_days) ?> / 30</span></div>
  </div>

  <!-- 3. AI INSIGHTS -->
  <div class="card">
    <h2><span class="num">3.</span> AI Insights (live)
      <span class="pill <?= status_class($ins_status) ?>" style="margin-left:auto">
        <?= status_dot($ins_status) ?> <?= fmt_num($insights_total) ?>
      </span>
    </h2>
    <div class="row"><span class="k">Total live insights (products)</span>
      <span class="v"><?= fmt_num($insights_total) ?></span></div>
    <div class="row"><span class="k">loss / loss_cause</span>
      <span class="v"><?= $ins_per_q['loss'] ?> · <?= $ins_per_q['loss_cause'] ?></span></div>
    <div class="row"><span class="k">gain / gain_cause</span>
      <span class="v"><?= $ins_per_q['gain'] ?> · <?= $ins_per_q['gain_cause'] ?></span></div>
    <div class="row"><span class="k">order / anti_order</span>
      <span class="v"><?= $ins_per_q['order'] ?> · <?= $ins_per_q['anti_order'] ?></span></div>
    <div class="row"><span class="k">Last insight created</span>
      <span class="v"><?= htmlspecialchars((string)($ins_last_created ?? '—')) ?>
        <span class="dim"> · <?= ago($ins_last_created) ?></span></span></div>
  </div>

  <!-- 4. AI STUDIO HEALTH -->
  <div class="card">
    <h2><span class="num">4.</span> AI Studio Health
      <span class="pill <?= status_class($studio_status) ?>" style="margin-left:auto">
        <?= status_dot($studio_status) ?> <?= fmt_eur($spend_total) ?>/7d
      </span>
    </h2>
    <div class="row"><span class="k">Balance bg / desc / magic</span>
      <span class="v">
        <?= fmt_num($studio_balance['bg']['total']) ?> ·
        <?= fmt_num($studio_balance['desc']['total']) ?> ·
        <?= fmt_num($studio_balance['magic']['total']) ?>
      </span></div>
    <div class="row"><span class="k">Spend last 7d</span>
      <span class="v"><?= fmt_eur($spend_total) ?>
        <span class="dim"> · <?= fmt_num($spend_calls) ?> calls</span></span></div>
    <?php foreach ($spend_rows as $sr): ?>
      <div class="row"><span class="k">  · <?= htmlspecialchars((string)$sr['feature']) ?></span>
        <span class="v"><?= fmt_num((int)$sr['cnt']) ?>
          <span class="dim"> · <?= fmt_eur((float)$sr['cost']) ?></span></span></div>
    <?php endforeach; ?>
    <?php if (empty($spend_rows)): ?>
      <div class="row"><span class="k">  · per-feature breakdown</span>
        <span class="v dim">no calls in 7d</span></div>
    <?php endif; ?>
    <div class="row"><span class="k">Refunds last 7d</span>
      <span class="v"><?= fmt_num($refunds_7d) ?></span></div>
    <div class="row"><span class="k">Anti-abuse triggers 7d</span>
      <span class="v"><?= fmt_num($abuse_triggers_7d) ?>
        <span class="dim"> · cap <?= defined('AI_ABUSE_DAILY_HARD_CAP') ? (int)AI_ABUSE_DAILY_HARD_CAP : 30 ?>/day</span></span></div>
  </div>

  <!-- 5. INFRASTRUCTURE -->
  <div class="card">
    <h2><span class="num">5.</span> Infrastructure
      <span class="pill <?= status_class($db_status === 'ok' && $cron_status === 'ok' && $disk_status === 'ok' && $diag_status === 'ok' ? 'ok' : ($cron_status === 'bad' || $db_status === 'bad' || $disk_status === 'bad' || $diag_status === 'bad' ? 'bad' : 'warn')) ?>"
            style="margin-left:auto">
        <?= status_dot($cron_status) ?> infra
      </span>
    </h2>
    <div class="row"><span class="k">Cron compute_insights_15min</span>
      <span class="v"><?= status_dot($cron_status) ?>
        <?= $cron && !empty($cron['last_run_at']) ? htmlspecialchars((string)$cron['last_run_at']) : '—' ?>
        <span class="dim"> · <?= $cron_age_min !== null ? $cron_age_min . 'm ago' : 'no heartbeat' ?>
        <?= $cron && ($cron['last_status'] ?? '') !== '' ? ' · ' . htmlspecialchars((string)$cron['last_status']) : '' ?></span>
      </span></div>
    <div class="row"><span class="k">DB latency</span>
      <span class="v"><?= status_dot($db_status) ?> <?= fmt_num($db_latency_ms) ?> ms</span></div>
    <div class="row"><span class="k">Disk /var/www</span>
      <span class="v"><?= status_dot($disk_status) ?> <?= htmlspecialchars($disk_line) ?></span></div>
    <div class="row"><span class="k">Diagnostic Cat A / D</span>
      <span class="v">
        <?php if ($diag): ?>
          <?= status_dot($diag_status) ?>
          A=<?= number_format((float)$diag['a'], 0) ?>% ·
          D=<?= number_format((float)$diag['d'], 0) ?>%
          <span class="dim"> · run #<?= (int)$diag['id'] ?> · <?= ago($diag['run_timestamp'] ?? null) ?></span>
        <?php else: ?>
          <span class="dim">no runs</span>
        <?php endif; ?>
      </span></div>
    <div class="row"><span class="k">Bluetooth printer log</span>
      <span class="v"><?= status_dot($printer_status) ?>
        <span class="dim"><?= htmlspecialchars($printer_note) ?></span></span></div>
  </div>

  <!-- 6. BETA BLOCKERS -->
  <div class="card" style="grid-column: 1 / -1;">
    <h2><span class="num">6.</span> Beta Blockers (STATE_OF_THE_PROJECT.md)
      <span class="pill <?= $header_ready ? 'ok' : 'bad' ?>" style="margin-left:auto">
        <?= $header_ready ? '🟢 NO P0' : '🔴 P0 BLOCKED' ?> · <?= count($blockers) ?> issues
      </span>
    </h2>
    <?php if (!$blockers_parse_ok): ?>
      <div class="row"><span class="k">parse</span>
        <span class="v dim">не успях да парсна STATE_OF_THE_PROJECT.md (KNOWN ISSUES таблицата)</span></div>
    <?php elseif (empty($blockers)): ?>
      <div class="row"><span class="k">issues</span>
        <span class="v dim">няма issues в KNOWN ISSUES — 🎉</span></div>
    <?php else: ?>
      <table class="blk">
        <thead>
          <tr><th>#</th><th>Severity</th><th>Issue</th><th>Кога</th></tr>
        </thead>
        <tbody>
        <?php foreach ($blockers as $b):
          $sev = $b['severity'];
          $cls = stripos($sev, 'P0') !== false ? 'sev-p0'
               : (stripos($sev, 'P1') !== false ? 'sev-p1'
               : (stripos($sev, 'RESOLVED') !== false ? 'sev-resolved' : 'sev-p2'));
        ?>
          <tr>
            <td><?= htmlspecialchars($b['num']) ?></td>
            <td class="<?= $cls ?>"><?= htmlspecialchars($sev) ?></td>
            <td><?= htmlspecialchars($b['issue']) ?></td>
            <td><span class="v dim"><?= htmlspecialchars($b['when']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<div class="footer">
  S86.BETA.READINESS · auto-refresh 60s · server <?= htmlspecialchars(gethostname() ?: 'unknown') ?> · php <?= htmlspecialchars(PHP_VERSION) ?>
</div>

</body>
</html>
